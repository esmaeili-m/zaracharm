<?php

use App\Models\SmsBatch;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\Sms\MelipayamakClient;
use App\Services\Sms\SmsProviderException;
use App\Services\Sms\TemplateManager;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    #[Url(except: 30)]
    public int $days = 30;

    public ?float $credit = null;

    public ?string $creditError = null;

    public bool $creditLoaded = false;

    #[Layout('layouts.dashboard')]
    public function mount(): void
    {
        abort_if(!auth()->user()->can('sms.view'), 403);
        $this->days = in_array($this->days, [7, 30, 90], true) ? $this->days : 30;
    }

    public function updatedDays(): void
    {
        $this->days = in_array($this->days, [7, 30, 90], true) ? $this->days : 30;
        unset($this->stats, $this->chart);
        $this->dispatch('sms-chart-updated', chart: $this->chart);
    }

    /** اعتبار پنل (کش ۵ دقیقه؛ با wire:init بعد از نمایش صفحه دریافت می‌شود) */
    public function loadCredit(bool $fresh = false): void
    {
        $client = app(MelipayamakClient::class);

        if (!$client->isConfigured()) {
            $this->creditError = 'اطلاعات اتصال ملی پیامک تنظیم نشده است.';
            $this->creditLoaded = true;
            return;
        }

        if ($fresh) {
            Cache::forget('sms-credit');
        }

        try {
            $this->credit = Cache::remember('sms-credit', 300, fn () => $client->credit());
            $this->creditError = null;
        } catch (SmsProviderException $e) {
            $this->creditError = $e->getMessage();
        }

        $this->creditLoaded = true;
    }

    #[Computed]
    public function stats(): array
    {
        $from = now()->subDays($this->days - 1)->startOfDay();
        $today = now()->startOfDay();

        $period = SmsMessage::where('created_at', '>=', $from)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($c) => (int) $c);

        $templates = SmsTemplate::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($c) => (int) $c);

        return [
            'today' => SmsMessage::where('sent_at', '>=', $today)->whereIn('status', [...SmsMessage::SENT, 'unknown'])->count(),
            'today_failed' => SmsMessage::where('updated_at', '>=', $today)->where('status', 'failed')->count(),
            'accepted' => (int) $period->only(SmsMessage::SENT)->sum(),
            'delivered' => (int) ($period['delivered'] ?? 0),
            'undelivered' => (int) ($period['undelivered'] ?? 0),
            'failed' => (int) ($period['failed'] ?? 0),
            'unknown' => (int) ($period['unknown'] ?? 0),
            'queued' => SmsMessage::whereIn('status', ['queued', 'sending'])->count(),
            'templates_approved' => (int) ($templates['approved'] ?? 0),
            'templates_pending' => (int) ($templates['pending'] ?? 0),
            'templates_problem' => (int) collect($templates)->only(['rejected', 'failed', 'unknown', 'missing'])->sum(),
            'estimated_cost' => SmsBatch::where('created_at', '>=', $from)->where('is_fake', false)->sum('estimated_cost'),
            'has_estimate' => config('sms.estimated_price') !== null,
            'stale_queue' => SmsBatch::where('status', 'queued')->where('created_at', '<', now()->subMinutes(5))->exists(),
            'unmapped' => SmsTemplate::whereNotNull('provider_status')->exists() && !app(TemplateManager::class)->statusMap(),
        ];
    }

    #[Computed]
    public function chart(): array
    {
        $from = now()->subDays($this->days - 1)->startOfDay();

        $rows = SmsMessage::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, status, count(*) as c')
            ->groupBy('d', 'status')
            ->get();

        $labels = [];
        $series = ['accepted' => [], 'delivered' => [], 'failed' => []];

        for ($i = 0; $i < $this->days; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();
            $day = $rows->where('d', $key);
            $labels[] = Jalalian::fromCarbon($date)->format('m/d');
            $series['delivered'][] = (int) $day->where('status', 'delivered')->sum('c');
            $series['accepted'][] = (int) $day->whereIn('status', ['accepted', 'undelivered', 'unknown'])->sum('c');
            $series['failed'][] = (int) $day->where('status', 'failed')->sum('c');
        }

        return [
            'labels' => $labels,
            'series' => [
                ['name' => 'تحویل‌شده', 'data' => $series['delivered']],
                ['name' => 'ارسال‌شده (بدون گزارش تحویل نهایی)', 'data' => $series['accepted']],
                ['name' => 'ناموفق', 'data' => $series['failed']],
            ],
        ];
    }

    #[Computed]
    public function recentBatches()
    {
        return SmsBatch::with('creator:id,first_name,last_name')->latest('id')->limit(6)->get();
    }

    #[Computed]
    public function recentErrors()
    {
        return SmsMessage::with('batch:id,template_title')
            ->where('status', 'failed')
            ->latest('updated_at')->limit(6)
            ->get(['id', 'sms_batch_id', 'mobile', 'error', 'provider_code', 'updated_at']);
    }
};
?>

