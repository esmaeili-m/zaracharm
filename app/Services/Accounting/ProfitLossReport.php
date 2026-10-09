<?php

namespace App\Services\Accounting;

use App\Models\AccountingEntry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Morilog\Jalali\Jalalian;

/**
 * گزارش سود و زیان
 *
 *  فروش خالص   = (جمع کالا − تخفیف + ارسال) سفارش‌های پرداخت‌شده سایت + فاکتورهای حضوری + مارکت‌پلیس
 *               (مالیات جداست و جزو درآمد نیست)
 *  بهای تمام‌شده = تعداد × قیمت خرید لحظه فروش (cost_price قلم؛ در نبود آن قیمت خرید فعلی واریانت)
 *  مرجوعی       = مبلغ استردادشده به کاربران (کیف پول)؛ بهای کالای برگشتی از بهای تمام‌شده کم می‌شود
 *  سود ناخالص   = فروش خالص − مرجوعی − (بهای تمام‌شده − بهای کالای برگشتی)
 *  سود خالص     = سود ناخالص + درآمدهای متفرقه − هزینه‌ها   (فقط دسته‌هایی که affects_profit دارند)
 *
 * سفارش/فاکتوری که کامل بازگشت وجه شده (payment_status / status = refunded) نه فروش حساب می‌شود نه هزینه.
 * هر شاخص با یک کوئری گروه‌بندی‌شده بر اساس روز محاسبه می‌شود؛ جمع کل و ماهانه از همین داده‌ها ساخته می‌شوند.
 */
class ProfitLossReport
{
    public const LABELS = [
        'sales' => 'فروش کالا',
        'shipping' => 'هزینه ارسال دریافتی',
        'refunds' => 'مرجوعی (استرداد به مشتری)',
        'cogs' => 'بهای تمام‌شده کالای فروش‌رفته',
        'returned_cogs' => 'بهای کالای برگشتی',
        'other_income' => 'درآمدهای متفرقه',
        'expenses' => 'هزینه‌ها',
        'tax' => 'مالیات دریافتی',
    ];

    protected Carbon $from;
    protected Carbon $to;
    protected ?array $daily = null;

    public function __construct(Carbon $from, Carbon $to)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    /**
     * @return array<string, array<string, int>>  metric => [Y-m-d => amount]
     */
    public function daily(): array
    {
        if ($this->daily !== null) {
            return $this->daily;
        }

        $metrics = array_fill_keys(array_keys(self::LABELS), []);

        $add = function (string $metric, iterable $rows) use (&$metrics) {
            foreach ($rows as $day => $amount) {
                $metrics[$metric][$day] = ($metrics[$metric][$day] ?? 0) + (int) $amount;
            }
        };

        // ---- سفارش‌های سایت ----
        $orders = fn () => DB::table('orders')->where('orders.payment_status', 'paid');
        $orderDate = 'orders.created_at';

        $add('sales', $this->sum($orders(), $orderDate, 'orders.subtotal - orders.discount_amount'));
        $add('shipping', $this->sum($orders(), $orderDate, 'orders.shipping_amount'));
        $add('tax', $this->sum($orders(), $orderDate, 'orders.tax_amount'));
        $add('cogs', $this->sum(
            $orders()->join('order_items as oi', 'oi.order_id', '=', 'orders.id')->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.variant_id'),
            $orderDate,
            'oi.quantity * ' . $this->cost('order_items', 'oi')
        ));

        // ---- فاکتورهای حضوری / دستی (بدون سفارش) ----
        $invoices = fn () => DB::table('invoices')->whereNull('invoices.order_id')->whereNull('invoices.deleted_at')->where('invoices.status', 'paid');
        $invoiceDate = 'COALESCE(invoices.paid_at, invoices.created_at)';

        $add('sales', $this->sum($invoices(), $invoiceDate, 'invoices.subtotal - invoices.discount_amount'));
        $add('shipping', $this->sum($invoices(), $invoiceDate, 'invoices.shipping_amount'));
        $add('tax', $this->sum($invoices(), $invoiceDate, 'invoices.tax_amount'));
        $add('cogs', $this->sum(
            $invoices()->join('invoice_items as ii', 'ii.invoice_id', '=', 'invoices.id')->leftJoin('product_variants as pv', 'pv.id', '=', 'ii.variant_id'),
            $invoiceDate,
            'ii.quantity * ' . $this->cost('invoice_items', 'ii')
        ));

        // ---- مارکت‌پلیس‌ها ----
        if (Schema::hasTable('marketplace_orders')) {
            $mp = fn () => DB::table('marketplace_orders')
                ->where('marketplace_orders.payment_status', 'paid')
                ->whereNotIn('marketplace_orders.status', ['cancelled', 'returned']);
            $mpDate = 'COALESCE(marketplace_orders.paid_at, marketplace_orders.ordered_at, marketplace_orders.created_at)';

            $add('sales', $this->sum($mp(), $mpDate, 'marketplace_orders.items_amount'));
            $add('shipping', $this->sum($mp(), $mpDate, 'marketplace_orders.shipping_amount'));
            $add('cogs', $this->sum(
                $mp()->join('marketplace_order_items as mi', 'mi.marketplace_order_id', '=', 'marketplace_orders.id')
                    ->leftJoin('product_variants as pv', 'pv.id', '=', 'mi.product_variant_id'),
                $mpDate,
                'mi.quantity * ' . $this->cost('marketplace_order_items', 'mi')
            ));
        }

        // ---- مرجوعی‌ها (استرداد به کیف پول) ----
        if (Schema::hasTable('return_requests')) {
            $returns = fn () => DB::table('return_requests')->where('return_requests.status', 'refunded')->whereNull('return_requests.deleted_at');

            $add('refunds', $this->sum($returns(), 'return_requests.refunded_at', 'return_requests.refund_amount'));
            $add('returned_cogs', $this->sum(
                $returns()->join('return_request_items as rri', 'rri.return_request_id', '=', 'return_requests.id')
                    ->join('order_items as oi', 'oi.id', '=', 'rri.order_item_id')
                    ->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.variant_id'),
                'return_requests.refunded_at',
                'rri.quantity * ' . $this->cost('order_items', 'oi')
            ));
        }

        // ---- دفتر حسابداری: فقط ثبت‌های دستی با دسته‌ی اثرگذار بر سود ----
        foreach (['other_income' => 'income', 'expenses' => 'expense'] as $metric => $type) {
            $add($metric, $this->sum($this->manualEntries($type), 'accounting_entries.entry_date', 'accounting_entries.amount'));
        }

        return $this->daily = $metrics;
    }

