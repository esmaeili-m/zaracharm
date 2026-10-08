<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Models\Product;
use App\Services\Catalog\CompareList;

/*
 * صفحه مقایسه محصولات
 * محصولات ستونی، مشخصات ردیفی؛ ردیف‌هایی که مقدارشان بین محصولات متفاوت است برجسته می‌شوند.
 */
new class extends Component
{
    #[On('compare-updated')]
    public function refresh(): void
    {
        unset($this->products, $this->table);
    }

    public function remove(int $productId): void
    {
        app(CompareList::class)->remove($productId);
        $this->refresh();
        $this->dispatch('compare-updated');
    }

    public function clear(): void
    {
        app(CompareList::class)->clear();
        $this->refresh();
        $this->dispatch('compare-updated');
    }

    #[Computed]
    public function products()
    {
        return app(CompareList::class)->products([
            'media',
            'brand',
            'primaryCategory',
            'ratings',
            'comments',
            'specifications',
            'variants' => fn ($q) => $q->where('status', true),
            'variants.inventoryItems.inventory',
            'variants.values.option',
        ]);
    }

    /** خلاصه قابل مقایسه هر محصول */
    protected function summary(Product $product): array
    {
        $variants = $product->variants;

        $rows = $variants->map(function ($variant) {
            $pricing = rescue(fn () => $variant->priceData(), [], false) ?: [];
            $final = (int) ($pricing['after_discount'] ?? $variant->price ?? 0);

            return [
                'final' => $final,
                'before' => !empty($pricing['has_discount']) ? (int) ($pricing['before_discount'] ?? 0) : null,
                'percent' => !empty($pricing['has_discount']) ? (int) round((float) ($pricing['discount_percent'] ?? 0)) : null,
                'stock' => $variant->availableStock(),
            ];
        })->filter(fn ($r) => $r['final'] > 0);

        // قیمت نمایشی: ارزان‌ترین تنوع موجود (یا ارزان‌ترین در صورت ناموجودی کامل)
        $inStock = $rows->filter(fn ($r) => $r['stock'] > 0);
        $best = ($inStock->isNotEmpty() ? $inStock : $rows)->sortBy('final')->first();

        // مقادیر ویژگی‌های قیمت‌ساز موجود در تنوع‌های فعال
        $options = $variants->flatMap->values
            ->filter(fn ($value) => $value->option)
            ->groupBy(fn ($value) => $value->option->title)
            ->map(fn ($values) => $values->pluck('title')->unique()->values()->implode('، '));

        $specs = $product->specifications
            ->filter(fn ($spec) => !isset($spec->pivot->status) || $spec->pivot->status)
            ->mapWithKeys(fn ($spec) => [$spec->title => $this->specValue($spec)])
            ->filter(fn ($value) => $value !== null && $value !== '');

        $ratingCount = $product->ratings->count();

        return [
            'price' => $best['final'] ?? null,
            'before' => $best['before'] ?? null,
            'percent' => $best['percent'] ?? null,
            'stock' => (int) $rows->sum('stock'),
            'variants' => $variants->count(),
            'options' => $options,
            'specs' => $specs,
            'rating' => $ratingCount ? round($product->ratings->avg('rating'), 1) : null,
            'rating_count' => $ratingCount,
            'comments' => $product->comments->count(),
        ];
    }

    protected function specValue($spec): ?string
    {
        $value = $spec->value;

        if ($value instanceof \DateTimeInterface) {
            return verta($value)->format('Y/m/d');
        }

        if (is_numeric($value) && str_contains((string) $value, '.')) {
            return rtrim(rtrim((string) $value, '0'), '.');
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * ردیف‌های جدول: [label, group, cells(per product: display), compare(per product: normalized), different]
     */
    #[Computed]
    public function table(): array
    {
        $products = $this->products;
        $summaries = $products->mapWithKeys(fn ($p) => [$p->id => $this->summary($p)]);
        $rows = [];

        $add = function (string $group, string $label, callable $cell, ?callable $key = null) use ($products, $summaries, &$rows) {
            $cells = [];
            $keys = [];

            foreach ($products as $product) {
                $cells[$product->id] = $cell($product, $summaries[$product->id]);
                $keys[$product->id] = $key ? $key($product, $summaries[$product->id]) : strip_tags((string) $cells[$product->id]);
            }

            $rows[] = [
                'group' => $group,
                'label' => $label,
                'cells' => $cells,
                'different' => $products->count() > 1 && count(array_unique(array_map(fn ($k) => trim((string) $k), $keys))) > 1,
            ];
        };

        $dash = '<span class="text-gray-300 dark:text-zinc-600">—</span>';

        // ---- قیمت و موجودی
        $add('قیمت و موجودی', 'قیمت', fn ($p, $s) => $s['price']
            ? '<span class="text-[14px] font-black text-gray-900 dark:text-white tabular-nums">' . number_format($s['price']) . '</span> <span class="text-[10px] font-bold text-gray-400">تومان</span>'
            : $dash, fn ($p, $s) => $s['price']);

        $add('قیمت و موجودی', 'قیمت قبلی / تخفیف', fn ($p, $s) => $s['before']
            ? '<span class="line-through text-gray-400 tabular-nums">' . number_format($s['before']) . '</span>'
                . ($s['percent'] ? ' <span class="inline-block mr-1 px-2 py-0.5 rounded-lg bg-red-500 text-white text-[10px] font-black">٪' . $s['percent'] . '</span>' : '')
            : '<span class="text-gray-400 text-[11px] font-bold">بدون تخفیف</span>', fn ($p, $s) => $s['before'] . '|' . $s['percent']);

        $add('قیمت و موجودی', 'موجودی', fn ($p, $s) => $s['stock'] > 0
            ? '<span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-black text-[12px]"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>موجود</span>'
                . ($s['stock'] <= 5 ? ' <span class="text-[10px] font-bold text-amber-600">(' . $s['stock'] . ' عدد باقی‌مانده)</span>' : '')
            : '<span class="inline-flex items-center gap-1.5 text-gray-400 font-black text-[12px]"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>ناموجود</span>',
            fn ($p, $s) => $s['stock'] > 0 ? ($s['stock'] <= 5 ? 'low' : 'in') : 'out');

        // ---- اطلاعات کلی
        $add('اطلاعات کلی', 'برند', fn ($p) => $p->brand?->title ? e($p->brand->title) : $dash, fn ($p) => $p->brand?->title);
        $add('اطلاعات کلی', 'دسته‌بندی', fn ($p) => $p->primaryCategory?->title ? e($p->primaryCategory->title) : $dash, fn ($p) => $p->primaryCategory?->title);
        $add('اطلاعات کلی', 'امتیاز کاربران', fn ($p, $s) => $s['rating'] !== null
            ? '<span class="inline-flex items-center gap-1 font-black text-[12px] text-gray-900 dark:text-white"><svg class="w-3.5 h-3.5 text-secondary-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><span class="tabular-nums">' . $s['rating'] . '</span><span class="text-gray-400 font-bold">از ۵</span></span> <span class="text-[10px] font-bold text-gray-400">(' . $s['rating_count'] . ' رأی)</span>'
            : '<span class="text-gray-400 text-[11px] font-bold">بدون امتیاز</span>', fn ($p, $s) => $s['rating']);
        $add('اطلاعات کلی', 'دیدگاه‌ها', fn ($p, $s) => $s['comments'] ? $s['comments'] . ' دیدگاه' : '<span class="text-gray-400 text-[11px] font-bold">بدون دیدگاه</span>', fn ($p, $s) => $s['comments']);

        // ---- ویژگی‌های قیمت‌ساز (رنگ، سایز، ...)
        $optionTitles = $summaries->flatMap(fn ($s) => $s['options']->keys())->unique()->values();
        foreach ($optionTitles as $title) {
            $add('تنوع‌های قابل انتخاب', $title, fn ($p, $s) => isset($s['options'][$title]) ? e($s['options'][$title]) : $dash, fn ($p, $s) => $s['options'][$title] ?? null);
        }

        // ---- مشخصات فنی (اجتماع مشخصات همه محصولات)
        $specTitles = $summaries->flatMap(fn ($s) => $s['specs']->keys())->unique()->values();
        foreach ($specTitles as $title) {
            $add('مشخصات فنی', $title, fn ($p, $s) => isset($s['specs'][$title]) ? e($s['specs'][$title]) : $dash, fn ($p, $s) => $s['specs'][$title] ?? null);
        }

        return $rows;
    }
};
?>

<div dir="rtl" x-data="{ onlyDiff: false }">
    <section class="container mx-auto px-4 py-10">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-4">
                <div class="w-1.5 h-10 bg-brown-600 rounded-full"></div>
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white">مقایسه محصولات</h1>
                    <p class="text-[11px] font-bold text-gray-400 mt-1">
                        {{ $this->products->count() }} از {{ CompareList::MAX }} محصول — ردیف‌های متفاوت با رنگ متمایز مشخص شده‌اند
                    </p>
                </div>
            </div>

            @if($this->products->count() > 1)
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/40 dark:bg-white/[0.03] border border-white/60 dark:border-white/10 cursor-pointer select-none">
                        <input type="checkbox" x-model="onlyDiff" class="w-4 h-4 rounded accent-brown-600">
                        <span class="text-[11px] font-black text-gray-700 dark:text-gray-200">فقط تفاوت‌ها</span>
                    </label>
                    <button type="button" wire:click="clear" wire:confirm="همه محصولات از مقایسه حذف شوند؟"
                            class="px-4 py-2.5 rounded-xl text-[11px] font-black text-gray-500 dark:text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition-colors">
                        حذف همه
                    </button>
                </div>
            @endif
        </div>

        @if($this->products->isEmpty())
            {{-- Empty --}}
            <div class="relative overflow-hidden bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-12 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-teal-500/10 flex items-center justify-center mb-5 text-teal-500">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"></path><path d="M8 21H3v-5"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>
                </div>
                <h3 class="text-sm font-black text-gray-900 dark:text-white">لیست مقایسه خالی است</h3>
                <p class="text-[11px] font-bold text-gray-400 mt-2 leading-6">در صفحه هر محصول، روی آیکون مقایسه (کنار علاقه‌مندی در گالری تصاویر) بزنید تا به این لیست اضافه شود.</p>
                <a href="{{ url('/') }}" class="inline-block mt-6 px-6 py-3 rounded-2xl bg-brown-600 text-white text-[11px] font-black shadow-lg shadow-brown-500/20">رفتن به فروشگاه</a>
            </div>
        @else
            @if($this->products->count() === 1)
                <div class="mb-6 p-4 rounded-2xl bg-teal-500/10 border border-teal-500/20 text-[11px] font-bold text-teal-700 dark:text-teal-400">
                    برای مقایسه، حداقل یک محصول دیگر اضافه کنید.
                </div>
            @endif

            <div class="relative overflow-hidden bg-white/40 dark:bg-white/[0.02] backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.04)]">
                <div class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0 text-right">
                        @php $count = $this->products->count(); @endphp

                        {{-- سرستون محصولات --}}
                        <thead>
                        <tr>
                            <th class="sticky right-0 z-20 w-28 md:w-48 min-w-28 md:min-w-48 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md p-4 align-bottom border-b border-gray-100 dark:border-white/5">
                                <span class="text-[11px] font-black text-gray-400">{{ $count }} محصول</span>
                            </th>
                            @foreach($this->products as $product)
                                <th wire:key="compare-head-{{ $product->id }}" class="min-w-[170px] md:min-w-[220px] p-4 align-top border-b border-gray-100 dark:border-white/5 font-normal">
                                    <div class="relative flex flex-col items-center text-center gap-3">
                                        <button type="button" wire:click="remove({{ $product->id }})" title="حذف از مقایسه" aria-label="حذف {{ $product->title }} از مقایسه"
                                                class="absolute top-0 left-0 w-8 h-8 rounded-xl flex items-center justify-center text-gray-400 bg-white/70 dark:bg-white/5 border border-gray-100 dark:border-white/10 hover:bg-red-500 hover:text-white hover:border-red-500 transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                        <a href="{{ route('products.show', $product->slug) }}" class="block w-24 h-24 md:w-32 md:h-32 rounded-[1.5rem] bg-white dark:bg-black/20 border border-white/60 dark:border-white/5 p-2 shadow-sm">
                                            @if($product->featured_image_url)
                                                <img src="{{ $product->featured_image_url }}" alt="{{ $product->title }}" class="w-full h-full object-contain">
                                            @else
                                                <span class="w-full h-full flex items-center justify-center text-gray-300">
                                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </span>
                                            @endif
                                        </a>
                                        <a href="{{ route('products.show', $product->slug) }}" class="text-[12px] font-black text-gray-900 dark:text-white leading-6 line-clamp-2 hover:text-brown-600 transition-colors">{{ $product->title }}</a>
                                        <a href="{{ route('products.show', $product->slug) }}" class="px-4 py-2 rounded-xl bg-brown-600 text-white text-[10px] font-black shadow-lg shadow-brown-500/20 hover:bg-brown-700 transition-all active:scale-95">مشاهده و خرید</a>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                        </thead>

                        <tbody>
                        @php $group = null; @endphp
                        @foreach($this->table as $index => $row)
                            @if($row['group'] !== $group)
                                @php $group = $row['group']; @endphp
                                <tr>
                                    <td colspan="{{ $count + 1 }}" class="px-4 pt-6 pb-2">
                                        <span class="sticky right-4 inline-flex items-center gap-2 text-[12px] font-black text-gray-900 dark:text-white">
                                            <span class="w-1 h-4 rounded-full bg-brown-600"></span>{{ $group }}
                                        </span>
                                    </td>
                                </tr>
                            @endif

                            <tr wire:key="compare-row-{{ $index }}"
                                @unless($row['different']) x-show="!onlyDiff" @endunless
                                class="group/row">
                                <th scope="row"
                                    class="sticky right-0 z-10 p-4 text-[11px] font-black align-top border-b border-gray-100 dark:border-white/5
                                    {{ $row['different'] ? 'bg-amber-50/95 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'bg-white/95 dark:bg-zinc-950/95 text-gray-500 dark:text-gray-400' }} backdrop-blur-md">
                                    <span class="flex items-center gap-1.5">
                                        @if($row['different'])
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="مقدار متفاوت"></span>
                                        @endif
                                        {{ $row['label'] }}
                                    </span>
                                </th>
                                @foreach($this->products as $product)
                                    <td class="p-4 text-center align-top text-[12px] font-bold text-gray-700 dark:text-gray-200 leading-6 border-b border-gray-100 dark:border-white/5
                                        {{ $row['different'] ? 'bg-amber-50/60 dark:bg-amber-500/[0.06]' : '' }}">
                                        {!! $row['cells'][$product->id] !!}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="mt-4 text-[10px] font-bold text-gray-400 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded bg-amber-100 dark:bg-amber-500/20 border border-amber-300/60"></span>
                ردیف‌هایی که مقدار آن‌ها بین محصولات متفاوت است.
                <span class="md:hidden">برای دیدن همه محصولات جدول را به چپ و راست بکشید.</span>
            </p>
        @endif
    </section>
</div>
