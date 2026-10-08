<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Enums\SpecificationType;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\ProductVariantOptionValue;
use App\Models\Specification;
use App\Services\Catalog\VariantCodeGenerator;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

/*
 * مدیریت مرحله‌ای محصول:
 *   ۱. مشخصات فنی        (فقط نمایشی؛ روی قیمت اثری ندارد)
 *   ۲. ویژگی‌های قیمت‌ساز  (رنگ، سایز، ... ؛ هر ترکیب = یک تنوع با قیمت و موجودی جدا)
 *   ۳. قیمت، SKU و بارکد  (برای هر تنوع)
 *   ۴. موجودی و انبار     (برای هر تنوع در هر انبار)
 *
 * داده هر مرحله در آرایه جدا نگه داشته می‌شود و ذخیره یک مرحله فقط همان مرحله را از دیتابیس
 * دوباره می‌خواند؛ تغییرات ذخیره‌نشده مراحل دیگر و قیمت‌ها پس از ثبت موجودی از بین نمی‌روند.
 */
new class extends Component
{
    public const STEPS = [
        'specs' => 'مشخصات فنی',
        'options' => 'ویژگی‌های مؤثر بر قیمت',
        'prices' => 'قیمت، SKU و بارکد',
        'stock' => 'موجودی و انبار',
    ];

    public Product $product;

    #[Url(except: 'specs')]
    public string $step = 'specs';

    // ---- ۱. مشخصات فنی: specId => value
    public array $specs = [];
    public array $specsOriginal = [];
    public array $addSpecIds = [];

    // ---- ۲. ویژگی‌های قیمت‌ساز: optionId => [valueId => bool]
    public array $optionValues = [];
    public array $optionValuesOriginal = [];
    public ?int $newOptionId = null;

    // ---- ۳. قیمت: variantId => [sku, barcode, price, cost_price, status]
    public array $variants = [];
    public array $variantsOriginal = [];
    public $bulkPrice = null;
    public $bulkCostPrice = null;

    // ---- ۴. موجودی: variantId => inventoryId => [quantity, minimum]
    public array $stock = [];
    public array $stockOriginal = [];

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Product $product)
    {
        abort_if(!auth()->user()->can('products.view'), 403);

        $this->product = $product;

        // مسیرهای قدیمی (settings / specifications / prices) به مرحله متناظر باز می‌شوند
        $legacy = [
            'products.settings' => 'options',
            'products.specifications' => 'specs',
            'products.prices' => 'prices',
        ][request()->route()?->getName()] ?? null;

        if ($legacy && !request()->has('step')) {
            $this->step = $legacy;
        }

        if (!array_key_exists($this->step, self::STEPS)) {
            $this->step = 'specs';
        }

        // محصول ساده (بدون ویژگی قیمت‌ساز و بدون تنوع): تنوع پیش‌فرض تا مرحله قیمت و موجودی فوراً قابل استفاده باشد
        if (auth()->user()->can('products.edit')
            && !ProductVariant::withTrashed()->where('product_id', $product->id)->exists()
            && !ProductOption::where('product_id', $product->id)->exists()) {
            ProductVariant::create(['product_id' => $product->id, 'status' => true, 'is_default' => true]);
        }

        $this->loadSpecs();
        $this->loadOptions();
        $this->loadPrices();
        $this->loadStock();
    }

    protected function authorizeEdit(string $permission = 'products.edit'): void
    {
        abort_if(!auth()->user()->can($permission), 403);
    }

    public function goTo(string $step): void
    {
        if (array_key_exists($step, self::STEPS)) {
            $this->step = $step;
            $this->resetErrorBag();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ۱. مشخصات فنی
    |--------------------------------------------------------------------------
    */
    protected function loadSpecs(): void
    {
        $this->specs = ProductSpecification::with('specification')
            ->where('product_id', $this->product->id)
            ->get()
            ->filter(fn ($row) => $row->specification)
            ->mapWithKeys(fn (ProductSpecification $row) => [$row->specification_id => $this->specValue($row)])
            ->all();

        $this->specsOriginal = $this->specs;
        $this->addSpecIds = [];
        unset($this->productSpecifications, $this->availableSpecifications);
    }

    protected function specValue(ProductSpecification $row)
    {
        return match ((int) $row->specification->type) {
            SpecificationType::Number->value => $row->number_value,
            SpecificationType::Decimal->value => $row->decimal_value,
            SpecificationType::Boolean->value => $row->boolean_value === null ? null : (bool) $row->boolean_value,
            SpecificationType::Date->value => $row->date_value ? Jalalian::fromCarbon($row->date_value)->format('Y/m/d') : null,
            default => $row->text_value,
        };
    }

    #[Computed]
    public function productSpecifications()
    {
        return Specification::whereIn('id', array_keys($this->specs))->orderBy('sort')->get();
    }

    #[Computed]
    public function availableSpecifications()
    {
        return Specification::where('status', true)
            ->whereNotIn('id', array_keys($this->specs))
            ->orderBy('sort')
            ->get(['id', 'title', 'type']);
    }

    public function addSpecs(): void
    {
        $ids = Specification::where('status', true)->whereIn('id', array_map('intval', $this->addSpecIds))->pluck('id');

        foreach ($ids as $id) {
            $this->specs[$id] ??= null;   // فقط به فرم اضافه می‌شود؛ با «ذخیره مشخصات» ثبت می‌شود
        }

        $this->addSpecIds = [];
        unset($this->productSpecifications, $this->availableSpecifications);
    }

    public function removeSpec(int $specId): void
    {
        unset($this->specs[$specId]);
        unset($this->productSpecifications, $this->availableSpecifications);
    }

    public function saveSpecs(): void
    {
        $this->authorizeEdit();

        $definitions = Specification::whereIn('id', array_keys($this->specs))->get()->keyBy('id');
        $rows = [];

        foreach ($this->specs as $specId => $value) {
            $spec = $definitions[$specId] ?? null;

            if (!$spec) {
                continue;
            }

            $column = [
                'text_value' => null, 'number_value' => null, 'decimal_value' => null,
                'boolean_value' => null, 'date_value' => null,
            ];

            if ($value === null || $value === '') {
                $this->addError("specs.$specId", "مقدار «{$spec->title}» را وارد کنید یا آن را حذف کنید.");
                continue;
            }

            switch ((int) $spec->type) {
                case SpecificationType::Number->value:
                    if (!is_numeric($value) || (int) $value != $value) {
                        $this->addError("specs.$specId", "«{$spec->title}» باید عدد صحیح باشد.");
                        continue 2;
                    }
                    $column['number_value'] = (int) $value;
                    break;
                case SpecificationType::Decimal->value:
                    if (!is_numeric($value)) {
                        $this->addError("specs.$specId", "«{$spec->title}» باید عدد باشد.");
                        continue 2;
                    }
                    $column['decimal_value'] = $value;
                    break;
                case SpecificationType::Boolean->value:
                    $column['boolean_value'] = (bool) $value;
                    break;
                case SpecificationType::Date->value:
                    try {
                        $column['date_value'] = Jalalian::fromFormat('Y/m/d', (string) $value)->toCarbon()->toDateString();
                    } catch (\Throwable) {
                        $this->addError("specs.$specId", "تاریخ «{$spec->title}» را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.");
                        continue 2;
                    }
                    break;
                default:
                    $column['text_value'] = mb_substr(trim((string) $value), 0, 1000);
            }

            $rows[$specId] = $column;
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($rows) {
            ProductSpecification::where('product_id', $this->product->id)
                ->whereNotIn('specification_id', array_keys($rows) ?: [0])
                ->delete();

            foreach ($rows as $specId => $values) {
                ProductSpecification::updateOrCreate(
                    ['product_id' => $this->product->id, 'specification_id' => $specId],
                    $values + ['status' => true]
                );
            }
        });

        $this->loadSpecs();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'مشخصات فنی ذخیره شد.');
    }

    /*
    |--------------------------------------------------------------------------
    | ۲. ویژگی‌های مؤثر بر قیمت
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function productOptions()
    {
        return ProductOption::with(['option.values' => fn ($q) => $q->where('status', true)->orderBy('sort')])
            ->where('product_id', $this->product->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->filter(fn ($po) => $po->option);
    }

    #[Computed]
    public function availableOptions()
    {
        return Option::active()
            ->whereNotIn('id', $this->productOptions->pluck('option_id'))
            ->orderBy('title')
            ->pluck('title', 'id');
    }

    protected function loadOptions(): void
    {
        unset($this->productOptions, $this->availableOptions, $this->variantRows);

        // مقادیر انتخاب‌شده = مقادیری که تنوع‌های فعال محصول دارند (غیرفعال کردن تنوع = برداشتن تیک)
        $used = ProductVariantOptionValue::whereIn('product_variant_id', $this->product->variants()->where('status', true)->pluck('id'))
            ->pluck('option_value_id')
            ->unique()
            ->flip();

        $this->optionValues = [];

        foreach ($this->productOptions as $po) {
            foreach ($po->option->values as $value) {
                $this->optionValues[$po->option_id][$value->id] = $used->has($value->id);
            }
        }

        $this->optionValuesOriginal = $this->optionValues;
    }

    public function addOption(): void
    {
        $this->authorizeEdit();

        $this->validate(['newOptionId' => ['required', Rule::exists('options', 'id')->where('status', true)]], [
            'newOptionId.required' => 'ویژگی را انتخاب کنید.',
            'newOptionId.exists' => 'ویژگی انتخاب‌شده معتبر نیست.',
        ]);

        $existing = ProductOption::withTrashed()->where('product_id', $this->product->id)->where('option_id', $this->newOptionId)->first();
        $sort = (int) ProductOption::where('product_id', $this->product->id)->max('sort') + 1;

        if ($existing) {
            $existing->trashed() && $existing->restore();
            $existing->update(['status' => true, 'sort' => $sort]);
        } else {
            ProductOption::create(['product_id' => $this->product->id, 'option_id' => $this->newOptionId, 'status' => true, 'is_required' => true, 'sort' => $sort]);
        }

        $this->newOptionId = null;
        $selected = $this->optionValues;   // انتخاب‌های ذخیره‌نشده حفظ شوند
        $this->loadOptions();
        $this->optionValues = array_replace_recursive($this->optionValues, $selected);
    }

    public function removeOption(int $productOptionId): void
    {
        $this->authorizeEdit();

        $po = ProductOption::where('product_id', $this->product->id)->findOrFail($productOptionId);
        $valueIds = $po->option?->values()->pluck('id') ?? collect();

        $inUse = ProductVariantOptionValue::whereIn('option_value_id', $valueIds)
            ->whereIn('product_variant_id', $this->product->variants()->pluck('id'))
            ->exists();

        if ($inUse) {
            $this->dispatch('alert', type: 'error', title: 'امکان حذف نیست',
                text: 'تنوع‌هایی از این ویژگی ساخته شده‌اند. ابتدا تیک مقادیر را بردارید، «ساخت تنوع‌ها» را بزنید و تنوع‌های خارج از ترکیب را غیرفعال کنید.');
            return;
        }

        $po->delete();
        unset($this->optionValues[$po->option_id]);
        $selected = $this->optionValues;
        $this->loadOptions();
        $this->optionValues = array_replace_recursive($this->optionValues, $selected);
    }

    /** تعداد ترکیب‌ها با انتخاب فعلی (پیش‌نمایش) */
    public function combinationCount(): int
    {
        $count = 1;
        $any = false;

        foreach ($this->optionValues as $values) {
            $checked = count(array_filter($values));
            if ($checked) {
                $count *= $checked;
                $any = true;
            }
        }

        return $any ? $count : 1;
    }

    public function generateVariants(): void
    {
        $this->authorizeEdit();

        // ترتیب ویژگی‌ها مطابق ترتیب محصول
        $groups = [];
        foreach ($this->productOptions as $po) {
            $checked = array_keys(array_filter($this->optionValues[$po->option_id] ?? []));
            if ($checked) {
                $groups[] = array_map('intval', $checked);
            }
        }

        if (count($groups) > 0 && $this->combinationCount() > 300) {
            $this->addError('optionValues', 'تعداد ترکیب‌ها بیش از ۳۰۰ است؛ مقادیر کمتری انتخاب کنید.');
            return;
        }

        $stats = DB::transaction(function () use ($groups) {
            $variants = ProductVariant::where('product_id', $this->product->id)->with('optionValues')->lockForUpdate()->get();

            // محصول بدون ویژگی قیمت‌ساز => یک تنوع پیش‌فرض
            if (!$groups) {
                if ($variants->isEmpty()) {
                    ProductVariant::create(['product_id' => $this->product->id, 'status' => true, 'is_default' => true]);

                    return ['created' => 1, 'reused' => 0, 'kept' => 0];
                }

                return ['created' => 0, 'reused' => 0, 'kept' => $variants->count()];
            }

            $sets = $variants->mapWithKeys(fn ($v) => [$v->id => $v->optionValues->pluck('option_value_id')->map(fn ($id) => (int) $id)->sort()->values()->all()]);
            $matched = [];
            $created = 0;
            $reused = 0;
            $kept = 0;

            foreach ($this->cartesian($groups) as $combo) {
                sort($combo);

                // تنوع موجود با همین ترکیب
                $existing = $sets->search(fn ($set) => $set === $combo);
                if ($existing !== false) {
                    $matched[$existing] = true;
                    $kept++;
                    continue;
                }

                // تنوعی که مقادیرش زیرمجموعه این ترکیب است (مثلاً قبل از افزودن «سایز») => تکمیل همان تنوع
                // تا قیمت، موجودی و سوابق سفارش آن حفظ شود
                $candidate = $sets->search(fn ($set, $id) => !isset($matched[$id]) && !array_diff($set, $combo));

                if ($candidate !== false) {
                    foreach (array_diff($combo, $sets[$candidate]) as $valueId) {
                        ProductVariantOptionValue::create(['product_variant_id' => $candidate, 'option_value_id' => $valueId]);
                    }
                    ProductVariant::whereKey($candidate)->update(['is_default' => false]);
                    $sets[$candidate] = $combo;
                    $matched[$candidate] = true;
                    $reused++;
                    continue;
                }

                $variant = ProductVariant::create(['product_id' => $this->product->id, 'status' => true]);
                foreach ($combo as $valueId) {
                    ProductVariantOptionValue::create(['product_variant_id' => $variant->id, 'option_value_id' => $valueId]);
                }
                $sets[$variant->id] = $combo;
                $matched[$variant->id] = true;
                $created++;
            }

            return compact('created', 'reused', 'kept');
        });

        // فقط مرحله ۲ و ردیف‌های جدید مراحل ۳ و ۴ بارگذاری می‌شوند؛ تغییرات ذخیره‌نشده قیمت/موجودی حفظ می‌شود
        $this->loadOptions();
        $this->mergeNewVariants();

        $outside = count($this->outsideVariantIds());
        $this->dispatch('alert', type: 'success', title: 'تنوع‌ها به‌روز شد',
            text: "{$stats['created']} تنوع جدید، {$stats['reused']} تنوع تکمیل‌شده، {$stats['kept']} تنوع بدون تغییر."
                . ($outside ? " {$outside} تنوع خارج از ترکیب‌های انتخابی است." : ''));

        if ($stats['created'] || $stats['reused']) {
            $this->step = 'prices';
        }
    }

    private function cartesian(array $groups): array
    {
        $result = [[]];

        foreach ($groups as $group) {
            $next = [];
            foreach ($result as $partial) {
                foreach ($group as $item) {
                    $next[] = array_merge($partial, [$item]);
                }
            }
            $result = $next;
        }

        return $result;
    }

    /**
     * تنوع‌های فعالی که مقداری دارند که تیک آن برداشته شده (مثلاً رنگ «قرمز» دیگر فروخته نمی‌شود)
     * تنوع‌هایی که فقط ویژگی تازه‌اضافه‌شده را ندارند با «ساخت تنوع‌ها» تکمیل می‌شوند، نه غیرفعال
     */
    public function outsideVariantIds(): array
    {
        $checked = collect($this->optionValues)->flatMap(fn ($values) => array_keys(array_filter($values)))->map(fn ($id) => (int) $id);

        return $this->variantRows
            ->filter(fn ($variant) => $variant->status && $variant->values->contains(fn ($value) => !$checked->contains((int) $value->id)))
            ->keys()
            ->all();
    }

    public function deactivateOutside(): void
    {
        $this->authorizeEdit();

        $ids = $this->outsideVariantIds();
        ProductVariant::whereIn('id', $ids)->where('product_id', $this->product->id)->get()->each->update(['status' => false]);

        foreach ($ids as $id) {
            if (isset($this->variants[$id])) {
                $this->variants[$id]['status'] = false;
                $this->variantsOriginal[$id]['status'] = false;
            }
        }

        $selection = $this->optionValues;
        $this->loadOptions();
        $this->optionValues = array_replace_recursive($this->optionValues, $selection);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: count($ids) . ' تنوع غیرفعال شد (سوابق سفارش و موجودی حفظ می‌شود).');
    }

    /*
    |--------------------------------------------------------------------------
    | ۳. قیمت، SKU و بارکد
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function variantRows()
    {
        return ProductVariant::with(['values.option'])
            ->where('product_id', $this->product->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->keyBy('id');
    }

    protected function variantFormRow(ProductVariant $variant): array
    {
        return [
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => $variant->price,
            'cost_price' => $variant->cost_price,
            'status' => (bool) $variant->status,
        ];
    }

    protected function loadPrices(): void
    {
        unset($this->variantRows);
        $this->variants = $this->variantRows->map(fn ($v) => $this->variantFormRow($v))->all();
        $this->variantsOriginal = $this->variants;
    }

    /** پس از ساخت تنوع: فقط ردیف‌های جدید به فرم‌های قیمت و موجودی اضافه می‌شوند */
    protected function mergeNewVariants(): void
    {
        unset($this->variantRows);

        foreach ($this->variantRows as $id => $variant) {
            if (!isset($this->variants[$id])) {
                $this->variants[$id] = $this->variantFormRow($variant);
                $this->variantsOriginal[$id] = $this->variants[$id];
            }
        }

        foreach (array_keys($this->variants) as $id) {
            if (!isset($this->variantRows[$id])) {
                unset($this->variants[$id], $this->variantsOriginal[$id]);
            }
        }

        $this->mergeStockRows();
    }

    public function variantLabel(ProductVariant $variant): string
    {
        $values = $variant->values->sortBy(fn ($v) => $this->productOptions->search(fn ($po) => $po->option_id === $v->option_id));

        return $values->map(fn ($v) => ($v->option?->title ? $v->option->title . ': ' : '') . $v->title)->implode(' | ') ?: 'تنوع پیش‌فرض (بدون ویژگی)';
    }

    /** مقدار تازه در فرم قرار می‌گیرد؛ با «ذخیره قیمت‌ها» ثبت می‌شود */
    public function generateCode(int $variantId, string $field): void
    {
        if (!isset($this->variants[$variantId]) || !in_array($field, ['sku', 'barcode'], true)) {
            return;
        }

        $generator = app(VariantCodeGenerator::class);
        $this->variants[$variantId][$field] = $field === 'sku' ? $generator->sku($this->product->id) : $generator->barcode();
    }

    public function applyBulk(): void
    {
        foreach (['price' => 'bulkPrice', 'cost_price' => 'bulkCostPrice'] as $field => $property) {
            if ($this->{$property} !== null && $this->{$property} !== '') {
                foreach ($this->variants as $id => $row) {
                    $this->variants[$id][$field] = (int) $this->{$property};
                }
            }
        }

        $this->reset(['bulkPrice', 'bulkCostPrice']);
    }

    public function savePrices(): void
    {
        $this->authorizeEdit();

        $rules = [];
        $messages = [];

        foreach (array_keys($this->variants) as $id) {
            $rules["variants.$id.sku"] = ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($id)];
            $rules["variants.$id.barcode"] = ['nullable', 'string', 'max:64'];

            // یکتایی بارکد فقط وقتی تغییر کرده بررسی می‌شود (داده‌های قدیمی تکراری مانع ذخیره قیمت نشوند)
            if (trim((string) $this->variants[$id]['barcode']) !== trim((string) ($this->variantsOriginal[$id]['barcode'] ?? ''))) {
                $rules["variants.$id.barcode"][] = Rule::unique('product_variants', 'barcode')->ignore($id)->whereNull('deleted_at');
            }
            $rules["variants.$id.price"] = ['nullable', 'integer', 'min:0'];
            $rules["variants.$id.cost_price"] = ['nullable', 'integer', 'min:0'];
            $rules["variants.$id.status"] = ['boolean'];

            $messages += [
                "variants.$id.sku.unique" => 'این SKU برای کالای دیگری ثبت شده است.',
                "variants.$id.sku.max" => 'SKU حداکثر ۱۰۰ کاراکتر است.',
                "variants.$id.barcode.unique" => 'این بارکد برای کالای دیگری ثبت شده است.',
                "variants.$id.barcode.max" => 'بارکد حداکثر ۶۴ کاراکتر است.',
                "variants.$id.price.integer" => 'قیمت باید عدد باشد.',
                "variants.$id.price.min" => 'قیمت نمی‌تواند منفی باشد.',
                "variants.$id.cost_price.integer" => 'قیمت خرید باید عدد باشد.',
            ];
        }

        // SKU/بارکد تکراری بین ردیف‌های همین فرم (فقط برای مقادیری که تغییر کرده‌اند)
        foreach (['sku', 'barcode'] as $field) {
            foreach ($this->variants as $id => $row) {
                $value = trim((string) $row[$field]);

                if ($value === '' || $value === trim((string) ($this->variantsOriginal[$id][$field] ?? ''))) {
                    continue;
                }

                foreach ($this->variants as $otherId => $other) {
                    if ($otherId !== $id && trim((string) $other[$field]) === $value) {
                        $this->addError("variants.$id.$field", ($field === 'sku' ? 'SKU' : 'بارکد') . ' تکراری در همین فرم.');
                        break;
                    }
                }
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->validate($rules, $messages);


        DB::transaction(function () {
            $models = ProductVariant::where('product_id', $this->product->id)->whereIn('id', array_keys($this->variants))->get()->keyBy('id');

            foreach ($this->variants as $id => $row) {
                $variant = $models[$id] ?? null;

                if (!$variant) {
                    continue;
                }

                // ذخیره با مدل تا SKU/بارکد خالی خودکار تولید شود و همگام‌سازی مارکت‌پلیس اجرا شود
                $variant->fill([
                    'sku' => filled($row['sku']) ? trim((string) $row['sku']) : null,
                    'barcode' => filled($row['barcode']) ? trim((string) $row['barcode']) : null,
                    'price' => filled($row['price']) ? (int) $row['price'] : null,
                    'cost_price' => filled($row['cost_price']) ? (int) $row['cost_price'] : null,
                    'status' => (bool) $row['status'],
                ]);

                if ($variant->isDirty()) {
                    $variant->save();
                }
            }
        });

        $this->loadPrices();
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'قیمت‌ها، SKU و بارکدها ذخیره شد.');
    }

    /*
    |--------------------------------------------------------------------------
    | ۴. موجودی و انبار
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function inventories()
    {
        return Inventory::active()->orderBy('sort')->get(['id', 'title']);
    }

    #[Computed]
    public function stockRows()
    {
        return InventoryItem::whereIn('product_variant_id', array_keys($this->variants))
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => $rows->keyBy('inventory_id'));
    }

    protected function stockFormRow(?InventoryItem $row): array
    {
        return [
            'quantity' => $row ? (int) $row->quantity : 0,
            'minimum' => $row ? (int) $row->minimum_quantity : 0,
        ];
    }

    protected function loadStock(): void
    {
        unset($this->stockRows);
        $this->stock = [];

        foreach (array_keys($this->variants) as $variantId) {
            foreach ($this->inventories as $inventory) {
                $this->stock[$variantId][$inventory->id] = $this->stockFormRow($this->stockRows[$variantId][$inventory->id] ?? null);
            }
        }

        $this->stockOriginal = $this->stock;
    }

    protected function mergeStockRows(): void
    {
        unset($this->stockRows);

        foreach (array_keys($this->variants) as $variantId) {
            foreach ($this->inventories as $inventory) {
                if (!isset($this->stock[$variantId][$inventory->id])) {
                    $this->stock[$variantId][$inventory->id] = $this->stockFormRow($this->stockRows[$variantId][$inventory->id] ?? null);
                    $this->stockOriginal[$variantId][$inventory->id] = $this->stock[$variantId][$inventory->id];
                }
            }
        }

        foreach (array_keys($this->stock) as $variantId) {
            if (!isset($this->variants[$variantId])) {
                unset($this->stock[$variantId], $this->stockOriginal[$variantId]);
            }
        }
    }

    public function saveStock(): void
    {
        $this->authorizeEdit('inventories.edit');

        $service = app(InventoryService::class);
        $saved = 0;

        foreach ($this->stock as $variantId => $inventories) {
            if (!isset($this->variants[$variantId])) {
                continue;
            }

            foreach ($inventories as $inventoryId => $row) {
                $original = $this->stockOriginal[$variantId][$inventoryId] ?? null;
                $quantity = $row['quantity'];
                $minimum = $row['minimum'];

                if (!is_numeric($quantity) || (int) $quantity < 0 || (int) $quantity != $quantity) {
                    $this->addError("stock.$variantId.$inventoryId.quantity", 'موجودی باید عدد صحیح و نامنفی باشد.');
                    continue;
                }

                if (!is_numeric($minimum) || (int) $minimum < 0) {
                    $this->addError("stock.$variantId.$inventoryId.minimum", 'حداقل موجودی نامعتبر است.');
                    continue;
                }

                if ($original && (int) $original['quantity'] === (int) $quantity && (int) $original['minimum'] === (int) $minimum) {
                    continue;
                }

                try {
                    // ثبت در دفتر حرکات انبار + جلوگیری از کمتر شدن از مقدار رزرو سفارش‌های باز
                    $item = $service->setQuantity((int) $variantId, (int) $inventoryId, (int) $quantity, 'اصلاح موجودی از مدیریت محصول');
                    $item->update(['minimum_quantity' => (int) $minimum]);
                } catch (ValidationException $e) {
                    $this->addError("stock.$variantId.$inventoryId.quantity", collect($e->errors())->flatten()->first());
                    continue;
                }

                // فقط ردیف ذخیره‌شده به‌روز می‌شود؛ بقیه فرم (و قیمت‌ها) دست‌نخورده می‌ماند
                $this->stockOriginal[$variantId][$inventoryId] = ['quantity' => (int) $quantity, 'minimum' => (int) $minimum];
                $this->stock[$variantId][$inventoryId] = $this->stockOriginal[$variantId][$inventoryId];
                $saved++;
            }
        }

        unset($this->stockRows);

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->dispatch('alert', type: 'warning', title: 'ذخیره ناقص', text: $saved . ' ردیف ذخیره شد؛ ردیف‌های دارای خطا را اصلاح کنید.');
            return;
        }

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: $saved ? $saved . ' ردیف موجودی ذخیره شد.' : 'تغییری برای ذخیره وجود نداشت.');
    }

    /*
    |--------------------------------------------------------------------------
    | وضعیت مراحل
    |--------------------------------------------------------------------------
    */
    public function dirty(string $step): bool
    {
        return match ($step) {
            'specs' => $this->specs != $this->specsOriginal,
            'options' => $this->optionValues != $this->optionValuesOriginal,
            'prices' => $this->variants != $this->variantsOriginal,
            'stock' => $this->stock != $this->stockOriginal,
            default => false,
        };
    }

    public function summary(): array
    {
        $active = collect($this->variantsOriginal)->filter(fn ($v) => $v['status']);

        return [
            'specs' => count($this->specsOriginal),
            'options' => collect($this->optionValuesOriginal)->filter(fn ($values) => array_filter($values))->count(),
            'variants' => $active->count(),
            'missing_price' => $active->filter(fn ($v) => blank($v['price']))->count(),
            'stock' => (int) $this->stockRows->flatten()->sum(fn ($row) => max(0, $row->quantity - $row->reserved_quantity)),
        ];
    }
};
?>

