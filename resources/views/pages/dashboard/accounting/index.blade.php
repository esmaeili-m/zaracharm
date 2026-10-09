<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\AccountingAccount;
use App\Models\Supplier;
use App\Services\Accounting\ProfitLossReport;
use App\Support\JalaliDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    public $info = [];
    public string $period = 'this_month';
    public $customFrom = '';
    public $customTo = '';

    public const PERIODS = [
        'this_month' => 'این ماه',
        'last_month' => 'ماه قبل',
        'last_3_months' => '۳ ماه اخیر',
        'this_year' => 'امسال',
        'last_year' => 'سال قبل',
        'custom' => 'بازه دلخواه',
    ];

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('accounting.view'), 403);
        $this->info['header'] = 'حسابداری: سود و زیان';

        app(\App\Services\Accounting\AccountingSync::class)->run();

        $this->customFrom = Jalalian::now()->format('Y/m') . '/01';
        $this->customTo = JalaliDate::today();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    #[Computed]
    public function range(): array
    {
        $now = Jalalian::now();

        // شروع ماه شمسی n ماه قبل (بدون subMonths که در روز ۳۱ از ماه کوتاه‌تر سرریز می‌کند)
        $monthStart = function (int $monthsAgo) use ($now) {
            $index = $now->getYear() * 12 + ($now->getMonth() - 1) - $monthsAgo;

            return (new Jalalian(intdiv($index, 12), $index % 12 + 1, 1))->toCarbon();
        };

        return match ($this->period) {
            'last_month' => [$monthStart(1), $monthStart(0)->subDay()],
            'last_3_months' => [$monthStart(2), now()],
            'this_year' => [(new Jalalian($now->getYear(), 1, 1))->toCarbon(), now()],
            'last_year' => [(new Jalalian($now->getYear() - 1, 1, 1))->toCarbon(), (new Jalalian($now->getYear(), 1, 1))->toCarbon()->subDay()],
            'custom' => $this->customRange(),
            default => [$monthStart(0), now()],
        };
    }

    protected function customRange(): array
    {
        $from = JalaliDate::toCarbon($this->customFrom) ?? now()->startOfMonth();
        $to = JalaliDate::toCarbon($this->customTo) ?? now();

        return $from->lte($to) ? [$from, $to] : [$to, $from];
    }

    #[Computed]
    public function report(): ProfitLossReport
    {
        [$from, $to] = $this->range;

        return new ProfitLossReport(Carbon::instance($from), Carbon::instance($to));
    }

    #[Computed]
    public function summary(): array
    {
        return $this->report->summary();
    }

    #[Computed]
    public function accounts()
    {
        $balances = AccountingAccount::balances();

        return AccountingAccount::active()->orderBy('sort')->get()
            ->each(fn ($a) => $a->balance = $balances[$a->id] ?? 0);
    }

    #[Computed]
    public function supplierDebt(): int
    {
        return (int) collect(Supplier::balances())->filter(fn ($v) => $v > 0)->sum();
    }

    /**
     * ارزش موجودی انبار به قیمت خرید
     */
    #[Computed]
    public function inventoryValue(): int
    {
        return (int) DB::table('inventory_items as ii')
            ->join('product_variants as pv', 'pv.id', '=', 'ii.product_variant_id')
            ->join('inventories as inv', 'inv.id', '=', 'ii.inventory_id')
            ->whereNull('ii.deleted_at')
            ->whereNull('pv.deleted_at')
            ->whereNull('inv.deleted_at')
            ->where('ii.quantity', '>', 0)
            ->sum(DB::raw('ii.quantity * COALESCE(pv.cost_price, 0)'));
    }

    /**
     * واریانت‌های موجود در انبار که قیمت خرید ندارند (سود را بیش از واقع نشان می‌دهند)
     */
    #[Computed]
    public function missingCostCount(): int
    {
        return (int) DB::table('product_variants')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('cost_price')->orWhere('cost_price', 0))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('inventory_items')
                ->whereColumn('inventory_items.product_variant_id', 'product_variants.id')
                ->whereNull('inventory_items.deleted_at')
                ->where('inventory_items.quantity', '>', 0))
            ->count();
    }

    public function rangeLabel(): string
    {
        [$from, $to] = $this->range;

        return JalaliDate::format($from) . ' تا ' . JalaliDate::format($to);
    }
};
?>

