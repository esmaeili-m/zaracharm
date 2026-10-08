<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Enums\CampaignTargetType;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CampaignReward;
use App\Models\CampaignTarget;
use App\Models\Category;
use App\Models\Product;
use App\Services\Campaigns\CampaignPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;

/*
 * ویزارد کمپین تخفیف
 *   ۱. اطلاعات کمپین   ۲. محصولات مشمول   ۳. تنظیم تخفیف (با پیش‌نمایش قیمت قبل/بعد)
 *   ۴. محدودیت‌ها و شرایط   ۵. بررسی نهایی (ذخیره پیش‌نویس / فعال‌سازی)
 *
 * هر مرحله با «ذخیره و ادامه» ثبت می‌شود (کمپین جدید از مرحله ۱ به‌صورت پیش‌نویس ساخته می‌شود)
 * تا با بستن صفحه چیزی از دست نرود. فقط شرایطی ارائه می‌شود که موتور قیمت واقعاً اعمال می‌کند.
 */
new class extends Component
{
    public const STEPS = [
        'info' => 'اطلاعات کمپین',
        'targets' => 'انتخاب محصولات',
        'discount' => 'تنظیم تخفیف',
        'limits' => 'محدودیت‌ها و شرایط',
        'review' => 'بررسی نهایی',
    ];

    public ?int $campaignId = null;

    #[Url(except: 'info')]
    public string $step = 'info';

    // ---- ۱. اطلاعات
    public string $title = '';
    public ?string $description = null;
    public int $type = 0;
    public ?string $startDate = null;
    public string $startTime = '00:00';
    public ?string $endDate = null;
    public string $endTime = '23:59';

    // ---- ۲. محصولات
    public bool $allStore = false;
    public array $productIds = [];
    public array $categoryIds = [];
    public array $brandIds = [];
    public string $targetTab = 'products';
    public string $productSearch = '';
    public ?int $filterCategory = null;
    public ?int $filterBrand = null;
    public int $productPage = 1;

    // ---- ۳. تخفیف
    public int $rewardType = 0;      // 0 درصدی ، 1 مبلغ ثابت
    public $rewardValue = null;
    public $rewardCap = null;

    // ---- ۴. محدودیت‌ها (null = غیرفعال)
    public bool $useLimit = false;
    public $usageLimit = null;
    public bool $useCustomerLimit = false;
    public $usagePerCustomer = null;
    public bool $useMinItem = false;
    public $minItemPrice = null;
    public bool $useMaxItem = false;
    public $maxItemPrice = null;
    public int $priority = 0;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(?Campaign $campaign = null)
    {
        abort_if(!auth()->user()->can($campaign?->exists ? 'campaigns.view' : 'campaigns.create'), 403);

        $legacy = [
            'campaign.targets' => 'targets',
            'campaign.conditions' => 'limits',
            'campaign.rewards' => 'discount',
        ][request()->route()?->getName()] ?? null;

        if ($legacy && !request()->has('step')) {
            $this->step = $legacy;
        }

        if ($campaign?->exists) {
            $this->campaignId = $campaign->id;
            $this->fill($this->formFromCampaign($campaign));
        } else {
            $this->step = 'info';
        }

        if (!array_key_exists($this->step, self::STEPS)) {
            $this->step = 'info';
        }
    }

    protected function formFromCampaign(Campaign $campaign): array
    {
        $start = $campaign->getRawOriginal('start_at') ? \Carbon\Carbon::parse($campaign->getRawOriginal('start_at')) : null;
        $end = $campaign->getRawOriginal('end_at') ? \Carbon\Carbon::parse($campaign->getRawOriginal('end_at')) : null;
        $targets = app(CampaignPlanner::class)->targetsOf($campaign);
        $reward = $campaign->rewards()->whereIn('reward_type', [0, 1])->latest('id')->first();
        $settings = (array) $campaign->settings;

        return [
            'title' => $campaign->title,
            'description' => $campaign->description ? trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $campaign->description)))) : null,
            'type' => in_array((int) $campaign->getRawOriginal('type'), Campaign::PRICED_TYPES, true) ? (int) $campaign->getRawOriginal('type') : 0,
            'startDate' => $start ? Jalalian::fromCarbon($start)->format('Y/m/d') : null,
            'startTime' => $start ? $start->format('H:i') : '00:00',
            'endDate' => $end ? Jalalian::fromCarbon($end)->format('Y/m/d') : null,
            'endTime' => $end ? $end->format('H:i') : '23:59',
            'allStore' => $targets['all'],
            'productIds' => $targets['products'],
            'categoryIds' => $targets['categories'],
            'brandIds' => $targets['brands'],
            'rewardType' => $reward ? (int) $reward->reward_type : 0,
            'rewardValue' => $reward ? ((int) $reward->reward_type === 0 ? (float) $reward->value + 0 : (int) $reward->value) : null,
            'rewardCap' => $reward?->max_value ? (int) $reward->max_value : null,
            'useLimit' => !empty($settings['usage_limit']),
            'usageLimit' => $settings['usage_limit'] ?? null,
            'useCustomerLimit' => !empty($settings['usage_per_customer']),
            'usagePerCustomer' => $settings['usage_per_customer'] ?? null,
            'useMinItem' => !empty($settings['min_item_price']),
            'minItemPrice' => $settings['min_item_price'] ?? null,
            'useMaxItem' => !empty($settings['max_item_price']),
            'maxItemPrice' => $settings['max_item_price'] ?? null,
            'priority' => (int) $campaign->priority,
        ];
    }

    #[Computed]
    public function campaign(): ?Campaign
    {
        return $this->campaignId ? Campaign::find($this->campaignId) : null;
    }

    protected function planner(): CampaignPlanner
    {
        return app(CampaignPlanner::class);
    }

    protected function authorizeEdit(): void
    {
        abort_if(!auth()->user()->can($this->campaignId ? 'campaigns.edit' : 'campaigns.create'), 403);
    }

    public function goTo(string $step): void
    {
        if (!array_key_exists($step, self::STEPS) || (!$this->campaignId && $step !== 'info')) {
            return;
        }

        $this->step = $step;
        $this->resetErrorBag();
    }

    protected function next(string $current): void
    {
        $keys = array_keys(self::STEPS);
        $this->step = $keys[array_search($current, $keys, true) + 1] ?? $current;
    }

    /*
    |--------------------------------------------------------------------------
    | ۱. اطلاعات کمپین
    |--------------------------------------------------------------------------
    */
    protected function parseDate(?string $date, string $time, string $field): ?\Carbon\Carbon
    {
        if (blank($date)) {
            return null;
        }

        try {
            $carbon = Jalalian::fromFormat('Y/m/d H:i', trim($date) . ' ' . (preg_match('/^\d{1,2}:\d{2}$/', $time) ? $time : '00:00'))->toCarbon();
        } catch (\Throwable) {
            $this->addError($field, 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.');

            return null;
        }

        // ستون‌های تاریخ از نوع TIMESTAMP هستند (حداکثر سال ۲۰۳۷ میلادی)
        if ($carbon->year > 2037 || $carbon->year < 2000) {
            $this->addError($field, 'تاریخ خارج از بازه مجاز است.');

            return null;
        }

        return $carbon;
    }

    public function saveInfo(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:0,1'],
            'startTime' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'endTime' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
        ], [
            'title.required' => 'نام کمپین را وارد کنید.',
            'title.min' => 'نام کمپین حداقل ۲ کاراکتر است.',
            'title.max' => 'نام کمپین حداکثر ۲۰۰ کاراکتر است.',
            'description.max' => 'توضیح حداکثر ۲۰۰۰ کاراکتر است.',
            'type.in' => 'نوع کمپین معتبر نیست.',
            '*.regex' => 'ساعت را به شکل ۱۴:۳۰ وارد کنید.',
        ]);

        $start = $this->parseDate($this->startDate, $this->startTime, 'startDate');
        $end = $this->parseDate($this->endDate, $this->endTime, 'endDate');

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if ($start && $end && $end->lte($start)) {
            $this->addError('endDate', 'زمان پایان باید بعد از زمان شروع باشد.');
            return;
        }

        if ($end && $end->isPast() && (!$this->campaignId || $this->campaign?->state !== 'ended')) {
            $this->addError('endDate', 'زمان پایان گذشته است.');
            return;
        }

        $data = [
            'title' => trim($this->title),
            'description' => filled($this->description) ? nl2br(e(trim($this->description)), false) : null,
            'type' => $this->type,
            'start_at' => $start,
            'end_at' => $end,
        ];

        if ($this->campaignId) {
            $this->campaign->update($data);
        } else {
            $campaign = Campaign::create($data + [
                'slug' => $this->uniqueSlug($this->title),
                'status' => Campaign::STATUS_DRAFT,
                'priority' => 0,
            ]);

            // از این پس آدرس صفحه شناسه کمپین را دارد (رفرش = ادامه همان پیش‌نویس)
            $this->redirectRoute('campaign.edit', ['campaign' => $campaign->id, 'step' => 'targets']);

            return;
        }

        unset($this->campaign);
        $this->next('info');
    }

    protected function uniqueSlug(string $title): string
    {
        $base = trim(preg_replace('/[^a-zA-Z0-9\-_\p{Arabic}]+/u', '-', $title), '-') ?: 'campaign';
        $slug = $base;
        $i = 2;

        while (Campaign::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /*
    |--------------------------------------------------------------------------
    | ۲. انتخاب محصولات
    |--------------------------------------------------------------------------
    */
    public function targets(): array
    {
        return ['all' => $this->allStore, 'products' => $this->productIds, 'categories' => $this->categoryIds, 'brands' => $this->brandIds];
    }

    #[Computed]
    public function categories()
    {
        return Category::active()->orderBy('title')->pluck('title', 'id');
    }

    #[Computed]
    public function brands()
    {
        return Brand::active()->orderBy('title')->pluck('title', 'id');
    }

    protected function searchQuery()
    {
        $term = trim($this->productSearch);

        return Product::query()
            ->where('status', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%"))))
            ->when($this->filterCategory, fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $this->filterCategory)))
            ->when($this->filterBrand, fn ($q) => $q->where('brand_id', $this->filterBrand));
    }

    #[Computed]
    public function searchResults()
    {
        return $this->searchQuery()
            ->with(['brand:id,title', 'featuredImage'])
            ->withMin(['variants' => fn ($q) => $q->where('status', true)], 'price')
            ->orderBy('title')
            ->paginate(10, ['*'], 'p', $this->productPage);
    }

    public function updated($property): void
    {
        if (in_array($property, ['productSearch', 'filterCategory', 'filterBrand'], true)) {
            $this->productPage = 1;
        }
    }

    public function setPage(int $page): void
    {
        $this->productPage = max(1, $page);
    }

    public function toggleProduct(int $id): void
    {
        $this->productIds = in_array($id, $this->productIds, true)
            ? array_values(array_diff($this->productIds, [$id]))
            : array_values(array_merge($this->productIds, [$id]));
    }

    public function addAllResults(): void
    {
        $ids = $this->searchQuery()->limit(500)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->productIds = array_values(array_unique(array_merge($this->productIds, $ids)));
    }

    public function removeTarget(string $list, int $id): void
    {
        if (in_array($list, ['productIds', 'categoryIds', 'brandIds'], true)) {
            $this->{$list} = array_values(array_diff(array_map('intval', $this->{$list}), [$id]));
        }
    }

    public function clearTargets(): void
    {
        $this->reset(['productIds', 'categoryIds', 'brandIds']);
    }

    #[Computed]
    public function selectedProducts()
    {
        return Product::whereIn('id', $this->productIds)->orderBy('title')->get(['id', 'title']);
    }

    #[Computed]
    public function affectedCount(): int
    {
        return $this->planner()->productQuery($this->targets())->count();
    }

    public function saveTargets(): void
    {
        $this->authorizeEdit();

        $this->productIds = array_values(array_unique(array_map('intval', $this->productIds)));
        $this->categoryIds = array_values(array_unique(array_map('intval', $this->categoryIds)));
        $this->brandIds = array_values(array_unique(array_map('intval', $this->brandIds)));

        if (!$this->allStore && !$this->productIds && !$this->categoryIds && !$this->brandIds) {
            $this->addError('targets', 'حداقل یک محصول، دسته‌بندی یا برند انتخاب کنید (یا «کل فروشگاه» را بزنید).');
            return;
        }

        DB::transaction(function () {
            CampaignTarget::where('campaign_id', $this->campaignId)->delete();

            $rows = $this->allStore
                ? [[CampaignTargetType::ALL->value, null]]
                : array_merge(
                    array_map(fn ($id) => [CampaignTargetType::PRODUCT->value, $id], $this->productIds),
                    array_map(fn ($id) => [CampaignTargetType::CATEGORY->value, $id], $this->categoryIds),
                    array_map(fn ($id) => [CampaignTargetType::BRAND->value, $id], $this->brandIds),
                );

            foreach ($rows as [$type, $id]) {
                CampaignTarget::create(['campaign_id' => $this->campaignId, 'target_type' => $type, 'target_id' => $id]);
            }
        });

        $this->next('targets');
    }

    /*
    |--------------------------------------------------------------------------
    | ۳. تنظیم تخفیف
    |--------------------------------------------------------------------------
    */
    protected function limits(): array
    {
        return [
            'min_item_price' => $this->useMinItem ? (int) $this->minItemPrice : 0,
            'max_item_price' => $this->useMaxItem ? (int) $this->maxItemPrice : 0,
        ];
    }

    #[Computed]
    public function preview(): array
    {
        return $this->planner()->preview($this->targets(), $this->rewardType, $this->rewardValue, $this->rewardType === 0 ? $this->rewardCap : null, $this->limits(), $this->campaignId);
    }

    protected function validateReward(): bool
    {
        $rules = $this->rewardType === 0
            ? ['rewardValue' => ['required', 'numeric', 'gt:0', 'max:100'], 'rewardCap' => ['nullable', 'integer', 'min:1']]
            : ['rewardValue' => ['required', 'integer', 'min:1']];

        $this->validate($rules + ['rewardType' => ['required', 'in:0,1']], [
            'rewardValue.required' => 'مقدار تخفیف را وارد کنید.',
            'rewardValue.gt' => 'درصد تخفیف باید بیشتر از صفر باشد.',
            'rewardValue.max' => 'درصد تخفیف حداکثر ۱۰۰ است.',
            'rewardValue.min' => 'مبلغ تخفیف باید بیشتر از صفر باشد.',
            'rewardValue.integer' => 'مبلغ تخفیف باید عدد صحیح (تومان) باشد.',
            'rewardValue.numeric' => 'درصد تخفیف باید عدد باشد.',
            'rewardCap.integer' => 'سقف تخفیف باید عدد صحیح (تومان) باشد.',
            'rewardCap.min' => 'سقف تخفیف باید بیشتر از صفر باشد.',
        ]);

        return true;
    }

    public function saveDiscount(): void
    {
        $this->authorizeEdit();
        $this->validateReward();

        DB::transaction(function () {
            // ویزارد یک پاداش قیمتی دارد؛ پاداش‌های درصدی/ثابت قبلی جایگزین می‌شوند
            CampaignReward::where('campaign_id', $this->campaignId)->whereIn('reward_type', [0, 1])->delete();

            CampaignReward::create([
                'campaign_id' => $this->campaignId,
                'reward_type' => $this->rewardType,
                'value' => $this->rewardType === 0 ? (float) $this->rewardValue : (int) $this->rewardValue,
                'max_value' => $this->rewardType === 0 && filled($this->rewardCap) ? (int) $this->rewardCap : null,
            ]);
        });

        $this->next('discount');
    }

    /*
    |--------------------------------------------------------------------------
    | ۴. محدودیت‌ها و شرایط
    |--------------------------------------------------------------------------
    */
    public function saveLimits(): void
    {
        $this->authorizeEdit();

        $rules = ['priority' => ['required', 'integer', 'min:0', 'max:1000']];
        $this->useLimit && $rules['usageLimit'] = ['required', 'integer', 'min:1'];
        $this->useCustomerLimit && $rules['usagePerCustomer'] = ['required', 'integer', 'min:1'];
        $this->useMinItem && $rules['minItemPrice'] = ['required', 'integer', 'min:1'];
        $this->useMaxItem && $rules['maxItemPrice'] = ['required', 'integer', 'min:1'];

        $this->validate($rules, [
            '*.required' => 'مقدار را وارد کنید یا این شرط را خاموش کنید.',
            '*.integer' => 'مقدار باید عدد صحیح باشد.',
            '*.min' => 'مقدار باید بیشتر از صفر باشد.',
            'priority.max' => 'اولویت حداکثر ۱۰۰۰ است.',
        ]);

        if ($this->useMinItem && $this->useMaxItem && (int) $this->maxItemPrice < (int) $this->minItemPrice) {
            $this->addError('maxItemPrice', 'حداکثر قیمت کالا باید از حداقل بیشتر باشد.');
            return;
        }

        if ($this->useLimit && $this->useCustomerLimit && (int) $this->usagePerCustomer > (int) $this->usageLimit) {
            $this->addError('usagePerCustomer', 'سقف هر مشتری نمی‌تواند از سقف کل بیشتر باشد.');
            return;
        }

        $settings = array_merge((array) $this->campaign->settings, [
            'usage_limit' => $this->useLimit ? (int) $this->usageLimit : null,
            'usage_per_customer' => $this->useCustomerLimit ? (int) $this->usagePerCustomer : null,
            'min_item_price' => $this->useMinItem ? (int) $this->minItemPrice : null,
            'max_item_price' => $this->useMaxItem ? (int) $this->maxItemPrice : null,
        ]);

        $this->campaign->update(['settings' => $settings, 'priority' => $this->priority]);
        unset($this->campaign);
        $this->next('limits');
    }

    #[Computed]
    public function legacyConditions()
    {
        return $this->campaignId ? $this->campaign->conditions()->get() : collect();
    }

    #[Computed]
    public function legacyRewards()
    {
        return $this->campaignId ? $this->campaign->rewards()->whereNotIn('reward_type', [0, 1])->get() : collect();
    }

    public function removeLegacy(): void
    {
        $this->authorizeEdit();
        $this->campaign->conditions()->delete();
        $this->campaign->rewards()->whereNotIn('reward_type', [0, 1])->delete();
        unset($this->legacyConditions, $this->legacyRewards);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'تنظیمات قدیمیِ بی‌اثر حذف شد.');
    }

    /*
    |--------------------------------------------------------------------------
    | ۵. بررسی نهایی
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function overlaps()
    {
        if (!$this->campaignId) {
            return collect();
        }

        $start = $this->campaign->getRawOriginal('start_at') ? \Carbon\Carbon::parse($this->campaign->getRawOriginal('start_at')) : null;
        $end = $this->campaign->getRawOriginal('end_at') ? \Carbon\Carbon::parse($this->campaign->getRawOriginal('end_at')) : null;

        return $this->planner()->overlappingCampaigns($this->targets(), $start, $end, $this->campaignId);
    }

    /** مواردی که مانع فعال‌سازی‌اند */
    public function blockers(): array
    {
        $campaign = $this->campaign;
        $blockers = [];

        if (!$campaign) {
            return ['ابتدا اطلاعات کمپین را ذخیره کنید.'];
        }

        if (!$campaign->targets()->exists()) {
            $blockers[] = 'محصولات کمپین انتخاب و ذخیره نشده است (مرحله ۲).';
        }

        if (!$campaign->rewards()->whereIn('reward_type', [0, 1])->where('value', '>', 0)->exists()) {
            $blockers[] = 'مقدار تخفیف ذخیره نشده است (مرحله ۳).';
        }

        if ($campaign->getRawOriginal('end_at') && \Carbon\Carbon::parse($campaign->getRawOriginal('end_at'))->isPast()) {
            $blockers[] = 'زمان پایان کمپین گذشته است (مرحله ۱).';
        }

        return $blockers;
    }

    /** تغییرات ذخیره‌نشده مرحله فعلی نسبت به دیتابیس */
    public function hasUnsaved(): bool
    {
        if (!$this->campaignId) {
            return filled($this->title);
        }

        $saved = $this->formFromCampaign($this->campaign);
        $keys = match ($this->step) {
            'info' => ['title', 'description', 'type', 'startDate', 'startTime', 'endDate', 'endTime'],
            'targets' => ['allStore', 'productIds', 'categoryIds', 'brandIds'],
            'discount' => ['rewardType', 'rewardValue', 'rewardCap'],
            'limits' => ['useLimit', 'usageLimit', 'useCustomerLimit', 'usagePerCustomer', 'useMinItem', 'minItemPrice', 'useMaxItem', 'maxItemPrice', 'priority'],
            default => [],
        };

        $normalize = fn ($value) => is_array($value)
            ? json_encode(collect($value)->map(fn ($v) => (int) $v)->unique()->sort()->values()->all())
            : (is_bool($value) ? ($value ? '1' : '0') : trim((string) ($value ?? '')));

        foreach ($keys as $key) {
            $current = $normalize($this->{$key});
            $stored = $normalize($saved[$key] ?? null);

            // عددها: 20 و 20.0 یکسان‌اند
            if (is_numeric($current) && is_numeric($stored) ? (float) $current !== (float) $stored : $current !== $stored) {
                return true;
            }
        }

        return false;
    }

    public function finish(bool $activate): void
    {
        $this->authorizeEdit();

        if ($activate && ($blockers = $this->blockers())) {
            $this->dispatch('alert', type: 'error', title: 'امکان فعال‌سازی نیست', text: implode(' ', $blockers));
            return;
        }

        $this->campaign->update(['status' => $activate ? Campaign::STATUS_ACTIVE : Campaign::STATUS_DRAFT]);
        unset($this->campaign);

        $state = $this->campaign->state;
        session()->flash('campaign-saved', $activate
            ? ($state === 'scheduled' ? 'کمپین «' . $this->campaign->title . '» زمان‌بندی شد و در زمان شروع خودکار فعال می‌شود.' : 'کمپین «' . $this->campaign->title . '» فعال شد.')
            : 'کمپین «' . $this->campaign->title . '» به‌صورت پیش‌نویس ذخیره شد.');

        $this->redirectRoute('campaign.index');
    }

    public function summary(): array
    {
        $campaign = $this->campaign;
        $preview = $this->preview;
        $rows = $preview['rows']->where('amount', '>', 0)->where('overridden', false);

        return [
            'products' => $this->affectedCount,
            'variants' => $preview['stats']['variants'],
            'reward' => $this->planner()->rewardText($this->rewardType, $this->rewardValue, $this->rewardType === 0 ? $this->rewardCap : null),
            'sample' => $rows->sortByDesc('price')->first(),
            'min_final' => $rows->min('final'),
            'max_final' => $rows->max('final'),
            'start' => $campaign?->getRawOriginal('start_at') ? verta($campaign->getRawOriginal('start_at'))->format('Y/m/d H:i') : 'از همین حالا',
            'end' => $campaign?->getRawOriginal('end_at') ? verta($campaign->getRawOriginal('end_at'))->format('Y/m/d H:i') : 'بدون پایان',
            'conditions' => array_values(array_filter([
                $this->useLimit ? 'حداکثر ' . number_format((int) $this->usageLimit) . ' سفارش در کل' : null,
                $this->useCustomerLimit ? 'هر مشتری حداکثر ' . number_format((int) $this->usagePerCustomer) . ' سفارش' : null,
                $this->useMinItem ? 'فقط کالاهای با قیمت ' . number_format((int) $this->minItemPrice) . ' تومان و بیشتر' : null,
                $this->useMaxItem ? 'فقط کالاهای با قیمت تا ' . number_format((int) $this->maxItemPrice) . ' تومان' : null,
            ])),
        ];
    }
};
?>

