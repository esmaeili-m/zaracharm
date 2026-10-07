<?php

namespace App\Enums;

/**
 * وضعیت استاندارد پرداخت‌ها (ستون payments.status)
 */
enum PaymentStatus: string
{
    case Pending = 'pending';       // در انتظار (درگاه باز شده / کارت‌به‌کارت در انتظار بررسی / پرداخت در محل)
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Rejected = 'rejected';     // کارت‌به‌کارت رد شده توسط ادمین

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Paid => 'پرداخت‌شده',
            self::Failed => 'ناموفق',
            self::Cancelled => 'لغو شده',
            self::Refunded => 'بازگشت وجه',
            self::Rejected => 'رد شده',
        };
    }

    // کلاس badge پنل مدیریت (Bootstrap)
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed, self::Rejected => 'danger',
            self::Cancelled => 'secondary',
            self::Refunded => 'info',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
