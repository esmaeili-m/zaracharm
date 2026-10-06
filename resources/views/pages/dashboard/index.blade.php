<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\Wallet;
use App\Models\Comment;
use App\Models\Payment;
use App\Models\ProductQuestion;
use App\Models\ReturnRequest;
use App\Models\StockMovement;

new #[Layout('layouts.dashboard')] class extends Component {

    // بازه گزارش (روز): 7 | 30 | 90 | 365
    #[Url(except: '30')]
    public string $period = '30';

    public function updatedPeriod(): void
    {
        if (!in_array($this->period, ['7', '30', '90', '365'], true)) {
            $this->period = '30';
        }

        // نمودارها wire:ignore دارند؛ داده جدید با رویداد ارسال می‌شود
        $this->dispatch('dashboard-charts-updated', charts: $this->charts);
    }

    /*
    |--------------------------------------------------------------------------
    | بازه زمانی
    |--------------------------------------------------------------------------
    */
    protected function days(): int
    {
        return (int) (in_array($this->period, ['7', '30', '90', '365'], true) ? $this->period : 30);
    }

    protected function range(): array
    {
        $end = Carbon::now();
        $start = Carbon::today()->subDays($this->days() - 1);

        return [$start, $end];
    }

    protected function previousRange(): array
    {
        [$start] = $this->range();

        return [$start->copy()->subDays($this->days()), $start->copy()->subSecond()];
    }

    protected function growth(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | فروش (سفارش‌های پرداخت‌شده سایت + فاکتورهای پرداخت‌شده دستی/حضوری)
    |--------------------------------------------------------------------------
    */
    protected function onlineRevenue(Carbon $from, Carbon $to): int
    {
        return (int) Order::where('payment_status', 'paid')->whereBetween('created_at', [$from, $to])->sum('total_amount');
    }

    protected function offlineRevenue(Carbon $from, Carbon $to): int
    {
        return (int) Invoice::whereNull('order_id')->where('status', 'paid')->whereBetween('created_at', [$from, $to])->sum('total_amount');
    }

    protected function paidOrdersCount(Carbon $from, Carbon $to): int
    {
        return Order::where('payment_status', 'paid')->whereBetween('created_at', [$from, $to])->count()
            + Invoice::whereNull('order_id')->where('status', 'paid')->whereBetween('created_at', [$from, $to])->count();
    }

    /**
     * شاخص‌های کلیدی بازه + مقایسه با بازه قبل
     */
    #[Computed]
    public function kpis(): array
    {
        [$from, $to] = $this->range();
        [$pFrom, $pTo] = $this->previousRange();

        $online = $this->onlineRevenue($from, $to);
        $offline = $this->offlineRevenue($from, $to);
        $revenue = $online + $offline;
        $prevRevenue = $this->onlineRevenue($pFrom, $pTo) + $this->offlineRevenue($pFrom, $pTo);

        $orders = $this->paidOrdersCount($from, $to);
        $prevOrders = $this->paidOrdersCount($pFrom, $pTo);

        $aov = $orders ? intdiv($revenue, $orders) : 0;
        $prevAov = $prevOrders ? intdiv($prevRevenue, $prevOrders) : 0;

        $users = User::whereBetween('created_at', [$from, $to])->count();
        $prevUsers = User::whereBetween('created_at', [$pFrom, $pTo])->count();

        $tickets = Ticket::whereBetween('created_at', [$from, $to])->count();
        $prevTickets = Ticket::whereBetween('created_at', [$pFrom, $pTo])->count();

        $views = DB::table('views')->whereBetween('created_at', [$from, $to])->count();
        $prevViews = DB::table('views')->whereBetween('created_at', [$pFrom, $pTo])->count();

        $refunds = (int) ReturnRequest::where('status', 'refunded')->whereBetween('refunded_at', [$from, $to])->sum('refund_amount');

        return [
            'revenue' => ['value' => $revenue, 'growth' => $this->growth($revenue, $prevRevenue), 'online' => $online, 'offline' => $offline],
            'orders' => ['value' => $orders, 'growth' => $this->growth($orders, $prevOrders)],
            'aov' => ['value' => $aov, 'growth' => $this->growth($aov, $prevAov)],
            'users' => ['value' => $users, 'growth' => $this->growth($users, $prevUsers), 'total' => User::count()],
            'tickets' => ['value' => $tickets, 'growth' => $this->growth($tickets, $prevTickets)],
            'views' => ['value' => $views, 'growth' => $this->growth($views, $prevViews)],
            'refunds' => ['value' => $refunds],
        ];
    }

    /**
     * کارهای در انتظار رسیدگی (با لینک مستقیم)
     */
    #[Computed]
    public function pending(): array
    {
        $items = [
            ['label' => 'سفارش در انتظار پرداخت', 'count' => Order::where('status', 'pending')->where('payment_status', 'unpaid')->where('expires_at', '>', now())->count(), 'icon' => 'ri-time-line', 'color' => 'warning', 'route' => route('invoices.index', ['status' => 'unpaid']), 'perm' => 'invoices.view'],
            ['label' => 'سفارش در حال پردازش', 'count' => Order::where('status', 'processing')->count(), 'icon' => 'ri-loader-4-line', 'color' => 'info', 'route' => route('invoices.index', ['status' => 'paid']), 'perm' => 'invoices.view'],
            ['label' => 'پرداخت کارت‌به‌کارت در انتظار تأیید', 'count' => Payment::where('status', 'pending')->count(), 'icon' => 'ri-bank-card-line', 'color' => 'primary', 'route' => route('invoices.index'), 'perm' => 'invoices.view'],
            ['label' => 'تیکت باز', 'count' => Ticket::where('status', 'open')->count(), 'icon' => 'ri-customer-service-2-line', 'color' => 'danger', 'route' => route('tickets.index'), 'perm' => 'tickets.view'],
            ['label' => 'درخواست مرجوعی جدید', 'count' => ReturnRequest::where('status', 'pending')->count(), 'icon' => 'ri-arrow-go-back-line', 'color' => 'warning', 'route' => route('returns.index'), 'perm' => 'returns.view'],
            ['label' => 'دیدگاه در انتظار تأیید', 'count' => Comment::where('is_approved', 0)->whereNull('deleted_at')->whereNull('parent_id')->count(), 'icon' => 'ri-chat-3-line', 'color' => 'secondary', 'route' => route('comments.index'), 'perm' => 'comments.view'],
            ['label' => 'پرسش محصول بدون تأیید', 'count' => ProductQuestion::where('status', false)->count(), 'icon' => 'ri-questionnaire-line', 'color' => 'secondary', 'route' => route('product-questions.index'), 'perm' => 'product-questions.view'],
            ['label' => 'کالای با موجودی کم', 'count' => $this->lowStockCount, 'icon' => 'ri-error-warning-line', 'color' => 'danger', 'route' => route('inventories.index'), 'perm' => 'inventories.view'],
        ];

        // فقط کارهایی که کاربر به بخش مربوطش دسترسی دارد
        return array_values(array_filter($items, fn ($item) => auth()->user()->can($item['perm'])));
    }

    #[Computed]
    public function lowStockCount(): int
    {
        return InventoryItem::where('status', true)
            ->whereRaw('(quantity - reserved_quantity) <= minimum_quantity')
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | نمودارها
    |--------------------------------------------------------------------------
    */

    /**
     * سری زمانی روزانه => برای ۳۶۵ روز به ماه‌های شمسی تجمیع می‌شود
     *
     * @param  array<string, array<string, int>>  $series  [name => ['Y-m-d' => value]]
     */
    protected function timeline(array $series): array
    {
        [$start] = $this->range();
        $days = $this->days();
        $monthly = $days > 90;

        $buckets = [];
        $labels = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $monthly ? verta($date)->format('Y/m') : $date->format('Y-m-d');

            if (!isset($labels[$key])) {
                $labels[$key] = $monthly ? verta($date)->format('F y') : verta($date)->format('d F');
            }

            foreach ($series as $name => $values) {
                $buckets[$name][$key] = ($buckets[$name][$key] ?? 0) + (int) ($values[$date->format('Y-m-d')] ?? 0);
            }
        }

        return [
            'labels' => array_values($labels),
            'series' => collect($buckets)->map(fn ($values, $name) => ['name' => $name, 'data' => array_values($values)])->values()->all(),
        ];
    }

    protected function dailySum($query, string $column = 'total_amount', string $dateColumn = 'created_at'): array
    {
        [$from, $to] = $this->range();

        return $query->whereBetween($dateColumn, [$from, $to])
            ->selectRaw("DATE({$dateColumn}) as d, SUM({$column}) as v")
            ->groupBy('d')
            ->pluck('v', 'd')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    protected function dailyCount($query, string $dateColumn = 'created_at'): array
    {
        [$from, $to] = $this->range();

        return $query->whereBetween($dateColumn, [$from, $to])
            ->selectRaw("DATE({$dateColumn}) as d, COUNT(*) as v")
            ->groupBy('d')
            ->pluck('v', 'd')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    #[Computed]
    public function charts(): array
    {
        [$from, $to] = $this->range();

        // فروش آنلاین / حضوری
        $sales = $this->timeline([
            'فروش سایت' => $this->dailySum(Order::where('payment_status', 'paid')),
            'فروش حضوری و دستی' => $this->dailySum(Invoice::whereNull('order_id')->where('status', 'paid')),
        ]);

        // فعالیت: سفارش، کاربر جدید، تیکت
        $activity = $this->timeline([
            'سفارش' => $this->dailyCount(Order::query()),
            'کاربر جدید' => $this->dailyCount(User::query()),
            'تیکت' => $this->dailyCount(Ticket::query()),
        ]);

        // وضعیت سفارش‌ها در بازه
        $orderLabels = ['pending' => 'در انتظار', 'processing' => 'در حال پردازش', 'shipped' => 'ارسال شده', 'completed' => 'تکمیل شده', 'cancelled' => 'لغو شده'];
        $ordersByStatus = Order::whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // روش پرداخت سفارش‌های پرداخت‌شده
        $methodLabels = ['wallet' => 'کیف پول', 'transfer' => 'کارت به کارت', 'gateway' => 'درگاه', 'cod' => 'در محل'];
        $methods = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->select('payment_method', DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');
        $offline = $this->offlineRevenue($from, $to);

        // وضعیت تیکت‌ها (کل)
        $ticketLabels = ['open' => 'باز', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته'];
        $ticketsByStatus = Ticket::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        // پرفروش‌ترین محصولات (مبلغ)
        $top = $this->topProducts;

        return [
            'sales' => $sales,
            'activity' => $activity,
            'orders' => [
                'labels' => $ordersByStatus->keys()->map(fn ($k) => $orderLabels[$k] ?? $k)->values()->all(),
                'values' => $ordersByStatus->values()->map(fn ($v) => (int) $v)->all(),
            ],
            'payments' => [
                'labels' => array_merge(
                    $methods->keys()->map(fn ($k) => $methodLabels[$k] ?? ($k ?: 'نامشخص'))->values()->all(),
                    $offline ? ['فروش حضوری/دستی'] : []
                ),
                'values' => array_merge($methods->values()->map(fn ($v) => (int) $v)->all(), $offline ? [$offline] : []),
            ],
            'tickets' => [
                'labels' => $ticketsByStatus->keys()->map(fn ($k) => $ticketLabels[$k] ?? $k)->values()->all(),
                'values' => $ticketsByStatus->values()->map(fn ($v) => (int) $v)->all(),
            ],
            'topProducts' => [
                'labels' => $top->pluck('product_name')->map(fn ($n) => \Illuminate\Support\Str::limit($n, 28))->all(),
                'values' => $top->pluck('revenue')->map(fn ($v) => (int) $v)->all(),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | جدول‌ها و فهرست‌ها
    |--------------------------------------------------------------------------
    */

    // پرفروش‌ترین‌ها در بازه (سفارش‌های پرداخت‌شده + فاکتورهای حضوری)
    #[Computed]
    public function topProducts()
    {
        [$from, $to] = $this->range();

        $online = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.created_at', [$from, $to])
            ->select('order_items.product_name', DB::raw('SUM(order_items.quantity) as qty'), DB::raw('SUM(order_items.total_price) as revenue'))
            ->groupBy('order_items.product_name');

        $offline = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNull('invoices.order_id')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.status', 'paid')
            ->whereBetween('invoices.created_at', [$from, $to])
            ->select('invoice_items.product_name', DB::raw('SUM(invoice_items.quantity) as qty'), DB::raw('SUM(invoice_items.total_price) as revenue'))
            ->groupBy('invoice_items.product_name');

        return DB::query()
            ->fromSub($online->unionAll($offline), 'sales')
            ->select('product_name', DB::raw('SUM(qty) as qty'), DB::raw('SUM(revenue) as revenue'))
            ->groupBy('product_name')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function topCustomers()
    {
        [$from, $to] = $this->range();

        return Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total_amount) as spent'))
            ->groupBy('user_id')
            ->orderByDesc('spent')
            ->with('user:id,first_name,last_name,mobile')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function ticketStats(): array
    {
        [$from, $to] = $this->range();

        // میانگین زمان اولین پاسخ پشتیبانی (دقیقه) برای تیکت‌های بازه
        // پاسخ پشتیبانی = پیامی که نویسنده‌اش صاحب تیکت نیست
        $firstReplies = DB::table('tickets')
            ->joinSub(
                DB::table('ticket_messages')
                    ->join('tickets as owner', 'owner.id', '=', 'ticket_messages.ticket_id')
                    ->whereColumn('ticket_messages.user_id', '!=', 'owner.user_id')
                    ->select('ticket_messages.ticket_id', DB::raw('MIN(ticket_messages.created_at) as first_reply'))
                    ->groupBy('ticket_messages.ticket_id'),
                'fr',
                'fr.ticket_id', '=', 'tickets.id'
            )
            ->whereBetween('tickets.created_at', [$from, $to])
            ->get(['tickets.created_at', 'fr.first_reply']);

        $avgMinutes = $firstReplies->isEmpty()
            ? null
            : (int) round($firstReplies->avg(fn ($r) => Carbon::parse($r->created_at)->diffInMinutes(Carbon::parse($r->first_reply))));

        return [
            'open' => Ticket::where('status', 'open')->count(),
            'answered' => Ticket::where('status', 'answered')->count(),
            'high' => Ticket::where('priority', 'high')->where('status', '!=', 'closed')->count(),
            'closed_period' => Ticket::where('status', 'closed')->whereBetween('closed_at', [$from, $to])->count(),
            'avg_first_reply' => $avgMinutes,
        ];
    }

    #[Computed]
    public function recentOrders()
    {
        return Order::with('user:id,first_name,last_name,mobile')
            ->latest()
            ->take(7)
            ->get();
    }

    #[Computed]
    public function recentTickets()
    {
        return Ticket::with('user:id,first_name,last_name,mobile')
            ->whereIn('status', ['open', 'answered'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderByDesc('last_reply_at')
            ->take(6)
            ->get();
    }

    #[Computed]
    public function lowStockItems()
    {
        return InventoryItem::with(['productVariant.product:id,title', 'productVariant.values', 'inventory:id,title'])
            ->where('status', true)
            ->whereRaw('(quantity - reserved_quantity) <= minimum_quantity')
            ->orderByRaw('(quantity - reserved_quantity)')
            ->take(6)
            ->get();
    }

    #[Computed]
    public function recentMovements()
    {
        return StockMovement::with(['variant.product:id,title', 'inventory:id,title'])
            ->latest('id')
            ->take(6)
            ->get();
    }

    #[Computed]
    public function walletBalance(): int
    {
        return (int) Wallet::sum('balance');
    }

    /*
    |--------------------------------------------------------------------------
    | نمایش
    |--------------------------------------------------------------------------
    */
    public function money(int|string|null $amount): string
    {
        return number_format((int) ($amount ?? 0));
    }

    public function compact(int $amount): string
    {
        return match (true) {
            $amount >= 1_000_000_000 => round($amount / 1_000_000_000, 1) . ' میلیارد',
            $amount >= 1_000_000 => round($amount / 1_000_000, 1) . ' میلیون',
            default => number_format($amount),
        };
    }

    public function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'pending'    => 'در انتظار',
            'processing' => 'در حال پردازش',
            'shipped'    => 'ارسال شده',
            'completed'  => 'تکمیل شده',
            'cancelled'  => 'لغو شده',
            default      => $status,
        };
    }

    public function orderStatusBadge(string $status): string
    {
        return match ($status) {
            'pending'    => 'warning',
            'processing' => 'info',
            'shipped'    => 'primary',
            'completed'  => 'success',
            'cancelled'  => 'danger',
            default      => 'secondary',
        };
    }

    public function ticketStatusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'باز',
            'answered' => 'پاسخ داده شده',
            'closed' => 'بسته',
            default => $status,
        };
    }

    public function durationLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        return match (true) {
            $minutes < 60 => $minutes . ' دقیقه',
            $minutes < 1440 => round($minutes / 60, 1) . ' ساعت',
            default => round($minutes / 1440, 1) . ' روز',
        };
    }
};
?>

<div dir="rtl">
    @push('styles')
        <link rel="stylesheet" href="{{ asset('dashboard/libs/apexcharts/apexcharts.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('dashboard/libs/apexcharts/apexcharts.min.js') }}"></script>
    @endpush

    @can('dashboard.view')
    @php
        $kpis = $this->kpis;
        $periodLabel = ['7' => '۷ روز اخیر', '30' => '۳۰ روز اخیر', '90' => '۳ ماه اخیر', '365' => 'یک سال اخیر'][$period] ?? '';
    @endphp

    {{-- Header --}}
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="page-title fw-semibold fs-18 mb-1">داشبورد مدیریت</h1>
            <div class="text-muted small">
                {{ verta()->format('l، d F Y') }}
                <span class="mx-1">·</span>
                گزارش {{ $periodLabel }} در مقایسه با دوره قبل
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="btn-group" role="group" aria-label="بازه گزارش">
                @foreach(['7' => '۷ روز', '30' => '۳۰ روز', '90' => '۳ ماه', '365' => '۱ سال'] as $value => $label)
                    <button type="button"
                            wire:click="$set('period', '{{ $value }}')"
                            class="btn btn-sm {{ $period === $value ? 'btn-primary' : 'btn-outline-primary' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @can('invoices.create')
                <a href="{{ route('invoices.create') }}" class="btn btn-sm btn-success-light">
                    <i class="ri-add-line align-middle"></i>
                    فروش حضوری
                </a>
            @endcan

            <span wire:loading wire:target="period" class="spinner-border spinner-border-sm text-primary" role="status"></span>
        </div>
    </div>

    <div wire:loading.class="opacity-50" wire:target="period" style="transition: opacity .2s">

        {{-- KPI cards --}}
        <div class="row g-3 mb-1">
            @php
                $cards = [
                    ['title' => 'درآمد', 'value' => $this->compact($kpis['revenue']['value']), 'suffix' => 'تومان', 'growth' => $kpis['revenue']['growth'], 'icon' => 'ri-money-dollar-circle-line', 'color' => 'primary',
                        'hint' => 'سایت ' . $this->compact($kpis['revenue']['online']) . ' · حضوری ' . $this->compact($kpis['revenue']['offline'])],
                    ['title' => 'سفارش موفق', 'value' => number_format($kpis['orders']['value']), 'suffix' => 'عدد', 'growth' => $kpis['orders']['growth'], 'icon' => 'ri-shopping-bag-3-line', 'color' => 'success',
                        'hint' => 'میانگین سبد ' . $this->compact($kpis['aov']['value']) . ' تومان'],
                    ['title' => 'کاربر جدید', 'value' => number_format($kpis['users']['value']), 'suffix' => 'نفر', 'growth' => $kpis['users']['growth'], 'icon' => 'ri-user-add-line', 'color' => 'info',
                        'hint' => 'کل کاربران ' . number_format($kpis['users']['total'])],
                    ['title' => 'تیکت جدید', 'value' => number_format($kpis['tickets']['value']), 'suffix' => 'عدد', 'growth' => $kpis['tickets']['growth'], 'icon' => 'ri-customer-service-2-line', 'color' => 'warning', 'invert' => true,
                        'hint' => 'میانگین اولین پاسخ ' . $this->durationLabel($this->ticketStats['avg_first_reply'])],
                    ['title' => 'بازدید محصولات', 'value' => number_format($kpis['views']['value']), 'suffix' => 'بازدید', 'growth' => $kpis['views']['growth'], 'icon' => 'ri-eye-line', 'color' => 'secondary',
                        'hint' => 'نرخ تبدیل ' . ($kpis['views']['value'] ? round($kpis['orders']['value'] / $kpis['views']['value'] * 100, 2) : 0) . '٪'],
                    ['title' => 'مبالغ مسترد', 'value' => $this->compact($kpis['refunds']['value']), 'suffix' => 'تومان', 'growth' => null, 'icon' => 'ri-arrow-go-back-line', 'color' => 'danger',
                        'hint' => 'موجودی کیف پول‌ها ' . $this->compact($this->walletBalance)],
                ];
            @endphp

            @foreach($cards as $card)
                <div class="col-xxl-2 col-xl-4 col-md-6">
                    <div class="card custom-card h-100 mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                <span class="avatar avatar-md bg-{{ $card['color'] }}-transparent rounded-3">
                                    <i class="{{ $card['icon'] }} fs-20"></i>
                                </span>

                                @if(!is_null($card['growth']))
                                    @php
                                        $up = $card['growth'] >= 0;
                                        $good = ($card['invert'] ?? false) ? !$up : $up;
                                    @endphp
                                    <span class="badge bg-{{ $good ? 'success' : 'danger' }}-transparent" title="نسبت به دوره قبل">
                                        <i class="ri-arrow-{{ $up ? 'up' : 'down' }}-line"></i>
                                        {{ abs($card['growth']) }}٪
                                    </span>
                                @endif
                            </div>
                            <div class="text-muted small mb-1">{{ $card['title'] }}</div>
                            <div class="d-flex align-items-baseline gap-1">
                                <span class="fs-20 fw-bold">{{ $card['value'] }}</span>
                                <span class="text-muted small">{{ $card['suffix'] }}</span>
                            </div>
                            <div class="text-muted fs-11 mt-2">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Charts --}}
        <div wire:ignore
             x-data="dashboardCharts(@js($this->charts))"
             @dashboard-charts-updated.window="update($event.detail.charts)">

            <div class="row g-3 mt-0">
                <div class="col-xl-8">
                    <div class="card custom-card h-100 mb-0">
                        <div class="card-header justify-content-between">
                            <div class="card-title">روند فروش</div>
                            <span class="text-muted small">مبالغ به تومان</span>
                        </div>
                        <div class="card-body pt-0">
                            <div x-ref="sales" style="min-height: 320px"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card custom-card h-100 mb-0">
                        <div class="card-header">
                            <div class="card-title">سهم روش‌های پرداخت</div>
                        </div>
                        <div class="card-body pt-0">
                            <div x-ref="payments" style="min-height: 320px"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-xl-8">
                    <div class="card custom-card h-100 mb-0">
                        <div class="card-header">
                            <div class="card-title">فعالیت سایت (سفارش، کاربر جدید، تیکت)</div>
                        </div>
                        <div class="card-body pt-0">
                            <div x-ref="activity" style="min-height: 290px"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card custom-card h-100 mb-0">
                        <div class="card-header">
                            <div class="card-title">وضعیت سفارش‌های دوره</div>
                        </div>
                        <div class="card-body pt-0">
                            <div x-ref="orders" style="min-height: 290px"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-xl-8">
                    <div class="card custom-card h-100 mb-0">
                        <div class="card-header">
                            <div class="card-title">پرفروش‌ترین محصولات (مبلغ فروش)</div>
                        </div>
                        <div class="card-body pt-0">
                            <div x-ref="topProducts" style="min-height: 280px"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card custom-card h-100 mb-0">
                        <div class="card-header">
                            <div class="card-title">تیکت‌ها</div>
                        </div>
                        <div class="card-body pt-0">
                            <div x-ref="tickets" style="min-height: 220px"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Support + pending --}}
        <div class="row g-3 mt-0">
            <div class="col-xl-4">
                <div class="card custom-card h-100 mb-0">
                    <div class="card-header">
                        <div class="card-title">کارهای در انتظار رسیدگی</div>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @foreach($this->pending as $task)
                                <li class="list-group-item">
                                    <a href="{{ $task['route'] }}" class="d-flex align-items-center justify-content-between text-reset">
                                        <span class="d-flex align-items-center gap-2">
                                            <span class="avatar avatar-sm bg-{{ $task['color'] }}-transparent rounded-2"><i class="{{ $task['icon'] }}"></i></span>
                                            <span class="small">{{ $task['label'] }}</span>
                                        </span>
                                        <span class="badge {{ $task['count'] ? 'bg-' . $task['color'] : 'bg-light text-muted' }}">{{ number_format($task['count']) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card custom-card h-100 mb-0">
                    <div class="card-header justify-content-between">
                        <div class="card-title">پشتیبانی</div>
                        <a href="{{ route('tickets.index') }}" class="small">همه تیکت‌ها</a>
                    </div>
                    <div class="card-body">
                        @php
                            $ts = $this->ticketStats;
                        @endphp
                        <div class="row g-2 mb-3 text-center">
                            <div class="col-4"><div class="border rounded p-2"><div class="fw-bold text-danger">{{ $ts['open'] }}</div><div class="fs-11 text-muted">باز</div></div></div>
                            <div class="col-4"><div class="border rounded p-2"><div class="fw-bold text-info">{{ $ts['answered'] }}</div><div class="fs-11 text-muted">پاسخ داده</div></div></div>
                            <div class="col-4"><div class="border rounded p-2"><div class="fw-bold text-success">{{ $ts['closed_period'] }}</div><div class="fs-11 text-muted">بسته‌شده دوره</div></div></div>
                        </div>
                        <div class="d-flex justify-content-between small mb-3">
                            <span class="text-muted">میانگین اولین پاسخ</span>
                            <span class="fw-semibold">{{ $this->durationLabel($ts['avg_first_reply']) }}</span>
                        </div>
                        @if($ts['high'])
                            <div class="alert alert-danger py-2 small mb-3">{{ $ts['high'] }} تیکت با اولویت بالا باز است.</div>
                        @endif

                        <ul class="list-unstyled mb-0">
                            @forelse($this->recentTickets as $ticket)
                                <li class="d-flex align-items-center justify-content-between py-2 border-bottom" wire:key="dash-ticket-{{ $ticket->id }}">
                                    <span class="text-truncate" style="max-width: 70%">
                                        <span class="d-block small fw-semibold text-truncate">{{ $ticket->title }}</span>
                                        <span class="fs-11 text-muted">{{ trim(($ticket->user?->first_name ?? '') . ' ' . ($ticket->user?->last_name ?? '')) ?: $ticket->user?->mobile }}</span>
                                    </span>
                                    <span class="d-flex flex-column align-items-end gap-1">
                                        @if($ticket->priority === 'high')
                                            <span class="badge bg-danger-transparent fs-10">فوری</span>
                                        @endif
                                        <span class="badge bg-{{ $ticket->status === 'open' ? 'warning' : 'info' }}-transparent fs-10">{{ $this->ticketStatusLabel($ticket->status) }}</span>
                                    </span>
                                </li>
                            @empty
                                <li class="text-muted small text-center py-3">تیکت باز وجود ندارد.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card custom-card h-100 mb-0">
                    <div class="card-header">
                        <div class="card-title">مشتریان برتر دوره</div>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            @forelse($this->topCustomers as $index => $row)
                                <li class="d-flex align-items-center justify-content-between py-2 {{ !$loop->last ? 'border-bottom' : '' }}" wire:key="dash-customer-{{ $row->user_id }}">
                                    <span class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm bg-primary-transparent rounded-circle fw-bold">{{ $index + 1 }}</span>
                                        <span>
                                            <a href="{{ route('invoices.index', ['user' => $row->user_id]) }}" class="d-block small fw-semibold text-reset">
                                                {{ trim(($row->user?->first_name ?? '') . ' ' . ($row->user?->last_name ?? '')) ?: $row->user?->mobile }}
                                            </a>
                                            <span class="fs-11 text-muted">{{ $row->orders }} سفارش</span>
                                        </span>
                                    </span>
                                    <span class="small fw-semibold">{{ $this->compact((int) $row->spent) }}</span>
                                </li>
                            @empty
                                <li class="text-muted small text-center py-3">در این دوره خریدی ثبت نشده است.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tables --}}
        <div class="row g-3 mt-0 mb-4">
            <div class="col-xl-7">
                <div class="card custom-card h-100 mb-0">
                    <div class="card-header justify-content-between">
                        <div class="card-title">آخرین سفارش‌ها</div>
                        <a href="{{ route('invoices.index') }}" class="small">همه فاکتورها</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover text-nowrap align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>سفارش</th>
                                    <th>مشتری</th>
                                    <th>مبلغ</th>
                                    <th>وضعیت</th>
                                    <th>زمان</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($this->recentOrders as $order)
                                    <tr wire:key="dash-order-{{ $order->id }}">
                                        <td class="fw-semibold small">{{ $order->order_number }}</td>
                                        <td class="small">
                                            {{ trim(($order->user?->first_name ?? '') . ' ' . ($order->user?->last_name ?? '')) ?: $order->user?->mobile }}
                                        </td>
                                        <td class="small">{{ $this->money($order->total_amount) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $this->orderStatusBadge($order->status) }}-transparent">{{ $this->orderStatusLabel($order->status) }}</span>
                                            @if($order->payment_status === 'paid')
                                                <i class="ri-checkbox-circle-fill text-success" title="پرداخت‌شده"></i>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ verta($order->created_at)->formatDifference() }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">سفارشی ثبت نشده است.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card custom-card mb-3">
                    <div class="card-header justify-content-between">
                        <div class="card-title">هشدار موجودی</div>
                        <a href="{{ route('inventories.index') }}" class="small">انبارها</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @forelse($this->lowStockItems as $item)
                                @php
                                    $available = (int) $item->quantity - (int) $item->reserved_quantity;
                                @endphp
                                <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="dash-stock-{{ $item->id }}">
                                    <span class="small">
                                        <span class="fw-semibold">{{ $item->productVariant?->product?->title ?? '—' }}</span>
                                        <span class="text-muted">{{ $item->productVariant?->label }}</span>
                                        <span class="d-block fs-11 text-muted">{{ $item->inventory?->title }} · حداقل {{ $item->minimum_quantity }}</span>
                                    </span>
                                    <span class="badge bg-{{ $available <= 0 ? 'danger' : 'warning' }}">{{ $available <= 0 ? 'ناموجود' : $available . ' عدد' }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted small text-center py-3">همه کالاها موجودی کافی دارند.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="card custom-card mb-0">
                    <div class="card-header">
                        <div class="card-title">آخرین گردش انبار</div>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @forelse($this->recentMovements as $movement)
                                <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="dash-move-{{ $movement->id }}">
                                    <span class="small">
                                        <span class="fw-semibold">{{ $movement->variant?->product?->title ?? '—' }}</span>
                                        <span class="d-block fs-11 text-muted">{{ $movement->type_label }} · {{ $movement->inventory?->title }} · {{ verta($movement->created_at)->formatDifference() }}</span>
                                    </span>
                                    <span class="fw-semibold small {{ $movement->quantity_change < 0 ? 'text-danger' : ($movement->quantity_change > 0 ? 'text-success' : 'text-muted') }}" dir="ltr">
                                        @if($movement->quantity_change)
                                            {{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}
                                        @else
                                            {{ $movement->reserved_change > 0 ? '+' : '' }}{{ $movement->reserved_change }} رزرو
                                        @endif
                                    </span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted small text-center py-3">گردشی ثبت نشده است.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @else
        {{-- کاربر پنل بدون دسترسی گزارش‌ها: میان‌بر بخش‌های مجاز --}}
        <div class="my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-1">خوش آمدید{{ trim(auth()->user()->full_name) ? '، ' . auth()->user()->full_name : '' }}</h1>
            <div class="text-muted small">{{ verta()->format('l، d F Y') }}</div>
        </div>

        @php
            $shortcuts = collect(\App\Support\Permissions::AREAS)
                ->filter(fn ($meta, $area) => in_array('view', $meta['actions'], true) && auth()->user()->can($area . '.view'))
                ->map(fn ($meta, $area) => ['label' => $meta['label'], 'route' => match ($area) {
                    'campaigns' => 'campaign.index',
                    'messages' => 'messages.index',
                    'storage' => 'storage.index',
                    default => $area . '.index',
                }])
                ->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']));
        @endphp

        <div class="row g-3">
            @forelse($shortcuts as $area => $item)
                <div class="col-xl-3 col-md-4 col-sm-6">
                    <a href="{{ route($item['route']) }}" class="card custom-card h-100 mb-0 text-reset">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar avatar-md bg-primary-transparent rounded-3"><i class="ri-arrow-left-up-line fs-18"></i></span>
                            <span class="fw-semibold">{{ $item['label'] }}</span>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-warning mb-0">هنوز دسترسی به هیچ بخشی برای شما تعریف نشده است. با مدیر سایت تماس بگیرید.</div>
                </div>
            @endforelse
        </div>
    @endcan

    @script
    <script>
        Alpine.data('dashboardCharts', (initial) => ({
            charts: {},

            init() {
                // اطمینان از بارگذاری ApexCharts (اسکریپت در stack لایه داشبورد)
                const start = () => window.ApexCharts ? this.render(initial) : setTimeout(start, 50);
                start();
            },

            destroy() {
                Object.values(this.charts).forEach((chart) => chart.destroy());
            },

            dark() {
                return document.documentElement.getAttribute('data-theme-mode') === 'dark';
            },

            fa(value) {
                return new Intl.NumberFormat('fa-IR').format(value ?? 0);
            },

            compact(value) {
                if (value >= 1e9) return this.fa(+(value / 1e9).toFixed(1)) + ' میلیارد';
                if (value >= 1e6) return this.fa(+(value / 1e6).toFixed(1)) + ' میلیون';
                if (value >= 1e3) return this.fa(+(value / 1e3).toFixed(0)) + ' هزار';
                return this.fa(value);
            },

            base(extra = {}) {
                return Object.assign({
                    fontFamily: 'inherit',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    foreColor: this.dark() ? '#a3a6b7' : '#6e7080',
                    animations: { speed: 400 },
                }, extra);
            },

            empty(values) {
                return !values || values.length === 0 || values.every((v) => !v);
            },

            noData: { text: 'داده‌ای برای این دوره ثبت نشده است', style: { fontSize: '13px' } },

            options(data) {
                const self = this;
                const colors = ['#5b5fc7', '#26bf94', '#f5b849', '#e6533c', '#23b7e5', '#845adf'];
                const grid = { borderColor: this.dark() ? 'rgba(255,255,255,.07)' : '#f0f0f5', strokeDashArray: 4 };

                return {
                    sales: {
                        chart: this.base({ type: 'area', height: 320, stacked: true }),
                        series: data.sales.series,
                        colors: ['#5b5fc7', '#26bf94'],
                        dataLabels: { enabled: false },
                        stroke: { curve: 'smooth', width: 2 },
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
                        xaxis: { categories: data.sales.labels, tickAmount: 10, labels: { rotate: 0, hideOverlappingLabels: true } },
                        yaxis: { labels: { formatter: (v) => self.compact(v) } },
                        tooltip: { y: { formatter: (v) => self.fa(v) + ' تومان' } },
                        legend: { position: 'top', horizontalAlign: 'left' },
                        grid,
                        noData: this.noData,
                    },
                    activity: {
                        chart: this.base({ type: 'bar', height: 290 }),
                        series: data.activity.series,
                        colors: ['#5b5fc7', '#23b7e5', '#f5b849'],
                        plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
                        dataLabels: { enabled: false },
                        xaxis: { categories: data.activity.labels, tickAmount: 10, labels: { rotate: 0, hideOverlappingLabels: true } },
                        yaxis: { labels: { formatter: (v) => self.fa(Math.round(v)) } },
                        tooltip: { y: { formatter: (v) => self.fa(v) } },
                        legend: { position: 'top', horizontalAlign: 'left' },
                        grid,
                        noData: this.noData,
                    },
                    payments: this.donut(data.payments, 320, (v) => self.fa(v) + ' تومان', true),
                    orders: this.donut(data.orders, 290, (v) => self.fa(v) + ' سفارش'),
                    tickets: this.donut(data.tickets, 220, (v) => self.fa(v) + ' تیکت', false, ['#e6533c', '#23b7e5', '#26bf94']),
                    topProducts: {
                        chart: this.base({ type: 'bar', height: 280 }),
                        series: [{ name: 'فروش', data: data.topProducts.values }],
                        colors,
                        plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: '60%' } },
                        dataLabels: { enabled: true, formatter: (v) => self.compact(v), style: { fontSize: '11px' } },
                        xaxis: { categories: data.topProducts.labels, labels: { formatter: (v) => self.compact(v) } },
                        tooltip: { y: { formatter: (v) => self.fa(v) + ' تومان' } },
                        legend: { show: false },
                        grid,
                        noData: this.noData,
                    },
                };
            },

            donut(data, height, formatter, money = false, colors = null) {
                const self = this;

                return {
                    chart: this.base({ type: 'donut', height }),
                    series: this.empty(data.values) ? [] : data.values,
                    labels: data.labels,
                    colors: colors ?? ['#f5b849', '#23b7e5', '#5b5fc7', '#26bf94', '#e6533c', '#845adf'],
                    legend: { position: 'bottom', fontSize: '12px' },
                    dataLabels: { enabled: false },
                    stroke: { width: 0 },
                    tooltip: { y: { formatter } },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '72%',
                                labels: {
                                    show: true,
                                    value: { formatter: (v) => money ? self.compact(+v) : self.fa(v) },
                                    total: {
                                        show: true,
                                        label: 'مجموع',
                                        formatter: (w) => {
                                            const sum = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                            return money ? self.compact(sum) : self.fa(sum);
                                        },
                                    },
                                },
                            },
                        },
                    },
                    noData: this.noData,
                };
            },

            render(data) {
                const options = this.options(data);

                Object.entries(options).forEach(([key, opts]) => {
                    if (!this.$refs[key]) return;
                    this.charts[key] = new ApexCharts(this.$refs[key], opts);
                    this.charts[key].render();
                });
            },

            update(data) {
                if (!window.ApexCharts || Object.keys(this.charts).length === 0) {
                    return;
                }

                const options = this.options(data);

                Object.entries(options).forEach(([key, opts]) => {
                    const chart = this.charts[key];
                    if (!chart) return;

                    const update = { series: opts.series };
                    if (opts.labels) update.labels = opts.labels;
                    if (opts.xaxis) update.xaxis = opts.xaxis;
                    chart.updateOptions(update, true, true);
                });
            },
        }));
    </script>
    @endscript
</div>