<div>
    @php
        $stepKeys = array_keys($this::STEPS);
        $currentIndex = array_search($step, $stepKeys, true);
        $campaign = $this->campaign;
        $readOnly = !auth()->user()->can($campaignId ? 'campaigns.edit' : 'campaigns.create');
    @endphp

    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $campaign ? 'کمپین «' . $campaign->title . '»' : 'کمپین تخفیف جدید' }}</h1>
            <div class="text-muted small">
                @if($campaign)
                    <span class="badge {{ Campaign::STATES[$campaign->state]['class'] }}">{{ Campaign::STATES[$campaign->state]['label'] }}</span>
                    @if($campaign->state === 'active')
                        <span class="text-warning ms-1"><i class="ri-error-warning-line"></i> این کمپین فعال است؛ ذخیره هر مرحله بلافاصله روی قیمت‌های سایت اعمال می‌شود.</span>
                    @endif
                @else
                    مراحل را به ترتیب کامل کنید؛ تا «فعال‌سازی» در مرحله آخر، کمپین روی قیمت‌ها اثری ندارد.
                @endif
            </div>
        </div>
        <a href="{{ route('campaign.index') }}" class="btn btn-light btn-wave"><i class="ri-arrow-right-line align-middle"></i> لیست کمپین‌ها</a>
    </div>

    {{-- نوار مراحل --}}
    <div class="card custom-card">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap gap-2">
                @foreach($this::STEPS as $key => $label)
                    @php $i = $loop->index; @endphp
                    <button type="button" wire:click="goTo('{{ $key }}')" @disabled(!$campaignId && $key !== 'info')
                            class="btn flex-fill text-start {{ $step === $key ? 'btn-primary' : ($i < $currentIndex ? 'btn-success-light' : 'btn-light') }}">
                        <span class="fw-semibold">
                            @if($i < $currentIndex)<i class="ri-check-line"></i>@else{{ $i + 1 }}.@endif
                            {{ $label }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @if($step !== 'review' && $this->hasUnsaved())
        <div class="alert alert-warning py-2 small"><i class="ri-save-line"></i> تغییرات این مرحله هنوز ذخیره نشده است؛ «ذخیره و ادامه» را بزنید.</div>
    @endif

    {{-- ===================== ۱. اطلاعات کمپین ===================== --}}
    @if($step === 'info')
        <div class="card custom-card">
            <div class="card-header"><div class="card-title">۱. اطلاعات کمپین</div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">نام کمپین <span class="text-danger">*</span></label>
                        <input type="text" wire:model.blur="title" class="form-control @error('title') is-invalid @enderror" placeholder="مثلاً: حراج پاییزه کیف‌های چرم">
                        <div class="form-text">در بخش کمپین صفحه اصلی و گزارش‌ها نمایش داده می‌شود.</div>
                        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">نوع نمایش</label>
                        <select wire:model="type" class="form-select @error('type') is-invalid @enderror">
                            <option value="0">کمپین تخفیف</option>
                            <option value="1">فروش ویژه (شگفت‌انگیز)</option>
                        </select>
                        <div class="form-text">فقط نشان و ظاهر بخش کمپین را تغییر می‌دهد؛ محاسبه تخفیف یکسان است.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">توضیح کمپین</label>
                        <textarea wire:model.blur="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="یک یا دو جمله برای معرفی کمپین به مشتری (اختیاری)"></textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">شروع</label>
                        <div class="input-group">
                            <input type="text" data-jdp wire:model.blur="startDate" class="form-control @error('startDate') is-invalid @enderror" placeholder="خالی = از زمان فعال‌سازی" dir="ltr">
                            <input type="time" wire:model.blur="startTime" class="form-control" style="max-width: 120px" dir="ltr">
                        </div>
                        @error('startDate') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">پایان</label>
                        <div class="input-group">
                            <input type="text" data-jdp wire:model.blur="endDate" class="form-control @error('endDate') is-invalid @enderror" placeholder="خالی = بدون پایان" dir="ltr">
                            <input type="time" wire:model.blur="endTime" class="form-control" style="max-width: 120px" dir="ltr">
                        </div>
                        <div class="form-text">برای «فروش ویژه» شمارش معکوس بر اساس همین زمان است.</div>
                        @error('endDate') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
            @unless($readOnly)
                <div class="card-footer d-flex justify-content-end">
                    <button type="button" class="btn btn-primary" wire:click="saveInfo" wire:loading.attr="disabled" wire:target="saveInfo">ذخیره و ادامه <i class="ri-arrow-left-line"></i></button>
                </div>
            @endunless
        </div>
    @endif

    {{-- ===================== ۲. انتخاب محصولات ===================== --}}
    @if($step === 'targets')
        <div class="row">
            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div>
                            <div class="card-title">۲. تخفیف روی چه محصولاتی اعمال شود؟</div>
                            <div class="small text-muted mt-1">تخفیف روی همه تنوع‌های (رنگ، سایز، ...) محصولات انتخاب‌شده اعمال می‌شود.</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="all-store" wire:model.live="allStore">
                            <label class="form-check-label fw-semibold" for="all-store">کل فروشگاه</label>
                        </div>
                    </div>
                    <div class="card-body">
                        @error('targets') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

                        @if($allStore)
                            <div class="alert alert-info mb-0">تخفیف روی <strong>همه محصولات فعال فروشگاه</strong> ({{ number_format($this->affectedCount) }} محصول) اعمال می‌شود.</div>
                        @else
                            <ul class="nav nav-tabs mb-3">
                                @foreach(['products' => 'محصولات', 'categories' => 'دسته‌بندی‌ها', 'brands' => 'برندها'] as $key => $label)
                                    <li class="nav-item"><button type="button" class="nav-link {{ $targetTab === $key ? 'active' : '' }}" wire:click="$set('targetTab', '{{ $key }}')">{{ $label }}</button></li>
                                @endforeach
                            </ul>

                            @if($targetTab === 'products')
                                <div class="row g-2 mb-3">
                                    <div class="col-md-5"><input type="text" wire:model.live.debounce.400ms="productSearch" class="form-control" placeholder="جستجوی نام محصول یا SKU"></div>
                                    <div class="col-md-3">
                                        <select wire:model.live="filterCategory" class="form-select">
                                            <option value="">همه دسته‌ها</option>
                                            @foreach($this->categories as $id => $title)<option value="{{ $id }}">{{ $title }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select wire:model.live="filterBrand" class="form-select">
                                            <option value="">همه برندها</option>
                                            @foreach($this->brands as $id => $title)<option value="{{ $id }}">{{ $title }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2"><button type="button" class="btn btn-outline-primary w-100" wire:click="addAllResults" title="افزودن همه نتایج این جستجو">همه نتایج</button></div>
                                </div>

                                <div class="list-group">
                                    @forelse($this->searchResults as $product)
                                        @php $checked = in_array($product->id, $productIds, true); @endphp
                                        <label class="list-group-item d-flex align-items-center gap-3 {{ $checked ? 'bg-primary-transparent' : '' }}" wire:key="pick-{{ $product->id }}">
                                            <input class="form-check-input m-0" type="checkbox" @checked($checked) wire:click="toggleProduct({{ $product->id }})">
                                            @if($product->featured_image_url)
                                                <img src="{{ $product->featured_image_url }}" width="36" height="36" class="rounded" style="object-fit: cover" alt="">
                                            @endif
                                            <span class="flex-fill">
                                                <span class="d-block fw-semibold small">{{ $product->title }}</span>
                                                <span class="small text-muted">{{ $product->brand?->title }}</span>
                                            </span>
                                            <span class="small text-muted">{{ $product->variants_min_price ? 'از ' . number_format($product->variants_min_price) . ' تومان' : 'بدون قیمت' }}</span>
                                        </label>
                                    @empty
                                        <div class="text-center text-muted py-4">محصولی یافت نشد.</div>
                                    @endforelse
                                </div>
                                @if($this->searchResults->lastPage() > 1)
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <button type="button" class="btn btn-sm btn-light" wire:click="setPage({{ $productPage - 1 }})" @disabled($productPage <= 1)>قبلی</button>
                                        <span class="small text-muted">صفحه {{ $productPage }} از {{ $this->searchResults->lastPage() }}</span>
                                        <button type="button" class="btn btn-sm btn-light" wire:click="setPage({{ $productPage + 1 }})" @disabled($productPage >= $this->searchResults->lastPage())>بعدی</button>
                                    </div>
                                @endif
                            @elseif($targetTab === 'categories')
                                <div class="form-text mb-2">همه محصولات این دسته‌ها (حتی محصولاتی که بعداً اضافه می‌شوند) مشمول تخفیف می‌شوند.</div>
                                <div class="row g-2">
                                    @foreach($this->categories as $id => $title)
                                        <div class="col-md-4"><label class="form-check"><input type="checkbox" class="form-check-input" value="{{ $id }}" wire:model.live="categoryIds"> <span class="form-check-label">{{ $title }}</span></label></div>
                                    @endforeach
                                </div>
                            @else
                                <div class="form-text mb-2">همه محصولات این برندها مشمول تخفیف می‌شوند.</div>
                                <div class="row g-2">
                                    @foreach($this->brands as $id => $title)
                                        <div class="col-md-4"><label class="form-check"><input type="checkbox" class="form-check-input" value="{{ $id }}" wire:model.live="brandIds"> <span class="form-check-label">{{ $title }}</span></label></div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- انتخاب‌ها --}}
            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">انتخاب‌شده‌ها</div>
                        @if(!$allStore && ($productIds || $categoryIds || $brandIds))
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="clearTargets">حذف همه</button>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="text-center p-3 rounded bg-primary-transparent mb-3">
                            <div class="fs-3 fw-bold">{{ number_format($this->affectedCount) }}</div>
                            <div class="small">محصول مشمول این کمپین</div>
                        </div>
                        @unless($allStore)
                            @foreach([['productIds', 'محصولات', $this->selectedProducts->pluck('title', 'id')], ['categoryIds', 'دسته‌بندی‌ها', $this->categories->only($categoryIds)], ['brandIds', 'برندها', $this->brands->only($brandIds)]] as [$list, $label, $items])
                                @if(count($items))
                                    <div class="small fw-semibold text-muted mb-1">{{ $label }} ({{ count($items) }})</div>
                                    <div class="d-flex flex-wrap gap-1 mb-3" style="max-height: 180px; overflow-y: auto">
                                        @foreach($items as $id => $title)
                                            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1" wire:key="sel-{{ $list }}-{{ $id }}">
                                                {{ \Illuminate\Support\Str::limit($title, 28) }}
                                                <button type="button" class="btn-close" style="font-size: .5rem" wire:click="removeTarget('{{ $list }}', {{ $id }})" aria-label="حذف"></button>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        @endunless
                    </div>
                </div>
            </div>
        </div>
        @unless($readOnly)
            <div class="d-flex justify-content-between mb-4">
                <button type="button" class="btn btn-light" wire:click="goTo('info')"><i class="ri-arrow-right-line"></i> مرحله قبل</button>
                <button type="button" class="btn btn-primary" wire:click="saveTargets" wire:loading.attr="disabled" wire:target="saveTargets">ذخیره و ادامه <i class="ri-arrow-left-line"></i></button>
            </div>
        @endunless
    @endif

    {{-- ===================== ۳. تنظیم تخفیف ===================== --}}
    @if($step === 'discount' || $step === 'limits' || $step === 'review')
        @php
            $preview = $this->preview;
            $stats = $preview['stats'];
        @endphp
    @endif

    @if($step === 'discount')
        <div class="card custom-card">
            <div class="card-header"><div class="card-title">۳. چقدر تخفیف داده شود؟</div></div>
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-md-4">
                        <label class="form-label">نوع تخفیف</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" id="rt-0" value="0" wire:model.live="rewardType">
                            <label class="btn btn-outline-primary" for="rt-0">درصدی ٪</label>
                            <input type="radio" class="btn-check" id="rt-1" value="1" wire:model.live="rewardType">
                            <label class="btn btn-outline-primary" for="rt-1">مبلغ ثابت</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ $rewardType === 0 ? 'درصد تخفیف' : 'مبلغ تخفیف (تومان)' }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" min="0" step="{{ $rewardType === 0 ? '0.5' : '1000' }}" wire:model.live.debounce.400ms="rewardValue" class="form-control @error('rewardValue') is-invalid @enderror" dir="ltr">
                            <span class="input-group-text">{{ $rewardType === 0 ? '٪' : 'تومان' }}</span>
                        </div>
                        <div class="form-text">{{ $rewardType === 0 ? 'از قیمت هر کالا کم می‌شود.' : 'از قیمت هر کالا کم می‌شود؛ بیشتر از قیمت کالا کم نمی‌شود.' }}</div>
                        @error('rewardValue') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    @if($rewardType === 0)
                        <div class="col-md-4">
                            <label class="form-label">سقف تخفیف هر کالا (اختیاری)</label>
                            <div class="input-group">
                                <input type="number" min="0" step="1000" wire:model.live.debounce.400ms="rewardCap" class="form-control @error('rewardCap') is-invalid @enderror" dir="ltr" placeholder="بدون سقف">
                                <span class="input-group-text">تومان</span>
                            </div>
                            <div class="form-text">مثلاً ۲۰٪ تا حداکثر ۵۰۰٬۰۰۰ تومان برای هر کالا.</div>
                            @error('rewardCap') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>

                {{-- هشدارها --}}
                @if($rewardType === 0 && (float) $rewardValue >= 70)
                    <div class="alert alert-warning mt-3 mb-0">⚠️ تخفیف {{ $rewardValue }}٪ بسیار زیاد است؛ مقدار را دوباره بررسی کنید.</div>
                @endif
                @if($stats['loss'])
                    <div class="alert alert-danger mt-3 mb-0">⚠️ این تخفیف ممکن است باعث فروش محصول با ضرر شود: قیمت نهایی <strong>{{ $stats['loss'] }}</strong> تنوع از قیمت خرید آن کمتر می‌شود.</div>
                @endif
                @if($stats['free'])
                    <div class="alert alert-danger mt-3 mb-0">⚠️ قیمت نهایی {{ $stats['free'] }} تنوع صفر (رایگان) می‌شود.</div>
                @endif
                @if($stats['overridden'])
                    <div class="alert alert-info mt-3 mb-0"><i class="ri-information-line"></i> روی {{ $stats['overridden'] }} تنوع تخفیف بزرگ‌تری از کمپین/تخفیف دیگری فعال است و همان اعمال می‌شود (تخفیف‌ها جمع نمی‌شوند؛ فقط بزرگ‌ترین تخفیف اعمال می‌شود).</div>
                @endif
            </div>
        </div>

        @include('pages.dashboard.campaign.partials.preview-table', ['preview' => $preview])

        @unless($readOnly)
            <div class="d-flex justify-content-between mb-4">
                <button type="button" class="btn btn-light" wire:click="goTo('targets')"><i class="ri-arrow-right-line"></i> مرحله قبل</button>
                <button type="button" class="btn btn-primary" wire:click="saveDiscount" wire:loading.attr="disabled" wire:target="saveDiscount">ذخیره و ادامه <i class="ri-arrow-left-line"></i></button>
            </div>
        @endunless
    @endif

    {{-- ===================== ۴. محدودیت‌ها ===================== --}}
    @if($step === 'limits')
        <div class="card custom-card">
            <div class="card-header">
                <div>
                    <div class="card-title">۴. محدودیت‌ها و شرایط</div>
                    <div class="small text-muted mt-1">همه شرایط اختیاری‌اند؛ هر شرط را با کلید کنارش روشن یا خاموش کنید.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach([
                        ['useLimit', 'usageLimit', 'محدودیت تعداد کل استفاده', 'سفارش', 'پس از این تعداد سفارش پرداخت‌شده، تخفیف کمپین خودکار متوقف می‌شود.'],
                        ['useCustomerLimit', 'usagePerCustomer', 'محدودیت برای هر مشتری', 'سفارش', 'هر مشتری حداکثر این تعداد سفارش با قیمت کمپین ثبت می‌کند.'],
                        ['useMinItem', 'minItemPrice', 'حداقل قیمت کالا', 'تومان', 'فقط کالاهایی که قیمتشان این مقدار یا بیشتر است تخفیف می‌گیرند.'],
                        ['useMaxItem', 'maxItemPrice', 'حداکثر قیمت کالا', 'تومان', 'فقط کالاهایی که قیمتشان تا این مقدار است تخفیف می‌گیرند.'],
                    ] as [$toggle, $field, $label, $unit, $help])
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 {{ $this->{$toggle} ? 'border-primary' : '' }}">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="{{ $toggle }}" wire:model.live="{{ $toggle }}">
                                    <label class="form-check-label fw-semibold" for="{{ $toggle }}">{{ $label }}</label>
                                </div>
                                <div class="small text-muted mb-2">{{ $help }}</div>
                                @if($this->{$toggle})
                                    <div class="input-group input-group-sm">
                                        <input type="number" min="1" wire:model.live.debounce.400ms="{{ $field }}" class="form-control @error($field) is-invalid @enderror" dir="ltr">
                                        <span class="input-group-text">{{ $unit }}</span>
                                    </div>
                                    @error($field) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="ri-information-line"></i> تخفیف کمپین روی <strong>قیمت هر کالا</strong> اعمال می‌شود و در صفحه محصول دیده می‌شود. برای تخفیفی که به <strong>مبلغ کل سبد خرید</strong> وابسته است (مثلاً «خرید بالای ۲ میلیون»)، از بخش «کدهای تخفیف» استفاده کنید.
                </div>

                <details class="mt-3" @if($priority) open @endif>
                    <summary class="fw-semibold small">تنظیمات پیشرفته</summary>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label small">اولویت کمپین</label>
                            <input type="number" min="0" max="1000" wire:model.blur="priority" class="form-control form-control-sm @error('priority') is-invalid @enderror" dir="ltr">
                            <div class="form-text">فقط وقتی دو کمپین تخفیف <strong>برابر</strong> روی یک کالا دارند، کمپین با اولویت بالاتر انتخاب می‌شود.</div>
                            @error('priority') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </details>

                @if($this->legacyConditions->isNotEmpty() || $this->legacyRewards->isNotEmpty())
                    <div class="alert alert-warning mt-3 mb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>
                            این کمپین تنظیمات قدیمی دارد که موتور قیمت هرگز آن‌ها را اعمال نمی‌کرد:
                            {{ $this->legacyConditions->map(fn ($c) => $c->condition_type_name . ' ' . $c->operator_name . ' ' . $c->value)->merge($this->legacyRewards->map(fn ($r) => $r->reward_type_name))->implode('، ') }}
                        </span>
                        @unless($readOnly)
                            <button type="button" class="btn btn-sm btn-warning" wire:click="removeLegacy" wire:confirm="تنظیمات قدیمیِ بی‌اثر حذف شوند؟">حذف آن‌ها</button>
                        @endunless
                    </div>
                @endif
            </div>
        </div>

        @if($stats['excluded'])
            <div class="alert alert-info"><i class="ri-information-line"></i> با شرایط قیمت کالا، {{ $stats['excluded'] }} تنوع از تخفیف خارج می‌شوند.</div>
        @endif

        @unless($readOnly)
            <div class="d-flex justify-content-between mb-4">
                <button type="button" class="btn btn-light" wire:click="goTo('discount')"><i class="ri-arrow-right-line"></i> مرحله قبل</button>
                <button type="button" class="btn btn-primary" wire:click="saveLimits" wire:loading.attr="disabled" wire:target="saveLimits">ذخیره و ادامه <i class="ri-arrow-left-line"></i></button>
            </div>
        @endunless
    @endif

    {{-- ===================== ۵. بررسی نهایی ===================== --}}
    @if($step === 'review')
        @php
            $summary = $this->summary();
            $blockers = $this->blockers();
        @endphp
        <div class="row">
            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">۵. بررسی نهایی</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach([
                                ['نام کمپین', $campaign?->title, 'info'],
                                ['نوع نمایش', $type === 1 ? 'فروش ویژه (شگفت‌انگیز)' : 'کمپین تخفیف', 'info'],
                                ['بازه زمانی', $summary['start'] . ' تا ' . $summary['end'], 'info'],
                                ['محصولات', $allStore ? 'کل فروشگاه (' . number_format($summary['products']) . ' محصول)' : number_format($summary['products']) . ' محصول (' . number_format($summary['variants']) . ' تنوع)', 'targets'],
                                ['تخفیف', $summary['reward'], 'discount'],
                                ['شرایط', $summary['conditions'] ? implode(' · ', $summary['conditions']) : 'بدون شرط', 'limits'],
                            ] as [$label, $value, $editStep])
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="d-flex justify-content-between">
                                            <span class="small text-muted">{{ $label }}</span>
                                            @unless($readOnly)<button type="button" class="btn btn-sm btn-link p-0" wire:click="goTo('{{ $editStep }}')">ویرایش</button>@endunless
                                        </div>
                                        <div class="fw-semibold mt-1">{{ $value ?: '—' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($summary['sample'])
                            @php $s = $summary['sample']; @endphp
                            <div class="row g-2 text-center mt-3">
                                <div class="col-4"><div class="p-3 rounded bg-light"><div class="small text-muted">قیمت اصلی</div><div class="fw-bold">{{ number_format($s['price']) }}</div></div></div>
                                <div class="col-4"><div class="p-3 rounded bg-danger-transparent"><div class="small text-muted">تخفیف</div><div class="fw-bold">{{ $s['percent'] }}٪ ({{ number_format($s['amount']) }})</div></div></div>
                                <div class="col-4"><div class="p-3 rounded bg-success-transparent"><div class="small text-muted">قیمت نهایی</div><div class="fw-bold">{{ number_format($s['final']) }}</div></div></div>
                                <div class="col-12 small text-muted">نمونه: {{ $s['product'] }} {{ $s['variant'] ? '(' . $s['variant'] . ')' : '' }} — جزئیات همه کالاها در جدول پایین.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">بررسی‌ها</div></div>
                    <div class="card-body small">
                        @forelse($blockers as $blocker)
                            <div class="alert alert-danger py-2 mb-2"><i class="ri-close-circle-line"></i> {{ $blocker }}</div>
                        @empty
                            <div class="alert alert-success py-2 mb-2"><i class="ri-checkbox-circle-line"></i> اطلاعات کمپین کامل است.</div>
                        @endforelse
                        @if($stats['loss'])
                            <div class="alert alert-danger py-2 mb-2">⚠️ این تخفیف ممکن است باعث فروش {{ $stats['loss'] }} تنوع با ضرر شود (کمتر از قیمت خرید).</div>
                        @endif
                        @if($stats['overridden'])
                            <div class="alert alert-info py-2 mb-2">روی {{ $stats['overridden'] }} تنوع تخفیف بزرگ‌تری فعال است؛ این کمپین روی آن‌ها اعمال نمی‌شود.</div>
                        @endif
                        @foreach($this->overlaps as $overlap)
                            <div class="alert alert-warning py-2 mb-2">⚠️ تداخل با کمپین «{{ $overlap['campaign']->title }}» روی {{ $overlap['products'] }} محصول در همین بازه زمانی؛ برای هر کالا فقط تخفیف بزرگ‌تر اعمال می‌شود.</div>
                        @endforeach
                        @if(!$stats['loss'] && !$stats['overridden'] && $this->overlaps->isEmpty())
                            <div class="text-muted">تداخل یا هشدار دیگری یافت نشد.</div>
                        @endif
                    </div>
                    @unless($readOnly)
                        <div class="card-footer d-grid gap-2">
                            <button type="button" class="btn btn-success" wire:click="finish(true)" wire:loading.attr="disabled" @disabled($blockers)>
                                <i class="ri-flashlight-line"></i> {{ $campaign && $campaign->getRawOriginal('start_at') && \Carbon\Carbon::parse($campaign->getRawOriginal('start_at'))->isFuture() ? 'زمان‌بندی و فعال‌سازی کمپین' : 'فعال‌سازی کمپین' }}
                            </button>
                            <button type="button" class="btn btn-light" wire:click="finish(false)" wire:loading.attr="disabled">ذخیره پیش‌نویس</button>
                        </div>
                    @endunless
                </div>
            </div>
        </div>

        @include('pages.dashboard.campaign.partials.preview-table', ['preview' => $preview])
    @endif

    @push('scripts')
        <script src="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.js') }}"></script>
        <script>jalaliDatepicker.startWatch();</script>
    @endpush
</div>
