<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

new class extends Component
{
    use WithPagination;

    /*
    |--------------------------------------------------------------------
    | نکته مهم درباره منطق تخفیف (برای خودتان که بعداً برمی‌گردید سراغ این فایل)
    |--------------------------------------------------------------------
    | 1) product_variants.price  = قیمت فعلی فروش واریانت.
    |    product_variants.compare_price = قیمت قبل از تخفیفِ خودِ محصول (فقط برای خط‌خورده نمایش داده می‌شود).
    |    یعنی تخفیفِ «خودِ محصول» از قبل توی price اعمال شده و دوباره چیزی از آن کم نمی‌کنیم.
    |
    | 2) discounts + discount_targets: یک لایه‌ی تخفیفِ *اضافه* هستند که می‌توانند هدف‌گذاری شوند روی
    |    محصول خاص / برند / دسته‌بندی، با بازه زمانی و سقف تخفیف. اگر چند تخفیف روی یک محصول match شوند،
    |    آن‌ها را روی هم جمع نمی‌زنیم؛ فقط تخفیفی که کمترین قیمت نهایی را نتیجه می‌دهد اعمال می‌شود.
    |
    | 3) campaigns سطحش سبد خرید است (شرط روی جمع سبد + پاداش کلی)، نه قیمت تک‌محصول؛
    |    بنابراین در این صفحه (لیست محصولات) اعمال نمی‌شود.
    |
    | 4) type روی جدول discounts فرض شده: 1 = درصدی، 2 = مبلغ ثابت (تومان).
    |    اگر enum واقعی پروژه فرق دارد، فقط همین دو ثابت پایین را عوض کنید.
    |
    | 5) [جدید] Price Filter (minPrice/maxPrice) باید همیشه روی همین «Final Price» محاسبه‌شده کار کند،
    |    نه روی product_variants.price خام. چون finalPrice نتیجه‌ی محاسبه‌ی PHP (نه یک ستون SQL) است،
    |    تنها فیلترِ *امنِ* قابل انجام در سطح SQL محدودکردن کف قیمت (>= minPrice) روی basePrice است
    |    (چون finalPrice همیشه <= basePrice، پس finalPrice >= min ⇒ basePrice >= min، بدون false-negative).
    |    فیلترِ دقیقِ هر دو طرفِ بازه روی final_price، داخل viewProducts() بعد از محاسبه انجام می‌شود؛
    |    دقیقاً با همان الگویی که پیش‌تر برای onlyInStock/onlyDiscounted استفاده شده بود.
    |
    |--------------------------------------------------------------------
    | چرا render() نداریم
    |--------------------------------------------------------------------
    | این کامپوننت به‌جای متد render() از پراپرتی‌های #[Computed] استفاده می‌کند. هر پراپرتی
    | Computed (مثل paginator و viewProducts) فقط وقتی که در تمپلیت صدا زده می‌شود (به‌صورت
    | $this->paginator یا $this->viewProducts) اجرا و در همان درخواست کش می‌شود، بدون این‌که
    | لازم باشد state سنگین (کالکشن محصولات و...) به‌عنوان پراپرتی عمومی بین درخواست‌ها ذخیره و
    | هیدریت شود. Livewire به‌صورت خودکار، بعد از هر اکشن، تمپلیت را با همین پراپرتی‌های Computed
    | تازه دوباره رندر می‌کند؛ پس نیازی به render() صریح نیست.
    */
    private const DISCOUNT_TYPE_PERCENT = 1;
    private const DISCOUNT_TYPE_FIXED   = 2;

    public Category $category;
    public Collection $childCategories;
    public Collection $brands;

    public array $categoryIds = [];

    // فیلترها و مرتب‌سازی
    public string $sort = 'latest';
    public array $selectedCategories = [];
    public array $selectedBrands = [];

    public int $priceFloor = 0;
    public int $priceCeil = 0;
    public ?int $minPrice = null;
    public ?int $maxPrice = null;

    public bool $onlyInStock = false;
    public bool $onlyDiscounted = false;
    public bool $onlyNew = false;

    protected $paginationTheme = 'tailwind';

    public function mount($slug)
    {
        $this->category = Category::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        $this->childCategories = $this->category
            ->children()
            ->active()
            ->orderBy('title')
            ->get();

        $this->categoryIds = $this->category
            ->getAllDescendantIds()
            ->push($this->category->id)
            ->unique()
            ->values()
            ->toArray();

        // برندهایی که واقعاً محصولی در این دسته‌بندی دارند (برای فیلتر برند، به‌جای لیست ثابت)
        $this->brands = Brand::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereHas('products', function ($q) {
                $q->whereHas('categories', fn ($q2) => $q2->whereIn('categories.id', $this->categoryIds));
            })
            ->orderBy('title')
            ->get();

        // بازه‌ی واقعی قیمت بر اساس واریانت‌های محصولاتِ همین دسته (برای تنظیم اسلایدر قیمت)
        // توجه: این بازه بر مبنای basePrice است، نه finalPrice. چون finalPrice <= basePrice همیشه
        // برقرار است، این بازه یک محدوده‌ی «امن و محافظه‌کارانه» است (ممکن است در عمل چند محصولِ
        // تخفیف‌خورده حتی از priceFloor هم ارزان‌تر باشند، ولی چون کف اسلایدر را می‌شود همان‌جا
        // خالی گذاشت، مشکلی برای UX ایجاد نمی‌کند). محاسبه‌ی دقیقِ finalPrice برای کل کاتالوگ فقط
        // برای تعیین بازه‌ی اسلایدر، به معنای لود و پردازش تمام واریانت‌های دسته در هر mount است که
        // طبق قانون «از ایجاد Query اضافی جلوگیری کن» صرفه نمی‌کند؛ همان الگویی که خودِ کد قبلاً
        // برای sort=cheap هم پذیرفته بود.
        $bounds = DB::table('product_variants')
            ->join('category_product', 'product_variants.product_id', '=', 'category_product.product_id')
            ->whereIn('category_product.category_id', $this->categoryIds)
            ->where('product_variants.status', 1)
            ->whereNull('product_variants.deleted_at')
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $this->priceFloor = (int) ($bounds->min_price ?? 0);
        $this->priceCeil  = (int) ($bounds->max_price ?? 0);
        $this->minPrice   = $this->priceFloor;
        $this->maxPrice   = $this->priceCeil;
    }

    /**
     * تخفیف‌های فعالِ همین لحظه (status=1 و داخل بازه‌ی زمانی starts_at/ends_at).
     * Computed یعنی: در یک درخواست فقط یک‌بار اجرا و کش می‌شود، نیازی به نگه‌داشتنش بین درخواست‌ها نیست.
     */
    #[Computed]
    public function activeDiscounts(): Collection
    {
        $now = Carbon::now();

        return collect(
            DB::table('discounts')
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->get()
        );
    }

    /**
     * هدف‌های همان تخفیف‌های فعال، گروه‌بندی‌شده بر اساس discount_id.
     */
    #[Computed]
    public function discountTargetsByDiscount(): Collection
    {
        $discountIds = $this->activeDiscounts->pluck('id');

        if ($discountIds->isEmpty()) {
            return collect();
        }

        return collect(
            DB::table('discount_targets')->whereIn('discount_id', $discountIds)->get()
        )->groupBy('discount_id');
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
        $this->resetPage();
    }

    public function applyPriceRange(): void
    {
        $this->clampPriceRange();
        $this->resetPage();
    }

    /**
     * وقتی کاربر داخل اینپوت عددیِ «از» چیزی تایپ می‌کند (wire:model.live).
     */
    public function updatedMinPrice(): void
    {
        $this->clampPriceRange();
        $this->resetPage();
    }

    /**
     * وقتی کاربر داخل اینپوت عددیِ «تا» چیزی تایپ می‌کند (wire:model.live).
     */
    public function updatedMaxPrice(): void
    {
        $this->clampPriceRange();
        $this->resetPage();
    }

    /**
     * صدا زده می‌شود از اسلایدرِ Alpine، فقط یک‌بار در لحظه‌ی رها کردن دستگیره
     * (نه در حین درگ کردن) تا درخواست‌های شبکه‌ی اضافی ایجاد نشود.
     */
    public function updatePriceRange(int $min, int $max): void
    {
        $this->minPrice = $min;
        $this->maxPrice = $max;
        $this->clampPriceRange();
        $this->resetPage();
    }

    /**
     * تضمین می‌کند minPrice از maxPrice عبور نکند و هر دو داخل بازه‌ی [priceFloor, priceCeil] بمانند.
     */
    private function clampPriceRange(): void
    {
        if ($this->minPrice !== null) {
            $this->minPrice = max($this->priceFloor, min($this->minPrice, $this->priceCeil));
        }

        if ($this->maxPrice !== null) {
            $this->maxPrice = max($this->priceFloor, min($this->maxPrice, $this->priceCeil));
        }

        if ($this->minPrice !== null && $this->maxPrice !== null && $this->minPrice > $this->maxPrice) {
            [$this->minPrice, $this->maxPrice] = [
                min($this->minPrice, $this->maxPrice),
                max($this->minPrice, $this->maxPrice),
            ];
        }
    }

    public function updatedSelectedCategories(): void { $this->resetPage(); }
    public function updatedSelectedBrands(): void { $this->resetPage(); }
    public function updatedOnlyInStock(): void { $this->resetPage(); }
    public function updatedOnlyDiscounted(): void { $this->resetPage(); }
    public function updatedOnlyNew(): void { $this->resetPage(); }

    public function toggleFavorite(int $productId): void
    {
        if (! Auth::check()) {
            $this->dispatch('notify', type: 'error', message: 'برای افزودن به علاقه‌مندی ابتدا وارد شوید.');
            return;
        }

        $existing = DB::table('likes')
            ->where('user_id', Auth::id())
            ->where('likeable_type', Product::class)
            ->where('likeable_id', $productId)
            ->first();

        if ($existing) {
            DB::table('likes')->where('id', $existing->id)->delete();
        } else {
            DB::table('likes')->insert([
                'user_id' => Auth::id(),
                'likeable_type' => Product::class,
                'likeable_id' => $productId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // چون likes روی favorites تأثیر می‌گذارد و آن پراپرتی هم Computed و کش‌شده در همین درخواست است، پاکش می‌کنیم
        unset($this->favoriteProductIds);
    }

    /**
     * افزودن به سبد خرید.
     * توجه: جدول cart_items ستون variant_id ندارد (فقط product_id). چون این صفحه واریانت مشخصی را
     * پیشنهاد می‌دهد (دیفالت/ارزان‌ترین موجود)، شناسه‌ی واریانت را داخل ستون JSON با نام attributes
     * ذخیره می‌کنیم. اگر بعداً بخواهید محصولاتِ چند-واریانته را درست جمع بزنید، پیشنهادم اضافه‌کردن
     * ستون variant_id به cart_items است.
     */
    public function addToCart(int $variantId): void
    {
        if (! Auth::check()) {
            $this->dispatch('alert', type: 'error', message: 'برای خرید ابتدا وارد شوید.');
            return;
        }

        $variant = ProductVariant::query()->find($variantId);

        if (! $variant) {
            $this->dispatch('alert', type: 'error', message: 'این کالا در دسترس نیست.');
            return;
        }

        $stock = $this->stockForVariant($variant->id);
        if ($stock <= 0) {
            $this->dispatch('alert', type: 'error', message: 'موجودی این کالا تمام شده است.');
            return;
        }

        $cartId = DB::table('carts')
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->value('id');

        if (! $cartId) {
            $cartId = DB::table('carts')->insertGetId([
                'user_id' => Auth::id(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        [$finalPrice] = $this->finalPriceForVariant($variant->id, $variant->price, $variant->product_id, $variant->product->brand_id ?? null);

        DB::table('cart_items')->insert([
            'cart_id' => $cartId,
            'product_id' => $variant->product_id,
            'quantity' => 1,
            'price' => $finalPrice,
            'attributes' => json_encode(['variant_id' => $variant->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->dispatch('cart-updated');
        $this->dispatch('alert', type: 'success', message: 'به سبد خرید اضافه شد.');
    }

    /**
     * موجودی واقعی یک واریانت = مجموع (quantity - reserved_quantity) در انبارهای فعال.
     */
    private function stockForVariant(int $variantId): int
    {
        return (int) DB::table('inventory_items')
            ->where('product_variant_id', $variantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->selectRaw('SUM(quantity - reserved_quantity) as stock')
            ->value('stock');
    }


    private function finalPriceForVariant(int $variantId, int $basePrice, int $productId, ?int $brandId): array
    {
        if ($basePrice <= 0 || $this->activeDiscounts->isEmpty()) {
            return [$basePrice, 0];
        }

        $productCategoryIds = DB::table('category_product')
            ->where('product_id', $productId)
            ->pluck('category_id')
            ->all();

        $bestPrice = $basePrice;

        foreach ($this->activeDiscounts as $discount) {
            $targets = $this->discountTargetsByDiscount->get($discount->id, collect());

            $matches = $targets->contains(function ($target) use ($productId, $brandId, $productCategoryIds) {
                return match ($target->target_type) {
                    'App\\Models\\Product' => (int) $target->target_id === $productId,
                    'App\\Models\\Brand' => $brandId && (int) $target->target_id === $brandId,
                    'App\\Models\\Category' => in_array((int) $target->target_id, $productCategoryIds, true),
                    default => false,
                };
            });

            if (! $matches) {
                continue;
            }

            $off = $discount->type === self::DISCOUNT_TYPE_PERCENT
                ? $basePrice * ($discount->value / 100)
                : $discount->value;

            if ($discount->maximum_discount) {
                $off = min($off, $discount->maximum_discount);
            }

            $off = min($off, $basePrice);
            $candidatePrice = (int) round($basePrice - $off);

            if ($candidatePrice < $bestPrice) {
                $bestPrice = $candidatePrice;
            }
        }

        $percent = $bestPrice < $basePrice
            ? (int) round((($basePrice - $bestPrice) / $basePrice) * 100)
            : 0;

        return [$bestPrice, $percent];
    }

    /**
     * از میان واریانت‌های یک محصول، واریانتِ پیش‌فرض (is_default) را انتخاب می‌کند؛
     * اگر پیش‌فرض موجود نبود، ارزان‌ترین واریانتِ موجود در انبار را برمی‌گرداند.
     */
    private function pickDisplayVariant(Collection $variants, array $stockByVariant): ?object
    {
        $active = $variants->where('status', 1);

        if ($active->isEmpty()) {
            return null;
        }

        $default = $active->firstWhere('is_default', 1);

        if ($default && ($stockByVariant[$default->id] ?? 0) > 0) {
            return $default;
        }

        $cheapestInStock = $active
            ->filter(fn ($v) => ($stockByVariant[$v->id] ?? 0) > 0)
            ->sortBy('price')
            ->first();

        if ($cheapestInStock) {
            return $cheapestInStock;
        }

        // چیزی موجود نیست؛ همان دیفالت (یا ارزان‌ترین) را برای نمایش "ناموجود" برمی‌گردانیم
        return $default ?? $active->sortBy('price')->first();
    }

    /**
     * کوئری اصلی محصولات با اعمال همه‌ی فیلترها و مرتب‌سازی.
     */
    private function baseQuery()
    {
        $query = Product::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->with(['variants', 'media', 'brand'])
            ->whereHas('categories', function ($q) {
                $q->whereIn('categories.id', $this->categoryIds);
            });

        if (! empty($this->selectedCategories)) {
            $query->whereHas('categories', function ($q) {
                $q->whereIn('categories.id', $this->selectedCategories);
            });
        }

        if (! empty($this->selectedBrands)) {
            $query->whereIn('brand_id', $this->selectedBrands);
        }

        if ($this->onlyNew) {
            $query->where('created_at', '>=', now()->subDays(7));
        }

        // نارروینگِ امن قیمت (فقط کف): توضیح کامل بالای کلاس، نکته‌ی ۵.
        // عمداً سقف قیمت اینجا اعمال نمی‌شود؛ چون finalPrice می‌تواند به‌خاطر تخفیف،
        // خیلی کمتر از basePrice باشد و یک محدودیت SQL روی سقفِ basePrice محصولات
        // تخفیف‌خورده‌ی گران را به‌غلط از نتیجه حذف می‌کند. فیلترِ دقیقِ سقف داخل
        // viewProducts() روی final_price انجام می‌شود.
        if ($this->minPrice !== null) {
            $query->whereHas('variants', function ($q) {
                $q->where('status', 1)
                    ->whereNull('deleted_at')
                    ->where('price', '>=', $this->minPrice);
            });
        }

        switch ($this->sort) {
            case 'sales':
                $salesQuery = DB::table('product_variants')
                    ->join('order_items', 'product_variants.id', '=', 'order_items.variant_id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->whereNotIn('orders.status', ['cancelled'])
                    ->select('product_variants.product_id', DB::raw('SUM(order_items.quantity) as total_sales'))
                    ->groupBy('product_variants.product_id');

                $query->leftJoinSub($salesQuery, 'sales', function ($join) {
                    $join->on('products.id', '=', 'sales.product_id');
                })
                    ->select('products.*')
                    ->selectRaw('COALESCE(sales.total_sales, 0) as total_sales')
                    ->orderByDesc('total_sales');
                break;

            case 'cheap':
                // توجه: این مرتب‌سازی بر اساس قیمتِ پایه‌ی ارزان‌ترین واریانت است، نه لزوماً قیمت نهایی
                // بعد از اعمال تخفیف‌های جدول discounts (چون آن محاسبه سطح-SQL نیست). برای دقتِ ۱۰۰٪
                // پیشنهاد می‌شود یک ستون cached final_price روی product_variants نگه‌داری و در ثبت/ویرایش
                // تخفیف به‌روزرسانی شود.
                $priceQuery = DB::table('product_variants')
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->select('product_id', DB::raw('MIN(price) as min_price'))
                    ->groupBy('product_id');

                $query->joinSub($priceQuery, 'prices', function ($join) {
                    $join->on('products.id', '=', 'prices.product_id');
                })
                    ->select('products.*')
                    ->selectRaw('prices.min_price')
                    ->orderBy('prices.min_price', 'asc');
                break;

            default:
                $query->latest('products.created_at');
                break;
        }

        return $query;
    }

    /**
     * صفحه‌ی جاری محصولات (بدون فیلترهای onlyInStock/onlyDiscounted/minPrice/maxPrice که بعد از
     * محاسبه‌ی قیمت نهایی اعمال می‌شوند - چون به داده‌ی محاسبه‌شده وابسته‌اند، نه ستون خام دیتابیس).
     */
    #[Computed]
    public function paginator(): LengthAwarePaginator
    {
        return $this->baseQuery()->paginate(12);
    }

    /**
     * شناسه‌ی محصولاتی که کاربر لاگین‌شده لایک کرده، محدود به محصولات همین صفحه.
     */
    #[Computed]
    public function favoriteProductIds(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return DB::table('likes')
            ->where('user_id', Auth::id())
            ->where('likeable_type', Product::class)
            ->whereIn('likeable_id', $this->paginator->pluck('id'))
            ->pluck('likeable_id')
            ->all();
    }

    /**
     * ردیف‌های آماده‌ی نمایش برای صفحه‌ی جاری: واریانت انتخابی، قیمت نهایی، درصد تخفیف،
     * موجودی واقعی، تصویر، نوع کارت (transparent/background) و وضعیت علاقه‌مندی هر محصول.
     */
    #[Computed]
    public function viewProducts(): Collection
    {
        $paginated = $this->paginator;

        $variantIds = $paginated->flatMap(fn ($p) => $p->variants->pluck('id'))->all();

        $stockByVariant = $variantIds
            ? DB::table('inventory_items')
                ->whereIn('product_variant_id', $variantIds)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->selectRaw('product_variant_id, SUM(quantity - reserved_quantity) as stock')
                ->groupBy('product_variant_id')
                ->pluck('stock', 'product_variant_id')
                ->map(fn ($s) => (int) $s)
                ->all()
            : [];

        $rows = $paginated->getCollection()->map(function ($product) use ($stockByVariant) {
            $variant = $this->pickDisplayVariant($product->variants, $stockByVariant);

            $price = 0;
            $finalPrice = 0;
            $discountPercent = 0;
            $stock = 0;

            if ($variant) {
                $price = (int) $variant->price;
                $stock = $stockByVariant[$variant->id] ?? 0;


                [$finalPrice, $discountPercent] = $this->finalPriceForVariant(
                    $variant->id,
                    $price,
                    $product->id,
                    $product->brand_id
                );

                // اگر خودِ واریانت هم compare_price داشت و از finalPrice پایین‌تر بود، همان compare_price
                // به‌عنوان «قیمت قبل از تخفیف» برای خط‌خورده نمایش داده می‌شود.

                if ($variant->compare_price && $variant->compare_price > $finalPrice) {
                    $original = (int) $variant->compare_price;
                    $discountPercent = max($discountPercent, (int) round((($original - $finalPrice) / $original) * 100));

                }
            }

            $image = $product->media->firstWhere('collection', 'featured_image');

            // تشخیص نوع کارت از روی mime_type همان تصویر (بدون نیاز به ستون جدید در دیتابیس).
            // jpeg/jpg هرگز آلفا-چنل ندارد پس قطعاً Background است؛ png/webp/gif معمولاً برای
            // تصاویر برش‌خورده(cutout)ی محصول استفاده می‌شوند، پس Transparent در نظر گرفته می‌شوند.
            // این یک heuristic روی داده‌ی موجود است، نه تشخیص قطعیِ آلفا-چنل.
            $imageStyle = 'background';
            if ($image && in_array(strtolower((string) $image->mime_type), ['image/png', 'image/webp', 'image/gif'], true)) {
                $imageStyle = 'transparent';
            }

            return (object) [
                'product' => $product,
                'variant' => $variant,
                'price' => $price,
                'compare_price' => $variant->compare_price ?? null,
                'final_price' => $finalPrice,
                'discount_percent' => $discountPercent,
                'stock' => $stock,
                'image' => $image,
                'image_style' => $imageStyle,
                'is_favorited' => in_array($product->id, $this->favoriteProductIds, true),
            ];
        });

        // فیلترهای «فقط موجود»، «فقط تخفیف‌دار» و بازه‌ی قیمت، چون به قیمت/موجودیِ محاسبه‌شده
        // وابسته‌اند، بعد از map روی همین کالکشن اعمال می‌شوند (نه در کوئری اصلی).
        if ($this->onlyInStock) {
            $rows = $rows->filter(fn ($p) => $p->stock > 0);
        }

        if ($this->onlyDiscounted) {
            $rows = $rows->filter(fn ($p) => $p->discount_percent > 0);
        }

        if ($this->minPrice !== null || $this->maxPrice !== null) {
            $rows = $rows->filter(function ($p) {
                if ($this->minPrice !== null && $p->final_price < $this->minPrice) {
                    return false;
                }

                if ($this->maxPrice !== null && $p->final_price > $this->maxPrice) {
                    return false;
                }

                return true;
            });
        }

        return $rows->values();
    }
};
?>

<div>

    <section class="relative py-16 transition-colors duration-700">

        <div class="container">

            <div class="mb-12">

                {{-- Breadcrumb --}}
                <nav
                    class="flex items-center gap-2 text-[10px] font-black text-gray-400 mb-6
               bg-white/30 dark:bg-white/[0.02] w-fit px-4 py-2 rounded-full
               border border-white/40 dark:border-white/5 backdrop-blur-md"
                    dir="rtl"
                >

                    <a href="{{ route('home') }}"
                       class="hover:text-brown-500 transition-colors">
                        خانه
                    </a>

                    @if($category->parent)
                        <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 "
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path d="M15 19l-7-7 7-7"
                                  stroke-width="3"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"/>
                        </svg>

                        <a href="{{ route('home', $category->parent->slug) }}"
                           class="hover:text-brown-500 transition-colors">
                            {{ $category->parent->title }}
                        </a>
                    @endif

                    <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 "
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                        <path d="M15 19l-7-7 7-7"
                              stroke-width="3"
                              stroke-linecap="round"
                              stroke-linejoin="round"/>
                    </svg>

                    <span class="text-brown-600 dark:text-brown-400">
            {{ $category->title }}
        </span>

                </nav>


                {{-- Title + Sort --}}
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8">

                    {{-- Title --}}
                    <div class="relative">

                        <div class="absolute -right-4 top-0 w-1 h-12
                        bg-brown-500 rounded-full blur-[2px]"></div>

                        <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">
                            {{ $category->title }}
                        </h1>

                        <div class="flex items-center gap-2 mt-3">

                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>

                            <p class="text-xs text-gray-500 dark:text-gray-400 font-bold">
                                نمایش
                                <span class="text-gray-800 dark:text-white">
                        {{ $this->paginator->total() }}
                    </span>
                                محصول موجود
                            </p>

                        </div>

                    </div>


                    {{-- Sort --}}
                    <div
                        class="flex items-center gap-1 bg-white/40 dark:bg-white/[0.03]
                   backdrop-blur-md border border-white/40 dark:border-white/10
                   p-2 rounded-[1.8rem] shadow-lg shadow-gray-200/40
                   dark:shadow-none"
                    >

                        <div class="flex items-center px-4 gap-2">

                            <svg class="w-4 h-4 text-brown-500"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"
                                />
                            </svg>

                            <span class="text-[10px] font-black text-gray-400 whitespace-nowrap">
                    مرتب‌سازی:
                </span>

                        </div>


                        <div class="flex items-center gap-1">

                            {{-- جدیدترین --}}
                            <button
                                wire:click="setSort('latest')"
                                class="px-5 py-2.5 rounded-[1.2rem] text-[11px] font-black
                           transition-all active:scale-95
                           {{ $sort === 'latest'
                                ? 'bg-brown-500 text-white shadow-lg shadow-brown-500/25'
                                : 'text-gray-500 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-white/5'
                           }}"
                            >
                                جدیدترین
                            </button>


                            {{-- پرفروش‌ترین --}}
                            <button
                                wire:click="setSort('sales')"
                                class="px-5 py-2.5 rounded-[1.2rem] text-[11px] font-black
                           transition-all active:scale-95
                           {{ $sort === 'sales'
                                ? 'bg-brown-500 text-white shadow-lg shadow-brown-500/25'
                                : 'text-gray-500 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-white/5'
                           }}"
                            >
                                پرفروش‌ترین
                            </button>


                            {{-- ارزان‌ترین --}}
                            <button
                                wire:click="setSort('cheap')"
                                class="px-5 py-2.5 rounded-[1.2rem] text-[11px] font-black
                           transition-all active:scale-95
                           {{ $sort === 'cheap'
                                ? 'bg-brown-500 text-white shadow-lg shadow-brown-500/25'
                                : 'text-gray-500 dark:text-gray-400 hover:bg-white/60 dark:hover:bg-white/5'
                           }}"
                            >
                                ارزان‌ترین
                            </button>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Filter Showing in Responsive Break Point -->
            <div class="fixed bottom-28 right-6 z-[95] lg:hidden">
                <button onclick="toggleFilters(true)" class="flex items-center justify-center w-14 h-14 bg-white/40 dark:bg-white/[0.05] backdrop-blur-md text-brown-600 rounded-2xl shadow-lg border border-white/60 dark:border-white/10 active:scale-90 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                    </svg>
                </button>
            </div>

            <div id="filter-overlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[140] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

            {{-- Offcanvas + Desktop aside هر دو از یک partial فیلترها استفاده می‌کنند تا کد تکراری نشود --}}
            <div id="filter-offcanvas" class="fixed top-0 right-0 h-full w-[85%] max-w-[380px] bg-white/30 dark:bg-black/40 backdrop-blur-[30px] z-[150] translate-x-full transition-transform duration-500 ease-in-out border-l border-white/40 dark:border-white/10 shadow-lg lg:hidden">
                <div class="flex flex-col h-full">
                    <div class="p-6 flex items-center justify-between border-b border-white/40 dark:border-white/5 bg-white/20 dark:bg-white/[0.02]">
                        <h2 class="text-lg font-black text-gray-900 dark:text-white">فیلترها</h2>
                        <button onclick="toggleFilters(false)" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/40 dark:bg-white/5 text-gray-600 dark:text-gray-300 border border-white/60 dark:border-white/10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-8 custom-scrollbar">
                        @include('components.main.sections.category-filters')
                    </div>

                    <div class="p-6 bg-white/30 dark:bg-black/20 border-t border-white/40 dark:border-white/5">
                        <button onclick="toggleFilters(false)" class="w-full bg-brown-600 text-white py-4 rounded-[1.8rem] font-black shadow-lg shadow-brown-600/30 active:scale-95 transition-all">اعمال فیلترها</button>
                    </div>
                </div>
            </div>


            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

                <aside class="hidden lg:block lg:col-span-1 relative">
                    <div class="sticky top-10 space-y-6">
                        @include('components.main.sections.category-filters')
                    </div>
                </aside>

                <div class="lg:col-span-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">

                        @forelse($this->viewProducts as $row)

                            @php
                                $product = $row->product;
                                $variant = $row->variant;
                            @endphp

                            @if($row->image_style === 'transparent')

                                {{-- ===================== Card Type 1 : Transparent ===================== --}}
                                <div class="group relative h-full pt-12">

                                    <div
                                        class="absolute inset-0
                               bg-white/80 dark:bg-[#0a0a0a]/40
                               backdrop-blur-[20px]
                               rounded-[3rem]
                               border border-gray-100 dark:border-white/[0.08]
                               shadow-[0_20px_50px_rgba(0,0,0,0.02)]
                               transition-all duration-700
                               group-hover:border-brown-500/50
                               dark:group-hover:shadow-[0_0_60px_rgba(37,99,235,0.12)]"
                                    ></div>


                                    <div
                                        class="relative p-7 flex flex-col h-full z-10
                               transition-transform duration-500
                               group-hover:-translate-y-4"
                                    >

                                        {{-- Discount --}}
                                        @if($row->discount_percent > 0)

                                            <div class="absolute -top-6 -right-2 z-20">

                                                <div
                                                    class="bg-secondary-500 dark:bg-[#ff1744]
                                       text-white text-[12px] font-black
                                       w-12 h-12 rounded-[1.2rem]
                                       flex items-center justify-center
                                       shadow-lg shadow-red-500/40
                                       dark:shadow-[#ff1744]/30
                                       rotate-12
                                       group-hover:rotate-0
                                       transition-all duration-500
                                       border-2 border-white dark:border-white/20"
                                                >
                                                    {{ $row->discount_percent }}٪
                                                </div>

                                            </div>

                                        @endif


                                        {{-- Image --}}
                                        <div class="relative mb-8 flex items-center justify-center min-h-[180px]">

                                            <div
                                                class="absolute w-40 h-40
                                   bg-brown-500/20 dark:bg-indigo-500/20
                                   blur-[70px] rounded-full
                                   opacity-0 group-hover:opacity-100
                                   transition-all duration-1000"
                                            ></div>


                                            @if($row->image)

                                                <a href="{{ route('product.show', $product->slug) }}">
                                                    <img
                                                        src="{{ asset('storage/' . $row->image->file_path) }}"
                                                        class="relative z-10 w-full h-44 object-contain
                                           transition-all duration-700
                                           group-hover:scale-110
                                           group-hover:drop-shadow-[0_15px_35px_rgba(37,99,235,0.3)]"
                                                        alt="{{ $product->title }}"
                                                    >
                                                </a>

                                            @else

                                                <div
                                                    class="relative z-10 w-full h-44
                                       flex items-center justify-center
                                       text-gray-300 dark:text-zinc-700"
                                                >
                                                    <svg class="w-20 h-20"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M4 16l4-4 4 4 4-5 4 5M4 19h16M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"
                                                        />
                                                    </svg>
                                                </div>

                                            @endif


                                            {{-- Actions --}}
                                            <div
                                                class="absolute top-0 -left-2 z-20
                                   flex flex-col gap-3
                                   opacity-0 group-hover:opacity-100
                                   -translate-x-4 group-hover:translate-x-0
                                   transition-all duration-500"
                                            >

                                                {{-- Quick View --}}
                                                <div class="relative flex items-center group/tooltip">

                                                    <a
                                                        href="{{ route('product.show', $product->slug) }}"
                                                        class="w-10 h-10 quick-view-btn
                                           bg-white/90 dark:bg-zinc-900/90
                                           backdrop-blur-md
                                           text-gray-900 dark:text-white
                                           rounded-xl flex items-center justify-center
                                           shadow-sm border border-white dark:border-white/10
                                           hover:bg-secondary-500
                                           hover:text-white transition-all"
                                                    >

                                                        <svg class="w-5 h-5"
                                                             fill="none"
                                                             stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                            />
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M2.458 12C3.732 7.943 7.523 5 12 5
                                               c4.478 0 8.268 2.943 9.542 7
                                               -1.274 4.057-5.064 7-9.542 7
                                               -4.477 0-8.268-2.943-9.542-7z"
                                                            />
                                                        </svg>

                                                    </a>

                                                    <span
                                                        class="absolute right-full mr-3 whitespace-nowrap
                                           bg-gray-900 dark:bg-zinc-800 text-white
                                           text-[10px] py-1.5 px-3 rounded-lg
                                           opacity-0 pointer-events-none
                                           translate-x-2
                                           group-hover/tooltip:opacity-100
                                           group-hover/tooltip:translate-x-0
                                           transition-all duration-300
                                           border border-white/5"
                                                    >
                                    مشاهده سریع
                                </span>

                                                </div>


                                                {{-- Favorite --}}
                                                <div class="relative flex items-center group/tooltip">

                                                    <button
                                                        type="button"
                                                        wire:click="toggleFavorite({{ $product->id }})"
                                                        class="w-10 h-10
                                           bg-white/90 dark:bg-zinc-900/90
                                           backdrop-blur-md
                                           rounded-xl flex items-center justify-center
                                           shadow-sm border border-white dark:border-white/10
                                           transition-all
                                           {{ $row->is_favorited ? 'text-red-500' : 'text-gray-900 dark:text-white hover:text-red-500' }}"
                                                    >

                                                        <svg class="w-5 h-5"
                                                             fill="{{ $row->is_favorited ? 'currentColor' : 'none' }}"
                                                             stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M4.318 6.318a4.5 4.5 0 000 6.364
                                               L12 20.364l7.682-7.682
                                               a4.5 4.5 0 00-6.364-6.364
                                               L12 7.636l-1.318-1.318
                                               a4.5 4.5 0 00-6.364 0z"
                                                            />
                                                        </svg>

                                                    </button>

                                                    <span
                                                        class="absolute right-full mr-3 whitespace-nowrap
                                           bg-gray-900 dark:bg-zinc-800 text-white
                                           text-[10px] py-1.5 px-3 rounded-lg
                                           opacity-0 pointer-events-none
                                           translate-x-2
                                           group-hover/tooltip:opacity-100
                                           group-hover/tooltip:translate-x-0
                                           transition-all duration-300
                                           border border-white/5"
                                                    >
                                    {{ $row->is_favorited ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی' }}
                                </span>

                                                </div>

                                            </div>

                                        </div>


                                        {{-- Title --}}
                                        <a href="{{ route('products.show', $product->slug) }}">
                                            <h3
                                                class="text-[15px] font-black
                                                       text-gray-800 dark:text-zinc-100
                                                       mb-6 line-clamp-2 leading-7 h-14
                                                       group-hover:text-brown-600
                                                       dark:group-hover:text-brown-400
                                                       transition-colors"
                                            >
                                                {{ $product->title }}
                                            </h3>
                                        </a>


                                        {{-- Price --}}
                                        <div
                                            class="flex items-center justify-between
                               mt-auto pt-5
                               border-t border-gray-100 dark:border-white/5"
                                        >

                                            <div class="flex flex-col gap-1">

                                                @if($row->discount_percent > 0)

                                                    <span
                                                        class="text-[11px] text-gray-400 dark:text-zinc-500
                                           line-through tabular-nums leading-none"
                                                    >
                                    {{ number_format($row->compare_price && $row->compare_price > $row->final_price ? $row->compare_price : $row->price) }}
                                </span>

                                                @endif


                                                <div class="flex items-center gap-1.5">

                                <span
                                    class="text-2xl font-black
                                           text-gray-900 dark:text-white
                                           tracking-tighter tabular-nums"
                                >
                                    {{ number_format($row->final_price) }}
                                </span>

                                                    <span
                                                        class="text-[10px] text-gray-400
                                           dark:text-zinc-500 font-bold"
                                                    >
                                    تومان
                                </span>

                                                </div>

                                            </div>


                                            {{-- Add To Cart --}}
                                            <a
                                                href="{{ route('products.show', $variant->product->slug) }}"
                                                class="w-10 h-10
               rounded-xl
               bg-gray-100 dark:bg-white/5
               text-gray-500 dark:text-gray-300
               flex items-center justify-center
               hover:bg-brown-500 hover:text-white
               hover:scale-110
               transition-all"
                                                title="مشاهده محصول"
                                            >
                                                <svg
                                                    class="w-5 h-5 rtl:rotate-180"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 5l7 7-7 7"
                                                    />
                                                </svg>
                                            </a>

                                        </div>

                                    </div>

                                </div>

                            @else

                                {{-- ===================== Card Type 2 : Background ===================== --}}
                                <div class="group relative h-full">
                                    <div
                                        class="relative h-full flex flex-col
                                       rounded-[2rem]
                                       overflow-hidden
                                       border border-gray-100 dark:border-white/[0.08]
                                       shadow-[0_20px_50px_rgba(0,0,0,0.06)] dark:shadow-none
                                       bg-white dark:bg-[#0a0a0a]/40
                                       transition-all duration-500
                                       group-hover:-translate-y-2
                                       group-hover:shadow-xl
                                       group-hover:border-brown-400/40
                                       dark:group-hover:shadow-[0_0_50px_rgba(120,80,60,0.15)]"
                                    >
                                        {{-- Image + Gradient --}}
                                        <div class="relative w-full h-56 sm:h-60 shrink-0 overflow-hidden">

                                            @if($row->image)
                                                <a href="{{ route('product.show', $product->slug) }}">
                                                    <img
                                                        src="{{ asset('storage/' . $row->image->file_path) }}"
                                                        class="absolute inset-0 w-full h-full object-cover
                                               transition-transform duration-700
                                               group-hover:scale-110"
                                                        alt="{{ $product->title }}"
                                                    >
                                                </a>
                                            @else
                                                <div class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-white/5 text-gray-300 dark:text-zinc-700">
                                                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4-4 4 4 4-5 4 5M4 19h16M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/>
                                                    </svg>
                                                </div>
                                            @endif

                                            {{-- Gradient قهوه‌ای برای خوانایی عنوان روی تصویر، هماهنگ با دارک/لایت --}}
                                            <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-brown-900/95 via-brown-800/55 to-transparent dark:from-brown-950/95 dark:via-brown-900/55 pointer-events-none"></div>

                                            {{-- Discount badge --}}
                                            @if($row->discount_percent > 0)
                                                <div class="absolute top-4 right-4 z-20 bg-secondary-500 dark:bg-[#ff1744] text-white text-[11px] font-black w-11 h-11 rounded-2xl flex items-center justify-center shadow-lg border-2 border-white/80 dark:border-white/20">
                                                    {{ $row->discount_percent }}٪
                                                </div>
                                            @endif

                                            {{-- Actions --}}
                                            <div class="absolute top-4 left-4 z-20 flex flex-col gap-2 opacity-0 group-hover:opacity-100 -translate-y-2 group-hover:translate-y-0 transition-all duration-500">

                                                <div class="relative flex items-center group/tooltip">
                                                    <a
                                                        href="{{ route('product.show', $product->slug) }}"
                                                        class="w-9 h-9 quick-view-btn bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md text-gray-900 dark:text-white rounded-xl flex items-center justify-center shadow-sm hover:bg-secondary-500 hover:text-white transition-all"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </a>
                                                    <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 dark:bg-zinc-800 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-300 border border-white/5">
                                                        مشاهده سریع
                                                    </span>
                                                </div>

                                                <div class="relative flex items-center group/tooltip">
                                                    <button
                                                        type="button"
                                                        wire:click="toggleFavorite({{ $product->id }})"
                                                        class="w-9 h-9 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md rounded-xl flex items-center justify-center shadow-sm transition-all {{ $row->is_favorited ? 'text-red-500' : 'text-gray-900 dark:text-white hover:text-red-500' }}"
                                                    >
                                                        <svg class="w-4 h-4" fill="{{ $row->is_favorited ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                                        </svg>
                                                    </button>
                                                    <span class="absolute right-full mr-3 whitespace-nowrap bg-gray-900 dark:bg-zinc-800 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 pointer-events-none translate-x-2 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-x-0 transition-all duration-300 border border-white/5">
                                                        {{ $row->is_favorited ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی' }}
                                                    </span>
                                                </div>
                                            </div>

                                            {{-- Title روی گرادینت --}}
                                            <div class="absolute inset-x-0 bottom-0 p-4 z-10">
                                                <a href="{{ route('products.show', $product->slug) }}">
                                                    <h3 class="text-sm font-black text-white mb-0 line-clamp-2 leading-6 drop-shadow-[0_2px_4px_rgba(0,0,0,0.4)]">
                                                        {{ $product->title }}
                                                    </h3>
                                                </a>
                                            </div>
                                        </div>

                                        {{-- Price --}}
                                        <div class="p-5 mt-auto flex items-center justify-between border-t border-gray-100 dark:border-white/5">
                                            <div class="flex flex-col gap-1">
                                                @if($row->discount_percent > 0)
                                                    <span class="text-[11px] text-gray-400 dark:text-zinc-500 line-through tabular-nums leading-none">
                                                        {{ number_format($row->compare_price && $row->compare_price > $row->final_price ? $row->compare_price : $row->price) }}
                                                    </span>
                                                @endif
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-xl font-black text-gray-900 dark:text-white tracking-tighter tabular-nums">{{ number_format($row->final_price) }}</span>
                                                    <span class="text-[10px] text-gray-400 dark:text-zinc-500 font-bold">تومان</span>
                                                </div>
                                            </div>

                                            <a
                                                href="{{ route('products.show', $variant->product->slug) }}"
                                                class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-300 flex items-center justify-center hover:bg-brown-500 hover:text-white hover:scale-110 transition-all"
                                                title="مشاهده محصول"
                                            >
                                                <svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            @endif

                        @empty

                            <div class="col-span-full py-20 text-center">

                                <div
                                    class="w-20 h-20 mx-auto mb-5
                       rounded-3xl
                       bg-gray-100 dark:bg-white/5
                       flex items-center justify-center"
                                >
                                    <svg class="w-10 h-10 text-gray-300"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.5"
                                            d="M9.172 16.172a4 4 0 015.656 0
                           M9 10h.01M15 10h.01
                           M21 12a9 9 0 11-18 0
                           9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>

                                <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">
                                    محصولی پیدا نشد
                                </h3>

                                <p class="text-xs text-gray-400 mt-2">
                                    در این دسته‌بندی محصولی با شرایط انتخاب‌شده وجود ندارد.
                                </p>

                            </div>

                        @endforelse

                    </div>

                    {{-- Pagination واقعی (بر پایه‌ی paginate(12)) --}}
                    <div class="mt-16 flex items-center justify-center">
                        {{ $this->paginator->onEachSide(1)->links() }}
                    </div>

                </div>
            </div>


        </div>

    </section>

</div>
