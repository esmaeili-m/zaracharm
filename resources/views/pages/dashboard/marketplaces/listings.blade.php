<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Marketplaces\Capability;
use App\Marketplaces\Exceptions\MarketplaceException;
use App\Marketplaces\Jobs\SyncListingJob;
use App\Marketplaces\Services\ListingBuilder;
use App\Marketplaces\Services\SyncService;
use App\Models\Marketplace;
use App\Models\MarketplaceListing;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    #[Url(except: '')]
    public string $marketplace = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $search = '';

    // ---- فرم اتصال ----
    public ?int $listingId = null;
    public ?int $formMarketplaceId = null;
    public string $variantSearch = '';
    public ?int $variantId = null;
    public ?string $externalId = null;
    public ?string $externalVariantId = null;
    public ?string $externalSku = null;
    public ?string $externalUrl = null;
    public bool $isActive = true;
    public bool $syncContent = true;
    public bool $syncPrice = true;
    public bool $syncStock = true;

    // ---- محصولات مارکت‌پلیس ----
    public int $remotePage = 1;
    public array $remoteItems = [];
    public bool $remoteHasMore = false;
    public array $remoteSelection = [];   // external key => variant id

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('marketplaces.view'), 403);
    }

    protected function authorizeEdit(): void
    {
        abort_if(!auth()->user()->can('marketplaces.edit'), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['marketplace', 'status', 'search'], true)) {
            $this->resetPage();
        }

        if ($property === 'marketplace') {
            $this->reset(['remoteItems', 'remoteSelection', 'remoteHasMore']);
            $this->remotePage = 1;
        }
    }

    #[Computed]
    public function marketplaces()
    {
        return Marketplace::orderBy('id')->get()->filter(fn ($m) => $m->hasDriver())->values();
    }

    #[Computed]
    public function current(): ?Marketplace
    {
        return $this->marketplace !== '' ? $this->marketplaces->firstWhere('id', (int) $this->marketplace) : null;
    }

    #[Computed]
    public function data()
    {
        $term = trim($this->search);

        return MarketplaceListing::query()
            ->with(['marketplace', 'product:id,title,slug', 'variant.optionValues.optionValue', 'variant.inventoryItems.inventory'])
            ->when($this->marketplace !== '', fn ($q) => $q->where('marketplace_id', (int) $this->marketplace))
            ->when($this->status !== '', fn ($q) => $this->status === 'unlinked' ? $q->whereNull('external_id') : $q->where('sync_status', $this->status))
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($q) use ($term) {
                    $q->where('external_id', 'like', "%{$term}%")
                        ->orWhere('external_sku', 'like', "%{$term}%")
                        ->orWhereHas('product', fn ($p) => $p->where('title', 'like', "%{$term}%"))
                        ->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', "%{$term}%"));
                });
            })
            ->latest('id')
            ->paginate(25);
    }

    public function variantLabel(?ProductVariant $variant): string
    {
        if (!$variant) {
            return '—';
        }

        $options = $variant->optionValues->map(fn ($ov) => $ov->optionValue?->title)->filter()->implode(' / ');

        return trim(($variant->product?->title ?? '') . ($options ? ' - ' . $options : '') . ($variant->sku ? ' (' . $variant->sku . ')' : ''));
    }

    // ---------------------------------------------------------------- فرم

    #[Computed]
    public function variantResults()
    {
        $term = trim($this->variantSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return ProductVariant::query()
            ->with(['product:id,title', 'optionValues.optionValue'])
            ->where(fn ($q) => $q->where('sku', 'like', "%{$term}%")->orWhere('barcode', $term)->orWhereHas('product', fn ($p) => $p->where('title', 'like', "%{$term}%")))
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function formMarketplace(): ?Marketplace
    {
        return $this->formMarketplaceId ? $this->marketplaces->firstWhere('id', $this->formMarketplaceId) : null;
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        return $this->variantId ? ProductVariant::with(['product', 'optionValues.optionValue'])->find($this->variantId) : null;
    }

    public function newListing(?int $variantId = null, ?string $externalId = null, ?string $sku = null): void
    {
        $this->resetErrorBag();
        $this->reset(['listingId', 'variantSearch', 'externalVariantId', 'externalUrl']);
        $this->formMarketplaceId = $this->current?->id ?? $this->marketplaces->first()?->id;
        $this->variantId = $variantId;
        $this->externalId = $externalId;
        $this->externalSku = $sku;
        $this->isActive = $this->syncContent = $this->syncPrice = $this->syncStock = true;
        unset($this->selectedVariant, $this->formMarketplace);
    }

    public function editListing(int $id): void
    {
        $listing = MarketplaceListing::findOrFail($id);
        $this->resetErrorBag();
        $this->listingId = $listing->id;
        $this->formMarketplaceId = $listing->marketplace_id;
        $this->variantId = $listing->product_variant_id;
        $this->variantSearch = '';
        $this->externalId = $listing->external_id;
        $this->externalVariantId = $listing->external_variant_id;
        $this->externalSku = $listing->external_sku;
        $this->externalUrl = $listing->external_url;
        $this->isActive = $listing->is_active;
        $this->syncContent = $listing->sync_content;
        $this->syncPrice = $listing->sync_price;
        $this->syncStock = $listing->sync_stock;
        unset($this->selectedVariant, $this->formMarketplace);
    }

    public function selectVariant(int $id): void
    {
        $this->variantId = $id;
        $this->variantSearch = '';
        unset($this->selectedVariant, $this->variantResults);
    }

    public function save(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'formMarketplaceId' => ['required', Rule::in($this->marketplaces->pluck('id')->all())],
            'variantId' => [
                'required', 'exists:product_variants,id',
                Rule::unique('marketplace_listings', 'product_variant_id')->where('marketplace_id', $this->formMarketplaceId)->ignore($this->listingId),
            ],
            'externalId' => ['nullable', 'string', 'max:100'],
            'externalVariantId' => ['nullable', 'string', 'max:100'],
            'externalSku' => ['nullable', 'string', 'max:100'],
            'externalUrl' => ['nullable', 'url', 'max:255'],
        ], [
            'formMarketplaceId.required' => 'مارکت‌پلیس را انتخاب کنید.',
            'variantId.required' => 'محصول/واریانت فروشگاه را انتخاب کنید.',
            'variantId.unique' => 'این واریانت قبلاً به این مارکت‌پلیس متصل شده است.',
            'externalUrl.url' => 'آدرس معتبر نیست.',
        ]);

        $marketplace = $this->formMarketplace;

        // بدون شناسه فقط برای مارکت‌پلیس‌هایی که ایجاد محصول یا خوراک (Pull) دارند معنی دارد
        if (blank($this->externalId) && !$marketplace->supports(Capability::CREATE_LISTING) && !$marketplace->supports(Capability::PRODUCT_FEED)) {
            $this->addError('externalId', 'شناسه محصول/تنوع ' . $marketplace->title . ' را وارد کنید (ایجاد محصول از API پشتیبانی نمی‌شود).');
            return;
        }

        if (filled($this->externalId)) {
            $duplicate = MarketplaceListing::where('marketplace_id', $marketplace->id)
                ->where('external_id', trim($this->externalId))
                ->where('external_variant_id', filled($this->externalVariantId) ? trim($this->externalVariantId) : null)
                ->when($this->listingId, fn ($q) => $q->where('id', '!=', $this->listingId))
                ->exists();

            if ($duplicate) {
                $this->addError('externalId', 'این شناسه قبلاً به واریانت دیگری متصل شده است.');
                return;
            }
        }

        $variant = ProductVariant::findOrFail($this->variantId);
        $payload = [
            'marketplace_id' => $marketplace->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'external_id' => filled($this->externalId) ? trim($this->externalId) : null,
            'external_variant_id' => filled($this->externalVariantId) ? trim($this->externalVariantId) : null,
            'external_sku' => filled($this->externalSku) ? trim($this->externalSku) : $variant->sku,
            'external_url' => $this->externalUrl ?: null,
            'is_active' => $this->isActive,
            'sync_content' => $this->syncContent,
            'sync_price' => $this->syncPrice,
            'sync_stock' => $this->syncStock,
        ];

        if ($this->listingId) {
            $listing = MarketplaceListing::findOrFail($this->listingId);
            $changedTarget = $listing->external_id !== $payload['external_id'] || $listing->external_variant_id !== $payload['external_variant_id'];
            $listing->update($payload + ($changedTarget ? ['synced_price' => null, 'synced_stock' => null, 'synced_active' => null, 'content_hash' => null, 'sync_status' => 'pending'] : []));
        } else {
            $listing = MarketplaceListing::create($payload + ['sync_status' => 'pending']);
        }

        if ($listing->is_active && $marketplace->isUsable() && array_intersect(array_merge(Capability::LISTING_SYNC, [Capability::CREATE_LISTING]), $marketplace->driver()->capabilities())) {
            SyncListingJob::dispatch($listing->id);
        }

        unset($this->data);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'اتصال ذخیره شد' . ($marketplace->isUsable() ? ' و برای همگام‌سازی در صف قرار گرفت.' : '.'));
    }

    // ---------------------------------------------------------------- عملیات ردیف

    public function syncListing(int $id, bool $force = false): void
    {
        $this->authorizeEdit();
        $listing = MarketplaceListing::with('marketplace')->findOrFail($id);

        if (!$listing->marketplace->isUsable()) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $listing->marketplace->title . ' فعال یا تنظیم نشده است.');
            return;
        }

        try {
            $result = app(SyncService::class)->syncListing($listing, null, $force);
        } catch (MarketplaceException $e) {
            SyncListingJob::dispatch($listing->id, null, $force)->delay(now()->addMinute());
            $this->dispatch('alert', type: 'warning', title: 'خطای موقت', text: $e->getMessage() . ' — تلاش مجدد در صف قرار گرفت.');
            unset($this->data);
            return;
        }

        $listing->refresh();
        unset($this->data);

        $messages = ['created' => 'محصول در مارکت‌پلیس ایجاد شد.', 'updated' => 'اطلاعات به‌روز شد.', 'unchanged' => 'تغییری برای ارسال وجود نداشت.'];

        $listing->sync_status === 'failed'
            ? $this->dispatch('alert', type: 'error', title: 'خطا', text: (string) $listing->last_error)
            : $this->dispatch('alert', type: 'success', title: 'همگام‌سازی', text: $messages[$result] ?? 'انجام شد.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeEdit();
        $listing = MarketplaceListing::findOrFail($id);
        $listing->update(['is_active' => !$listing->is_active]);
        unset($this->data);
    }

    public function deleteListing(int $id): void
    {
        $this->authorizeEdit();
        // فقط اتصال حذف می‌شود؛ محصول در مارکت‌پلیس دست‌نخورده می‌ماند
        MarketplaceListing::findOrFail($id)->delete();
        unset($this->data);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'اتصال حذف شد (محصول در مارکت‌پلیس حذف نمی‌شود).');
    }

    /**
     * افزودن همه واریانت‌های فعال بدون اتصال (مارکت‌پلیس‌هایی که ایجاد محصول یا خوراک دارند)
     */
    public function addAllVariants(): void
    {
        $this->authorizeEdit();
        $marketplace = $this->current;

        if (!$marketplace || (!$marketplace->supports(Capability::CREATE_LISTING) && !$marketplace->supports(Capability::PRODUCT_FEED))) {
            return;
        }

        $count = 0;
        ProductVariant::query()
            ->where('status', true)
            ->whereHas('product', fn ($q) => $q->where('status', true))
            ->whereNotIn('id', MarketplaceListing::where('marketplace_id', $marketplace->id)->select('product_variant_id'))
            ->select(['id', 'product_id', 'sku'])
            ->chunkById(200, function ($variants) use ($marketplace, &$count) {
                foreach ($variants as $variant) {
                    MarketplaceListing::create([
                        'marketplace_id' => $marketplace->id,
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'external_sku' => $variant->sku,
                        'sync_status' => 'pending',
                    ]);
                    $count++;
                }
            });

        unset($this->data);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: $count . ' واریانت اضافه شد.' . ($marketplace->supports(Capability::CREATE_LISTING) ? ' برای ایجاد در ' . $marketplace->title . ' از «Sync دستی» استفاده کنید.' : ''));
    }

    // ---------------------------------------------------------------- محصولات مارکت‌پلیس

    public function loadRemote(int $page = 1): void
    {
        $this->authorizeEdit();
        $marketplace = $this->current;

        if (!$marketplace || !$marketplace->isUsable() || !$marketplace->supports(Capability::REMOTE_LISTINGS)) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'مارکت‌پلیس فعال و تنظیم نشده است.');
            return;
        }

        try {
            $result = app(SyncService::class)->remoteListings($marketplace, max(1, $page));
        } catch (MarketplaceException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $e->getMessage());
            return;
        }

        $linked = MarketplaceListing::where('marketplace_id', $marketplace->id)->whereNotNull('external_id')
            ->get(['external_id', 'external_variant_id', 'product_variant_id'])
            ->keyBy(fn ($l) => $l->external_id . '|' . $l->external_variant_id);

        $skus = collect($result->items)->pluck('sku')->filter()->unique()->values();
        $bySku = $skus->isEmpty() ? collect() : ProductVariant::whereIn('sku', $skus)->pluck('id', 'sku');

        $this->remotePage = max(1, $page);
        $this->remoteHasMore = $result->hasMore;
        $this->remoteSelection = [];
        $this->remoteItems = collect($result->items)->map(function ($item) use ($linked, $bySku) {
            $key = $item->externalId . '|' . $item->externalVariantId;
            $match = $item->sku ? ($bySku[$item->sku] ?? null) : null;

            if (!isset($linked[$key]) && $match) {
                $this->remoteSelection[$key] = $match;
            }

            return [
                'key' => $key,
                'external_id' => $item->externalId,
                'external_variant_id' => $item->externalVariantId,
                'title' => $item->title,
                'sku' => $item->sku,
                'price' => $item->price,
                'stock' => $item->stock,
                'url' => $item->url,
                'linked_variant' => $linked[$key]->product_variant_id ?? null,
                'match' => $match,
            ];
        })->all();
    }

    /** اتصال ردیف‌های تطبیق‌داده‌شده (با SKU یا انتخاب دستی شناسه واریانت) */
    public function linkRemote(): void
    {
        $this->authorizeEdit();
        $marketplace = $this->current;

        if (!$marketplace) {
            return;
        }

        $linked = 0;
        $skipped = 0;

        foreach ($this->remoteItems as $item) {
            $variantId = (int) ($this->remoteSelection[$item['key']] ?? 0);

            if (!$variantId || $item['linked_variant']) {
                continue;
            }

            $variant = ProductVariant::find($variantId);
            $exists = MarketplaceListing::where('marketplace_id', $marketplace->id)
                ->where(fn ($q) => $q->where('product_variant_id', $variantId)
                    ->orWhere(fn ($q) => $q->where('external_id', $item['external_id'])->where('external_variant_id', $item['external_variant_id'])))
                ->exists();

            if (!$variant || $exists) {
                $skipped++;
                continue;
            }

            $listing = MarketplaceListing::create([
                'marketplace_id' => $marketplace->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'external_id' => $item['external_id'],
                'external_variant_id' => $item['external_variant_id'],
                'external_sku' => $item['sku'],
                'external_url' => $item['url'],
                // محتوای محصول موجود در مارکت‌پلیس تا انتخاب مدیر بازنویسی نمی‌شود
                'sync_content' => false,
                'sync_status' => 'pending',
            ]);

            if ($marketplace->isUsable()) {
                SyncListingJob::dispatch($listing->id, ['price', 'stock', 'status']);
            }
            $linked++;
        }

        unset($this->data);
        $this->loadRemote($this->remotePage);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: "{$linked} مورد متصل شد" . ($skipped ? " ({$skipped} مورد تکراری رد شد)" : '') . '.');
    }

    public function currentValues(MarketplaceListing $listing): ?array
    {
        if (!$listing->variant || !$listing->marketplace?->hasDriver()) {
            return null;
        }

        try {
            $data = app(ListingBuilder::class)->build($listing->marketplace, $listing->variant);
        } catch (\Throwable) {
            return null;
        }

        return ['price' => $data->price, 'stock' => $data->stock, 'active' => $data->active];
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">اتصال محصولات به مارکت‌پلیس‌ها</h1>
            <div class="text-muted small">هر واریانت فروشگاه به یک محصول/تنوع در مارکت‌پلیس متصل می‌شود</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('marketplaces.index') }}" class="btn btn-light btn-wave"><i class="ri-store-2-line align-middle"></i> مارکت‌پلیس‌ها</a>
            @can('marketplaces.edit')
                <button type="button" class="btn btn-primary btn-wave" data-bs-toggle="modal" href="#listingModal" wire:click="newListing"><i class="ri-add-line"></i> اتصال جدید</button>
            @endcan
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <select wire:model.live="marketplace" class="form-select form-select-sm">
                        <option value="">همه مارکت‌پلیس‌ها</option>
                        @foreach($this->marketplaces as $m)
                            <option value="{{ $m->id }}">{{ $m->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="status" class="form-select form-select-sm">
                        <option value="">همه وضعیت‌ها</option>
                        @foreach(MarketplaceListing::STATUSES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                        <option value="unlinked">بدون شناسه مارکت‌پلیس</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" placeholder="نام محصول، SKU یا شناسه مارکت‌پلیس">
                </div>
                @can('marketplaces.edit')
                    @if($this->current && ($this->current->supports(Capability::CREATE_LISTING) || $this->current->supports(Capability::PRODUCT_FEED)))
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="addAllVariants" wire:confirm="همه واریانت‌های فعال بدون اتصال به {{ $this->current->title }} اضافه شوند؟">افزودن همه محصولات</button>
                        </div>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    {{-- ===================== محصولات مارکت‌پلیس ===================== --}}
    @can('marketplaces.edit')
        @if($this->current && $this->current->supports(Capability::REMOTE_LISTINGS))
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">محصولات {{ $this->current->title }} <span class="small text-muted fw-normal">— تطبیق خودکار با SKU</span></div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-info-light" wire:click="loadRemote(1)" wire:loading.attr="disabled" wire:target="loadRemote">
                            <span wire:loading wire:target="loadRemote" class="spinner-border spinner-border-sm"></span> دریافت فهرست
                        </button>
                        @if($remoteItems)
                            <button type="button" class="btn btn-sm btn-success" wire:click="linkRemote" @disabled(!array_filter($remoteSelection))>اتصال موارد انتخاب‌شده</button>
                        @endif
                    </div>
                </div>
                @if($remoteItems)
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle text-nowrap">
                                <thead><tr><th>شناسه</th><th>عنوان</th><th>کد فروشنده / SKU</th><th>قیمت</th><th>موجودی</th><th>واریانت فروشگاه (شناسه)</th></tr></thead>
                                <tbody>
                                @foreach($remoteItems as $item)
                                    <tr wire:key="remote-{{ $item['key'] }}">
                                        <td dir="ltr">{{ $item['external_id'] }}@if($item['external_variant_id']) / {{ $item['external_variant_id'] }}@endif</td>
                                        <td class="text-wrap" style="max-width: 320px">
                                            @if($item['url'])<a href="{{ $item['url'] }}" target="_blank" rel="noopener">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif
                                        </td>
                                        <td dir="ltr">{{ $item['sku'] ?? '—' }}</td>
                                        <td>{{ $item['price'] !== null ? number_format($item['price']) : '—' }}</td>
                                        <td>{{ $item['stock'] ?? '—' }}</td>
                                        <td style="width: 200px">
                                            @if($item['linked_variant'])
                                                <span class="badge bg-success-transparent">متصل (واریانت {{ $item['linked_variant'] }})</span>
                                            @else
                                                <input type="number" min="1" wire:model="remoteSelection.{{ $item['key'] }}" class="form-control form-control-sm {{ $item['match'] ? 'border-success' : '' }}" placeholder="شناسه واریانت">
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-sm btn-light" wire:click="loadRemote({{ $remotePage - 1 }})" @disabled($remotePage <= 1)>صفحه قبل</button>
                            <span class="small text-muted">صفحه {{ $remotePage }}</span>
                            <button type="button" class="btn btn-sm btn-light" wire:click="loadRemote({{ $remotePage + 1 }})" @disabled(!$remoteHasMore)>صفحه بعد</button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endcan

    {{-- ===================== اتصال‌ها ===================== --}}
    <div class="card custom-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap">
                    <thead>
                    <tr>
                        <th>محصول فروشگاه</th><th>مارکت‌پلیس</th><th>شناسه در مارکت‌پلیس</th>
                        <th>قیمت (فعلی / ارسال‌شده)</th><th>موجودی (فعلی / ارسال‌شده)</th>
                        <th>وضعیت</th><th>آخرین Sync</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->data as $listing)
                        @php $now = $this->currentValues($listing); @endphp
                        <tr wire:key="listing-{{ $listing->id }}" class="{{ $listing->is_active ? '' : 'opacity-50' }}">
                            <td class="text-wrap" style="max-width: 280px">
                                <div class="fw-semibold">{{ $this->variantLabel($listing->variant) }}</div>
                                <div class="small text-muted">واریانت {{ $listing->product_variant_id }}</div>
                            </td>
                            <td>{{ $listing->marketplace?->title }}</td>
                            <td dir="ltr">
                                @if($listing->external_id)
                                    @if($listing->external_url)<a href="{{ $listing->external_url }}" target="_blank" rel="noopener">{{ $listing->external_id }}</a>@else{{ $listing->external_id }}@endif
                                    @if($listing->external_variant_id)<span class="text-muted">/ {{ $listing->external_variant_id }}</span>@endif
                                @else
                                    <span class="badge bg-warning-transparent">{{ $listing->marketplace?->supports(Capability::CREATE_LISTING) ? 'ایجاد در Sync' : ($listing->marketplace?->supports(Capability::PRODUCT_FEED) ? 'در خوراک' : 'وارد نشده') }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $now ? number_format($now['price']) : '—' }}
                                <span class="text-muted">/ {{ $listing->synced_price !== null ? number_format($listing->synced_price) : '—' }}</span>
                                @if($now && $listing->synced_price !== null && $now['price'] !== $listing->synced_price)<i class="ri-error-warning-line text-warning" title="مغایرت"></i>@endif
                            </td>
                            <td>
                                {{ $now['stock'] ?? '—' }}
                                <span class="text-muted">/ {{ $listing->synced_stock ?? '—' }}</span>
                                @if($now && $listing->synced_stock !== null && $now['stock'] !== $listing->synced_stock)<i class="ri-error-warning-line text-warning" title="مغایرت"></i>@endif
                            </td>
                            <td>
                                <span class="badge bg-{{ ['synced' => 'success', 'failed' => 'danger'][$listing->sync_status] ?? 'secondary' }}-transparent">{{ $listing->status_label }}</span>
                                @if($listing->last_error)
                                    <div class="small text-danger text-wrap" style="max-width: 240px">{{ $listing->last_error }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $listing->last_synced_at ? verta($listing->last_synced_at)->format('Y/m/d H:i') : '—' }}</td>
                            <td>
                                @can('marketplaces.edit')
                                    <div class="hstack gap-2">
                                        @if(!$listing->marketplace?->supports(Capability::PRODUCT_FEED))
                                            <a href="javascript:void(0)" wire:click="syncListing({{ $listing->id }})" class="text-success" title="همگام‌سازی"><i class="ri-refresh-line"></i></a>
                                            <a href="javascript:void(0)" wire:click="syncListing({{ $listing->id }}, true)" class="text-primary" title="ارسال کامل دوباره"><i class="ri-upload-cloud-2-line"></i></a>
                                        @endif
                                        <a href="javascript:void(0)" wire:click="toggleActive({{ $listing->id }})" class="text-warning" title="{{ $listing->is_active ? 'توقف همگام‌سازی' : 'فعال‌سازی' }}"><i class="ri-{{ $listing->is_active ? 'pause' : 'play' }}-circle-line"></i></a>
                                        <a href="#listingModal" data-bs-toggle="modal" wire:click="editListing({{ $listing->id }})" class="text-info"><i class="ri-edit-line"></i></a>
                                        <a href="javascript:void(0)" wire:click="deleteListing({{ $listing->id }})" wire:confirm="اتصال حذف شود؟ (محصول در مارکت‌پلیس حذف نمی‌شود)" class="text-danger"><i class="ri-delete-bin-5-line"></i></a>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">اتصالی ثبت نشده است.</td></tr>
                    @endforelse
                    <tr><td colspan="100">{{ $this->data->links() }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===================== مودال اتصال ===================== --}}
    <div wire:ignore.self class="modal fade" id="listingModal">
        <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
            <form wire:submit="save" autocomplete="off">
                <div class="modal-header"><h6 class="modal-title">{{ $listingId ? 'ویرایش اتصال' : 'اتصال محصول' }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-start">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">مارکت‌پلیس</label>
                            <select wire:model.live="formMarketplaceId" class="form-select @error('formMarketplaceId') is-invalid @enderror" @disabled($listingId)>
                                @foreach($this->marketplaces as $m)
                                    <option value="{{ $m->id }}">{{ $m->title }}</option>
                                @endforeach
                            </select>
                            @error('formMarketplaceId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 position-relative">
                            <label class="form-label">محصول / واریانت فروشگاه</label>
                            @if($this->selectedVariant)
                                <div class="form-control d-flex justify-content-between align-items-center">
                                    <span class="text-truncate">{{ $this->variantLabel($this->selectedVariant) }}</span>
                                    @unless($listingId)<button type="button" class="btn btn-sm btn-link p-0" wire:click="$set('variantId', null)">تغییر</button>@endunless
                                </div>
                            @else
                                <input type="text" wire:model.live.debounce.300ms="variantSearch" class="form-control @error('variantId') is-invalid @enderror" placeholder="جستجوی نام محصول یا SKU">
                                @if($this->variantResults->isNotEmpty())
                                    <div class="list-group position-absolute w-100 shadow" style="z-index: 10; max-height: 260px; overflow-y: auto">
                                        @foreach($this->variantResults as $v)
                                            <button type="button" class="list-group-item list-group-item-action small" wire:click="selectVariant({{ $v->id }})">{{ $this->variantLabel($v) }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                            @error('variantId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        @php $fm = $this->formMarketplace; @endphp
                        <div class="col-md-6">
                            <label class="form-label">شناسه محصول در {{ $fm?->title }}</label>
                            <input type="text" wire:model="externalId" dir="ltr" class="form-control @error('externalId') is-invalid @enderror"
                                   placeholder="{{ $fm?->supports(Capability::CREATE_LISTING) ? 'خالی = ایجاد محصول جدید در Sync' : ($fm?->supports(Capability::PRODUCT_FEED) ? 'نیازی نیست' : ($fm?->provider === 'digikala' ? 'شناسه تنوع (DKPC)' : '')) }}">
                            @error('externalId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">شناسه تنوع در مارکت‌پلیس (اختیاری)</label>
                            <input type="text" wire:model="externalVariantId" dir="ltr" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">کد فروشنده / SKU در مارکت‌پلیس</label>
                            <input type="text" wire:model="externalSku" dir="ltr" class="form-control" placeholder="پیش‌فرض: SKU واریانت">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">آدرس صفحه محصول در مارکت‌پلیس</label>
                            <input type="text" wire:model="externalUrl" dir="ltr" class="form-control @error('externalUrl') is-invalid @enderror">
                            @error('externalUrl') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="l-active" wire:model="isActive">
                                <label class="form-check-label" for="l-active">فعال</label>
                            </div>
                            @if($fm?->supports(Capability::UPDATE_CONTENT))
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="l-content" wire:model="syncContent">
                                    <label class="form-check-label" for="l-content">همگام‌سازی نام، توضیحات و تصاویر</label>
                                </div>
                            @endif
                            @if($fm?->supports(Capability::UPDATE_PRICE))
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="l-price" wire:model="syncPrice">
                                    <label class="form-check-label" for="l-price">همگام‌سازی قیمت</label>
                                </div>
                            @endif
                            @if($fm?->supports(Capability::UPDATE_STOCK))
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="l-stock" wire:model="syncStock">
                                    <label class="form-check-label" for="l-stock">همگام‌سازی موجودی</label>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">ذخیره</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button></div>
            </form>
        </div></div>
    </div>
</div>
