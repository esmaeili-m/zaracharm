<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Marketplaces\Capability;
use App\Marketplaces\Jobs\PullOrdersJob;
use App\Marketplaces\Jobs\SyncListingJob;
use App\Models\Marketplace;
use App\Models\MarketplaceSyncLog;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    #[Url(except: '')]
    public string $marketplace = '';

    #[Url(except: '')]
    public string $operation = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $direction = '';

    #[Url(except: '')]
    public string $search = '';

    public ?int $selectedId = null;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('marketplaces.view'), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['marketplace', 'operation', 'status', 'direction', 'search'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function marketplaces()
    {
        return Marketplace::orderBy('id')->get(['id', 'title']);
    }

    #[Computed]
    public function data()
    {
        $term = trim($this->search);

        return MarketplaceSyncLog::query()
            ->with(['marketplace:id,title', 'listing:id,product_variant_id,external_id', 'order:id,external_id'])
            ->when($this->marketplace !== '', fn ($q) => $q->where('marketplace_id', (int) $this->marketplace))
            ->when($this->operation !== '', fn ($q) => $q->where('operation', $this->operation))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->direction !== '', fn ($q) => $q->where('direction', $this->direction))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('message', 'like', "%{$term}%")->orWhere('url', 'like', "%{$term}%")))
            ->latest('id')
            ->paginate(30);
    }

    #[Computed]
    public function selected(): ?MarketplaceSyncLog
    {
        return $this->selectedId ? MarketplaceSyncLog::with(['marketplace', 'listing', 'order'])->find($this->selectedId) : null;
    }

    public function show(int $id): void
    {
        $this->selectedId = $id;
        unset($this->selected);
    }

    public function canRetry(MarketplaceSyncLog $log): bool
    {
        return $log->status === 'failed' && $log->direction === 'out'
            && ($log->marketplace_listing_id || $log->operation === 'orders.pull');
    }

    /** تلاش دوباره دستی عملیات ناموفق */
    public function retry(int $id): void
    {
        abort_if(!auth()->user()->can('marketplaces.edit'), 403);

        $log = MarketplaceSyncLog::with('marketplace')->findOrFail($id);

        if (!$this->canRetry($log)) {
            return;
        }

        if (!$log->marketplace?->isUsable()) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'مارکت‌پلیس فعال یا تنظیم نشده است.');
            return;
        }

        if ($log->marketplace_listing_id) {
            SyncListingJob::dispatch($log->marketplace_listing_id);
        } elseif ($log->marketplace->supports(Capability::PULL_ORDERS)) {
            PullOrdersJob::dispatch($log->marketplace_id);
        }

        $log->update(['retried_at' => now()]);
        unset($this->data, $this->selected);
        $this->dispatch('alert', type: 'success', title: 'تلاش مجدد', text: 'عملیات دوباره در صف قرار گرفت.');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">لاگ همگام‌سازی مارکت‌پلیس‌ها</h1>
            <div class="text-muted small">درخواست‌ها، پاسخ‌ها، خطاها و رویدادهای ورودی (اطلاعات محرمانه حذف شده است)</div>
        </div>
        <a href="{{ route('marketplaces.index') }}" class="btn btn-light btn-wave"><i class="ri-store-2-line align-middle"></i> مارکت‌پلیس‌ها</a>
    </div>

    <div class="card custom-card">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-2">
                    <select wire:model.live="marketplace" class="form-select form-select-sm">
                        <option value="">همه مارکت‌پلیس‌ها</option>
                        @foreach($this->marketplaces as $m)
                            <option value="{{ $m->id }}">{{ $m->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="operation" class="form-select form-select-sm">
                        <option value="">همه عملیات‌ها</option>
                        @foreach(MarketplaceSyncLog::OPERATIONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="status" class="form-select form-select-sm">
                        <option value="">همه نتایج</option>
                        <option value="success">موفق</option>
                        <option value="failed">ناموفق</option>
                        <option value="skipped">رد شده</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="direction" class="form-select form-select-sm">
                        <option value="">ورودی و خروجی</option>
                        <option value="out">خروجی (به مارکت‌پلیس)</option>
                        <option value="in">ورودی (از مارکت‌پلیس)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" placeholder="جستجو در پیام یا آدرس">
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle text-nowrap">
                    <thead><tr><th>زمان</th><th>مارکت‌پلیس</th><th>عملیات</th><th>جهت</th><th>نتیجه</th><th>HTTP</th><th>پیام</th><th>تلاش</th><th></th></tr></thead>
                    <tbody>
                    @forelse($this->data as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="small">{{ verta($log->created_at)->format('Y/m/d H:i:s') }}</td>
                            <td>{{ $log->marketplace?->title }}</td>
                            <td>{{ $log->operation_label }}
                                @if($log->listing)<div class="small text-muted">اتصال واریانت {{ $log->listing->product_variant_id }}</div>@endif
                                @if($log->order)<div class="small text-muted" dir="ltr">سفارش {{ $log->order->external_id }}</div>@endif
                            </td>
                            <td><span class="badge bg-light text-dark">{{ $log->direction === 'in' ? 'ورودی' : 'خروجی' }}</span></td>
                            <td><span class="badge bg-{{ ['success' => 'success', 'failed' => 'danger'][$log->status] ?? 'secondary' }}-transparent">{{ ['success' => 'موفق', 'failed' => 'ناموفق', 'skipped' => 'رد شده'][$log->status] ?? $log->status }}</span>
                                @if($log->retryable)<span class="badge bg-warning-transparent" title="خطای موقت؛ صف دوباره تلاش می‌کند">موقت</span>@endif
                            </td>
                            <td>{{ $log->http_status ?? '—' }}@if($log->duration_ms)<div class="small text-muted">{{ $log->duration_ms }}ms</div>@endif</td>
                            <td class="text-wrap small" style="max-width: 360px">{{ $log->message }}</td>
                            <td>{{ $log->attempt }}</td>
                            <td>
                                <div class="hstack gap-2">
                                    <a href="#marketplaceLogModal" data-bs-toggle="modal" wire:click="show({{ $log->id }})" class="text-info"><i class="ri-eye-line"></i></a>
                                    @can('marketplaces.edit')
                                        @if($this->canRetry($log))
                                            <a href="javascript:void(0)" wire:click="retry({{ $log->id }})" class="text-warning" title="{{ $log->retried_at ? 'تلاش مجدد شده در ' . verta($log->retried_at)->format('H:i') : 'تلاش مجدد' }}"><i class="ri-restart-line"></i></a>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">لاگی ثبت نشده است.</td></tr>
                    @endforelse
                    <tr><td colspan="100">{{ $this->data->links() }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="marketplaceLogModal">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable"><div class="modal-content">
            @php $l = $this->selected; @endphp
            <div class="modal-header"><h6 class="modal-title">جزئیات لاگ #{{ $l?->id }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-start">
                @if($l)
                    <dl class="row small">
                        <dt class="col-3">مارکت‌پلیس</dt><dd class="col-9">{{ $l->marketplace?->title }}</dd>
                        <dt class="col-3">عملیات</dt><dd class="col-9">{{ $l->operation_label }} ({{ $l->direction === 'in' ? 'ورودی' : 'خروجی' }})</dd>
                        @if($l->url)<dt class="col-3">درخواست</dt><dd class="col-9" dir="ltr"><code>{{ $l->method }} {{ $l->url }}</code></dd>@endif
                        <dt class="col-3">نتیجه</dt><dd class="col-9">{{ $l->status }} @if($l->http_status)(HTTP {{ $l->http_status }})@endif @if($l->duration_ms)— {{ $l->duration_ms }}ms @endif</dd>
                        @if($l->message)<dt class="col-3">پیام</dt><dd class="col-9">{{ $l->message }}</dd>@endif
                        <dt class="col-3">تلاش</dt><dd class="col-9">{{ $l->attempt }}@if($l->retried_at) — تلاش مجدد دستی: {{ verta($l->retried_at)->format('Y/m/d H:i') }}@endif</dd>
                    </dl>
                    @if($l->request)
                        <h6 class="fw-semibold">درخواست</h6>
                        <pre class="bg-light p-2 rounded small" dir="ltr" style="max-height: 280px; overflow: auto">{{ json_encode($l->request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @endif
                    @if($l->response)
                        <h6 class="fw-semibold">پاسخ</h6>
                        <pre class="bg-light p-2 rounded small" dir="ltr" style="max-height: 360px; overflow: auto">{{ json_encode($l->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @endif
                @endif
            </div>
        </div></div>
    </div>
</div>
