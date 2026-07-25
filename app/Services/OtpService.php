<?php

namespace App\Services;

use App\Models\OtpCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Services\SmsService;
class OtpService
{
    public function generate(string $mobile): string
    {
        return (string) rand(1000, 9999);
    }

    public function send(string $mobile): void
    {
        // جلوگیری از اسپم (هر 120 ثانیه یکبار)
        $lastOtp = OtpCode::where('mobile', $mobile)
            ->latest()
            ->first();

        if ($lastOtp && $lastOtp->created_at->gt(now()->subSeconds(120))) {
            throw new \Exception('لطفاً کمی صبر کنید و دوباره تلاش کنید.');
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

    public function verify(string $mobile, string $code): bool
    {
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
            return false;
        }

        $otp->update([
            'used_at' => now(),
        ]);

        return true;
    }

    private function sendSms(string $mobile, string $code): void
    {
        logger()->info("CODE: $code");
        try {

            $result = app(SmsService::class)->send(
                $mobile,
                $code
            );

            logger()->info('SMS sent', [
                'mobile' => $mobile,
                'result' => $result,
            ]);

        } catch (\Throwable $e) {

            logger()->error('SMS failed', [
                'mobile' => $mobile,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e; // برای اینکه Livewire هم خطا رو نشون بده
        }
    }
}
