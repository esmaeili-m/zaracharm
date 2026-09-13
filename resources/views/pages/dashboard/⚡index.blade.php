<?php // resources/views/components/admin/⚡dashboard.blade.php
// نکته: نام فایل واقعی باید با ایموجی ⚡ شروع شود: admin/⚡dashboard.blade.php
// در این خروجی به دلیل محدودیت نام‌گذاری، بدون ⚡ ذخیره شده — قبل از استفاده rename کنید.

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\Ticket;
use App\Models\Wallet;
use App\Models\Comment;
use App\Models\Payment;

new #[Layout('layouts.dashboard')] class extends Component {

    // بازه نمودار فروش (روز)
    public string $period = '30';

    /**
     * کارت‌های آماری اصلی
     */
    #[Computed]
    public function stats(): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        return [
            'revenue_today'    => (int) Order::where('payment_status', 'paid')->whereDate('created_at', $today)->sum('total_amount'),
            'revenue_month'    => (int) Order::where('payment_status', 'paid')->where('created_at', '>=', $startOfMonth)->sum('total_amount'),
            'orders_total'     => Order::count(),
            'orders_today'     => Order::whereDate('created_at', $today)->count(),
            'orders_pending'   => Order::where('status', 'pending')->count(),
            'orders_processing'=> Order::where('status', 'processing')->count(),
            'users_total'      => User::count(),
            'users_today'      => User::whereDate('created_at', $today)->count(),
            'products_total'   => Product::where('status', 1)->whereNull('deleted_at')->count(),
            'low_stock'        => InventoryItem::whereColumn('quantity', '<=', 'minimum_quantity')->count(),
            'tickets_open'     => Ticket::whereIn('status', ['open', 'answered'])->count(),
            'payments_pending' => Payment::where('status', 'pending')->count(),
            'wallets_balance'  => (int) Wallet::sum('balance'),
            'comments_pending' => Comment::where('is_approved', 0)->whereNull('deleted_at')->count(),
        ];
    }

    /**
     * تعداد سفارش‌ها به تفکیک وضعیت (برای نمودار دونات)
     */
    #[Computed]
    public function ordersByStatus()
    {
        return Order::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    /**
     * داده نمودار فروش N روز اخیر
     */
    #[Computed]
    public function salesChart(): array
    {
        $days  = max(7, min(90, (int) $this->period));
        $start = Carbon::today()->subDays($days - 1);

        $rows = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, SUM(total_amount) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $labels = [];
        $values = [];

        for ($i = 0; $i < $days; $i++) {
            $date     = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->translatedFormat('d M');
            $values[] = (int) ($rows[$date] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    #[Computed]
    public function recentOrders()
    {
        return Order::with('user:id,first_name,last_name,mobile')
            ->latest()
            ->take(8)
            ->get();
    }

    #[Computed]
    public function topProducts()
    {
        return OrderItem::select('product_name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(total_price) as revenue'))
            ->groupBy('product_name')
            ->orderByDesc('qty')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentTickets()
    {
        return Ticket::with('user:id,first_name,last_name')
            ->whereIn('status', ['open', 'answered'])
            ->orderByDesc('last_reply_at')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function lowStockItems()
    {
        return InventoryItem::with('productVariant.product:id,title')
            ->whereColumn('quantity', '<=', 'minimum_quantity')
            ->orderBy('quantity')
            ->take(5)
            ->get();
    }

    /**
     * وقتی بازه نمودار فروش تغییر کند، داده جدید را برای Alpine/Chart.js ارسال می‌کنیم
     * بدون رفرش کل کامپوننت (چون کانتینر نمودار wire:ignore دارد)
     */
    public function updatedPeriod(): void
    {
        $this->dispatch('sales-chart-updated', chart: $this->salesChart);
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
            'pending'    => 'text-bg-warning-subtle text-warning-emphasis',
            'processing' => 'text-bg-info-subtle text-info-emphasis',
            'shipped'    => 'text-bg-primary-subtle text-primary-emphasis',
            'completed'  => 'text-bg-success-subtle text-success-emphasis',
            'cancelled'  => 'text-bg-danger-subtle text-danger-emphasis',
            default      => 'text-bg-secondary-subtle text-secondary-emphasis',
        };
    }

    public function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'unpaid'   => 'پرداخت‌نشده',
            'pending'  => 'در انتظار پرداخت',
            'paid'     => 'پرداخت‌شده',
            'failed'   => 'ناموفق',
            'refunded' => 'بازگشت وجه',
            default    => $status,
        };
    }
};
?>

<div dir="rtl" x-data>

    {{-- هدر --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h1 class="h4 fw-bold text-dark mb-1">داشبورد مدیریت</h1>
            <p class="text-muted small mb-0">{{ now()->translatedFormat('l، d F Y') }}</p>
        </div>

        @if($this->stats['low_stock'] > 0 || $this->stats['tickets_open'] > 0)
            <div class="d-flex flex-wrap gap-2">
                @if($this->stats['low_stock'] > 0)
                    <span class="badge rounded-pill text-bg-danger-subtle text-danger-emphasis fw-normal px-3 py-2">
                        <i class="bi bi-exclamation-triangle ms-1"></i>
                        {{ $this->stats['low_stock'] }} کالا با موجودی کم
                    </span>
                @endif
                @if($this->stats['tickets_open'] > 0)
                    <span class="badge rounded-pill text-bg-info-subtle text-info-emphasis fw-normal px-3 py-2">
                        <i class="bi bi-headset ms-1"></i>
                        {{ $this->stats['tickets_open'] }} تیکت باز
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- کارت‌های آماری --}}
    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">درآمد امروز</p>
                    <p class="h4 fw-bold text-dark mb-0">{{ number_format($this->stats['revenue_today']) }}</p>
                    <p class="text-muted small mb-0">تومان</p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">درآمد این ماه</p>
                    <p class="h4 fw-bold text-dark mb-0">{{ number_format($this->stats['revenue_month']) }}</p>
                    <p class="text-muted small mb-0">تومان</p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">سفارش‌های امروز</p>
                    <p class="h4 fw-bold text-dark mb-0">{{ number_format($this->stats['orders_today']) }}</p>
                    <p class="text-muted small mb-0">از {{ number_format($this->stats['orders_total']) }} سفارش کل</p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">کاربران جدید امروز</p>
                    <p class="h4 fw-bold text-dark mb-0">{{ number_format($this->stats['users_today']) }}</p>
                    <p class="text-muted small mb-0">از {{ number_format($this->stats['users_total']) }} کاربر کل</p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">سفارش‌های در انتظار</p>
                    <p class="h4 fw-bold text-warning mb-0">{{ number_format($this->stats['orders_pending']) }}</p>
                    <p class="text-muted small mb-0">{{ number_format($this->stats['orders_processing']) }} در حال پردازش</p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">پرداخت‌های در انتظار</p>
                    <p class="h4 fw-bold text-dark mb-0">{{ number_format($this->stats['payments_pending']) }}</p>
                    <p class="text-muted small mb-0">نیاز به بررسی دارند</p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">مجموع موجودی کیف‌پول‌ها</p>
                    <p class="h4 fw-bold text-dark mb-0">{{ number_format($this->stats['wallets_balance']) }}</p>
                    <p class="text-muted small mb-0">تومان</p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">نظرات در انتظار تایید</p>
                    <p class="h4 fw-bold text-dark mb-0">{{ number_format($this->stats['comments_pending']) }}</p>
                    <p class="text-muted small mb-0">{{ number_format($this->stats['products_total']) }} محصول فعال</p>
                </div>
            </div>
        </div>

    </div>

    {{-- نمودارها --}}
    <div class="row g-3 mb-4">

        {{-- نمودار فروش --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h6 fw-bold text-dark mb-0">روند فروش</h2>
                        <select wire:model.live="period" class="form-select form-select-sm w-auto">
                            <option value="7">۷ روز اخیر</option>
                            <option value="30">۳۰ روز اخیر</option>
                            <option value="90">۹۰ روز اخیر</option>
                        </select>
                    </div>

                    <div
                        wire:ignore
                        x-data="salesChart(@js($this->salesChart))"
                        x-init="init()"
                        @sales-chart-updated.window="update($event.detail.chart)"
                    >
                        <canvas x-ref="canvas" height="110"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- وضعیت سفارش‌ها --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 fw-bold text-dark mb-3">وضعیت سفارش‌ها</h2>
                    <div
                        wire:ignore
                        x-data="ordersStatusChart(@js($this->ordersByStatus))"
                        x-init="init()"
                    >
                        <canvas x-ref="canvas" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-3">

        {{-- آخرین سفارش‌ها --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h6 fw-bold text-dark mb-0">آخرین سفارش‌ها</h2>
                        <a href="{{ route('orders.index') }}" class="small text-muted text-decoration-none">
                            مشاهده همه <i class="bi bi-arrow-left"></i>
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr class="text-muted small">
                                <th class="fw-medium">شماره سفارش</th>
                                <th class="fw-medium">مشتری</th>
                                <th class="fw-medium">مبلغ</th>
                                <th class="fw-medium">وضعیت</th>
                                <th class="fw-medium">پرداخت</th>
                                <th class="fw-medium">تاریخ</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($this->recentOrders as $order)
                                <tr wire:key="order-{{ $order->id }}">
                                    <td>
                                        <a href="{{ route('orders.show', $order) }}" class="fw-medium text-dark text-decoration-none">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="small">{{ trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: ($order->user->mobile ?? '—') }}</td>
                                    <td class="small">{{ number_format($order->total_amount) }}</td>
                                    <td>
                                            <span class="badge rounded-pill fw-normal {{ $this->orderStatusBadge($order->status) }}">
                                                {{ $this->orderStatusLabel($order->status) }}
                                            </span>
                                    </td>
                                    <td class="small text-muted">{{ $this->paymentStatusLabel($order->payment_status) }}</td>
                                    <td class="small text-muted">{{ $order->created_at?->translatedFormat('d M H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">هنوز سفارشی ثبت نشده است</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ستون کناری --}}
        <div class="col-lg-4">
            <div class="d-flex flex-column gap-3">

                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 fw-bold text-dark mb-3">پرفروش‌ترین محصولات</h2>
                        <ul class="list-unstyled mb-0">
                            @forelse($this->topProducts as $item)
                                <li wire:key="top-{{ $loop->index }}" class="d-flex align-items-center justify-content-between small mb-2">
                                    <span class="text-dark text-truncate" style="max-width: 160px;">{{ $item->product_name }}</span>
                                    <span class="text-muted">{{ number_format($item->qty) }} عدد</span>
                                </li>
                            @empty
                                <li class="small text-muted">داده‌ای موجود نیست</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 fw-bold text-dark mb-3">تیکت‌های باز اخیر</h2>
                        <ul class="list-unstyled mb-0">
                            @forelse($this->recentTickets as $ticket)
                                <li wire:key="ticket-{{ $ticket->id }}" class="mb-2">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="small text-dark text-decoration-none d-block text-truncate">
                                        {{ $ticket->title }}
                                    </a>
                                    <span class="small text-muted">{{ trim(($ticket->user->first_name ?? '') . ' ' . ($ticket->user->last_name ?? '')) ?: 'کاربر' }}</span>
                                </li>
                            @empty
                                <li class="small text-muted">تیکت بازی وجود ندارد</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 fw-bold text-dark mb-3">موجودی کم</h2>
                        <ul class="list-unstyled mb-0">
                            @forelse($this->lowStockItems as $item)
                                <li wire:key="stock-{{ $item->id }}" class="d-flex align-items-center justify-content-between small mb-2">
                                    <span class="text-dark text-truncate" style="max-width: 160px;">{{ $item->productVariant?->product?->title ?? '—' }}</span>
                                    <span class="text-danger fw-medium">{{ $item->quantity }}</span>
                                </li>
                            @empty
                                <li class="small text-muted">همه کالاها موجودی کافی دارند</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('salesChart', (initial) => ({
            chart: null,
            init() {
                this.chart = new Chart(this.$refs.canvas, {
                    type: 'line',
                    data: {
                        labels: initial.labels,
                        datasets: [{
                            label: 'فروش (تومان)',
                            data: initial.values,
                            borderColor: '#5b5fc7',
                            backgroundColor: 'rgba(91,95,199,0.08)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { ticks: { callback: (v) => new Intl.NumberFormat('fa-IR').format(v) } },
                        },
                    },
                });
            },
            update(data) {
                this.chart.data.labels = data.labels;
                this.chart.data.datasets[0].data = data.values;
                this.chart.update();
            },
        }));

        Alpine.data('ordersStatusChart', (initial) => ({
            init() {
                const labels = {
                    pending: 'در انتظار',
                    processing: 'در حال پردازش',
                    shipped: 'ارسال شده',
                    completed: 'تکمیل شده',
                    cancelled: 'لغو شده',
                };
                new Chart(this.$refs.canvas, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(initial).map(k => labels[k] ?? k),
                        datasets: [{
                            data: Object.values(initial),
                            backgroundColor: ['#f0ad4e', '#5bc0de', '#5b5fc7', '#5cb85c', '#d9534f'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                    },
                });
            },
        }));
    </script>
    @endscript

</div>
