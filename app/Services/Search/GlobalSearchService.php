<?php

namespace App\Services\Search;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * جستجوی سراسری فروشگاه (مشترک بین جستجوی Navbar و صفحه /search)
 * فقط مواردی برگردانده می‌شوند که در Frontend قابل مشاهده‌اند (فعال، منتشرشده، حذف‌نشده).
 * جستجو با LIKE روی ستون‌های موجود انجام می‌شود و همه کوئری‌ها limit / paginate دارند.
 */
class GlobalSearchService
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    // ترتیب نمایش گروه‌ها
    public const TYPES = [
        'products' => 'محصولات',
        'brands' => 'برندها',
        'categories' => 'دسته‌بندی‌ها',
        'tags' => 'تگ‌ها',
        'articles' => 'مقالات',
        'pages' => 'صفحات',
    ];

    public const TYPE_LABELS = [
        'products' => 'محصول',
        'brands' => 'برند',
        'categories' => 'دسته‌بندی',
        'tags' => 'تگ',
        'articles' => 'مقاله',
        'pages' => 'صفحه',
    ];

    /**
     * یکسان‌سازی عبارت: حذف فاصله‌های اضافه و تبدیل حروف عربی به فارسی
     */
    public function normalize(?string $term): string
    {
        $term = str_replace(['ي', 'ك', 'ة', '‌'], ['ی', 'ک', 'ه', ' '], (string) $term);
        $term = preg_replace('/\s+/u', ' ', trim($term));

        return mb_substr($term, 0, self::MAX_LENGTH);
    }

    public function isSearchable(?string $term): bool
    {
        return mb_strlen($this->normalize($term)) >= self::MIN_LENGTH;
    }

    public function isValidType(?string $type): bool
    {
        return array_key_exists((string) $type, self::TYPES);
    }

    /**
     * نتایج خلاصه برای Navbar: از هر نوع حداکثر $perType مورد
     *
     * @return array{groups: array, has_more: bool}
     */
    public function preview(string $term, int $perType = 4): array
    {
        $term = $this->normalize($term);
        $groups = [];
        $hasMore = false;

        if (! $this->isSearchable($term)) {
            return ['groups' => [], 'has_more' => false];
        }

        foreach (array_keys(self::TYPES) as $type) {
            // یک مورد بیشتر می‌گیریم تا بفهمیم نتیجه بیشتری وجود دارد یا نه (بدون count اضافه)
            $rows = $this->query($type, $term)->limit($perType + 1)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $hasMore = $hasMore || $rows->count() > $perType;

            $groups[$type] = [
                'type' => $type,
                'label' => self::TYPES[$type],
                'items' => $rows->take($perType)->map(fn ($row) => $this->present($type, $row))->all(),
                'has_more' => $rows->count() > $perType,
            ];
        }

        return ['groups' => $groups, 'has_more' => $hasMore];
    }

    /**
     * تعداد نتایج هر نوع (برای تب‌های صفحه جستجو)
     */
    public function counts(string $term, ?string $tag = null): array
    {
        $term = $this->normalize($term);
        $counts = [];

        foreach ($this->typesFor($term, $tag) as $type) {
            $counts[$type] = $this->query($type, $term, $tag)->count();
        }

        return $counts;
    }

    /**
     * نتایج صفحه‌بندی‌شده‌ی یک نوع
     */
    public function paginate(string $type, string $term, int $perPage = 12, ?string $tag = null, string $pageName = 'page', bool $present = true): LengthAwarePaginator
    {
        $term = $this->normalize($term);

        $paginator = $this->query($type, $term, $tag)->paginate($perPage, ['*'], $pageName);

        if ($present) {
            $paginator->setCollection(
                $paginator->getCollection()->map(fn ($row) => $this->present($type, $row))
            );
        }

        return $paginator;
    }

    /**
     * چند نتیجه‌ی اول یک نوع (برای تب «همه» در صفحه جستجو)
     * $present = false => مدل‌ها (برای کارت‌های مشترک محصول / برند)
     */
    public function take(string $type, string $term, int $limit, ?string $tag = null, bool $present = true)
    {
        $rows = $this->query($type, $this->normalize($term), $tag)
            ->limit($limit)
            ->get();

        return $present
            ? $rows->map(fn ($row) => $this->present($type, $row))->all()
            : $rows;
    }

    /**
     * انواع قابل جستجو؛ وقتی فیلتر تگ فعال است فقط محتواهای تگ‌پذیر
     */
    public function typesFor(string $term, ?string $tag = null): array
    {
        if ($tag) {
            return ['products', 'articles'];
        }

        return $this->isSearchable($term) ? array_keys(self::TYPES) : [];
    }

    public function findTag(?string $slug): ?Tag
    {
        if (! $slug) {
            return null;
        }

        return Tag::where('status', true)->where('slug', $slug)->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Queries
    |--------------------------------------------------------------------------
    */

    public function query(string $type, string $term, ?string $tag = null): Builder
    {
        if (! $this->isValidType($type)) {
            throw new \InvalidArgumentException("Unknown search type [$type]");
        }

        if ($term === '') {
            // بدون عبارت (فقط فیلتر تگ) => همه محتوای قابل مشاهده‌ی همان تگ
            $query = match ($type) {
                'products' => $this->visibleProducts(),
                'articles' => $this->visibleArticles(),
                default => $this->tagsQuery('')->whereRaw('1 = 0'),
            };
        } else {
            $like = '%' . $this->escapeLike($term) . '%';

            $query = match ($type) {
                'products' => $this->productsQuery($like),
                'brands' => $this->brandsQuery($like),
                'categories' => $this->categoriesQuery($like),
                'tags' => $this->tagsQuery($like),
                'articles' => $this->articlesQuery($like),
                'pages' => $this->pagesQuery($like),
            };

            // عنوان‌هایی که با عبارت شروع می‌شوند اول نمایش داده شوند
            $table = $query->getModel()->getTable();
            $query->orderByRaw("CASE WHEN {$table}.title LIKE ? THEN 0 ELSE 1 END", [$this->escapeLike($term) . '%']);
        }

        $table = $query->getModel()->getTable();

        if ($tag && in_array($type, ['products', 'articles'], true)) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.slug', $tag)->where('tags.status', true));
        }

        return $query->orderByDesc("{$table}.id");
    }

    protected function visibleProducts(): Builder
    {
        return Product::query()
            ->active()
            ->has('variants')
            ->with(['featuredImage', 'brand:id,title', 'cheapestVariant']);
    }

    protected function productsQuery(string $like): Builder
    {
        return $this->visibleProducts()
            ->where(function ($q) use ($like) {
                $q->where('products.title', 'like', $like)
                    ->orWhere('products.short_description', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like))
                    ->orWhereHas('brand', fn ($b) => $b->where('status', true)->where('title', 'like', $like));
            });
    }

    protected function brandsQuery(string $like): Builder
    {
        return Brand::active()
            ->with('logo')
            ->withCount('activeProducts')
            ->where('title', 'like', $like);
    }

    protected function categoriesQuery(string $like): Builder
    {
        return Category::active()
            ->with(['featuredImage', 'parent:id,title'])
            ->where('title', 'like', $like);
    }

    protected function tagsQuery(string $like): Builder
    {
        return Tag::query()
            ->where('status', true)
            ->where('title', 'like', $like);
    }

    protected function visibleArticles(): Builder
    {
        return Article::active()
            ->with('featuredImage')
            ->where(function ($q) {
                $q->whereNull('articles.published_at')
                    ->orWhere('articles.published_at', '<=', now());
            });
    }

    protected function articlesQuery(string $like): Builder
    {
        return $this->visibleArticles()
            ->where(function ($q) use ($like) {
                $q->where('articles.title', 'like', $like)
                    ->orWhere('articles.short_description', 'like', $like);
            });
    }

    protected function pagesQuery(string $like): Builder
    {
        return Page::query()
            ->where('status', true)
            ->where('title', 'like', $like);
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    /**
     * تبدیل هر رکورد به یک ساختار یکسان برای نمایش
     */
    public function present(string $type, $row): array
    {
        $base = [
            'id' => $type . '-' . $row->id,
            'type' => $type,
            'type_label' => self::TYPE_LABELS[$type],
            'title' => $row->title,
            'subtitle' => null,
            'image' => null,
            'price' => null,
            'url' => '#',
        ];

        return match ($type) {
            'products' => array_merge($base, [
                'subtitle' => $row->brand?->title,
                'image' => $row->featured_image_url,
                'price' => $this->productPrice($row),
                'url' => route('products.show', $row->slug),
            ]),
            'brands' => array_merge($base, [
                'subtitle' => $row->active_products_count ? number_format($row->active_products_count) . ' محصول' : null,
                'image' => $row->logo_url,
                'url' => route('brands.show', $row->slug),
            ]),
            'categories' => array_merge($base, [
                'subtitle' => $row->parent?->title,
                'image' => $row->featured_image_url,
                'url' => route('categories.show', $row->slug),
            ]),
            // صفحه اختصاصی تگ وجود ندارد؛ محتوای دارای این تگ در صفحه جستجو نمایش داده می‌شود
            'tags' => array_merge($base, [
                'url' => route('search', ['tag' => $row->slug]),
            ]),
            'articles' => array_merge($base, [
                'subtitle' => Str::limit(strip_tags((string) $row->short_description), 90),
                'image' => $row->featured_image_url,
                'url' => route('articles.show', $row->slug),
            ]),
            'pages' => array_merge($base, [
                'url' => $row->slug === 'home' ? route('home') : route('page.show', $row->slug),
            ]),
        };
    }

    protected function productPrice(Product $product): ?int
    {
        $pricing = $product->cheapestVariant?->priceData() ?? [];

        return isset($pricing['after_discount']) ? (int) $pricing['after_discount'] : null;
    }
}