<div>
    @php($s = $this->summary)

    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">بازه: {{ $this->rangeLabel() }}</div>
        </div>
        <div class="btn-list">
            @can('accounting.create')
                <a href="{{ route('accounting.entries') }}" class="btn btn-primary-light btn-wave">
                    <i class="ri-add-line align-middle"></i> ثبت هزینه / درآمد
                </a>
            @endcan
        </div>
    </div>

    @include('pages.dashboard.accounting.partials.nav', ['active' => 'accounting.index'])

    {{-- انتخاب بازه --}}
    <div class="card custom-card">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div class="btn-group flex-wrap" role="group">
                @foreach($this::PERIODS as $key => $label)
                    <button type="button" wire:click="$set('period', '{{ $key }}')"
                            class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</button>
                @endforeach
            </div>
            @if($period === 'custom')
                <div class="d-flex gap-2 align-items-end">
                    <div>
                        <label class="form-label small mb-1">از</label>
                        <input data-jdp wire:model.lazy="customFrom" type="text" dir="ltr" class="form-control form-control-sm" style="width: 120px">
                    </div>
                    <div>
                        <label class="form-label small mb-1">تا</label>
                        <input data-jdp wire:model.lazy="customTo" type="text" dir="ltr" class="form-control form-control-sm" style="width: 120px">
                    </div>
                </div>
            @endif
            <div wire:loading class="spinner-border spinner-border-sm text-primary ms-2" role="status"></div>
        </div>
    </div>

    @if($this->missingCostCount)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="ri-error-warning-line fs-18"></i>
            <div>
                {{ number_format($this->missingCostCount) }} کالای موجود در انبار قیمت خرید ندارد؛ بهای تمام‌شده آن‌ها صفر حساب می‌شود و سود بیشتر از واقع نشان داده می‌شود.
                قیمت خرید را در صفحه قیمت محصولات وارد کنید یا کالا را از طریق «خریدها» وارد انبار کنید.
            </div>
        </div>
    @endif

    {{-- شاخص‌ها --}}
    <div class="row">
        @foreach([
            ['فروش خالص', $s['net_sales'], 'primary', 'ri-shopping-bag-3-line', 'فروش کالا + ارسال (بدون مالیات)'],
            ['سود ناخالص', $s['gross_profit'], $s['gross_profit'] < 0 ? 'danger' : 'success', 'ri-funds-line', 'فروش − مرجوعی − بهای تمام‌شده'],
            ['هزینه‌ها', $s['expenses'], 'warning', 'ri-hand-coin-line', 'هزینه‌های ثبت‌شده با دسته اثرگذار'],
            ['سود خالص', $s['net_profit'], $s['net_profit'] < 0 ? 'danger' : 'success', 'ri-line-chart-line', 'حاشیه سود: ' . $s['margin'] . '٪'],
        ] as [$label, $value, $color, $icon, $hint])
            <div class="col-xl-3 col-md-6">
                <div class="card custom-card">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <span class="avatar avatar-md bg-{{ $color }}-transparent"><i class="{{ $icon }} fs-20"></i></span>
                        <div class="min-w-0">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fs-18 fw-bold text-{{ $value < 0 ? 'danger' : 'default' }}">{{ number_format($value) }} <small class="fs-11 text-muted">تومان</small></div>
                            <div class="small text-muted">{{ $hint }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        {{-- صورت سود و زیان --}}
        <div class="col-xl-6">
            <div class="card custom-card">
                <div class="card-header"><div class="card-title">صورت سود و زیان</div></div>
                <div class="card-body p-0">
                    <table class="table mb-0 align-middle">
                        <tbody>
                        <tr><td>فروش کالا</td><td class="text-end">{{ number_format($s['sales']) }}</td></tr>
                        <tr><td>هزینه ارسال دریافتی</td><td class="text-end">{{ number_format($s['shipping']) }}</td></tr>
                        <tr class="table-light fw-semibold"><td>فروش خالص</td><td class="text-end">{{ number_format($s['net_sales']) }}</td></tr>
                        <tr><td class="text-danger">− مرجوعی (استرداد به مشتری)</td><td class="text-end text-danger">{{ number_format($s['refunds']) }}</td></tr>
                        <tr>
                            <td class="text-danger">
                                − بهای تمام‌شده کالای فروش‌رفته
                                @if($s['returned_cogs'])
                                    <div class="small text-muted">پس از کسر {{ number_format($s['returned_cogs']) }} بهای کالای برگشتی</div>
                                @endif
                            </td>
                            <td class="text-end text-danger">{{ number_format($s['net_cogs']) }}</td>
                        </tr>
                        <tr class="table-light fw-semibold"><td>سود ناخالص</td><td class="text-end {{ $s['gross_profit'] < 0 ? 'text-danger' : '' }}">{{ number_format($s['gross_profit']) }}</td></tr>
                        <tr><td class="text-success">+ درآمدهای متفرقه</td><td class="text-end text-success">{{ number_format($s['other_income']) }}</td></tr>
                        <tr><td class="text-danger">− هزینه‌ها</td><td class="text-end text-danger">{{ number_format($s['expenses']) }}</td></tr>
                        <tr class="fw-bold fs-15 {{ $s['net_profit'] < 0 ? 'table-danger' : 'table-success' }}">
                            <td>سود خالص</td><td class="text-end">{{ number_format($s['net_profit']) }} تومان</td>
                        </tr>
                        @if($s['tax'])
                            <tr><td class="small text-muted">مالیات دریافتی از مشتری (بدهی به دارایی؛ جزو سود نیست)</td><td class="text-end small text-muted">{{ number_format($s['tax']) }}</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            {{-- حساب‌ها --}}
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">موجودی حساب‌ها (امروز)</div>
                    <a href="{{ route('accounting.accounts') }}" class="small">مدیریت حساب‌ها</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($this->accounts as $account)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('accounting.entries', ['account' => $account->id]) }}" class="text-default">
                                    <i class="{{ $account->type === 'cash' ? 'ri-safe-2-line' : 'ri-bank-line' }} me-1 text-muted"></i>{{ $account->title }}
                                </a>
                                <span class="fw-semibold {{ $account->balance < 0 ? 'text-danger' : '' }}">{{ number_format($account->balance) }}</span>
                            </li>
                        @endforeach
                        <li class="list-group-item d-flex justify-content-between fw-bold bg-light">
                            <span>جمع</span><span>{{ number_format($this->accounts->sum('balance')) }} تومان</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-6">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="text-muted small mb-1">ارزش موجودی انبار (به قیمت خرید)</div>
                            <div class="fs-16 fw-bold">{{ number_format($this->inventoryValue) }} <small class="fs-11 text-muted">تومان</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="text-muted small mb-1">بدهی به تأمین‌کنندگان</div>
                            <div class="fs-16 fw-bold {{ $this->supplierDebt > 0 ? 'text-danger' : '' }}">{{ number_format($this->supplierDebt) }} <small class="fs-11 text-muted">تومان</small></div>
                            @can('purchases.view')
                                <a href="{{ route('suppliers.index') }}" class="small">مشاهده تأمین‌کنندگان</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- ماهانه --}}
        <div class="col-xl-8">
            <div class="card custom-card">
                <div class="card-header"><div class="card-title">تفکیک ماهانه</div></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm text-nowrap align-middle mb-0">
                            <thead>
                            <tr>
                                <th>ماه</th>
                                <th class="text-end">فروش خالص</th>
                                <th class="text-end">مرجوعی</th>
                                <th class="text-end">بهای تمام‌شده</th>
                                <th class="text-end">هزینه‌ها</th>
                                <th class="text-end">سود خالص</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($this->report->monthly() as $month => $m)
                                @php([$y, $mo] = explode('/', $month))
                                <tr>
                                    <td>{{ \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $month . '/01')->format('%B') }} {{ $y }}</td>
                                    <td class="text-end">{{ number_format($m['net_sales']) }}</td>
                                    <td class="text-end text-danger">{{ $m['refunds'] ? number_format($m['refunds']) : '—' }}</td>
                                    <td class="text-end">{{ number_format($m['net_cogs']) }}</td>
                                    <td class="text-end">{{ number_format($m['expenses'] - $m['other_income']) }}</td>
                                    <td class="text-end fw-bold {{ $m['net_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($m['net_profit']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2">ستون هزینه‌ها = هزینه‌ها منهای درآمدهای متفرقه.</div>
                </div>
            </div>
        </div>

        {{-- به تفکیک دسته --}}
        <div class="col-xl-4">
            <div class="card custom-card">
                <div class="card-header"><div class="card-title">هزینه و درآمد به تفکیک دسته</div></div>
                <div class="card-body">
                    @php($categories = $this->report->byCategory())
                    @php($maxExpense = max(1, (int) $categories->where('type', 'expense')->max('total')))
                    @forelse($categories as $row)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>
                                    {{ $row->title }}
                                    @unless($row->affects_profit)
                                        <span class="text-muted">(خارج از سود و زیان)</span>
                                    @endunless
                                </span>
                                <span class="fw-semibold text-{{ $row->type === 'expense' ? 'danger' : 'success' }}">{{ number_format($row->total) }}</span>
                            </div>
                            <div class="progress progress-xs">
                                <div class="progress-bar bg-{{ $row->type === 'expense' ? 'danger' : 'success' }}" style="width: {{ min(100, round($row->total / $maxExpense * 100)) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">
                            در این بازه هزینه یا درآمد دستی ثبت نشده است.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-body small text-muted">
            <strong>نحوه محاسبه:</strong>
            فروش از سفارش‌های پرداخت‌شده سایت، فاکتورهای حضوری پرداخت‌شده و سفارش‌های پرداخت‌شده مارکت‌پلیس‌ها جمع می‌شود (سفارش کاملاً بازگشت‌وجه‌شده حساب نمی‌شود).
            بهای تمام‌شده = تعداد × قیمت خرید کالا در لحظه فروش. مرجوعی = مبالغ استردادشده به کیف پول مشتری.
            هزینه‌ها و درآمدهای متفرقه از ثبت‌های دستی دفتر دریافت و پرداخت می‌آیند؛ خرید کالا و پرداخت به تأمین‌کننده هزینه حساب نمی‌شوند (کالا وارد انبار می‌شود و هنگام فروش در بهای تمام‌شده می‌آید).
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.js') }}"></script>
        <script>jalaliDatepicker.startWatch();</script>
    @endpush
</div>