<div>
    @push('scripts')
        <script src="{{ asset('dashboard/libs/apexcharts/apexcharts.min.js') }}"></script>
    @endpush

    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">مدیریت پیامک</h1>
            <div class="text-muted small">ارسال با الگوهای تأییدشده ملی پیامک، مخاطبان، گزارش ارسال و تحویل</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('sms.send')
                <a href="{{ route('sms.send') }}" class="btn btn-primary btn-wave"><i class="ri-send-plane-2-line align-middle"></i> ارسال پیامک</a>
            @endcan
            <a href="{{ route('sms.templates') }}" class="btn btn-light btn-wave"><i class="ri-file-list-3-line align-middle"></i> الگوها</a>
            @can('sms.contacts')
                <a href="{{ route('sms.contacts') }}" class="btn btn-light btn-wave"><i class="ri-contacts-book-2-line align-middle"></i> مخاطبان</a>
            @endcan
        </div>
    </div>

    @include('pages.dashboard.sms.partials.nav', ['active' => 'sms.index'])

    @php $s = $this->stats; @endphp

    @if($s['stale_queue'])
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="ri-error-warning-line fs-18"></i>
            <div>ارسال‌هایی بیش از ۵ دقیقه در صف مانده‌اند. ارسال گروهی به پردازشگر صف نیاز دارد؛ مطمئن شوید <code dir="ltr">php artisan queue:work</code> روی سرور در حال اجراست.</div>
        </div>
    @endif
    @if($s['unmapped'])
        <div class="alert alert-info d-flex gap-2 align-items-start">
            <i class="ri-information-line fs-18"></i>
            <div>معنای کدهای وضعیت الگو هنوز تعیین نشده و هیچ الگویی «تأییدشده» محسوب نمی‌شود. در <a href="{{ route('sms.templates') }}">صفحه الگوها</a> نگاشت وضعیت را تنظیم کنید.</div>
        </div>
    @endif

    <div class="d-flex justify-content-end mb-2">
        <select wire:model.live="days" class="form-select form-select-sm w-auto">
            <option value="7">۷ روز اخیر</option>
            <option value="30">۳۰ روز اخیر</option>
            <option value="90">۹۰ روز اخیر</option>
        </select>
    </div>

    <div class="row g-3">
        @foreach([
            ['ارسال‌شده امروز', $s['today'], 'ri-send-plane-line', 'primary', 'ناموفق امروز: ' . number_format($s['today_failed'])],
            ['پذیرفته‌شده در دوره', $s['accepted'], 'ri-check-double-line', 'info', 'تحویل‌شده: ' . number_format($s['delivered']) . ' | تحویل‌نشده: ' . number_format($s['undelivered'])],
            ['ناموفق در دوره', $s['failed'], 'ri-close-circle-line', 'danger', 'نامشخص: ' . number_format($s['unknown'])],
            ['در صف ارسال', $s['queued'], 'ri-time-line', 'warning', 'در انتظار پردازش'],
            ['الگوهای تأییدشده', $s['templates_approved'], 'ri-file-shield-2-line', 'success', 'در انتظار: ' . number_format($s['templates_pending']) . ' | ردشده/خطا/نامشخص: ' . number_format($s['templates_problem'])],
        ] as [$title, $value, $icon, $color, $hint])
            <div class="col-xxl col-md-4 col-sm-6">
                <div class="card custom-card h-100 mb-0">
                    <div class="card-body d-flex align-items-start gap-3">
                        <span class="avatar avatar-md bg-{{ $color }}-transparent"><i class="{{ $icon }} fs-20"></i></span>
                        <div class="flex-fill">
                            <div class="text-muted small mb-1">{{ $title }}</div>
                            <div class="fs-20 fw-semibold">{{ number_format($value) }}</div>
                            <div class="text-muted fs-11 mt-1">{{ $hint }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-xxl col-md-4 col-sm-6">
            <div class="card custom-card h-100 mb-0" wire:init="loadCredit">
                <div class="card-body d-flex align-items-start gap-3">
                    <span class="avatar avatar-md bg-secondary-transparent"><i class="ri-wallet-3-line fs-20"></i></span>
                    <div class="flex-fill">
                        <div class="text-muted small mb-1 d-flex justify-content-between">
                            اعتبار پنل ملی پیامک
                            <a href="javascript:void(0)" wire:click="loadCredit(true)" title="به‌روزرسانی"><i class="ri-refresh-line" wire:loading.class="ri-spin" wire:target="loadCredit"></i></a>
                        </div>
                        @if(!$creditLoaded)
                            <div class="placeholder-glow"><span class="placeholder col-8"></span></div>
                        @elseif($creditError)
                            <div class="text-danger fs-12">{{ $creditError }}</div>
                        @else
                            <div class="fs-20 fw-semibold">{{ number_format($credit, $credit == floor($credit) ? 0 : 2) }}</div>
                            <div class="text-muted fs-11 mt-1">مقدار گزارش‌شده توسط GetCredit</div>
                        @endif
                        @if($s['has_estimate'] && $s['estimated_cost'])
                            <div class="text-muted fs-11 mt-1">هزینه تخمینی دوره: {{ number_format($s['estimated_cost']) }} ریال</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card mt-3" wire:ignore x-data="smsChart(@js($this->chart))" @sms-chart-updated.window="update($event.detail.chart)">
        <div class="card-header justify-content-between">
            <div class="card-title">روند ارسال پیامک</div>
            <span class="text-muted small">«ارسال‌شده» یعنی پذیرش توسط سرویس؛ الزاماً تحویل به گوشی نیست</span>
        </div>
        <div class="card-body pt-0"><div x-ref="chart" style="min-height: 300px"></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card custom-card h-100 mb-0">
                <div class="card-header justify-content-between">
                    <div class="card-title">آخرین ارسال‌ها</div>
                    <a href="{{ route('sms.reports') }}" class="small">همه گزارش‌ها</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle text-nowrap mb-0">
                            <thead><tr><th>#</th><th>الگو</th><th>منبع</th><th>گیرندگان</th><th>وضعیت</th><th>زمان</th></tr></thead>
                            <tbody>
                            @forelse($this->recentBatches as $batch)
                                <tr wire:key="b-{{ $batch->id }}">
                                    <td><a href="{{ route('sms.reports', ['batch' => $batch->id]) }}">{{ $batch->id }}</a></td>
                                    <td class="text-truncate" style="max-width: 180px">{{ $batch->template_title }}</td>
                                    <td>{{ \App\Models\SmsBatch::SOURCES[$batch->source] ?? $batch->source }}</td>
                                    <td>{{ number_format($batch->total) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $batch->statusBadge()['color'] }}-transparent">{{ $batch->statusBadge()['title'] }}</span>
                                        @if($batch->is_fake)<span class="badge bg-warning-transparent">شبیه‌سازی</span>@endif
                                    </td>
                                    <td class="small text-muted">{{ verta($batch->created_at)->format('Y/m/d H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">هنوز پیامکی از این بخش ارسال نشده است.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card custom-card h-100 mb-0">
                <div class="card-header"><div class="card-title">خطاهای اخیر</div></div>
                <div class="card-body">
                    @forelse($this->recentErrors as $error)
                        <div class="d-flex gap-2 mb-3" wire:key="e-{{ $error->id }}">
                            <i class="ri-error-warning-line text-danger"></i>
                            <div class="flex-fill">
                                <div class="small fw-medium">{{ $error->error }} @if($error->provider_code !== null)<span class="text-muted">(کد {{ $error->provider_code }})</span>@endif</div>
                                <div class="fs-11 text-muted">
                                    <span dir="ltr">{{ $error->maskedMobile() }}</span> — {{ $error->batch?->template_title }} — {{ verta($error->updated_at)->formatDifference() }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4"><i class="ri-checkbox-circle-line fs-24 d-block text-success"></i>خطایی ثبت نشده است.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('smsChart', (initial) => ({
            chart: null,
            init() {
                const start = () => window.ApexCharts ? this.render(initial) : setTimeout(start, 50);
                start();
            },
            destroy() { this.chart?.destroy(); },
            fa(v) { return new Intl.NumberFormat('fa-IR').format(v ?? 0); },
            render(data) {
                const dark = document.documentElement.getAttribute('data-theme-mode') === 'dark';
                this.chart = new ApexCharts(this.$refs.chart, {
                    chart: { type: 'bar', height: 300, stacked: true, fontFamily: 'inherit', toolbar: { show: false }, foreColor: dark ? '#a3a6b7' : '#6e7080' },
                    series: data.series,
                    colors: ['#26bf94', '#23b7e5', '#e6533c'],
                    plotOptions: { bar: { columnWidth: '55%', borderRadius: 2 } },
                    dataLabels: { enabled: false },
                    xaxis: { categories: data.labels, tickAmount: 10, labels: { rotate: 0, hideOverlappingLabels: true } },
                    yaxis: { labels: { formatter: (v) => this.fa(Math.round(v)) } },
                    tooltip: { y: { formatter: (v) => this.fa(v) } },
                    legend: { position: 'top', horizontalAlign: 'left' },
                    grid: { borderColor: dark ? 'rgba(255,255,255,.07)' : '#f0f0f5', strokeDashArray: 4 },
                    noData: { text: 'داده‌ای برای این دوره ثبت نشده است' },
                });
                this.chart.render();
            },
            update(data) {
                if (!this.chart) return;
                this.chart.updateOptions({ xaxis: { categories: data.labels } });
                this.chart.updateSeries(data.series);
            },
        }));
    </script>
    @endscript
</div>
