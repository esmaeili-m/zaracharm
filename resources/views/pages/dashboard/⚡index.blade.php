<?php

use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Rating;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
new class extends \Livewire\Component
{
    public $totalUsers;
    public $totalCourses;
    public $totalRevenue;
    public $totalInvoices;
    public $pendingTickets;
    public $newUsersThisMonth;

    // داده‌های نمودارها (JSON)
    public $monthlyRevenueJson;
    public $courseSalesJson;
    public $newUsersJson;
    public $courseShareJson;

    // جداول
    public $topCourses;
    public $recentInvoices;
    public $recentRatings;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        $this->loadStats();
        $this->loadCharts();
        $this->loadTables();
    }

    private function loadStats()
    {
        $this->totalUsers = User::whereNull('deleted_at')->count();

        $this->totalCourses = Course::where('status', 1)->whereNull('deleted_at')->count();

        $this->totalRevenue = Invoice::where('status', 'paid')->sum('total_amount');

        $this->totalInvoices = Invoice::where('status', 'paid')->count();

        $this->pendingTickets = Ticket::where('status', 'open')->count();

        $this->newUsersThisMonth = User::whereNull('deleted_at')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    private function loadCharts()
    {
        // نمودار درآمد ماهانه ۶ ماه اخیر
        $monthly = Invoice::where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(total_amount) as total')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get();

        $monthNames = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

        $this->monthlyRevenueJson = json_encode([
            'labels' => $monthly->pluck('month')->map(fn($m) => $monthNames[$m] ?? $m)->toArray(),
            'data'   => $monthly->pluck('total')->toArray(),
        ]);

        // نمودار فروش هر دوره (top 6)
        $courseSales = DB::table('invoice_items')
            ->join('courses', 'invoice_items.course_id', '=', 'courses.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'paid')
            ->selectRaw('courses.title, COUNT(*) as sales_count, SUM(invoice_items.price) as total')
            ->groupBy('courses.id', 'courses.title')
            ->orderByDesc('sales_count')
            ->limit(6)
            ->get();

        $this->courseSalesJson = json_encode([
            'labels' => $courseSales->pluck('title')->toArray(),
            'data'   => $courseSales->pluck('sales_count')->toArray(),
        ]);

        // نمودار کاربران جدید ۶ ماه اخیر
        $newUsers = User::whereNull('deleted_at')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get();

        $this->newUsersJson = json_encode([
            'labels' => $newUsers->pluck('month')->map(fn($m) => $monthNames[$m] ?? $m)->toArray(),
            'data'   => $newUsers->pluck('total')->toArray(),
        ]);

        // نمودار دونات سهم هر دوره از درآمد
        $courseShare = DB::table('invoice_items')
            ->join('courses', 'invoice_items.course_id', '=', 'courses.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'paid')
            ->selectRaw('courses.title, SUM(invoice_items.price) as total')
            ->groupBy('courses.id', 'courses.title')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $this->courseShareJson = json_encode([
            'labels' => $courseShare->pluck('title')->toArray(),
            'data'   => $courseShare->pluck('total')->toArray(),
        ]);
    }

    private function loadTables()
    {
        // پرفروش‌ترین دوره‌ها
        $this->topCourses = DB::table('courses')
            ->leftJoin('invoice_items', 'invoice_items.course_id', '=', 'courses.id')
            ->leftJoin('invoices', function ($join) {
                $join->on('invoices.id', '=', 'invoice_items.invoice_id')
                    ->where('invoices.status', 'paid');
            })
            ->leftJoin('ratings', function ($join) {
                $join->on('ratings.rateable_id', '=', 'courses.id')
                    ->where('ratings.rateable_type', 'App\\Models\\Course');
            })
            ->where('courses.status', 1)
            ->whereNull('courses.deleted_at')
            ->selectRaw('
        courses.id,
        courses.title,
        courses.price,
        courses.discount_price,
        COUNT(DISTINCT invoices.id) as sales_count,
        COALESCE(SUM(invoice_items.price),0) as total_revenue,
        ROUND(AVG(ratings.rating),1) as avg_rating,
        COUNT(DISTINCT ratings.id) as ratings_count
    ')
            ->groupBy(
                'courses.id',
                'courses.title',
                'courses.price',
                'courses.discount_price'
            )
            ->orderByDesc('sales_count')
            ->limit(5)
            ->get();

        // آخرین فاکتورها
        $this->recentInvoices = Invoice::with('user')
            ->latest()
            ->limit(6)
            ->get();

        // آخرین نظرات
        $this->recentRatings = Rating::with(['user'])
            ->where('rateable_type', 'App\\Models\\Course')
            ->latest()
            ->limit(5)
            ->get();
    }
};
?>

<div>
    <div>
        <div class="main-content app-content">
            <div class="container-fluid">
                <div class="col-xxl-12">
                    <div class="row">

                        {{-- ========== کارت‌های آمار ========== --}}

                        <div class="col-xxl-3 col-md-6">
                            <div class="card custom-card overflow-hidden main-custom-card">
                                <div class="card-body">
                                    <div class="d-flex gap-3">
                                        <div class="avatar avatar-md primary">
                                            <i class="ti ti-shopping-cart fs-22 text-white"></i>
                                        </div>
                                        <div class="flex-fill">
                                            <div class="fw-medium fs-13 mb-1">تعداد فروش دوره‌ها</div>
                                            <div class="fs-22 fw-semibold mb-1">{{ number_format($totalInvoices) }}</div>
                                            <div class="d-flex align-items-center fs-12">
                            <span class="text-primary fw-semibold me-1">
                                <i class="ti ti-trending-up me-1"></i>فاکتورهای پرداخت شده
                            </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xxl-3 col-md-6">
                            <div class="card custom-card overflow-hidden main-custom-card">
                                <div class="card-body">
                                    <div class="d-flex gap-3">
                                        <div class="avatar avatar-md secondary">
                                            <i class="ti ti-currency-dollar fs-22 text-white"></i>
                                        </div>
                                        <div class="flex-fill">
                                            <div class="fw-medium fs-13 mb-1">درآمد کل</div>
                                            <div class="fs-22 fw-semibold mb-1">{{ number_format($totalRevenue) }} <small class="fs-12 fw-normal">تومان</small></div>
                                            <div class="fs-12 text-muted">مجموع فاکتورهای پرداخت شده</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xxl-3 col-md-6">
                            <div class="card custom-card overflow-hidden main-custom-card">
                                <div class="card-body">
                                    <div class="d-flex gap-3">
                                        <div class="avatar avatar-md warning">
                                            <i class="ti ti-users fs-22 text-white"></i>
                                        </div>
                                        <div class="flex-fill">
                                            <div class="fw-medium fs-13 mb-1">تعداد کاربران</div>
                                            <div class="fs-22 fw-semibold mb-1">{{ number_format($totalUsers) }}</div>
                                            <div class="d-flex align-items-center fs-12">
                            <span class="text-success fw-semibold me-1">
                                <i class="ti ti-trending-up me-1"></i>{{ number_format($newUsersThisMonth) }} نفر این ماه
                            </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xxl-3 col-md-6">
                            <div class="card custom-card overflow-hidden main-custom-card">
                                <div class="card-body">
                                    <div class="d-flex gap-3">
                                        <div class="avatar avatar-md danger">
                                            <i class="ti ti-book fs-22 text-white"></i>
                                        </div>
                                        <div class="flex-fill">
                                            <div class="fw-medium fs-13 mb-1">تعداد دوره‌ها</div>
                                            <div class="fs-22 fw-semibold mb-1">{{ number_format($totalCourses) }}</div>
                                            <div class="fs-12 text-muted">
                                                @if($pendingTickets > 0)
                                                    <span class="text-danger fw-semibold">
                                    <i class="ti ti-ticket me-1"></i>{{ $pendingTickets }} تیکت باز
                                </span>
                                                @else
                                                    <span class="text-success">تیکت باز وجود ندارد</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ========== نمودار درآمد ماهانه ========== --}}

                        <div class="col-xxl-8">
                            <div class="card custom-card">
                                <div class="card-header justify-content-between">
                                    <div class="card-title">درآمد ماهانه (۶ ماه اخیر)</div>
                                </div>
                                <div class="card-body pb-0">
                                    <canvas id="monthlyRevenueChart" height="120"></canvas>
                                </div>
                            </div>
                        </div>

                        {{-- ========== نمودار دونات سهم دوره‌ها ========== --}}

                        <div class="col-xxl-4 col-xl-6">
                            <div class="card custom-card">
                                <div class="card-header justify-content-between">
                                    <div class="card-title">سهم هر دوره از درآمد</div>
                                </div>
                                <div class="card-body">
                                    <canvas id="courseShareChart" height="220"></canvas>
                                </div>
                            </div>
                        </div>

                        {{-- ========== نمودار فروش هر دوره ========== --}}

                        <div class="col-xxl-6">
                            <div class="card custom-card">
                                <div class="card-header">
                                    <div class="card-title">فروش هر دوره</div>
                                </div>
                                <div class="card-body">
                                    <canvas id="courseSalesChart" height="180"></canvas>
                                </div>
                            </div>
                        </div>

                        {{-- ========== نمودار کاربران جدید ========== --}}

                        <div class="col-xxl-6">
                            <div class="card custom-card">
                                <div class="card-header">
                                    <div class="card-title">کاربران جدید (ماهانه)</div>
                                </div>
                                <div class="card-body">
                                    <canvas id="newUsersChart" height="180"></canvas>
                                </div>
                            </div>
                        </div>

                        {{-- ========== دوره‌های پرفروش ========== --}}

                        <div class="col-xxl-7">
                            <div class="card custom-card overflow-hidden">
                                <div class="card-header justify-content-between">
                                    <div class="card-title">دوره‌های پرفروش</div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table text-nowrap table-hover">
                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>نام دوره</th>
                                                <th class="text-center">فروش</th>
                                                <th>قیمت</th>
                                                <th class="text-center">امتیاز</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse($topCourses as $index => $course)
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>
                                                        <span class="fw-semibold">{{ $course->title }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary-transparent">{{ number_format($course->sales_count) }}</span>
                                                    </td>
                                                    <td>
                                                        @if($course->discount_price)
                                                            <span class="text-decoration-line-through text-muted fs-12">{{ number_format($course->price) }}</span>
                                                            <span class="fw-semibold text-success ms-1">{{ number_format($course->discount_price) }}</span>
                                                        @else
                                                            <span class="fw-semibold">{{ number_format($course->price) }}</span>
                                                        @endif
                                                        <small class="text-muted">ت</small>
                                                    </td>
                                                    <td class="text-center">
                                                        @if($course->avg_rating)
                                                            <span class="text-warning">
                                            <i class="ti ti-star-filled"></i>
                                            {{ $course->avg_rating }}
                                        </span>
                                                            <small class="text-muted">({{ $course->ratings_count }})</small>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">دوره‌ای یافت نشد</td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ========== آخرین نظرات ========== --}}

                        <div class="col-xxl-5">
                            <div class="card custom-card">
                                <div class="card-header justify-content-between">
                                    <div class="card-title">آخرین نظرات</div>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="list-group list-group-flush">
                                        @forelse($recentRatings as $rating)
                                            <li class="list-group-item">
                                                <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm avatar-rounded bg-primary-transparent">
                                <i class="ti ti-user fs-16"></i>
                            </span>
                                                    <div class="flex-fill">
                                                        <span class="fw-semibold d-block fs-13">{{ $rating->user?->name ?? 'کاربر' }}</span>
                                                        <span class="fs-12 text-muted">{{ $rating->created_at?->diffForHumans() }}</span>
                                                    </div>
                                                    <div>
                                                        @for($i = 1; $i <= 5; $i++)
                                                            <i class="ti ti-star{{ $i <= $rating->rating ? '-filled text-warning' : ' text-muted' }} fs-12"></i>
                                                        @endfor
                                                    </div>
                                                </div>
                                            </li>
                                        @empty
                                            <li class="list-group-item text-center text-muted py-3">نظری ثبت نشده</li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- ========== آخرین فاکتورها ========== --}}

                        <div class="col-xl-12">
                            <div class="card custom-card overflow-hidden">
                                <div class="card-header justify-content-between">
                                    <div class="card-title">آخرین فاکتورها</div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table text-nowrap table-hover">
                                            <thead>
                                            <tr>
                                                <th>شماره فاکتور</th>
                                                <th>کاربر</th>
                                                <th>مبلغ</th>
                                                <th>وضعیت</th>
                                                <th>تاریخ</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse($recentInvoices as $invoice)
                                                <tr>
                                                    <td>
                                                        <span class="fw-semibold">#{{ $invoice->invoice_number }}</span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm avatar-rounded bg-secondary-transparent">
                                            <i class="ti ti-user fs-14"></i>
                                        </span>
                                                            <span>{{ $invoice->user?->name ?? $invoice->user?->mobile ?? '-' }}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="fw-semibold">{{ number_format($invoice->total_amount) }}</span>
                                                        <small class="text-muted">تومان</small>
                                                    </td>
                                                    <td>
                                                        @switch($invoice->status)
                                                            @case('paid')
                                                                <span class="badge bg-success-transparent">پرداخت شده</span>
                                                                @break
                                                            @case('pending')
                                                                <span class="badge bg-warning-transparent">در انتظار</span>
                                                                @break
                                                            @case('failed')
                                                                <span class="badge bg-danger-transparent">ناموفق</span>
                                                                @break
                                                            @case('refunded')
                                                                <span class="badge bg-secondary-transparent">بازگشت داده شده</span>
                                                                @break
                                                        @endswitch
                                                    </td>
                                                    <td>
                                                        <span class="fw-semibold d-block">{{ $invoice->created_at?->format('Y-m-d') }}</span>
                                                        <span class="fs-12 text-muted">{{ $invoice->created_at?->format('H:i') }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">فاکتوری یافت نشد</td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>{{-- end row --}}
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const chartDefaults = {
                responsive: true,
                plugins: { legend: { display: false } },
            };

            // ۱. درآمد ماهانه
            const monthlyData = @json(json_decode($monthlyRevenueJson));
            new Chart(document.getElementById('monthlyRevenueChart'), {
                type: 'line',
                data: {
                    labels: monthlyData.labels,
                    datasets: [{
                        label: 'درآمد (تومان)',
                        data: monthlyData.data,
                        borderColor: '#6c5ffc',
                        backgroundColor: 'rgba(108,95,252,0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#6c5ffc',
                    }]
                },
                options: { ...chartDefaults, plugins: { legend: { display: true } } }
            });

            // ۲. سهم دوره‌ها (دونات)
            const shareData = @json(json_decode($courseShareJson));
            new Chart(document.getElementById('courseShareChart'), {
                type: 'doughnut',
                data: {
                    labels: shareData.labels,
                    datasets: [{
                        data: shareData.data,
                        backgroundColor: ['#6c5ffc','#26bf94','#f7b731','#fc5c65','#45aaf2'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom', labels: { font: { family: 'inherit' } } } }
                }
            });

            // ۳. فروش هر دوره
            const salesData = @json(json_decode($courseSalesJson));
            new Chart(document.getElementById('courseSalesChart'), {
                type: 'bar',
                data: {
                    labels: salesData.labels,
                    datasets: [{
                        label: 'تعداد فروش',
                        data: salesData.data,
                        backgroundColor: 'rgba(108,95,252,0.7)',
                        borderRadius: 6,
                    }]
                },
                options: { ...chartDefaults, plugins: { legend: { display: true } } }
            });

            // ۴. کاربران جدید
            const usersData = @json(json_decode($newUsersJson));
            new Chart(document.getElementById('newUsersChart'), {
                type: 'bar',
                data: {
                    labels: usersData.labels,
                    datasets: [{
                        label: 'کاربران جدید',
                        data: usersData.data,
                        backgroundColor: 'rgba(38,191,148,0.7)',
                        borderRadius: 6,
                    }]
                },
                options: { ...chartDefaults, plugins: { legend: { display: true } } }
            });
        </script>
    @endpush
</div>
