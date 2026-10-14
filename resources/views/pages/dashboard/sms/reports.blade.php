<?php

use App\Models\SmsAuditLog;
use App\Models\SmsBatch;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Sms\MelipayamakCodes;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsSender;
use App\Services\Sms\TemplateBody;
use App\Support\JalaliDate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    #[Url(except: 'batches')]
    public string $tab = 'batches';

    #[Url(as: 'batch', except: null)]
    public ?int $batchId = null;

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $mobile = '';

    #[Url(except: '')]
    public string $template = '';

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $source = '';

    public ?int $messageId = null;

    #[Layout('layouts.dashboard')]
    public function mount(): void
    {
        abort_if(!auth()->user()->can('sms.view'), 403);

        if ($this->batchId) {
            $this->tab = 'messages';
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['tab', 'batchId', 'from', 'to', 'status', 'mobile', 'template', 'user', 'source'], true)) {
            $this->resetPage();
        }

        if ($property === 'tab') {
            $this->status = '';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['from', 'to', 'status', 'mobile', 'template', 'user', 'source', 'batchId']);
        $this->resetPage();
    }

    public function canSeeNumbers(): bool
    {
        return auth()->user()->can('sms.export');
    }

    public function displayMobile(string $mobile): string
    {
        return $this->canSeeNumbers() ? $mobile : PhoneNumber::mask($mobile);
    }

    protected function dateRange($query, string $column)
    {
        return $query
            ->when(JalaliDate::toCarbon($this->from), fn ($q, $from) => $q->where($column, '>=', $from))
            ->when(JalaliDate::toCarbon($this->to), fn ($q, $to) => $q->where($column, '<', $to->copy()->addDay()));
    }

    #[Computed]
    public function batches()
    {
        $query = SmsBatch::with('creator:id,first_name,last_name')
            ->when($this->batchId, fn ($q) => $q->whereKey($this->batchId))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->template !== '', fn ($q) => $q->where('sms_template_id', (int) $this->template))
            ->when($this->user !== '', fn ($q) => $q->where('created_by', (int) $this->user))
            ->when($this->source !== '', fn ($q) => $q->where('source', $this->source))
            ->when(trim($this->mobile) !== '', fn ($q) => $q->whereHas('messages', fn ($m) => $m->where('mobile', 'like', '%' . (PhoneNumber::normalize($this->mobile) ?? trim($this->mobile)) . '%')));

        return $this->dateRange($query, 'created_at')->latest('id')->paginate(20);
    }

    /** شمارش وضعیت پیامک‌های عملیات‌های صفحه جاری (یک کوئری) */
    #[Computed]
    public function batchCounts(): array
    {
        $ids = $this->batches->pluck('id');

        return SmsMessage::whereIn('sms_batch_id', $ids)
            ->selectRaw('sms_batch_id, status, count(*) as c')->groupBy('sms_batch_id', 'status')->get()
            ->groupBy('sms_batch_id')
            ->map(fn ($rows) => $rows->pluck('c', 'status')->map(fn ($c) => (int) $c)->all())
            ->all();
    }

    #[Computed]
    public function messages()
    {
        $query = SmsMessage::with('batch:id,template_title,source,created_by,is_fake')
            ->when($this->batchId, fn ($q) => $q->where('sms_batch_id', $this->batchId))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when(trim($this->mobile) !== '', fn ($q) => $q->where('mobile', 'like', '%' . (PhoneNumber::normalize($this->mobile) ?? trim($this->mobile)) . '%'))
            ->when($this->template !== '' || $this->user !== '' || $this->source !== '', fn ($q) => $q->whereHas('batch', fn ($b) => $b
                ->when($this->template !== '', fn ($b) => $b->where('sms_template_id', (int) $this->template))
                ->when($this->user !== '', fn ($b) => $b->where('created_by', (int) $this->user))
                ->when($this->source !== '', fn ($b) => $b->where('source', $this->source))));

        return $this->dateRange($query, 'created_at')->latest('id')->paginate(30);
    }

    #[Computed]
    public function audits()
    {
        $query = SmsAuditLog::with('user:id,first_name,last_name')
            ->when($this->user !== '', fn ($q) => $q->where('user_id', (int) $this->user))
            ->when($this->status !== '', fn ($q) => $q->where('action', $this->status));

        return $this->dateRange($query, 'created_at')->latest('id')->paginate(30);
    }

    #[Computed]
    public function currentBatch(): ?SmsBatch
    {
        return $this->batchId ? SmsBatch::with(['creator:id,first_name,last_name', 'template:id,status,provider_body_id'])->find($this->batchId) : null;
    }

    #[Computed]
    public function templates()
    {
        return SmsTemplate::orderBy('title')->get(['id', 'title']);
    }

    #[Computed]
    public function operators()
    {
        return User::whereIn('id', SmsBatch::whereNotNull('created_by')->distinct()->pluck('created_by'))->get(['id', 'first_name', 'last_name', 'mobile']);
    }

    #[Computed]
    public function selectedMessage(): ?SmsMessage
    {
        return $this->messageId ? SmsMessage::with('batch.creator:id,first_name,last_name', 'user:id,first_name,last_name', 'contact:id,name')->find($this->messageId) : null;
    }

    public function showMessage(int $id): void
    {
        $this->messageId = $id;
        unset($this->selectedMessage);
    }

    public function openBatch(int $id): void
    {
        $this->batchId = $id;
        $this->tab = 'messages';
        $this->status = '';
        $this->resetPage();
    }

    public function cancel(int $id): void
    {
        abort_if(!auth()->user()->can('sms.bulk'), 403);

        $count = app(SmsSender::class)->cancel(SmsBatch::findOrFail($id));
        unset($this->batches, $this->currentBatch, $this->messages, $this->batchCounts);
        $this->dispatch('alert', type: 'success', title: 'لغو شد', text: "{$count} پیامک در صف لغو شد.");
    }

    /** تلاش مجدد خطاهای موقت (و ادامه عملیات متوقف‌شده)؛ با messageId فقط همان پیامک */
    public function retry(int $batchId, ?int $messageId = null): void
    {
        $batch = SmsBatch::findOrFail($batchId);
        abort_if(!auth()->user()->can($batch->source === 'single' ? 'sms.send' : 'sms.bulk'), 403);

        try {
            $count = app(SmsSender::class)->retry($batch, $messageId);
        } catch (\RuntimeException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $e->getMessage());
            return;
        }

        unset($this->batches, $this->currentBatch, $this->messages, $this->batchCounts, $this->selectedMessage);
        $this->dispatch('alert', type: $count ? 'success' : 'info', title: 'تلاش مجدد',
            text: $count ? "{$count} پیامک دوباره در صف ارسال قرار گرفت." : 'پیامک قابل تلاش مجددی وجود ندارد (خطاهای دائمی و نامشخص تکرار نمی‌شوند).');
    }

    public function retryableCount(SmsBatch $batch): int
    {
        return $batch->messages()->where('status', 'failed')->where('error_permanent', false)
            ->where('attempts', '<', (int) config('sms.max_attempts', 3))->count()
            + ($batch->status === 'failed' ? $batch->messages()->where('status', 'queued')->count() : 0);
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">گزارش پیامک‌ها</h1>
            <div class="text-muted small">عملیات‌های ارسال، وضعیت هر گیرنده، خطاها و تحویل</div>
        </div>
    </div>

    @include('pages.dashboard.sms.partials.nav', ['active' => 'sms.reports'])

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a href="javascript:void(0)" class="nav-link {{ $tab === 'batches' ? 'active' : '' }}" wire:click="$set('tab', 'batches')"><i class="ri-stack-line me-1"></i>عملیات‌های ارسال</a></li>
        <li class="nav-item"><a href="javascript:void(0)" class="nav-link {{ $tab === 'messages' ? 'active' : '' }}" wire:click="$set('tab', 'messages')"><i class="ri-message-2-line me-1"></i>پیامک‌ها (هر گیرنده)</a></li>
        <li class="nav-item"><a href="javascript:void(0)" class="nav-link {{ $tab === 'audit' ? 'active' : '' }}" wire:click="$set('tab', 'audit')"><i class="ri-shield-check-line me-1"></i>رویدادها</a></li>
    </ul>

    {{-- فیلترها --}}
    <div class="card custom-card">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-6 col-md-2"><input data-jdp type="text" dir="ltr" class="form-control form-control-sm" wire:model.lazy="from" placeholder="از تاریخ"></div>
                <div class="col-6 col-md-2"><input data-jdp type="text" dir="ltr" class="form-control form-control-sm" wire:model.lazy="to" placeholder="تا تاریخ"></div>
                <div class="col-md-2">
                    <select wire:model.live="status" class="form-select form-select-sm">
                        <option value="">{{ $tab === 'audit' ? 'همه رویدادها' : 'همه وضعیت‌ها' }}</option>
                        @foreach($tab === 'batches' ? SmsBatch::STATUSES : ($tab === 'messages' ? SmsMessage::STATUSES : collect(SmsAuditLog::ACTIONS)->map(fn ($t) => ['title' => $t])->all()) as $key => $s)
                            <option value="{{ $key }}">{{ $s['title'] }}</option>
                        @endforeach
                    </select>
                </div>
                @if($tab !== 'audit')
                    <div class="col-md-2"><input type="text" dir="ltr" class="form-control form-control-sm" wire:model.live.debounce.500ms="mobile" placeholder="شماره گیرنده"></div>
                    <div class="col-md-2">
                        <select wire:model.live="template" class="form-select form-select-sm">
                            <option value="">همه الگوها</option>
                            @foreach($this->templates as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select wire:model.live="source" class="form-select form-select-sm">
                            <option value="">همه منابع</option>
                            @foreach(SmsBatch::SOURCES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2">
                    <select wire:model.live="user" class="form-select form-select-sm">
                        <option value="">همه کاربران</option>
                        @foreach($this->operators as $op)<option value="{{ $op->id }}">{{ trim($op->first_name . ' ' . $op->last_name) ?: $op->mobile }}</option>@endforeach
                    </select>
                </div>
                @if($tab !== 'audit')
                    <div class="col-md-2"><input type="number" class="form-control form-control-sm" wire:model.live.debounce.500ms="batchId" placeholder="شناسه عملیات"></div>
                @endif
                <div class="col-md-2"><button type="button" class="btn btn-sm btn-light w-100" wire:click="resetFilters"><i class="ri-filter-off-line"></i> پاک‌کردن فیلترها</button></div>
            </div>
        </div>
    </div>

    {{-- جزئیات عملیات --}}
    @if($tab === 'messages' && ($b = $this->currentBatch))
        @php $counts = $b->counts(); $running = in_array($b->status, ['queued', 'sending'], true); $retryable = $this->retryableCount($b); @endphp
        <div class="card custom-card" @if($running) wire:poll.5s @endif>
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="fw-semibold mb-1">عملیات #{{ $b->id }} — {{ $b->template_title }} <span class="text-muted small" dir="ltr">#{{ $b->provider_body_id }}</span></h6>
                        <div class="small text-muted">
                            {{ SmsBatch::SOURCES[$b->source] ?? $b->source }} —
                            {{ $b->creator ? trim($b->creator->first_name . ' ' . $b->creator->last_name) : 'سیستم' }} —
                            {{ verta($b->created_at)->format('Y/m/d H:i:s') }}
                            @if($b->finished_at) — پایان: {{ verta($b->finished_at)->format('H:i:s') }}@endif
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-start flex-wrap">
                        <span class="badge bg-{{ $b->statusBadge()['color'] }}-transparent">{{ $b->statusBadge()['title'] }}</span>
                        @if($b->is_fake)<span class="badge bg-warning-transparent">شبیه‌سازی</span>@endif
                        @can('sms.bulk')
                            @if($b->isCancellable() && $b->source !== 'single')
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="cancel({{ $b->id }})" wire:confirm="ارسال پیامک‌های باقی‌مانده لغو شود؟">لغو</button>
                            @endif
                        @endcan
                        @if($retryable && !$running && $b->status !== 'cancelled')
                            <button type="button" class="btn btn-sm btn-warning" wire:click="retry({{ $b->id }})" wire:confirm="{{ $retryable }} پیامک دوباره ارسال شود؟">
                                <i class="ri-restart-line"></i> {{ $b->status === 'failed' ? 'ادامه / تلاش مجدد' : 'تلاش مجدد ناموفق‌ها' }} ({{ $retryable }})
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-light" wire:click="$set('batchId', null)"><i class="ri-close-line"></i></button>
                    </div>
                </div>
                <div class="bg-light rounded p-2 small my-2" style="white-space: pre-wrap">{{ $b->template_body }}</div>
                @if($b->last_error)<div class="alert alert-danger small py-2 mb-2">توقف: {{ $b->last_error }}</div>@endif
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark">کل گیرندگان: {{ number_format($b->total) }}</span>
                    @foreach(SmsMessage::STATUSES as $key => $st)
                        @if($counts[$key] ?? 0)
                            <a href="javascript:void(0)" wire:click="$set('status', '{{ $key }}')" class="badge bg-{{ $st['color'] }}-transparent">{{ $st['title'] }}: {{ number_format($counts[$key]) }}</a>
                        @endif
                    @endforeach
                    @if($b->skipped)
                        <span class="badge bg-light text-dark" title="{{ collect($b->skipped_summary ?? [])->map(fn ($c, $r) => (\App\Services\Sms\AudienceBuilder::SKIP_REASONS[$r] ?? $r) . ': ' . $c)->implode(' | ') }}">
                            حذف‌شده پیش از ارسال: {{ number_format($b->skipped) }} <i class="ri-information-line"></i>
                        </span>
                    @endif
                    @if($b->estimated_cost)<span class="badge bg-light text-dark">هزینه تخمینی: {{ number_format($b->estimated_cost) }} ریال</span>@endif
                </div>
                @if($b->source_meta)
                    <div class="fs-11 text-muted mt-2">منبع: {{ collect($b->source_meta)->map(fn ($v, $k) => $k . '=' . (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v))->implode(' ، ') }}</div>
                @endif
            </div>
        </div>
    @endif

    <div class="card custom-card">
        <div class="card-body position-relative">
            <div wire:loading.flex wire:target="tab,from,to,status,mobile,template,user,source,batchId,gotoPage,nextPage,previousPage,resetFilters" class="position-absolute top-0 start-0 w-100 h-100 bg-body bg-opacity-50 align-items-center justify-content-center" style="z-index: 2">
                <span class="spinner-border text-primary"></span>
            </div>

            @if($tab === 'batches')
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-nowrap">
                        <thead><tr><th>#</th><th>زمان</th><th>الگو</th><th>منبع</th><th>کاربر</th><th>گیرندگان</th><th>نتیجه</th><th>وضعیت</th><th></th></tr></thead>
                        <tbody>
                        @forelse($this->batches as $batch)
                            @php $c = $this->batchCounts[$batch->id] ?? []; @endphp
                            <tr wire:key="b-{{ $batch->id }}">
                                <td>{{ $batch->id }}</td>
                                <td class="small">{{ verta($batch->created_at)->format('Y/m/d H:i') }}</td>
                                <td class="text-truncate" style="max-width: 200px">{{ $batch->template_title }}</td>
                                <td>{{ SmsBatch::SOURCES[$batch->source] ?? $batch->source }}</td>
                                <td class="small">{{ $batch->creator ? trim($batch->creator->first_name . ' ' . $batch->creator->last_name) : '—' }}</td>
                                <td>{{ number_format($batch->total) }}@if($batch->skipped)<span class="fs-11 text-muted"> (+{{ $batch->skipped }} حذف)</span>@endif</td>
                                <td class="small">
                                    <span class="text-success" title="تحویل‌شده">{{ $c['delivered'] ?? 0 }}</span> /
                                    <span class="text-info" title="پذیرفته‌شده">{{ ($c['accepted'] ?? 0) + ($c['undelivered'] ?? 0) }}</span> /
                                    <span class="text-danger" title="ناموفق">{{ $c['failed'] ?? 0 }}</span>
                                    @if($c['unknown'] ?? 0) / <span class="text-dark" title="نامشخص">{{ $c['unknown'] }}</span>@endif
                                    @if(($c['queued'] ?? 0) + ($c['sending'] ?? 0)) / <span class="text-muted" title="در صف">{{ ($c['queued'] ?? 0) + ($c['sending'] ?? 0) }}</span>@endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $batch->statusBadge()['color'] }}-transparent">{{ $batch->statusBadge()['title'] }}</span>
                                    @if($batch->is_fake)<span class="badge bg-warning-transparent">شبیه‌سازی</span>@endif
                                </td>
                                <td><a href="javascript:void(0)" wire:click="openBatch({{ $batch->id }})" class="text-info" title="جزئیات"><i class="ri-eye-line"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-5"><i class="ri-file-chart-line fs-32 d-block mb-2"></i>گزارشی یافت نشد.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $this->batches->links() }}
                <div class="fs-11 text-muted">ستون نتیجه: تحویل‌شده / پذیرفته‌شده (بدون گزارش تحویل نهایی یا تحویل‌نشده) / ناموفق / نامشخص / در صف</div>
            @elseif($tab === 'messages')
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-nowrap">
                        <thead><tr><th>زمان</th><th>گیرنده</th><th>عملیات / الگو</th><th>وضعیت</th><th>تحویل</th><th>خطا</th><th>تلاش</th><th></th></tr></thead>
                        <tbody>
                        @forelse($this->messages as $message)
                            <tr wire:key="m-{{ $message->id }}">
                                <td class="small">{{ verta($message->sent_at ?? $message->created_at)->format('Y/m/d H:i') }}</td>
                                <td><span dir="ltr">{{ $this->displayMobile($message->mobile) }}</span>@if($message->recipient_name)<div class="fs-11 text-muted">{{ $message->recipient_name }}</div>@endif</td>
                                <td class="small"><a href="javascript:void(0)" wire:click="openBatch({{ $message->sms_batch_id }})">#{{ $message->sms_batch_id }}</a> {{ \Illuminate\Support\Str::limit($message->batch?->template_title, 30) }}</td>
                                <td><span class="badge bg-{{ $message->statusBadge()['color'] }}-transparent">{{ $message->statusBadge()['title'] }}</span></td>
                                <td class="small">{{ MelipayamakCodes::deliveryLabel($message->delivery_code) ?? '—' }}</td>
                                <td class="small text-danger text-wrap" style="max-width: 240px">{{ $message->error }}@if($message->error && !$message->error_permanent && $message->status === 'failed') <span class="badge bg-warning-transparent">موقت</span>@endif</td>
                                <td>{{ $message->attempts }}</td>
                                <td><a href="#smsMessageModal" data-bs-toggle="modal" wire:click="showMessage({{ $message->id }})" class="text-info"><i class="ri-eye-line"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-5">پیامکی یافت نشد.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $this->messages->links() }}
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>زمان</th><th>کاربر</th><th>رویداد</th><th>جزئیات</th><th>IP</th></tr></thead>
                        <tbody>
                        @forelse($this->audits as $audit)
                            <tr wire:key="a-{{ $audit->id }}">
                                <td class="small text-nowrap">{{ verta($audit->created_at)->format('Y/m/d H:i:s') }}</td>
                                <td class="small">{{ $audit->user ? trim($audit->user->first_name . ' ' . $audit->user->last_name) : 'سیستم' }}</td>
                                <td>{{ SmsAuditLog::ACTIONS[$audit->action] ?? $audit->action }}@if($audit->subject_id)<span class="text-muted small"> #{{ $audit->subject_id }}</span>@endif</td>
                                <td class="small text-muted text-wrap" style="max-width: 420px" dir="auto">{{ $audit->meta ? json_encode($audit->meta, JSON_UNESCAPED_UNICODE) : '' }}</td>
                                <td class="small" dir="ltr">{{ $audit->ip }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">رویدادی ثبت نشده است.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $this->audits->links() }}
            @endif
        </div>
    </div>

    {{-- جزئیات پیامک --}}
    <div wire:ignore.self class="modal fade" id="smsMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                @php $m = $this->selectedMessage; @endphp
                <div class="modal-header"><h6 class="modal-title">پیامک #{{ $m?->id }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    @if($m)
                        @if($m->args !== null)
                            <div class="bg-light rounded p-3 mb-3 small" style="white-space: pre-wrap">{{ TemplateBody::render($m->batch->template_body, $m->args) }}</div>
                        @else
                            <div class="alert alert-light small">مقادیر متغیرها طبق سیاست نگهداری داده پاک شده است.</div>
                        @endif
                        <dl class="row small mb-0">
                            <dt class="col-sm-4">گیرنده</dt><dd class="col-sm-8"><span dir="ltr">{{ $this->displayMobile($m->mobile) }}</span> {{ $m->recipient_name }}
                                @if($m->user)<span class="badge bg-light text-dark">مشتری سایت</span>@endif @if($m->contact)<span class="badge bg-light text-dark">مخاطب دستی</span>@endif</dd>
                            <dt class="col-sm-4">عملیات</dt><dd class="col-sm-8">#{{ $m->sms_batch_id }} — {{ SmsBatch::SOURCES[$m->batch->source] ?? '' }} — {{ $m->batch->creator ? trim($m->batch->creator->first_name . ' ' . $m->batch->creator->last_name) : '' }}</dd>
                            <dt class="col-sm-4">وضعیت</dt><dd class="col-sm-8"><span class="badge bg-{{ $m->statusBadge()['color'] }}-transparent">{{ $m->statusBadge()['title'] }}</span> @if($m->batch->is_fake)<span class="badge bg-warning-transparent">شبیه‌سازی</span>@endif</dd>
                            <dt class="col-sm-4">شناسه ارسال ملی پیامک (recId)</dt><dd class="col-sm-8 font-monospace" dir="ltr">{{ $m->provider_rec_id ?? '—' }}</dd>
                            <dt class="col-sm-4">زمان ارسال</dt><dd class="col-sm-8">{{ $m->sent_at ? verta($m->sent_at)->format('Y/m/d H:i:s') : '—' }}</dd>
                            <dt class="col-sm-4">وضعیت تحویل</dt><dd class="col-sm-8">{{ MelipayamakCodes::deliveryLabel($m->delivery_code) ?? 'هنوز گزارشی دریافت نشده' }}@if($m->delivery_code !== null) <span class="text-muted">(کد {{ $m->delivery_code }})</span>@endif
                                @if($m->delivery_checked_at)<div class="fs-11 text-muted">آخرین استعلام: {{ verta($m->delivery_checked_at)->format('Y/m/d H:i') }}</div>@endif</dd>
                            @if($m->error)<dt class="col-sm-4">خطا</dt><dd class="col-sm-8 text-danger">{{ $m->error }}@if($m->provider_code !== null) (کد {{ $m->provider_code }})@endif — {{ $m->error_permanent ? 'دائمی' : 'موقت' }}</dd>@endif
                            <dt class="col-sm-4">تعداد تلاش</dt><dd class="col-sm-8">{{ $m->attempts }} از {{ config('sms.max_attempts') }}</dd>
                            <dt class="col-sm-4">هزینه واقعی</dt><dd class="col-sm-8 text-muted">{{ $m->cost !== null ? number_format($m->cost) . ' ریال' : 'از طریق وب‌سرویس ارائه نمی‌شود' }}</dd>
                        </dl>
                        @if($m->isRetryable())
                            <button type="button" class="btn btn-sm btn-warning mt-3" wire:click="retry({{ $m->sms_batch_id }}, {{ $m->id }})" wire:confirm="این پیامک دوباره ارسال شود؟"><i class="ri-restart-line"></i> تلاش مجدد</button>
                        @endif
                    @else
                        <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
                    @endif
                </div>
            </div>
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
