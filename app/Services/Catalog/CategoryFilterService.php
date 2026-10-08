<?php

namespace App\Services\Catalog;

use App\Enums\SpecificationType;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Specification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * فیلترهای پویای صفحه دسته‌بندی
 *
 * ۱) تعریف فیلترها: ویژگی‌های دسته (category_attributes) — از نزدیک‌ترین دسته‌ای که تعریف دارد
 *    (خود دسته یا والدها) ارث می‌رسد. اگر هیچ تعریفی نباشد، خودکار: مشخصات «قابل فیلتر» و
 *    ویژگی‌های قیمت‌سازی که محصولات همین دسته واقعاً دارند.
 * ۲) ایندکس محصولات دسته (کش‌شده): قیمت نهایی (موتور قیمت)، موجودی، برند، امتیاز، فروش،
 *    مقادیر مشخصات و ویژگی‌ها برای هر محصول.
 * ۳) اعمال فیلترها، شمارش هر گزینه (با در نظر گرفتن بقیه فیلترهای انتخاب‌شده) و مرتب‌سازی.
 *
 * فیلتری که در محصولات این دسته مقداری ندارد نمایش داده نمی‌شود.
 */
class CategoryFilterService
{
    public const DISPLAY_TYPES = [
        'spec' => ['checkbox' => 'چندانتخابی (Checkbox)', 'radio' => 'تک‌انتخابی (Radio)', 'select' => 'لیست کشویی', 'range' => 'بازه عددی', 'toggle' => 'بله/خیر'],
        'option' => ['buttons' => 'دکمه‌ای (مثل سایز)', 'color' => 'انتخابگر رنگ', 'select' => 'لیست کشویی'],
    ];

    // رنگ‌های رایج (وقتی برای مقدار رنگ کد رنگ ثبت نشده است)
    protected const COLOR_NAMES = [
        'سفید' => '#ffffff', 'مشکی' => '#111111', 'سیاه' => '#111111', 'خاکستری' => '#9ca3af', 'طوسی' => '#9ca3af', 'نقره‌ای' => '#c0c0c0', 'نقره ای' => '#c0c0c0',
        'قرمز' => '#dc2626', 'زرشکی' => '#7f1d1d', 'صورتی' => '#f472b6', 'نارنجی' => '#f97316', 'زرد' => '#facc15', 'طلایی' => '#d4a017',
        'سبز' => '#16a34a', 'یشمی' => '#0f766e', 'آبی' => '#2563eb', 'ابی' => '#2563eb', 'سرمه‌ای' => '#1e3a8a', 'سرمه ای' => '#1e3a8a', 'فیروزه‌ای' => '#06b6d4',
        'بنفش' => '#7c3aed', 'یاسی' => '#c4b5fd', 'قهوه‌ای' => '#78482d', 'قهوه ای' => '#78482d', 'کرم' => '#f5e6c8', 'بژ' => '#d6c3a5', 'شتری' => '#c19a6b', 'عسلی' => '#b7791f',
    ];

