<?php

namespace App\Services\Sms;

use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * فیلتر مشتریان سایت برای انتخاب گیرنده (تمام فیلتر و شمارش در دیتابیس)
 *
 * «سفارش» = سفارش پرداخت‌شده (payment_status = paid). جستجوی نام/موبایل همیشه AND است؛
 * سایر شرط‌ها بسته به match با AND (همه) یا OR (حداقل یکی) ترکیب می‌شوند.
 */
class CustomerFilter
{
    public const DEFAULTS = [
        'q' => '',
        'match' => 'all',
        'only_customers' => true,
        'status' => '',
        'registered_from' => '',
        'registered_to' => '',
        'orders' => '',
        'min_orders' => '',
        'max_orders' => '',
        'last_order_from' => '',
        'last_order_to' => '',
        'order_status' => '',
        'min_spent' => '',
        'max_spent' => '',
        'province' => '',
        'city' => '',
        'consent' => '',
    ];

    public static function query(array $filters): Builder
    {
        $f = array_merge(self::DEFAULTS, array_intersect_key($filters, self::DEFAULTS));

        $query = User::query()->whereNotNull('mobile')->where('mobile', '!=', '');

        if ($f['only_customers']) {
            // کارکنان پنل (دارای نقش غیر از user) مشتری حساب نمی‌شوند
            $query->whereDoesntHave('roles', fn ($r) => $r->where('name', '!=', 'user'));
        }

        if (($term = trim((string) $f['q'])) !== '') {
            $mobile = PhoneNumber::normalize($term);
            $fullName = $query->getConnection()->getDriverName() === 'sqlite'
                ? "(COALESCE(first_name,'') || ' ' || COALESCE(last_name,''))"
                : "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))";

            $query->where(function ($w) use ($term, $mobile, $fullName) {
                $w->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhereRaw("{$fullName} like ?", ["%{$term}%"])
                    ->orWhere('mobile', 'like', '%' . ($mobile ?? JalaliDate::latinDigits($term)) . '%');
            });
        }

        $conditions = self::conditions($f);

        if ($conditions) {
            $or = $f['match'] === 'any';
            $query->where(function ($w) use ($conditions, $or) {
                foreach ($conditions as $condition) {
                    $or ? $w->orWhere($condition) : $w->where($condition);
                }
            });
        }

        return $query;
    }

    /** @return list<\Closure> */
    protected static function conditions(array $f): array
    {
        $c = [];

        if ($f['status'] !== '' && $f['status'] !== null) {
            $c[] = fn ($q) => $q->where('users.status', (bool) $f['status']);
        }

        if ($from = JalaliDate::toCarbon($f['registered_from'])) {
            $c[] = fn ($q) => $q->where('users.created_at', '>=', $from);
        }

        if ($to = JalaliDate::toCarbon($f['registered_to'])) {
            $c[] = fn ($q) => $q->where('users.created_at', '<', $to->copy()->addDay());
        }

        if ($f['orders'] === 'with') {
            $c[] = fn ($q) => $q->whereExists(self::paidOrders());
        } elseif ($f['orders'] === 'without') {
            $c[] = fn ($q) => $q->whereNotExists(self::paidOrders());
        }

        if (is_numeric($f['min_orders'])) {
            $c[] = fn ($q) => $q->whereRaw(self::aggregate('count(*)') . ' >= ?', [(int) $f['min_orders']]);
        }

        if (is_numeric($f['max_orders'])) {
            $c[] = fn ($q) => $q->whereRaw(self::aggregate('count(*)') . ' <= ?', [(int) $f['max_orders']]);
        }

        if (is_numeric($f['min_spent'])) {
            $c[] = fn ($q) => $q->whereRaw(self::aggregate('coalesce(sum(total_amount),0)') . ' >= ?', [(int) $f['min_spent']]);
        }

        if (is_numeric($f['max_spent'])) {
            $c[] = fn ($q) => $q->whereRaw(self::aggregate('coalesce(sum(total_amount),0)') . ' <= ?', [(int) $f['max_spent']]);
        }

        if ($from = JalaliDate::toCarbon($f['last_order_from'])) {
            $c[] = fn ($q) => $q->whereRaw(self::aggregate('max(created_at)') . ' >= ?', [$from->toDateTimeString()]);
        }

        if ($to = JalaliDate::toCarbon($f['last_order_to'])) {
            $c[] = fn ($q) => $q->whereRaw(self::aggregate('max(created_at)') . ' < ?', [$to->copy()->addDay()->toDateTimeString()]);
        }

        if ($f['order_status'] !== '') {
            $c[] = fn ($q) => $q->whereExists(fn (QueryBuilder $s) => $s->selectRaw('1')->from('orders')
                ->whereColumn('orders.user_id', 'users.id')->where('orders.status', $f['order_status']));
        }

        if (trim((string) $f['province']) !== '' || trim((string) $f['city']) !== '') {
            $c[] = fn ($q) => $q->whereExists(fn (QueryBuilder $s) => $s->selectRaw('1')->from('addresses')
                ->whereColumn('addresses.user_id', 'users.id')
                ->when(trim((string) $f['province']) !== '', fn ($s) => $s->where('province', 'like', '%' . trim($f['province']) . '%'))
                ->when(trim((string) $f['city']) !== '', fn ($s) => $s->where('city', 'like', '%' . trim($f['city']) . '%')));
        }

        if ($f['consent'] === 'yes') {
            $c[] = fn ($q) => $q->whereExists(self::consent());
        } elseif ($f['consent'] === 'no') {
            $c[] = fn ($q) => $q->whereNotExists(self::consent());
        }

        return $c;
    }

    protected static function paidOrders(): \Closure
    {
        return fn (QueryBuilder $s) => $s->selectRaw('1')->from('orders')
            ->whereColumn('orders.user_id', 'users.id')->where('orders.payment_status', 'paid');
    }

    /** رضایت دریافت پیامک تبلیغاتی (تنظیمات اعلان کاربر) */
    public static function consent(): \Closure
    {
        return fn (QueryBuilder $s) => $s->selectRaw('1')->from('notification_preferences')
            ->whereColumn('notification_preferences.user_id', 'users.id')->where('notification_preferences.promotions', true);
    }

    protected static function aggregate(string $expression): string
    {
        return "(select {$expression} from orders where orders.user_id = users.id and orders.payment_status = 'paid')";
    }

    /** خلاصه خوانای فیلترها برای ذخیره در گزارش */
    public static function describe(array $filters): array
    {
        return collect(array_intersect_key($filters, self::DEFAULTS))
            ->reject(fn ($v, $k) => $v === '' || $v === null || ($k === 'match' && $v === 'all'))
            ->all();
    }
}
