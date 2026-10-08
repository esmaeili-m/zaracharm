<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Campaign;
use App\Models\CampaignReward;
use App\Models\CampaignTarget;
use App\Services\Campaigns\CampaignPlanner;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $state = '';

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('campaigns.view'), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'state'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function data()
    {
        $now = now();
        $term = trim($this->search);

        return Campaign::query()
            ->with(['rewards' => fn ($q) => $q->whereIn('reward_type', [0, 1])])
            ->withCount('usages')
            ->withSum('usages', 'discount_amount')
            ->withSum('usages', 'quantity')
            ->when($term !== '', fn ($q) => $q->where('title', 'like', "%{$term}%"))
            ->when($this->state !== '', function ($q) use ($now) {
                match ($this->state) {
                    'draft' => $q->where('status', Campaign::STATUS_DRAFT),
                    'paused' => $q->where('status', Campaign::STATUS_PAUSED),
                    'scheduled' => $q->where('status', Campaign::STATUS_ACTIVE)->where('start_at', '>', $now),
                    'ended' => $q->where('status', Campaign::STATUS_ACTIVE)->whereNotNull('end_at')->where('end_at', '<', $now),
                    'active' => $q->where('status', Campaign::STATUS_ACTIVE)
                        ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', $now))
                        ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $now)),
                    default => null,
                };
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', [Campaign::STATUS_ACTIVE, Campaign::STATUS_DRAFT])
            ->latest('id')
            ->paginate(15);
    }

    #[Computed]
    public function counts(): array
    {
        $now = now();

        return [
            'active' => Campaign::where('status', Campaign::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', $now))
                ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $now))->count(),
            'scheduled' => Campaign::where('status', Campaign::STATUS_ACTIVE)->where('start_at', '>', $now)->count(),
            'draft' => Campaign::where('status', Campaign::STATUS_DRAFT)->count(),
        ];
    }

    public function productCount(Campaign $campaign): int
    {
        $planner = app(CampaignPlanner::class);

        return $planner->productQuery($planner->targetsOf($campaign))->count();
    }

    public function rewardText(Campaign $campaign): string
    {
        $reward = $campaign->rewards->sortByDesc('id')->first();

        return $reward
            ? app(CampaignPlanner::class)->rewardText((int) $reward->reward_type, (float) $reward->value, $reward->max_value)
            : '—';
    }

    /** فعال ↔ غیرفعال (پیش‌نویس از مرحله «بررسی نهایی» ویزارد فعال می‌شود) */
    public function toggle(int $id): void
    {
        abort_if(!auth()->user()->can('campaigns.edit'), 403);

        $campaign = Campaign::findOrFail($id);
        $status = (int) $campaign->getRawOriginal('status');

        if ($status === Campaign::STATUS_DRAFT) {
            $this->redirectRoute('campaign.edit', ['campaign' => $id, 'step' => 'review']);
            return;
        }

        $campaign->update(['status' => $status === Campaign::STATUS_ACTIVE ? Campaign::STATUS_PAUSED : Campaign::STATUS_ACTIVE]);
        unset($this->data, $this->counts);

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق',
            text: 'کمپین «' . $campaign->title . '» ' . ($status === Campaign::STATUS_ACTIVE ? 'غیرفعال شد و دیگر روی قیمت‌ها اعمال نمی‌شود.' : 'دوباره فعال شد.'));
    }

    public function duplicate(int $id): void
    {
        abort_if(!auth()->user()->can('campaigns.create'), 403);

        $source = Campaign::with(['targets', 'rewards'])->findOrFail($id);

        $copy = DB::transaction(function () use ($source) {
            $end = $source->getRawOriginal('end_at');
            $base = $source->slug . '-copy';
            $slug = $base;
            $i = 2;
            while (Campaign::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }

            $copy = Campaign::create([
                'title' => $source->title . ' (کپی)',
                'slug' => $slug,
                'description' => $source->description,
                'type' => in_array((int) $source->getRawOriginal('type'), Campaign::PRICED_TYPES, true) ? (int) $source->getRawOriginal('type') : 0,
                'status' => Campaign::STATUS_DRAFT,
                'priority' => $source->priority,
                // کمپین پایان‌یافته: تاریخ‌ها برای تنظیم دوباره خالی می‌شوند
                'start_at' => $end && \Carbon\Carbon::parse($end)->isPast() ? null : ($source->getRawOriginal('start_at') ? \Carbon\Carbon::parse($source->getRawOriginal('start_at')) : null),
                'end_at' => $end && \Carbon\Carbon::parse($end)->isPast() ? null : ($end ? \Carbon\Carbon::parse($end) : null),
                'settings' => $source->settings,
            ]);

            foreach ($source->targets as $target) {
                CampaignTarget::create(['campaign_id' => $copy->id, 'target_type' => $target->target_type, 'target_id' => $target->target_id]);
            }

            foreach ($source->rewards->whereIn('reward_type', [0, 1]) as $reward) {
                CampaignReward::create(['campaign_id' => $copy->id, 'reward_type' => $reward->reward_type, 'value' => $reward->value, 'max_value' => $reward->max_value]);
            }

            return $copy;
        });

        $this->redirectRoute('campaign.edit', ['campaign' => $copy->id]);
    }

    public function delete(int $id): void
    {
        abort_if(!auth()->user()->can('campaigns.delete'), 403);

        $campaign = Campaign::withCount('usages')->findOrFail($id);

        if ($campaign->usages_count) {
            $this->dispatch('alert', type: 'error', title: 'حذف ممکن نیست',
                text: 'این کمپین در ' . $campaign->usages_count . ' سفارش استفاده شده و برای گزارش‌ها لازم است؛ به‌جای حذف آن را غیرفعال کنید.');
            return;
        }

        DB::transaction(function () use ($campaign) {
            $campaign->targets()->delete();
            $campaign->rewards()->delete();
            $campaign->conditions()->delete();
            $campaign->delete();
        });

        unset($this->data, $this->counts);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'کمپین حذف شد.');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">کمپین‌های تخفیف</h1>
            <div class="text-muted small">
                {{ $this->counts['active'] }} فعال · {{ $this->counts['scheduled'] }} زمان‌بندی‌شده · {{ $this->counts['draft'] }} پیش‌نویس
            </div>
        </div>
        @can('campaigns.create')
            <a href="{{ route('campaign.create') }}" class="btn btn-primary btn-wave"><i class="ri-add-line align-middle"></i> ساخت کمپین جدید</a>
        @endcan
    </div>

    @if(session('campaign-saved'))
        <div class="alert alert-success"><i class="ri-checkbox-circle-line"></i> {{ session('campaign-saved') }}</div>
    @endif

    <div class="card custom-card">
        <div class="card-body">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" placeholder="جستجوی نام کمپین">
                </div>
                <div class="col-md-7">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach(['' => 'همه'] + collect(Campaign::STATES)->map(fn ($s) => $s['label'])->all() as $key => $label)
                            <button type="button" wire:click="$set('state', '{{ $key }}')" class="btn btn-sm {{ $state === $key ? 'btn-primary' : 'btn-light' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap">
                    <thead>
                    <tr class="small">
                        <th>کمپین</th>
                        <th>وضعیت</th>
                        <th>محصولات</th>
                        <th>تخفیف</th>
                        <th>بازه زمانی</th>
                        <th>استفاده / عملکرد</th>
                        <th class="text-end">عملیات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->data as $campaign)
                        @php
                            $stateKey = $campaign->state;
                            $priced = in_array((int) $campaign->getRawOriginal('type'), Campaign::PRICED_TYPES, true);
                            $limit = (int) $campaign->setting('usage_limit');
                        @endphp
                        <tr wire:key="campaign-{{ $campaign->id }}">
                            <td class="text-wrap" style="max-width: 260px">
                                <a href="{{ route('campaign.edit', ['campaign' => $campaign->id, 'step' => 'review']) }}" class="fw-semibold">{{ $campaign->title }}</a>
                                <div class="small text-muted">
                                    {{ (int) $campaign->getRawOriginal('type') === 1 ? 'فروش ویژه (شگفت‌انگیز)' : ($priced ? 'کمپین تخفیف' : 'نوع پشتیبانی‌نشده') }}
                                    @unless($priced)<span class="badge bg-warning-transparent" title="موتور قیمت این نوع کمپین را اعمال نمی‌کند">بی‌اثر</span>@endunless
                                </div>
                            </td>
                            <td><span class="badge {{ Campaign::STATES[$stateKey]['class'] }}">{{ Campaign::STATES[$stateKey]['label'] }}</span></td>
                            <td class="small">{{ number_format($this->productCount($campaign)) }} محصول</td>
                            <td class="small">
                                {{ $this->rewardText($campaign) }}
                            </td>
                            <td class="small">
                                {{ $campaign->getRawOriginal('start_at') ? verta($campaign->getRawOriginal('start_at'))->format('Y/m/d H:i') : 'از ابتدا' }}
                                <i class="ri-arrow-left-line text-muted"></i>
                                {{ $campaign->getRawOriginal('end_at') ? verta($campaign->getRawOriginal('end_at'))->format('Y/m/d H:i') : 'بدون پایان' }}
                                @if($stateKey === 'active' && $campaign->getRawOriginal('end_at'))
                                    <div class="text-muted">{{ verta($campaign->getRawOriginal('end_at'))->formatDifference() }} تا پایان</div>
                                @elseif($stateKey === 'scheduled')
                                    <div class="text-info">شروع {{ verta($campaign->getRawOriginal('start_at'))->formatDifference() }}</div>
                                @endif
                            </td>
                            <td class="small">
                                <div>{{ number_format($campaign->usages_count) }}@if($limit) / {{ number_format($limit) }}@endif سفارش</div>
                                @if($campaign->usages_count)
                                    <div class="text-muted">{{ number_format((int) $campaign->usages_sum_quantity) }} کالا · {{ number_format((int) $campaign->usages_sum_discount_amount) }} تومان تخفیف</div>
                                @endif
                                @if($limit)
                                    <div class="progress mt-1" style="height: 4px; width: 110px">
                                        <div class="progress-bar" style="width: {{ min(100, round($campaign->usages_count / max(1, $limit) * 100)) }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('campaign.edit', ['campaign' => $campaign->id, 'step' => 'review']) }}" class="btn btn-light" title="مشاهده"><i class="ri-eye-line"></i></a>
                                    @can('campaigns.edit')
                                        <a href="{{ route('campaign.edit', $campaign->id) }}" class="btn btn-light" title="ویرایش"><i class="ri-edit-line"></i></a>
                                    @endcan
                                    @can('campaigns.create')
                                        <button type="button" class="btn btn-light" wire:click="duplicate({{ $campaign->id }})" title="کپی به‌عنوان پیش‌نویس"><i class="ri-file-copy-line"></i></button>
                                    @endcan
                                    @can('campaigns.edit')
                                        @if($stateKey === 'draft')
                                            <button type="button" class="btn btn-light text-success" wire:click="toggle({{ $campaign->id }})" title="بررسی و فعال‌سازی"><i class="ri-play-line"></i></button>
                                        @elseif($stateKey !== 'ended')
                                            <button type="button" class="btn btn-light {{ $stateKey === 'paused' ? 'text-success' : 'text-warning' }}" wire:click="toggle({{ $campaign->id }})"
                                                    @if($stateKey !== 'paused') wire:confirm="کمپین «{{ $campaign->title }}» غیرفعال شود؟ قیمت‌ها بلافاصله به حالت عادی برمی‌گردند." @endif
                                                    title="{{ $stateKey === 'paused' ? 'فعال‌سازی دوباره' : 'غیرفعال کردن' }}">
                                                <i class="ri-{{ $stateKey === 'paused' ? 'play' : 'pause' }}-line"></i>
                                            </button>
                                        @endif
                                    @endcan
                                    @can('campaigns.delete')
                                        <button type="button" class="btn btn-light text-danger" wire:click="delete({{ $campaign->id }})" wire:confirm="کمپین «{{ $campaign->title }}» حذف شود؟" title="حذف"><i class="ri-delete-bin-5-line"></i></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ri-price-tag-3-line fs-1 d-block mb-2"></i>
                                <strong>کمپینی یافت نشد.</strong>
                                @can('campaigns.create')
                                    <div class="mt-2"><a href="{{ route('campaign.create') }}" class="btn btn-sm btn-primary">ساخت اولین کمپین</a></div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                    @if($this->data->hasPages())
                        <tr><td colspan="7">{{ $this->data->links() }}</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