    /*
    |--------------------------------------------------------------------------
    | ۱. تعریف فیلترها
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, array{key: string, kind: string, id: int, title: string, display: string, source: string, model: Specification|Option}>
     */
    public function definitions(Category $category, array $index): Collection
    {
        $usedSpecs = [];
        $usedOptions = [];

        foreach ($index as $row) {
            foreach ($row['specs'] as $specId => $value) {
                $usedSpecs[$specId] = true;
            }
            foreach ($row['options'] as $optionId => $values) {
                $usedOptions[$optionId] = true;
            }
        }

        [$assigned, $source] = $this->assignedAttributes($category);

        if ($assigned !== null) {
            $rows = $assigned->where('is_filter', true);
            $specIds = $rows->where('attribute_type', CategoryAttribute::SPEC)->pluck('attribute_id')->all();
            $optionIds = $rows->where('attribute_type', CategoryAttribute::OPTION)->pluck('attribute_id')->all();
            $order = $rows->values()->mapWithKeys(fn ($r, $i) => [$r->attribute_type . ':' . $r->attribute_id => $i])->all();
        } else {
            // بدون تعریف: مشخصات «قابل فیلتر» + ویژگی‌های قیمت‌ساز محصولات همین دسته
            $specIds = Specification::where('status', true)->where('is_filterable', true)->whereIn('id', array_keys($usedSpecs))->pluck('id')->all();
            $optionIds = array_keys($usedOptions);
            $order = null;
            $source = 'auto';
        }

        $definitions = collect();

        foreach (Specification::where('status', true)->whereIn('id', array_intersect($specIds, array_keys($usedSpecs)))->get() as $spec) {
            $display = $this->specDisplay($spec);
            if ($display) {
                $definitions->push(['key' => 's' . $spec->id, 'kind' => 'spec', 'id' => $spec->id, 'title' => $spec->title, 'display' => $display, 'source' => $source, 'model' => $spec, 'sort' => $order['spec:' . $spec->id] ?? $spec->sort]);
            }
        }

        foreach (Option::where('status', true)->whereIn('id', array_intersect($optionIds, array_keys($usedOptions)))->get() as $option) {
            $definitions->push(['key' => 'o' . $option->id, 'kind' => 'option', 'id' => $option->id, 'title' => $option->title, 'display' => $this->optionDisplay($option), 'source' => $source, 'model' => $option, 'sort' => $order['option:' . $option->id] ?? (1000 + $option->sort)]);
        }

        return $definitions->sortBy('sort')->values();
    }

    /**
     * ویژگی‌های نزدیک‌ترین دسته‌ای که تعریف دارد (خود دسته، سپس والدها)
     *
     * @return array{0: ?Collection, 1: string}
     */
    public function assignedAttributes(Category $category): array
    {
        if (! Schema::hasTable('category_attributes')) {
            return [null, 'auto'];
        }

        foreach (array_merge([$category->id], $category->ancestorIds()) as $i => $categoryId) {
            $rows = CategoryAttribute::where('category_id', $categoryId)->orderBy('sort')->orderBy('id')->get();

            if ($rows->isNotEmpty()) {
                return [$rows, $i === 0 ? 'category' : 'parent'];
            }
        }

        return [null, 'auto'];
    }

    public function specDisplay(Specification $spec): ?string
    {
        $type = (int) $spec->type;
        $chosen = $spec->filter_type ?? null;

        $allowed = match ($type) {
            SpecificationType::Number->value, SpecificationType::Decimal->value => ['range', 'checkbox', 'radio', 'select'],
            SpecificationType::Boolean->value => ['toggle'],
            SpecificationType::Text->value => ['checkbox', 'radio', 'select'],
            default => [], // تاریخ: فیلتر نمی‌شود
        };

        if (! $allowed) {
            return null;
        }

        return in_array($chosen, $allowed, true) ? $chosen : $allowed[0];
    }

    public function optionDisplay(Option $option): string
    {
        $chosen = $option->display_type ?? null;

        if (in_array($chosen, ['buttons', 'color', 'select'], true)) {
            return $chosen;
        }

        return $this->looksLikeColor($option->title . ' ' . $option->slug) ? 'color' : 'buttons';
    }

    protected function looksLikeColor(string $text): bool
    {
        return (bool) preg_match('/رنگ|colou?r/iu', $text);
    }