<div>
    @php
        $summary = $this->summary();
        $outside = $this->outsideVariantIds();
    @endphp

    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            @if($product->featured_image_url)
                <img src="{{ $product->featured_image_url }}" width="56" height="56" class="rounded" style="object-fit: cover" alt="">
            @endif
            <div>
                <h1 class="page-title fw-medium fs-18 mb-1">{{ $product->title }}</h1>
                <div class="text-muted small">
                    {{ $summary['variants'] }} تنوع فعال
                    · موجودی قابل فروش: {{ number_format($summary['stock']) }}
                    @if($summary['missing_price'])
                        · <span class="text-danger">{{ $summary['missing_price'] }} تنوع بدون قیمت</span>
                    @endif
                </div>
            </div>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-light btn-wave"><i class="ri-arrow-right-line align-middle"></i> لیست کالاها</a>
    </div>

    @if(session('product-created'))
        <div class="alert alert-success">
            کالا ساخته شد. مراحل زیر را به ترتیب تکمیل کنید: مشخصات فنی (اختیاری) ← ویژگی‌های مؤثر بر قیمت (اگر کالا رنگ/سایز و... دارد) ← قیمت ← موجودی.
        </div>
    @endif

    {{-- مراحل --}}
    <div class="card custom-card">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap gap-2">
                @foreach($this::STEPS as $key => $label)
                    @php
                        $badge = match ($key) {
                            'specs' => $summary['specs'] . ' مشخصه',
                            'options' => $summary['options'] ? $summary['options'] . ' ویژگی' : 'بدون ویژگی',
                            'prices' => $summary['missing_price'] ? $summary['missing_price'] . ' بدون قیمت' : $summary['variants'] . ' تنوع',
                            'stock' => number_format($summary['stock']) . ' عدد',
                        };
                    @endphp
                    <button type="button" wire:click="goTo('{{ $key }}')"
                            class="btn flex-fill text-start {{ $step === $key ? 'btn-primary' : 'btn-light' }}">
                        <span class="fw-semibold">{{ $loop->iteration }}. {{ $label }}</span>
                        <span class="d-block small {{ $step === $key ? 'text-white-50' : 'text-muted' }}">
                            {{ $badge }}
                            @if($this->dirty($key))<span class="badge bg-warning text-dark ms-1">ذخیره نشده</span>@endif
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===================== ۱. مشخصات فنی ===================== --}}
    @if($step === 'specs')
        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div>
                    <div class="card-title">مشخصات فنی</div>
                    <div class="small text-muted mt-1"><i class="ri-information-line"></i> این مشخصات فقط در صفحه محصول نمایش داده می‌شوند و <strong>روی قیمت تأثیری ندارند</strong> (مثل جنس، ابعاد، کشور سازنده).</div>
                </div>
            </div>
            <div class="card-body">
                @if($this->availableSpecifications->isNotEmpty())
                    <div class="row g-2 align-items-end mb-4">
                        <div class="col-md-9">
                            <label class="form-label small">افزودن مشخصه</label>
                            <select wire:model="addSpecIds" multiple class="form-select" size="4">
                                @foreach($this->availableSpecifications as $spec)
                                    <option value="{{ $spec->id }}">{{ $spec->title }} ({{ SpecificationType::tryFrom((int) $spec->type)?->name ?? '' }})</option>
                                @endforeach
                            </select>
                            <div class="form-text">با Ctrl چند مورد را انتخاب کنید.</div>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-primary w-100" wire:click="addSpecs"><i class="ri-add-line"></i> افزودن به فرم</button>
                        </div>
                    </div>
                @endif

                <div class="row g-3">
                    @forelse($this->productSpecifications as $spec)
                        @php $type = (int) $spec->type; @endphp
                        <div class="col-md-6" wire:key="spec-{{ $spec->id }}">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0">{{ $spec->title }}</label>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="removeSpec({{ $spec->id }})">حذف</button>
                                </div>
                                @if($type === SpecificationType::Boolean->value)
                                    <select wire:model="specs.{{ $spec->id }}" class="form-select @error('specs.' . $spec->id) is-invalid @enderror">
                                        <option value="">انتخاب کنید</option>
                                        <option value="1">بله</option>
                                        <option value="0">خیر</option>
                                    </select>
                                @elseif($type === SpecificationType::Date->value)
                                    <input type="text" data-jdp wire:model.blur="specs.{{ $spec->id }}" class="form-control @error('specs.' . $spec->id) is-invalid @enderror" placeholder="۱۴۰۵/۰۱/۳۱" dir="ltr">
                                @elseif(in_array($type, [SpecificationType::Number->value, SpecificationType::Decimal->value], true))
                                    <input type="number" step="{{ $type === SpecificationType::Decimal->value ? 'any' : '1' }}" wire:model="specs.{{ $spec->id }}" class="form-control @error('specs.' . $spec->id) is-invalid @enderror" dir="ltr">
                                @else
                                    <input type="text" wire:model="specs.{{ $spec->id }}" class="form-control @error('specs.' . $spec->id) is-invalid @enderror">
                                @endif
                                @error('specs.' . $spec->id) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted py-4">مشخصه فنی‌ای اضافه نشده است.</div>
                    @endforelse
                </div>
            </div>
            @can('products.edit')
                <div class="card-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-primary" wire:click="saveSpecs" wire:loading.attr="disabled" wire:target="saveSpecs">ذخیره مشخصات فنی</button>
                    <button type="button" class="btn btn-light" wire:click="goTo('options')">مرحله بعد: ویژگی‌های مؤثر بر قیمت <i class="ri-arrow-left-line"></i></button>
                </div>
            @endcan
        </div>
    @endif

    {{-- ===================== ۲. ویژگی‌های مؤثر بر قیمت ===================== --}}
    @if($step === 'options')
        <div class="card custom-card">
            <div class="card-header">
                <div>
                    <div class="card-title">ویژگی‌های مؤثر بر قیمت</div>
                    <div class="small text-muted mt-1"><i class="ri-price-tag-3-line"></i> ویژگی‌هایی که مشتری هنگام خرید انتخاب می‌کند و <strong>هر ترکیب آن‌ها قیمت و موجودی جداگانه دارد</strong> (مثل رنگ و سایز). اگر محصول فقط یک قیمت دارد، ویژگی اضافه نکنید.</div>
                </div>
            </div>
            <div class="card-body">
                @can('products.edit')
                    @if($this->availableOptions->isNotEmpty())
                        <div class="row g-2 align-items-end mb-4">
                            <div class="col-md-6">
                                <label class="form-label small">افزودن ویژگی قیمت‌ساز</label>
                                <select wire:model="newOptionId" class="form-select @error('newOptionId') is-invalid @enderror">
                                    <option value="">انتخاب ویژگی (مثلاً رنگ)</option>
                                    @foreach($this->availableOptions as $id => $title)
                                        <option value="{{ $id }}">{{ $title }}</option>
                                    @endforeach
                                </select>
                                @error('newOptionId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-outline-primary w-100" wire:click="addOption"><i class="ri-add-line"></i> افزودن</button>
                            </div>
                        </div>
                    @endif
                @endcan

                @error('optionValues') <div class="alert alert-danger">{{ $message }}</div> @enderror

                @forelse($this->productOptions as $po)
                    <div class="border rounded p-3 mb-3" wire:key="po-{{ $po->id }}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold">{{ $po->option->title }}</span>
                            @can('products.edit')
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="removeOption({{ $po->id }})" wire:confirm="ویژگی «{{ $po->option->title }}» از محصول حذف شود؟">حذف ویژگی</button>
                            @endcan
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse($po->option->values as $value)
                                <input type="checkbox" class="btn-check" id="ov-{{ $value->id }}" wire:model.live="optionValues.{{ $po->option_id }}.{{ $value->id }}">
                                <label class="btn btn-sm btn-outline-primary" for="ov-{{ $value->id }}">{{ $value->title }}</label>
                            @empty
                                <span class="text-muted small">برای این ویژگی مقداری تعریف نشده است (منوی «ویژگی‌ها»).</span>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="alert alert-light border">
                        این محصول ویژگی قیمت‌ساز ندارد و <strong>یک قیمت و یک موجودی</strong> دارد. با «ساخت تنوع‌ها» تنوع پیش‌فرض ساخته می‌شود.
                    </div>
                @endforelse

                <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-0">
                    <span>با انتخاب فعلی <strong>{{ number_format($this->combinationCount()) }}</strong> تنوع (ترکیب) وجود خواهد داشت. هر تنوع در مرحله بعد قیمت، SKU و بارکد جدا می‌گیرد.</span>
                    @can('products.edit')
                        <button type="button" class="btn btn-primary" wire:click="generateVariants" wire:loading.attr="disabled" wire:target="generateVariants">
                            <span wire:loading wire:target="generateVariants" class="spinner-border spinner-border-sm"></span>
                            ساخت / به‌روزرسانی تنوع‌ها
                        </button>
                    @endcan
                </div>
                <div class="form-text">تنوع‌های موجود حذف نمی‌شوند؛ قیمت، موجودی و سوابق سفارش آن‌ها حفظ می‌شود. اگر ویژگی جدیدی اضافه کنید، تنوع‌های قبلی تکمیل می‌شوند.</div>

                @if($outside)
                    <div class="alert alert-warning mt-3 mb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>{{ count($outside) }} تنوع با ویژگی‌های انتخاب‌شده همخوانی ندارد.</span>
                        @can('products.edit')
                            <button type="button" class="btn btn-sm btn-warning" wire:click="deactivateOutside" wire:confirm="این تنوع‌ها غیرفعال شوند؟">غیرفعال کردن آن‌ها</button>
                        @endcan
                    </div>
                @endif
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="button" class="btn btn-light" wire:click="goTo('specs')"><i class="ri-arrow-right-line"></i> مرحله قبل</button>
                <button type="button" class="btn btn-light" wire:click="goTo('prices')">مرحله بعد: قیمت، SKU و بارکد <i class="ri-arrow-left-line"></i></button>
            </div>
        </div>
    @endif

    {{-- ===================== ۳. قیمت، SKU و بارکد ===================== --}}
    @if($step === 'prices')
        <div class="card custom-card">
            <div class="card-header">
                <div>
                    <div class="card-title">قیمت، SKU و بارکد هر تنوع</div>
                    <div class="small text-muted mt-1">قیمت‌ها به تومان است. SKU و بارکد خالی هنگام ذخیره خودکار تولید می‌شوند و بعداً قابل تغییرند؛ تغییر آن‌ها اثری روی قیمت، موجودی یا سفارش‌ها ندارد.</div>
                </div>
            </div>
            <div class="card-body">
                @if(!$variants)
                    <div class="alert alert-warning mb-0">این محصول هنوز تنوعی ندارد. در مرحله «ویژگی‌های مؤثر بر قیمت» دکمه «ساخت تنوع‌ها» را بزنید.</div>
                @else
                    @if(count($variants) > 1)
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-4"><label class="form-label small">قیمت فروش برای همه</label><input type="number" min="0" wire:model="bulkPrice" class="form-control form-control-sm" dir="ltr"></div>
                            <div class="col-md-4"><label class="form-label small">قیمت خرید برای همه</label><input type="number" min="0" wire:model="bulkCostPrice" class="form-control form-control-sm" dir="ltr"></div>
                            <div class="col-md-4"><button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="applyBulk">اعمال در فرم</button></div>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr class="small">
                                <th style="min-width: 180px">تنوع (ویژگی‌های قیمت‌ساز)</th>
                                <th style="min-width: 130px">قیمت فروش <span class="text-danger">*</span></th>
                                <th style="min-width: 120px">قیمت خرید</th>
                                <th style="min-width: 190px">SKU</th>
                                <th style="min-width: 190px">بارکد</th>
                                <th>فعال</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($variants as $id => $row)
                                @php $variant = $this->variantRows[$id] ?? null; @endphp
                                @continue(!$variant)
                                <tr wire:key="price-{{ $id }}" class="{{ $row['status'] ? '' : 'opacity-50' }}">
                                    <td>
                                        <div class="fw-semibold small">{{ $this->variantLabel($variant) }}</div>
                                        @if(in_array($id, $outside, true))<span class="badge bg-warning-transparent">خارج از ترکیب</span>@endif
                                    </td>
                                    <td>
                                        <input type="number" min="0" wire:model.blur="variants.{{ $id }}.price" class="form-control form-control-sm @error("variants.$id.price") is-invalid @enderror" dir="ltr">
                                        @error("variants.$id.price") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        @if(filled($row['price']))<div class="form-text">{{ number_format((int) $row['price']) }} تومان</div>@endif
                                    </td>
                                    <td>
                                        <input type="number" min="0" wire:model.blur="variants.{{ $id }}.cost_price" class="form-control form-control-sm @error("variants.$id.cost_price") is-invalid @enderror" dir="ltr">
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="text" wire:model.blur="variants.{{ $id }}.sku" class="form-control @error("variants.$id.sku") is-invalid @enderror" dir="ltr" placeholder="خودکار">
                                            <button type="button" class="btn btn-light" title="تولید SKU جدید" wire:click="generateCode({{ $id }}, 'sku')"><i class="ri-refresh-line"></i></button>
                                        </div>
                                        @error("variants.$id.sku") <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="text" wire:model.blur="variants.{{ $id }}.barcode" class="form-control @error("variants.$id.barcode") is-invalid @enderror" dir="ltr" placeholder="خودکار">
                                            <button type="button" class="btn btn-light" title="تولید بارکد جدید" wire:click="generateCode({{ $id }}, 'barcode')"><i class="ri-refresh-line"></i></button>
                                        </div>
                                        @error("variants.$id.barcode") <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" wire:model.live="variants.{{ $id }}.status">
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="card-footer d-flex justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-light" wire:click="goTo('options')"><i class="ri-arrow-right-line"></i> مرحله قبل</button>
                <div class="d-flex gap-2">
                    @can('products.edit')
                        <button type="button" class="btn btn-primary" wire:click="savePrices" wire:loading.attr="disabled" wire:target="savePrices" @disabled(!$variants)>
                            <span wire:loading wire:target="savePrices" class="spinner-border spinner-border-sm"></span>
                            ذخیره قیمت‌ها، SKU و بارکد
                        </button>
                    @endcan
                    <button type="button" class="btn btn-light" wire:click="goTo('stock')">مرحله بعد: موجودی <i class="ri-arrow-left-line"></i></button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== ۴. موجودی و انبار ===================== --}}
    @if($step === 'stock')
        <div class="card custom-card">
            <div class="card-header">
                <div>
                    <div class="card-title">موجودی هر تنوع در هر انبار</div>
                    <div class="small text-muted mt-1">«قابل فروش» = موجودی منهای مقدار رزروشده برای سفارش‌های باز. موجودی نمی‌تواند از مقدار رزرو کمتر شود. ذخیره موجودی هیچ اثری روی قیمت‌ها و SKUها ندارد.</div>
                </div>
            </div>
            <div class="card-body">
                @if(!$variants)
                    <div class="alert alert-warning mb-0">ابتدا تنوع‌های محصول را بسازید.</div>
                @elseif($this->inventories->isEmpty())
                    <div class="alert alert-warning mb-0">انبار فعالی تعریف نشده است. از منوی «انبارها» یک انبار بسازید.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr class="small">
                                <th style="min-width: 180px">تنوع</th>
                                <th>قیمت</th>
                                @foreach($this->inventories as $inventory)
                                    <th style="min-width: 210px">{{ $inventory->title }}</th>
                                @endforeach
                                <th>جمع قابل فروش</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($variants as $id => $row)
                                @php
                                    $variant = $this->variantRows[$id] ?? null;
                                    $sellable = 0;
                                @endphp
                                @continue(!$variant)
                                <tr wire:key="stock-{{ $id }}" class="{{ $row['status'] ? '' : 'opacity-50' }}">
                                    <td>
                                        <div class="fw-semibold small">{{ $this->variantLabel($variant) }}</div>
                                        <div class="small text-muted" dir="ltr">{{ $variant->sku }}</div>
                                    </td>
                                    <td class="small">{{ filled($variantsOriginal[$id]['price'] ?? null) ? number_format((int) $variantsOriginal[$id]['price']) : '—' }}</td>
                                    @foreach($this->inventories as $inventory)
                                        @php
                                            $db = $this->stockRows[$id][$inventory->id] ?? null;
                                            $reserved = (int) ($db?->reserved_quantity ?? 0);
                                            $sellable += max(0, (int) ($db?->quantity ?? 0) - $reserved);
                                        @endphp
                                        <td wire:key="stock-{{ $id }}-{{ $inventory->id }}">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">موجودی</span>
                                                <input type="number" min="{{ $reserved }}" wire:model.blur="stock.{{ $id }}.{{ $inventory->id }}.quantity" class="form-control @error("stock.$id.{$inventory->id}.quantity") is-invalid @enderror" dir="ltr">
                                            </div>
                                            <div class="input-group input-group-sm mt-1">
                                                <span class="input-group-text">هشدار کمتر از</span>
                                                <input type="number" min="0" wire:model.blur="stock.{{ $id }}.{{ $inventory->id }}.minimum" class="form-control @error("stock.$id.{$inventory->id}.minimum") is-invalid @enderror" dir="ltr">
                                            </div>
                                            @if($reserved)<div class="form-text">رزرو: {{ $reserved }}</div>@endif
                                            @error("stock.$id.{$inventory->id}.quantity") <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                            @error("stock.$id.{$inventory->id}.minimum") <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </td>
                                    @endforeach
                                    <td class="fw-semibold">{{ number_format($sellable) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="button" class="btn btn-light" wire:click="goTo('prices')"><i class="ri-arrow-right-line"></i> مرحله قبل</button>
                @can('inventories.edit')
                    <button type="button" class="btn btn-primary" wire:click="saveStock" wire:loading.attr="disabled" wire:target="saveStock" @disabled(!$variants)>
                        <span wire:loading wire:target="saveStock" class="spinner-border spinner-border-sm"></span>
                        ذخیره موجودی
                    </button>
                @endcan
            </div>
        </div>
    @endif

    @push('scripts')
        <script src="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.js') }}"></script>
        <script>
            jalaliDatepicker.startWatch();
            // هشدار خروج با تغییرات ذخیره‌نشده
            window.addEventListener('beforeunload', (e) => {
                if (document.querySelector('.badge.bg-warning') && document.querySelector('.badge.bg-warning').textContent.includes('ذخیره نشده')) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        </script>
    @endpush
</div>