    /**
     * جمع کل بازه + شاخص‌های محاسبه‌شده
     */
    public function summary(): array
    {
        return $this->derive(collect($this->daily())->map(fn ($days) => array_sum($days))->all());
    }

    /**
     * تفکیک ماه‌های شمسی داخل بازه
     *
     * @return array<string, array>  'Y/m' => metrics
     */
    public function monthly(): array
    {
        $buckets = [];

        foreach ($this->daily() as $metric => $days) {
            foreach ($days as $day => $amount) {
                $key = Jalalian::fromCarbon(Carbon::parse($day))->format('Y/m');
                $buckets[$key][$metric] = ($buckets[$key][$metric] ?? 0) + $amount;
            }
        }

        // همه ماه‌های بازه، حتی بدون تراکنش
        $result = [];
        $start = Jalalian::fromCarbon($this->from);
        $cursor = new Jalalian($start->getYear(), $start->getMonth(), 1);
        $end = Jalalian::fromCarbon($this->to)->format('Y/m');

        for ($i = 0; $i < 120; $i++) {
            $key = $cursor->format('Y/m');
            $result[$key] = $this->derive(array_merge(array_fill_keys(array_keys(self::LABELS), 0), $buckets[$key] ?? []));

            if ($key === $end) {
                break;
            }

            $cursor = $cursor->addMonths(1);
        }

        return $result;
    }

    /**
     * هزینه‌ها / درآمدهای متفرقه به تفکیک دسته
     *
     * @return \Illuminate\Support\Collection<int, object{title: string, type: string, affects_profit: bool, total: int}>
     */
    public function byCategory()
    {
        return AccountingEntry::query()
            ->leftJoin('accounting_categories as c', 'c.id', '=', 'accounting_entries.category_id')
            ->where('accounting_entries.source', 'manual')
            ->whereIn('accounting_entries.type', ['income', 'expense'])
            ->whereBetween('accounting_entries.entry_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw("accounting_entries.type, COALESCE(c.title, 'بدون دسته') as title, COALESCE(c.affects_profit, 1) as affects_profit, SUM(accounting_entries.amount) as total")
            ->groupBy('accounting_entries.type', 'c.title', 'c.affects_profit')
            ->orderByDesc('total')
            ->get();
    }

    protected function derive(array $m): array
    {
        $m['net_sales'] = $m['sales'] + $m['shipping'];
        $m['net_cogs'] = $m['cogs'] - $m['returned_cogs'];
        $m['gross_profit'] = $m['net_sales'] - $m['refunds'] - $m['net_cogs'];
        $m['net_profit'] = $m['gross_profit'] + $m['other_income'] - $m['expenses'];
        $m['margin'] = $m['net_sales'] > 0 ? round($m['net_profit'] / $m['net_sales'] * 100, 1) : 0;

        return $m;
    }

    protected function manualEntries(string $type): Builder
    {
        return DB::table('accounting_entries')
            ->leftJoin('accounting_categories as c', 'c.id', '=', 'accounting_entries.category_id')
            ->whereNull('accounting_entries.deleted_at')
            ->where('accounting_entries.source', 'manual')
            ->where('accounting_entries.type', $type)
            ->where(fn ($q) => $q->whereNull('c.id')->orWhere('c.affects_profit', true));
    }

    /**
     * @return array<string, int> [Y-m-d => sum]
     */
    protected function sum(Builder $query, string $dateExpr, string $amountExpr): array
    {
        return $query
            ->whereRaw("{$dateExpr} >= ?", [$this->from->toDateTimeString()])
            ->whereRaw("{$dateExpr} <= ?", [$this->to->toDateTimeString()])
            ->selectRaw("DATE({$dateExpr}) as day, SUM({$amountExpr}) as total")
            ->groupByRaw("DATE({$dateExpr})")
            ->pluck('total', 'day')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * قیمت خرید قلم: ذخیره‌شده در لحظه فروش، وگرنه قیمت خرید فعلی واریانت
     */
    protected function cost(string $table, string $alias): string
    {
        static $has = [];
        $has[$table] ??= Schema::hasColumn($table, 'cost_price');

        return $has[$table] ? "COALESCE({$alias}.cost_price, pv.cost_price, 0)" : 'COALESCE(pv.cost_price, 0)';
    }
}
