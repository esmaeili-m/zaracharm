<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Specification;
use App\Services\Catalog\CategoryFilterService;
use Illuminate\Support\Facades\DB;

/*
 * ویژگی‌ها و فیلترهای دسته‌بندی
 * منبع واحد فیلترهای صفحه دسته‌بندی: ویژگی‌هایی که اینجا برای دسته تعریف می‌شوند (به زیر‌دسته‌ها ارث می‌رسد).
 * تعریف خود ویژگی‌ها (نام، مقادیر) در «مشخصات فنی» و «ویژگی‌ها» است؛ اینجا فقط انتخاب، ترتیب و نحوه نمایش.
 */
new class extends Component
{
    public Category $category;
    public ?int $addSpecId = null;
    public ?int $addOptionId = null;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Category $category)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
        $this->category = $category;
    }

    protected function authorizeEdit(): void
    {
        abort_if(!auth()->user()->can('categories.edit'), 403);
    }

    protected function service(): CategoryFilterService
    {
        return app(CategoryFilterService::class);
    }

    #[Computed]
    public function rows()
    {
        return CategoryAttribute::with(['specification', 'option.values' => fn ($q) => $q->orderBy('sort')])
            ->where('category_id', $this->category->id)
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->filter(fn ($row) => $row->attribute());
    }

    /** دسته‌ای که فیلترها فعلاً از آن ارث می‌رسد (وقتی خود دسته تعریفی ندارد) */
    #[Computed]
    public function inheritedFrom(): ?Category
    {
        if ($this->rows->isNotEmpty()) {
            return null;
        }

        foreach ($this->category->ancestorIds() as $id) {
            if (CategoryAttribute::where('category_id', $id)->exists()) {
                return Category::find($id);
            }
        }

        return null;
    }

    #[Computed]
    public function availableSpecs()
    {
        $used = $this->rows->where('attribute_type', CategoryAttribute::SPEC)->pluck('attribute_id');

        return Specification::where('status', true)->whereNotIn('id', $used)->orderBy('title')->get(['id', 'title', 'type']);
    }

    #[Computed]
    public function availableOptions()
    {
        $used = $this->rows->where('attribute_type', CategoryAttribute::OPTION)->pluck('attribute_id');

        return Option::where('status', true)->whereNotIn('id', $used)->orderBy('title')->get(['id', 'title']);
    }

    /** پیش‌نمایش فیلترهای نهایی صفحه دسته (فقط ویژگی‌هایی که محصولات این دسته مقدار دارند) */
    #[Computed]
    public function preview()
    {
        $ids = $this->category->getAllDescendantIds()->push($this->category->id)->unique()->values()->all();
        $index = $this->service()->index($ids);

        return ['products' => count($index), 'definitions' => $this->service()->definitions($this->category, $index)];
    }

    protected function refresh(): void
    {
        unset($this->rows, $this->inheritedFrom, $this->availableSpecs, $this->availableOptions, $this->preview);
    }

    public function add(string $type): void
    {
        $this->authorizeEdit();

        $id = $type === CategoryAttribute::SPEC ? $this->addSpecId : $this->addOptionId;
        $exists = $type === CategoryAttribute::SPEC ? Specification::whereKey($id)->exists() : Option::whereKey($id)->exists();

        if (!$id || !$exists) {
            return;
        }

        CategoryAttribute::firstOrCreate(
            ['category_id' => $this->category->id, 'attribute_type' => $type, 'attribute_id' => $id],
            ['is_filter' => true, 'sort' => (int) CategoryAttribute::where('category_id', $this->category->id)->max('sort') + 1]
        );

        $this->reset(['addSpecId', 'addOptionId']);
        $this->refresh();
    }

    /** شروع از تعریف‌های دسته والد (برای سفارشی‌سازی همین دسته) */
    public function copyFromParent(): void
    {
        $this->authorizeEdit();

        if (!$parent = $this->inheritedFrom) {
            return;
        }

        DB::transaction(function () use ($parent) {
            foreach (CategoryAttribute::where('category_id', $parent->id)->orderBy('sort')->get() as $row) {
                CategoryAttribute::firstOrCreate(
                    ['category_id' => $this->category->id, 'attribute_type' => $row->attribute_type, 'attribute_id' => $row->attribute_id],
                    ['is_filter' => $row->is_filter, 'sort' => $row->sort]
                );
            }
        });

        $this->refresh();
    }

    public function toggleFilter(int $id): void
    {
        $this->authorizeEdit();
        $row = CategoryAttribute::where('category_id', $this->category->id)->findOrFail($id);
        $row->update(['is_filter' => !$row->is_filter]);
        $this->refresh();
    }

    public function move(int $id, string $direction): void
    {
        $this->authorizeEdit();

        $list = CategoryAttribute::where('category_id', $this->category->id)->orderBy('sort')->orderBy('id')->get()->values();
        $index = $list->search(fn ($r) => $r->id === $id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || !isset($list[$swap])) {
            return;
        }

        $items = $list->all();
        [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];

        DB::transaction(function () use ($items) {
            foreach ($items as $i => $item) {
                $item->update(['sort' => $i + 1]);
            }
        });

        $this->refresh();
    }

    public function remove(int $id): void
    {
        $this->authorizeEdit();
        CategoryAttribute::where('category_id', $this->category->id)->whereKey($id)->delete();
        $this->refresh();
    }

    /** نحوه نمایش فیلتر — روی خود ویژگی ذخیره می‌شود (در همه دسته‌ها یکسان) */
    public function setDisplay(string $type, int $attributeId, ?string $value): void
    {
        $this->authorizeEdit();

        $allowed = array_keys(CategoryFilterService::DISPLAY_TYPES[$type] ?? []);
        $value = in_array($value, $allowed, true) ? $value : null;

        if ($type === CategoryAttribute::SPEC) {
            Specification::whereKey($attributeId)->first()?->update(['filter_type' => $value]);
        } else {
            Option::whereKey($attributeId)->first()?->update(['display_type' => $value]);
        }

        \Illuminate\Support\Facades\Cache::forever('catalog-version', microtime(true));
        $this->refresh();
    }

    public function setColor(int $valueId, ?string $hex): void
    {
        $this->authorizeEdit();

        $hex = $hex && preg_match('/^#[0-9a-f]{6}$/i', $hex) ? strtolower($hex) : null;
        OptionValue::whereKey($valueId)->first()?->update(['color_code' => $hex]);
        $this->refresh();
    }

    public function displayOptions(string $type, $attribute): array
    {
        if ($type === CategoryAttribute::OPTION) {
            return CategoryFilterService::DISPLAY_TYPES['option'];
        }

        $all = CategoryFilterService::DISPLAY_TYPES['spec'];

        return match ((int) $attribute->type) {
            \App\Enums\SpecificationType::Number->value, \App\Enums\SpecificationType::Decimal->value => array_intersect_key($all, array_flip(['range', 'checkbox', 'radio', 'select'])),
            \App\Enums\SpecificationType::Boolean->value => array_intersect_key($all, array_flip(['toggle'])),
            \App\Enums\SpecificationType::Text->value => array_intersect_key($all, array_flip(['checkbox', 'radio', 'select'])),
            default => [],
        };
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">ویژگی‌ها و فیلترهای «{{ $category->title }}»</h1>
            <div class="text-muted small">ویژگی‌هایی که اینجا تعریف می‌کنید به‌صورت خودکار فیلترهای صفحه این دسته (و زیر‌دسته‌هایش) می‌شوند.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('categories.show', $category->slug) }}" target="_blank" class="btn btn-light btn-wave"><i class="ri-external-link-line"></i> مشاهده صفحه دسته</a>
            <a href="{{ route('categories.index') }}" class="btn btn-light btn-wave"><i class="ri-arrow-right-line"></i> دسته‌بندی‌ها</a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">ویژگی‌های این دسته</div>
                    <span class="small text-muted">ترتیب = ترتیب نمایش فیلترها</span>
                </div>
                <div class="card-body">
                    @if($this->inheritedFrom)
                        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span>این دسته تعریف جداگانه ندارد و فیلترها را از «{{ $this->inheritedFrom->title }}» ارث می‌برد.</span>
                            @can('categories.edit')
                                <button type="button" class="btn btn-sm btn-info" wire:click="copyFromParent">کپی و سفارشی‌سازی برای این دسته</button>
                            @endcan
                        </div>
                    @elseif($this->rows->isEmpty())
                        <div class="alert alert-light border">
                            هنوز ویژگی‌ای تعریف نشده است؛ فعلاً فیلترها خودکار از «مشخصات فنیِ قابل فیلتر» و «ویژگی‌های قیمت‌ساز» محصولات این دسته ساخته می‌شوند.
                        </div>
                    @endif

                    @foreach($this->rows as $row)
                        @php
                            $attribute = $row->attribute();
                            $isSpec = $row->attribute_type === CategoryAttribute::SPEC;
                            $current = $isSpec ? ($attribute->filter_type ?? '') : ($attribute->display_type ?? '');
                            $choices = $this->displayOptions($row->attribute_type, $attribute);
                            $effective = $isSpec ? app(CategoryFilterService::class)->specDisplay($attribute) : app(CategoryFilterService::class)->optionDisplay($attribute);
                        @endphp
                        <div class="border rounded p-3 mb-2 {{ $row->is_filter ? '' : 'opacity-75' }}" wire:key="ca-{{ $row->id }}">
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <div class="btn-group-vertical btn-group-sm">
                                    <button type="button" class="btn btn-light py-0" wire:click="move({{ $row->id }}, 'up')" @disabled($loop->first)><i class="ri-arrow-up-s-line"></i></button>
                                    <button type="button" class="btn btn-light py-0" wire:click="move({{ $row->id }}, 'down')" @disabled($loop->last)><i class="ri-arrow-down-s-line"></i></button>
                                </div>
                                <div class="flex-fill">
                                    <div class="fw-semibold">{{ $attribute->title }}</div>
                                    <span class="badge {{ $isSpec ? 'bg-info-transparent' : 'bg-primary-transparent' }}">{{ $isSpec ? 'مشخصه فنی' : 'ویژگی قیمت‌ساز (تنوع)' }}</span>
                                    @if($isSpec && !$choices)
                                        <span class="badge bg-warning-transparent">نوع تاریخ قابل فیلتر نیست</span>
                                    @endif
                                </div>
                                @if($choices)
                                    <div style="min-width: 190px">
                                        <select class="form-select form-select-sm" wire:change="setDisplay('{{ $row->attribute_type }}', {{ $attribute->id }}, $event.target.value || null)" @cannot('categories.edit') disabled @endcannot>
                                            <option value="" @selected($current === '')>خودکار ({{ $choices[$effective] ?? $effective }})</option>
                                            @foreach($choices as $value => $label)
                                                <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">روی همه دسته‌ها اعمال می‌شود.</div>
                                    </div>
                                @endif
                                @can('categories.edit')
                                    <div class="form-check form-switch m-0" title="نمایش به‌عنوان فیلتر">
                                        <input class="form-check-input" type="checkbox" id="ca-f-{{ $row->id }}" @checked($row->is_filter) wire:click="toggleFilter({{ $row->id }})">
                                        <label class="form-check-label small" for="ca-f-{{ $row->id }}">فیلتر</label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light text-danger" wire:click="remove({{ $row->id }})" wire:confirm="«{{ $attribute->title }}» از ویژگی‌های این دسته حذف شود؟"><i class="ri-delete-bin-5-line"></i></button>
                                @endcan
                            </div>

                            {{-- رنگ مقادیر برای انتخابگر رنگ --}}
                            @if(!$isSpec && $effective === 'color')
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    @foreach($attribute->values as $value)
                                        @php $hex = app(CategoryFilterService::class)->colorFor($value); @endphp
                                        <label class="d-flex align-items-center gap-1 border rounded px-2 py-1 small" wire:key="cv-{{ $value->id }}">
                                            <input type="color" value="{{ $hex ?? '#cccccc' }}" class="form-control form-control-color p-0 border-0" style="width: 24px; height: 24px"
                                                   wire:change="setColor({{ $value->id }}, $event.target.value)" @cannot('categories.edit') disabled @endcannot>
                                            {{ $value->title }}
                                            @unless($value->color_code)<span class="text-muted" title="رنگ از روی نام حدس زده شده است">*</span>@endunless
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @can('categories.edit')
                    <div class="card-footer">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">افزودن مشخصه فنی <span class="text-muted">(جنس، حافظه، ...)</span></label>
                                <div class="input-group input-group-sm">
                                    <select wire:model="addSpecId" class="form-select">
                                        <option value="">انتخاب کنید</option>
                                        @foreach($this->availableSpecs as $spec)
                                            <option value="{{ $spec->id }}">{{ $spec->title }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-primary" wire:click="add('spec')">افزودن</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">افزودن ویژگی قیمت‌ساز <span class="text-muted">(رنگ، سایز، ...)</span></label>
                                <div class="input-group input-group-sm">
                                    <select wire:model="addOptionId" class="form-select">
                                        <option value="">انتخاب کنید</option>
                                        @foreach($this->availableOptions as $option)
                                            <option value="{{ $option->id }}">{{ $option->title }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-primary" wire:click="add('option')">افزودن</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-text mt-2">ویژگی جدید را از منوهای «مشخصات فنی» یا «ویژگی‌ها» تعریف کنید؛ مقدار هر محصول در «مدیریت محصول» ثبت می‌شود.</div>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card custom-card">
                <div class="card-header"><div class="card-title">پیش‌نمایش فیلترهای صفحه</div></div>
                <div class="card-body small">
                    <div class="text-muted mb-3">{{ number_format($this->preview['products']) }} محصول فعال در این دسته و زیر‌دسته‌ها</div>
                    <div class="mb-2"><span class="badge bg-light text-dark">ثابت</span> قیمت · وضعیت کالا · امتیاز · برند</div>
                    @forelse($this->preview['definitions'] as $definition)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>{{ $definition['title'] }}</span>
                            <span class="text-muted">{{ (CategoryFilterService::DISPLAY_TYPES[$definition['kind']][$definition['display']] ?? $definition['display']) }}</span>
                        </div>
                    @empty
                        <div class="text-muted">فیلتر ویژگی‌ای نمایش داده نمی‌شود (محصولات این دسته برای ویژگی‌های انتخاب‌شده مقداری ندارند).</div>
                    @endforelse
                    <div class="form-text mt-3">ویژگی‌ای که هیچ محصولی از این دسته برایش مقدار ندارد، در صفحه نمایش داده نمی‌شود.</div>
                </div>
            </div>
        </div>
    </div>
</div>