    public function colorFor(OptionValue $value): ?string
    {
        if ($value->color_code && preg_match('/^#[0-9a-f]{3,8}$/i', $value->color_code)) {
            return $value->color_code;
        }

        $title = trim($value->title);

        foreach (self::COLOR_NAMES as $name => $hex) {
            if ($title === $name || str_starts_with($title, $name)) {
                return $hex;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | ۲. ایندکس محصولات
    |--------------------------------------------------------------------------
    */

    /**
     * @param  int[]  $categoryIds  دسته و همه زیر‌دسته‌ها
     * @return array<int, array> کلید = شناسه محصول
     */
    public function index(array $categoryIds): array
    {
        sort($categoryIds);
        $version = Cache::get('catalog-version', 0);

        return Cache::remember('category-index:' . $version . ':' . md5(implode(',', $categoryIds)), 600, fn () => $this->buildIndex($categoryIds));
    }

    protected function buildIndex(array $categoryIds): array
    {
        $products = DB::table('products')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereIn('id', DB::table('category_product')->whereIn('category_id', $categoryIds)->select('product_id'))
            ->get(['id', 'brand_id', 'created_at']);

        if ($products->isEmpty()) {
            return [];
        }

        $ids = $products->pluck('id')->all();

        $categories = DB::table('category_product')->whereIn('product_id', $ids)->get(['product_id', 'category_id'])
            ->groupBy('product_id')->map(fn ($rows) => $rows->pluck('category_id')->map(fn ($id) => (int) $id)->all());

        $variants = ProductVariant::query()
            ->whereIn('product_id', $ids)
            ->where('status', true)
            ->get(['id', 'product_id', 'price', 'is_default', 'updated_at'])
            ->groupBy('product_id');

        $variantIds = $variants->flatten()->pluck('id')->all();

        $stock = $variantIds ? DB::table('inventory_items')
            ->join('inventories', 'inventories.id', '=', 'inventory_items.inventory_id')
            ->whereIn('product_variant_id', $variantIds)
            ->where('inventory_items.status', 1)
            ->where('inventories.status', 1)
            ->whereNull('inventory_items.deleted_at')
            ->selectRaw('product_variant_id, SUM(quantity - reserved_quantity) as stock')
            ->groupBy('product_variant_id')
            ->pluck('stock', 'product_variant_id')
            ->map(fn ($s) => max(0, (int) $s))
            ->all() : [];

        // مقادیر ویژگی‌های قیمت‌ساز روی تنوع‌های فعال (فیلتر رنگ/سایز)
        $optionRows = $variantIds ? DB::table('product_variant_option_values as pvov')
            ->join('option_values as ov', 'ov.id', '=', 'pvov.option_value_id')
            ->join('product_variants as pv', 'pv.id', '=', 'pvov.product_variant_id')
            ->whereIn('pvov.product_variant_id', $variantIds)
            ->whereNull('pvov.deleted_at')
            ->whereNull('ov.deleted_at')
            ->where('ov.status', 1)
            ->get(['pv.product_id', 'pvov.product_variant_id', 'ov.option_id', 'ov.id as value_id']) : collect();

        $specRows = DB::table('product_specifications as ps')
            ->join('specifications as s', 's.id', '=', 'ps.specification_id')
            ->whereIn('ps.product_id', $ids)
            ->where('s.status', 1)
            ->whereNull('s.deleted_at')
            ->where(fn ($q) => $q->whereNull('ps.status')->orWhere('ps.status', 1))
            ->get(['ps.product_id', 'ps.specification_id', 's.type', 'ps.text_value', 'ps.number_value', 'ps.decimal_value', 'ps.boolean_value']);

        $ratings = DB::table('ratings')
            ->whereIn('rateable_type', [Product::class, (new Product)->getMorphClass()])
            ->whereIn('rateable_id', $ids)
            ->selectRaw('rateable_id, AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->groupBy('rateable_id')
            ->get()
            ->keyBy('rateable_id');

        $sales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->whereIn('product_variants.product_id', $ids)
            ->where('orders.payment_status', 'paid')
            ->selectRaw('product_variants.product_id, SUM(order_items.quantity) as qty')
            ->groupBy('product_variants.product_id')
            ->pluck('qty', 'product_id');

        $optionRows = $optionRows->groupBy('product_id');
        $specRows = $specRows->groupBy('product_id');
        $index = [];

        foreach ($products as $product) {
            $productVariants = $variants->get($product->id, collect());
            $display = $this->displayVariant($productVariants, $stock);

            $base = $display ? (int) $display->price : 0;
            $final = $display ? $this->finalPrice($display) : 0;
            $productStock = (int) $productVariants->sum(fn ($v) => $stock[$v->id] ?? 0);

            $options = [];
            foreach ($optionRows->get($product->id, []) as $row) {
                // فقط مقادیری که تنوع موجود دارند به فیلتر «موجود» مرتبط‌اند؛ اینجا همه تنوع‌های فعال
                $options[(int) $row->option_id][(int) $row->value_id] = true;
            }

            $specs = [];
            foreach ($specRows->get($product->id, []) as $row) {
                $value = match ((int) $row->type) {
                    SpecificationType::Number->value => $row->number_value !== null ? (float) $row->number_value : null,
                    SpecificationType::Decimal->value => $row->decimal_value !== null ? (float) $row->decimal_value : null,
                    SpecificationType::Boolean->value => $row->boolean_value !== null ? (bool) $row->boolean_value : null,
                    SpecificationType::Text->value => filled($row->text_value) ? trim(preg_replace('/\s+/u', ' ', $row->text_value)) : null,
                    default => null,
                };

                if ($value !== null) {
                    $specs[(int) $row->specification_id] = $value;
                }
            }

            $rating = $ratings->get($product->id);

            $index[$product->id] = [
                'id' => (int) $product->id,
                'brand' => $product->brand_id ? (int) $product->brand_id : null,
                'categories' => $categories->get($product->id, []),
                'created' => strtotime((string) $product->created_at) ?: 0,
                'variant' => $display?->id,
                'base' => $base,
                'price' => $final,
                'discount' => $base > 0 && $final < $base ? (int) round(($base - $final) / $base * 100) : 0,
                'stock' => $productStock,
                'rating' => $rating ? round((float) $rating->avg_rating, 1) : 0,
                'rating_count' => $rating ? (int) $rating->cnt : 0,
                'sales' => (int) ($sales[$product->id] ?? 0),
                'specs' => $specs,
                'options' => array_map(fn ($values) => array_keys($values), $options),
            ];
        }

        return $index;
    }

    /** تنوع نمایشی: پیش‌فرضِ موجود، ارزان‌ترینِ موجود، یا پیش‌فرض/ارزان‌ترین */
    protected function displayVariant(Collection $variants, array $stock)
    {
        $priced = $variants->filter(fn ($v) => (int) $v->price > 0);

        if ($priced->isEmpty()) {
            return $variants->first();
        }

        $default = $priced->firstWhere('is_default', 1);

        if ($default && ($stock[$default->id] ?? 0) > 0) {
            return $default;
        }

        return $priced->filter(fn ($v) => ($stock[$v->id] ?? 0) > 0)->sortBy('price')->first()
            ?? $default
            ?? $priced->sortBy('price')->first();
    }

    protected function finalPrice(ProductVariant $variant): int
    {
        try {
            $data = $variant->priceData();

            return (int) ($data['after_discount'] ?? $variant->price);
        } catch (Throwable) {
            return (int) $variant->price;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ۳. اعمال فیلتر، شمارش و مرتب‌سازی
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array  $state  [categories[], brands[], stock, discount, new, rating, price_min, price_max, specs[specId=>values|['min','max']|true], options[optionId=>valueIds], sort]
     * @param  array<int, int[]>  $childScopes  شناسه زیر‌دسته => همه شناسه‌های زیر‌شاخه آن
     * @return array{ids: int[], facets: array, bounds: array}
     */
    public function apply(array $index, Collection $definitions, array $state, array $childScopes = []): array
    {
        $definitionsByKey = $definitions->keyBy('key');
        $predicates = $this->predicates($state, $definitionsByKey, $childScopes);

        $matched = [];
        $facetPools = []; // کلید فیلتر => ردیف‌هایی که بجز همان فیلتر، بقیه را دارند

        foreach ($index as $row) {
            $failed = [];
            foreach ($predicates as $key => $test) {
                if (! $test($row)) {
                    $failed[] = $key;
                    if (count($failed) > 1) {
                        break;
                    }
                }
            }

            if (! $failed) {
                $matched[] = $row;
            } elseif (count($failed) === 1) {
                $facetPools[$failed[0]][] = $row;
            }
        }

        // ردیف‌هایی که همه فیلترها را دارند در شمارش همه فیلترها حساب می‌شوند
        $pool = fn (string $key) => array_merge($matched, $facetPools[$key] ?? []);

        $facets = [
            'categories' => $this->countCategories($pool('categories'), $childScopes),
            'brands' => $this->countValues($pool('brands'), fn ($r) => $r['brand'] ? [$r['brand']] : []),
            'stock' => count(array_filter($pool('stock'), fn ($r) => $r['stock'] > 0)),
            'discount' => count(array_filter($pool('discount'), fn ($r) => $r['discount'] > 0)),
            'new' => count(array_filter($pool('new'), fn ($r) => $r['created'] >= strtotime('-30 days'))),
            'rating' => collect([4, 3, 2])->mapWithKeys(fn ($n) => [$n => count(array_filter($pool('rating'), fn ($r) => $r['rating'] >= $n))])->all(),
            'price' => $this->bounds($pool('price'), fn ($r) => $r['price'] > 0 ? $r['price'] : null),
            'attributes' => [],
        ];

        foreach ($definitions as $definition) {
            $key = $definition['key'];
            $rows = $pool($key);

            $facets['attributes'][$key] = match (true) {
                $definition['kind'] === 'option' => $this->countValues($rows, fn ($r) => $r['options'][$definition['id']] ?? []),
                $definition['display'] === 'range' => $this->bounds($rows, fn ($r) => $r['specs'][$definition['id']] ?? null),
                $definition['display'] === 'toggle' => count(array_filter($rows, fn ($r) => ($r['specs'][$definition['id']] ?? null) === true)),
                default => $this->countValues($rows, fn ($r) => isset($r['specs'][$definition['id']]) ? [$this->valueKey($r['specs'][$definition['id']])] : []),
            };
        }

        return [
            'ids' => array_column($this->sort($matched, $state['sort'] ?? 'latest'), 'id'),
            'facets' => $facets,
            'bounds' => [
                'price' => $this->bounds(array_values($index), fn ($r) => $r['price'] > 0 ? $r['price'] : null),
            ],
        ];
    }

    public function valueKey($value): string
    {
        return is_float($value) ? rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') : (string) $value;
    }

    /** @return array<string, callable> */
    protected function predicates(array $state, Collection $definitions, array $childScopes): array
    {
        $p = [];

        if (! empty($state['categories'])) {
            $allowed = collect($state['categories'])->flatMap(fn ($id) => $childScopes[(int) $id] ?? [(int) $id])->flip()->all();
            $p['categories'] = fn ($r) => (bool) array_intersect_key(array_flip($r['categories']), $allowed);
        }

        if (! empty($state['brands'])) {
            $brands = array_flip(array_map('intval', $state['brands']));
            $p['brands'] = fn ($r) => $r['brand'] && isset($brands[$r['brand']]);
        }

        if (! empty($state['stock'])) {
            $p['stock'] = fn ($r) => $r['stock'] > 0;
        }

        if (! empty($state['discount'])) {
            $p['discount'] = fn ($r) => $r['discount'] > 0;
        }

        if (! empty($state['new'])) {
            $since = strtotime('-30 days');
            $p['new'] = fn ($r) => $r['created'] >= $since;
        }

        if (! empty($state['rating'])) {
            $min = (int) $state['rating'];
            $p['rating'] = fn ($r) => $r['rating'] >= $min;
        }

        if (($state['price_min'] ?? null) !== null || ($state['price_max'] ?? null) !== null) {
            $min = $state['price_min'] ?? null;
            $max = $state['price_max'] ?? null;
            $p['price'] = fn ($r) => $r['price'] > 0 && ($min === null || $r['price'] >= $min) && ($max === null || $r['price'] <= $max);
        }

        foreach ((array) ($state['specs'] ?? []) as $specId => $selection) {
            $definition = $definitions->get('s' . $specId);

            if (! $definition || $selection === null || $selection === [] || $selection === '' || $selection === false) {
                continue;
            }

            $id = (int) $specId;

            if ($definition['display'] === 'range') {
                $min = is_numeric($selection['min'] ?? null) ? (float) $selection['min'] : null;
                $max = is_numeric($selection['max'] ?? null) ? (float) $selection['max'] : null;
                if ($min === null && $max === null) {
                    continue;
                }
                $p['s' . $id] = fn ($r) => isset($r['specs'][$id]) && is_numeric($r['specs'][$id]) && ($min === null || $r['specs'][$id] >= $min) && ($max === null || $r['specs'][$id] <= $max);
            } elseif ($definition['display'] === 'toggle') {
                $p['s' . $id] = fn ($r) => ($r['specs'][$id] ?? null) === true;
            } else {
                $values = array_flip(array_map('strval', (array) $selection));
                $p['s' . $id] = fn ($r) => isset($r['specs'][$id]) && isset($values[$this->valueKey($r['specs'][$id])]);
            }
        }

        foreach ((array) ($state['options'] ?? []) as $optionId => $valueIds) {
            $valueIds = array_filter(array_map('intval', (array) $valueIds));

            if (! $valueIds || ! $definitions->has('o' . $optionId)) {
                continue;
            }

            $id = (int) $optionId;
            $wanted = array_flip($valueIds);
            $p['o' . $id] = fn ($r) => (bool) array_intersect_key(array_flip($r['options'][$id] ?? []), $wanted);
        }

        return $p;
    }

    protected function countValues(array $rows, callable $values): array
    {
        $counts = [];

        foreach ($rows as $row) {
            foreach ($values($row) as $value) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        return $counts;
    }

    protected function countCategories(array $rows, array $childScopes): array
    {
        $counts = [];

        foreach ($childScopes as $childId => $scope) {
            $scope = array_flip($scope);
            $counts[$childId] = count(array_filter($rows, fn ($r) => (bool) array_intersect_key(array_flip($r['categories']), $scope)));
        }

        return $counts;
    }

    /** @return array{min: ?float, max: ?float} */
    protected function bounds(array $rows, callable $value): array
    {
        $values = array_filter(array_map($value, $rows), fn ($v) => $v !== null && is_numeric($v));

        return $values ? ['min' => min($values), 'max' => max($values)] : ['min' => null, 'max' => null];
    }

    protected function sort(array $rows, string $sort): array
    {
        usort($rows, match ($sort) {
            'cheap' => fn ($a, $b) => [$a['price'] <= 0, $a['price']] <=> [$b['price'] <= 0, $b['price']],
            'expensive' => fn ($a, $b) => $b['price'] <=> $a['price'],
            'sales' => fn ($a, $b) => [$b['sales'], $b['created']] <=> [$a['sales'], $a['created']],
            'discount' => fn ($a, $b) => [$b['discount'], $b['created']] <=> [$a['discount'], $a['created']],
            'rating' => fn ($a, $b) => [$b['rating'], $b['rating_count']] <=> [$a['rating'], $a['rating_count']],
            default => fn ($a, $b) => [$b['created'], $b['id']] <=> [$a['created'], $a['id']],
        });

        // ناموجودها در انتهای لیست
        usort($rows, fn ($a, $b) => ($a['stock'] > 0 ? 0 : 1) <=> ($b['stock'] > 0 ? 0 : 1));

        return $rows;
    }
}
