<?php

namespace App\Services;

use App\Models\OtpCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\SmsService;

class OtpService
{
    // سقف ارسال پیامک (جلوگیری از حدس زدن کد ۴ رقمی و اسپم پیامک)
    public const SEND_PER_HOUR = 5;
    public const SEND_PER_DAY = 10;
    public const SEND_PER_IP_HOUR = 15;
    // بعد از این تعداد کد اشتباه (در همه کدهای ارسال‌شده) ورود با کد برای ۲۴ ساعت قفل می‌شود
    public const MAX_FAILED_PER_DAY = 10;

    public function generate(string $mobile): string
    {
        return (string) random_int(1000, 9999);
    }

    public function send(string $mobile): void
    {
        $this->ensureNotLocked($mobile);

        // جلوگیری از اسپم (هر 120 ثانیه یکبار)
        $lastOtp = OtpCode::where('mobile', $mobile)
            ->latest()
            ->first();

        if ($lastOtp && $lastOtp->created_at->gt(now()->subSeconds(120))) {
            throw new \Exception('لطفاً کمی صبر کنید و دوباره تلاش کنید.');
        }

        $limits = [
            'otp-send-hour:' . $mobile => [self::SEND_PER_HOUR, 3600],
            'otp-send-day:' . $mobile => [self::SEND_PER_DAY, 86400],
            'otp-send-ip:' . request()->ip() => [self::SEND_PER_IP_HOUR, 3600],
        ];

        foreach ($limits as $key => [$max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                throw new \Exception("تعداد درخواست کد بیش از حد مجاز است. لطفاً {$minutes} دقیقه دیگر تلاش کنید.");
            }
        }

        foreach ($limits as $key => [$max, $decay]) {
            RateLimiter::hit($key, $decay);
        }

        $code = $this->generate($mobile);

        OtpCode::create([
            'mobile' => $mobile,
            'code' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(5),
            'attempts' => 0,
            'sent_at' => now(),
        ]);

        $this->sendSms($mobile, $code);
    }

    /**
     * @throws \RuntimeException وقتی ورود با کد به‌خاطر تلاش‌های ناموفق زیاد قفل شده است
     */
    public function verify(string $mobile, string $code): bool
    {
        $this->ensureNotLocked($mobile);

        $otp = OtpCode::where('mobile', $mobile)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$otp) {
            return false;
        }

        // expire check
        if ($otp->expires_at->lt(now())) {
            return false;
        }

        // attempt limit
        if ($otp->attempts >= 5) {
            return false;
        }

        // increase attempts
        $otp->increment('attempts');

        // check code
        if (!Hash::check($code, $otp->code)) {
            RateLimiter::hit($this->failedKey($mobile), 86400);
            return false;
        }

        $otp->update([
            'used_at' => now(),
        ]);

        RateLimiter::clear($this->failedKey($mobile));

        return true;
    }

    protected function ensureNotLocked(string $mobile): void
    {
        if (RateLimiter::tooManyAttempts($this->failedKey($mobile), self::MAX_FAILED_PER_DAY)) {
            $hours = max(1, (int) ceil(RateLimiter::availableIn($this->failedKey($mobile)) / 3600));
            throw new \RuntimeException("به‌دلیل وارد کردن کد اشتباه زیاد، ورود با کد تا {$hours} ساعت دیگر غیرفعال است. می‌توانید با رمز عبور وارد شوید یا با پشتیبانی تماس بگیرید.");
        }
    }

    /** 09121234567 => 0912***4567 */
    protected function maskMobile(string $mobile): string
    {
        return strlen($mobile) >= 8 ? substr($mobile, 0, 4) . '***' . substr($mobile, -4) : '***';
    }

    protected function failedKey(string $mobile): string
    {
        return 'otp-failed:' . $mobile;
    }

    private function sendSms(string $mobile, string $code): void
    {
        // کد ورود هیچ‌وقت لاگ نمی‌شود (حتی در محیط توسعه)
        try {

            $result = app(SmsService::class)->send(
                $mobile,
                $code
            );

            logger()->info('SMS sent', [
                'mobile' => $this->maskMobile($mobile),
                'ok' => (bool) $result,
            ]);

        } catch (\Throwable $e) {

            logger()->error('SMS failed', [
                'mobile' => $this->maskMobile($mobile),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e; // برای اینکه Livewire هم خطا رو نشون بده
        }
    }
}
