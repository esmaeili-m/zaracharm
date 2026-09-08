<?php

use Livewire\Component;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
new class extends Component
{
    public $product;
    public $selectedVariant;
    public int $rating = 0;
    public string $activeTab = 'reviews';
    public string $commentBody = '';

    public array $pros = [];
    public array $cons = [];

    public string $prosInput = '';
    public string $consInput = '';
    public array $selectedOptions = [];
    public function mount($product)
    {
        $this->product = Product::active()->where('slug',$product)
            ->with(['variants.optionValues','primaryCategory','comments','specifications'])
            ->first();
        if (!$this->product){
            abort(404);
        }
        $this->selectedVariant = $this->product->cheapestVariant
            ?? $this->product->variants->first();

    }
    public function findVariant()
    {
        $variants = $this->product->variants;

        $this->selectedVariant = $variants->first(function ($variant) {

            foreach ($this->selectedOptions as $optionId => $valueId) {

                $variantOption = $variant->options
                    ->firstWhere('id', $optionId);

                if (!$variantOption) {
                    return false;
                }

                if ($variantOption->pivot->value_id != $valueId) {
                    return false;
                }
            }

            return true;
        });
    }
    public function addProsFromInput(): void
    {

        $value = trim($this->prosInput);

        if ($value === '') {
            return;
        }

        if (count($this->pros) >= 10) {
            $this->addError('pros', 'حداکثر می‌توانید ۱۰ ویژگی مثبت اضافه کنید.');
            return;
        }

        if (mb_strlen($value) > 100) {
            $this->addError('pros', 'ویژگی مثبت نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.');
            return;
        }

        if (in_array($value, $this->pros, true)) {
            $this->addError('pros', 'این ویژگی مثبت قبلاً اضافه شده است.');
            return;
        }

        $this->pros[] = $value;

        $this->prosInput = '';
        $this->resetErrorBag('pros');
    }
    public function getAverageRatingProperty()
    {
        return round($this->product->ratings->avg('rating') ?? 0, 1);
    }
    public function addConsFromInput(): void
    {
        $value = trim($this->consInput);

        if ($value === '') {
            return;
        }

        if (count($this->cons) >= 10) {
            $this->addError('cons', 'حداکثر می‌توانید ۱۰ نقطه ضعف اضافه کنید.');
            return;
        }

        if (mb_strlen($value) > 100) {
            $this->addError('cons', 'نقطه ضعف نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.');
            return;
        }

        if (in_array($value, $this->cons, true)) {
            $this->addError('cons', 'این مورد قبلاً اضافه شده است.');
            return;
        }

        $this->cons[] = $value;

        $this->consInput = '';

        $this->resetErrorBag('cons');
    }

    public function removePros(int $index)
    {
        unset($this->pros[$index]);

        $this->pros = array_values($this->pros);
    }

    public function removeCons(int $index)
    {
        unset($this->cons[$index]);

        $this->cons = array_values($this->cons);
    }

    public function addToCart(): void
    {
        $variantId=$this->selectedVariant->id;
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
        $pricing = app(\App\Services\Pricing\ProductPriceService::class)
            ->calculate($this->selectedVariant);

        $finalPrice = $pricing['after_discount'] ?? null;

        DB::table('cart_items')->insert([
            'cart_id' => $cartId,
            'product_id' => $variant->product_id,
            'quantity' => 1,
            'price' => $finalPrice,
            'variant_id' => $variant->id,
            'attributes' => json_encode(['variant_id' => $variant->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->dispatch('cart-updated');
        $this->dispatch('alert', type: 'success', message: 'به سبد خرید اضافه شد.');
    }
    public function selectOption($valueId)
    {
        $newValue = \App\Models\OptionValue::findOrFail($valueId);

        // مقادیر Variant فعلی
        $currentValues = $this->selectedVariant->values;
        // فقط مقدار ویژگی‌ای که کلیک شده را عوض کن
        $newValueIds = $currentValues
            ->map(function ($value) use ($newValue) {

                if ($value->option_id == $newValue->option_id) {
                    return $newValue->id;
                }

                return $value->id;
            })
            ->toArray();
        // Variant جدید را پیدا کن
        $this->selectedVariant = $this->product->variants
            ->first(function ($variant) use ($newValueIds) {

                return $variant->values
                        ->pluck('id')
                        ->sort()
                        ->values()
                        ->toArray()
                    ==
                    collect($newValueIds)
                        ->sort()
                        ->values()
                        ->toArray();
            });
    }
    private function stockForVariant(int $variantId): int
    {
        return (int) DB::table('inventory_items')
            ->where('product_variant_id', $variantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->selectRaw('SUM(quantity - reserved_quantity) as stock')
            ->value('stock');
    }

    public function submitComment(): void
    {
        $this->validate(
            [
                'rating' => [
                    'required',
                    'integer',
                    'between:1,5',
                ],

                'commentBody' => [
                    'required',
                    'string',
                    'min:10',
                    'max:2000',
                ],

                'pros' => [
                    'array',
                    'max:10',
                ],

                'pros.*' => [
                    'string',
                    'max:100',
                ],

                'cons' => [
                    'array',
                    'max:10',
                ],

                'cons.*' => [
                    'string',
                    'max:100',
                ],
            ],
            [
                'rating.required' => 'لطفاً امتیاز محصول را انتخاب کنید.',
                'rating.integer' => 'امتیاز انتخاب‌شده معتبر نیست.',
                'rating.between' => 'امتیاز محصول باید بین ۱ تا ۵ باشد.',

                'commentBody.required' => 'لطفاً متن دیدگاه خود را وارد کنید.',
                'commentBody.string' => 'متن دیدگاه واردشده معتبر نیست.',
                'commentBody.min' => 'متن دیدگاه باید حداقل ۱۰ کاراکتر باشد.',
                'commentBody.max' => 'متن دیدگاه نمی‌تواند بیشتر از ۲۰۰۰ کاراکتر باشد.',

                'pros.array' => 'ویژگی‌های مثبت واردشده معتبر نیستند.',
                'pros.max' => 'حداکثر می‌توانید ۱۰ ویژگی مثبت وارد کنید.',
                'pros.*.string' => 'ویژگی مثبت باید به صورت متن باشد.',
                'pros.*.max' => 'هر ویژگی مثبت نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',

                'cons.array' => 'نقاط ضعف واردشده معتبر نیستند.',
                'cons.max' => 'حداکثر می‌توانید ۱۰ نقطه ضعف وارد کنید.',
                'cons.*.string' => 'نقطه ضعف باید به صورت متن باشد.',
                'cons.*.max' => 'هر نقطه ضعف نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',
            ],
            [
                'rating' => 'امتیاز محصول',
                'commentBody' => 'متن دیدگاه',
                'pros' => 'ویژگی‌های مثبت',
                'pros.*' => 'ویژگی مثبت',
                'cons' => 'نقاط ضعف',
                'cons.*' => 'نقطه ضعف',
            ]
        );

        DB::transaction(function () {

            /*
            |--------------------------------------------------------------------------
            | ثبت دیدگاه
            |--------------------------------------------------------------------------
            */

            $comment = $this->product->comments()->create([
                'user_id' => auth()->id(),
                'body' => trim($this->commentBody),
                'name' => auth()->user()->full_name ?? auth()->user()->name ?? null,
                'is_approved' => false,
            ]);


            /*
            |--------------------------------------------------------------------------
            | ثبت امتیاز
            |--------------------------------------------------------------------------
            */

            $this->product->ratings()->updateOrCreate(
                [
                    'user_id' => auth()->id(),
                ],
                [
                    'rating' => $this->rating,
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | ثبت نقاط مثبت
            |--------------------------------------------------------------------------
            */

            foreach ($this->pros ?? [] as $pro) {

                $pro = trim($pro);

                if ($pro === '') {
                    continue;
                }

                $comment->points()->create([
                    'type' => 'positive',
                    'body' => $pro,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | ثبت نقاط منفی
            |--------------------------------------------------------------------------
            */

            foreach ($this->cons ?? [] as $con) {

                $con = trim($con);

                if ($con === '') {
                    continue;
                }

                $comment->points()->create([
                    'type' => 'negative',
                    'body' => $con,
                ]);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | پاک کردن فرم
        |--------------------------------------------------------------------------
        */

        $this->reset([
            'rating',
            'commentBody',
            'pros',
            'cons',
        ]);

        /*
        |--------------------------------------------------------------------------
        | باقی ماندن روی تب نظرات
        |--------------------------------------------------------------------------
        */

        $this->activeTab = 'reviews';

        session()->flash(
            'comment-success',
            'دیدگاه شما با موفقیت ثبت شد و پس از تأیید نمایش داده خواهد شد.'
        );
    }
    public function getPositivePointsProperty()
    {
        return $this->product->comments
            ->flatMap(fn ($comment) => $comment->commentPoints)
            ->where('type', 'positive')
            ->pluck('body')
            ->unique()
            ->values();
    }

    public function getNegativePointsProperty()
    {
        return $this->product->comments
            ->flatMap(fn ($comment) => $comment->commentPoints)
            ->where('type', 'negative')
            ->pluck('body')
            ->unique()
            ->values();
    }
}
?>

<div>
    <main class="space-y-12">

        <!-- CONTENT -->
        <section class="product-hero relative pt-8 overflow-hidden">
            <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-brown-600/5 blur-[100px] rounded-full -z-10 animate-pulse"></div>

            <div class="container relative z-10 px-4">
                <nav class="flex items-center gap-2 mb-8 text-[11px] font-bold text-gray-400 dark:text-gray-500">
                    <a href="index.html" class="hover:text-brown-600 transition-colors">خانه</a>

                    <i class="far fa-chevron-left text-[8px] opacity-40"></i>
                    <span class="text-gray-900 dark:text-white font-black">{{$product->title}}</span>
                </nav>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">

                    <div class="lg:col-span-5 space-y-6 flex flex-col items-center justify-center">
                        <!-- Icon box and main gallery -->
                        <div class="relative group bg-white/40 dark:bg-black/20 backdrop-blur-md rounded-[3rem] border border-white/60 dark:border-white/5 shadow-lg p-6 overflow-hidden w-full">

                            <!-- Icon box -->
                            <div class="absolute top-6 left-1/2 -translate-x-1/2 z-[100] isolate">
                                <div class="flex items-center gap-1.5 p-1.5 bg-white/40 dark:bg-black/40 backdrop-blur-md rounded-2xl border border-white/60 dark:border-white/10 shadow-lg opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-y-[-15px] group-hover:translate-y-0 flex-row-reverse">

                                    <div class="relative group/tooltip">
                                        <button onclick="toggleModal('wishlistModal')" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-red-500 hover:text-white transition-all duration-300">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                        </button>
                                        <span class="absolute top-full mt-3 left-1/2 -translate-x-1/2 px-3 py-1.5 bg-gray-900 dark:bg-zinc-800 text-white text-[10px] font-black rounded-lg whitespace-nowrap opacity-0 -translate-y-1 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-y-0 transition-all duration-300 pointer-events-none shadow-lg z-[110] border border-white/10">
                                        علاقه‌مندی
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 border-[5px] border-transparent border-b-gray-900 dark:border-b-zinc-800"></span>
                                    </span>
                                    </div>

                                    <div class="w-[1px] h-5 bg-gray-400/20 dark:bg-white/10"></div>

                                    <div class="relative group/tooltip">
                                        <button onclick="toggleModal('compareModal')" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-teal-500 hover:text-white transition-all duration-300">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"></path><path d="M8 21H3v-5"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>
                                        </button>
                                        <span class="absolute top-full mt-3 left-1/2 -translate-x-1/2 px-3 py-1.5 bg-gray-900 dark:bg-zinc-800 text-white text-[10px] font-black rounded-lg whitespace-nowrap opacity-0 -translate-y-1 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-y-0 transition-all duration-300 pointer-events-none shadow-lg z-[110] border border-white/10">
                                        مقایسه کالا
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 border-[5px] border-transparent border-b-gray-900 dark:border-b-zinc-800"></span>
                                    </span>
                                    </div>

                                    <div class="w-[1px] h-5 bg-gray-400/20 dark:bg-white/10"></div>

                                    <div class="relative group/tooltip">
                                        <button onclick="toggleModal('priceModal')" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-brown-600 hover:text-white transition-all duration-300">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                                        </button>
                                        <span class="absolute top-full mt-3 left-1/2 -translate-x-1/2 px-3 py-1.5 bg-gray-900 dark:bg-zinc-800 text-white text-[10px] font-black rounded-lg opacity-0 group-hover/tooltip:opacity-100 transition-all pointer-events-none z-[110]">
                                        نمودار قیمت
                                    </span>
                                    </div>

                                    <div class="relative group/tooltip">

                                        <button onclick="toggleModal('shareModal')" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-700 dark:text-gray-200 hover:bg-brown-600 hover:text-white transition-all duration-300">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
                                        </button>
                                        <span class="absolute top-full mt-3 left-1/2 -translate-x-1/2 px-3 py-1.5 bg-gray-900 dark:bg-zinc-800 text-white text-[10px] font-black rounded-lg whitespace-nowrap opacity-0 -translate-y-1 group-hover/tooltip:opacity-100 group-hover/tooltip:translate-y-0 transition-all duration-300 pointer-events-none shadow-lg z-[110] border border-white/10">
                                        اشتراک‌گذاری
                                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 border-[5px] border-transparent border-b-gray-900 dark:border-b-zinc-800"></span>
                                        </span>
                                    </div>

                                    <div class="w-[1px] h-5 bg-gray-400/20 dark:bg-white/10"></div>

                                </div>
                            </div>

                            <!-- Gallery -->
                            <div class="swiper productMainSwiper h-[380px] md:h-[450px] swiper-initialized swiper-horizontal swiper-rtl swiper-backface-hidden">
                                <div class="swiper-wrapper" id="swiper-wrapper-81a473b17c952bdb" aria-live="polite">
                                    @foreach($product->media()->get() ?? [] as $img)

                                    <div class="swiper-slide swiper-slide-active" style="width: 557px; margin-left: 10px;" role="group" aria-label="1 / 7">
                                        <div class="swiper-zoom-container flex items-center justify-center p-4">
                                            <img src="{{asset('storage/'.$img->file_path)}}" class="max-h-full w-auto object-contain transition-transform duration-700" alt="product">
                                        </div>
                                    </div>
                                    @endforeach

                                </div>

                                <div class="swiper-button-next !w-12 !h-12 !bg-white/90 dark:!bg-black/60 rounded-2xl after:!text-sm shadow-lg !text-brown-600 border border-white/50 dark:border-white/5" tabindex="0" role="button" aria-label="Next slide" aria-controls="swiper-wrapper-81a473b17c952bdb" aria-disabled="false"></div>
                                <div class="swiper-button-prev !w-12 !h-12 !bg-white/90 dark:!bg-black/60 rounded-2xl after:!text-sm shadow-lg !text-brown-600 border border-white/50 dark:border-white/5 swiper-button-disabled" tabindex="-1" role="button" aria-label="Previous slide" aria-controls="swiper-wrapper-81a473b17c952bdb" aria-disabled="true"></div>
                                <span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span></div>
                        </div>

                        <!-- Gallery thumbnail -->
                        <div class="w-full max-w-[400px]">
                            <div class="swiper productThumbsSwiper !pb-5 swiper-initialized swiper-horizontal swiper-free-mode swiper-rtl swiper-watch-progress swiper-backface-hidden swiper-thumbs">
                                <div class="swiper-wrapper" id="swiper-wrapper-c574f2f1c7b9375d" aria-live="polite" style="transform: translate3d(0px, 0px, 0px);">
                                    @foreach($product->media()->get() ?? [] as $img)
                                        <div class="swiper-slide shadow-lg cursor-pointer rounded-[1.5rem] border-2 border-transparent bg-white/40 dark:bg-white/5 p-2 transition-all opacity-40 overflow-hidden swiper-slide-visible swiper-slide-fully-visible swiper-slide-active swiper-slide-thumb-active" style="width: 88.75px; margin-left: 15px;" role="group" aria-label="1 / 6">
                                            <img src="{{asset('storage/'.$img->file_path)}}" class="w-full aspect-square object-contain" alt="thumb">
                                        </div>
                                    @endforeach

                                </div>
                                <span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span></div>
                        </div>
                    </div>

                    <div class="lg:col-span-7 space-y-6">

                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="text-[10px] font-black text-brown-600 bg-brown-600/10 px-3 py-1 rounded-lg">{{$product->primaryCategory?->title}}</span>
                                <span class="text-[10px] font-bold text-gray-400">شناسه کالا: {{$product->barcode}}</span>
                            </div>
                            <h1 class="text-2xl font-black text-gray-900 dark:text-white leading-relaxed">
                                {{$product->title}}
                            </h1>
                            <div class="flex items-center gap-6">
                                <div class="flex items-center gap-1.5 text-secondary-500 text-xs font-black">
                                    <i class="fas fa-star"></i> <span class="tabular-nums">۴.۸</span>
                                    <span class="text-gray-400 font-bold mr-1">({{$product->comments->count()}} دیدگاه)</span>
                                </div>
                                <div class="w-[1px] h-4 bg-gray-200 dark:bg-white/10"></div>
                                <a href="#" class="text-brown-500 text-[11px] font-bold hover:underline">پرسش و پاسخ (0)</a>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                            <div class="xl:col-span-7 space-y-6">
                                <!-- Variable -->
                                <div class="space-y-6">
                                    @foreach($product->options ?? [] as $option)
                                            <div class="space-y-4 ">
                                                <p class="text-[13px] font-black text-gray-900 dark:text-white">
                                                    {{ $option->title }}:
                                                    <span
                                                        id="selected-option-{{ $option->id }}"
                                                        class="text-gray-500 font-bold"
                                                    >
                                                    {{ $selectedVariant->values->where('option_id', $option->id)?->first()->title }}
                                                </span>
                                                </p>

                                                <div class="flex flex-wrap gap-2">
                                                    @foreach($product->variants->pluck('values')->flatten()->where('option_id', $option->id)->unique('slug') as $item)
                                                        @php
                                                            $isActive = $selectedVariant->values
                                                                ->where('option_id', $option->id)
                                                                ->contains('slug', $item->slug);
                                                        @endphp
                                                        <button
                                                            type="button"
                                                            wire:click="selectOption({{ $item->id }})"
                                                            class="option-btn px-4 py-2 rounded-xl
           {{ $isActive
                ? 'border-2 border-brown-600 shadow-lg shadow-brown-600/25'
                : 'border-2 border-transparent'
           }}
           bg-white dark:bg-white/5
           text-gray-700 dark:text-gray-300
           text-xs font-bold
           shadow-[0_3px_12px_rgba(0,0,0,0.08)]
           dark:shadow-[0_3px_12px_rgba(0,0,0,0.25)]
           hover:-translate-y-0.5
           hover:shadow-[0_5px_16px_rgba(0,0,0,0.12)]
           dark:hover:shadow-[0_5px_16px_rgba(0,0,0,0.3)]
           transition-all duration-200"
                                                        >
                                                            {{ $item->title }}
                                                        </button>
                                                    @endforeach

                                                </div>
                                            </div>


                                    @endforeach

                                </div>

                                <!-- Feature -->
                                <div class="space-y-4">
                                    <p class="text-[13px] font-black text-gray-900 dark:text-white">ویژگی‌های اصلی کالا:</p>

                                    <div class="grid grid-cols-2 gap-3">
                                        @foreach($product->specifications as $specification)
                                            <div class="bg-gray-50 dark:bg-white/5
                                                border border-gray-100 dark:border-white/5
                                                p-3 rounded-2xl
                                                flex flex-col gap-2
                                                transition-all
                                                hover:border-brown-500/30 group">

                                                <span class="text-[10px] text-gray-500 dark:text-gray-400 font-medium">
                                                    {{ $specification->title }}
                                                </span>

                                                                                        <span class="text-[11px] font-black text-gray-900 dark:text-white
                                                    group-hover:text-brown-600 transition-colors">

                                                    {{ $specification->value }}

                                                </span>

                                            </div>

                                        @endforeach


                                    </div>
                                </div>

                                <div class="p-4 bg-brown-50 dark:bg-brown-600/5 border border-brown-100 dark:border-brown-600/20 rounded-2xl flex gap-4">
                                    <i class="fas fa-info-circle text-brown-600 mt-1"></i>
                                    <p class="text-[11px] font-bold text-brown-800 dark:text-brown-400 leading-relaxed">
                                        درخواست مرجوعی در گروه کیف تنها در صورتی قابل تأیید است که کالا در شرایط اولیه باشد؛ کالا نباید استفاده شده باشد و هیچ‌گونه آثار استفاده، خط‌وخش یا آسیب‌دیدگی روی آن وجود نداشته باشد.
                                    </p>
                                </div>
                            </div>

                            <!-- Meta -->
                            <div class="xl:col-span-5 space-y-4">
                                <div class="bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/5 rounded-[2.5rem] p-6 space-y-6">
                                    <!-- Detail -->
                                    <div class="space-y-6">
                                        <div class="flex items-center justify-between group cursor-pointer">
                                            <div class="flex items-center gap-4">
                                                <div class="relative">
                                                    <div class="w-12 h-12 rounded-2xl bg-white dark:bg-white/5 flex items-center justify-center border border-gray-100 dark:border-white/10 shadow-sm transition-all group-hover:shadow-md group-hover:border-brown-500/30">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-500 dark:text-gray-400 group-hover:text-brown-600 transition-colors">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.615 3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.615m-15 0-1.44-2.16A1.5 1.5 0 0 1 2.46 5.43l1.11-1.665A1.5 1.5 0 0 1 4.808 3h14.384a1.5 1.5 0 0 1 1.238.765l1.11 1.665a1.5 1.5 0 0 1-1.24 2.33l-1.44 2.16"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-emerald-500 rounded-full border-2 border-white dark:border-[#121212] flex items-center justify-center shadow-lg">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3 h-3 text-white">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"></path>
                                                        </svg>
                                                    </div>
                                                </div>

                                                <a  href="{{route('page.show','about-us')}}" class="cursor-pointer group">
                                                    <p class="text-[14px] font-black text-gray-900 dark:text-white group-hover:text-brown-600 transition-colors">فروشنده: زاراچرم</p>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <div class="flex items-center gap-1 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded-lg border border-emerald-100/50 dark:border-emerald-500/20">
                                                            <span class="text-[10px] font-black text-emerald-700 dark:text-emerald-400">۴.۸</span>
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5 text-emerald-500">
                                                                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        </div>
                                                        <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500">مشاهده اطلاعات فروشنده</span>
                                                    </div>
                                                </a>

                                                <!-- Seller Modal -->
                                                <!-- End Seller Modal -->


                                            </div>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-300 group-hover:text-brown-500 transition-all transform group-hover:-translate-x-1">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"></path>
                                            </svg>
                                        </div>

                                        <div class="w-full h-[1px] bg-gradient-to-r from-transparent via-gray-200 dark:via-white/5 to-transparent"></div>

                                        <div class="space-y-4">
                                            <div class="flex items-center gap-4 group">
                                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gray-50 dark:bg-white/[0.03] text-gray-400 group-hover:bg-brown-50 dark:group-hover:bg-brown-500/10 group-hover:text-brown-600 transition-all duration-300">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"></path>
                                                    </svg>
                                                </div>
                                                <p class="text-[12px] font-bold text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">ضمانت اصالت و سلامت کالا</p>
                                            </div>

                                            <div class="flex items-center gap-4 group">
                                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gray-50 dark:bg-white/[0.03] text-gray-400 group-hover:bg-secondary-50 dark:group-hover:bg-secondary-500/10 group-hover:text-secondary-600 transition-all duration-300">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.129-1.125V11.25c0-4.446-3.61-8.156-8.086-8.156H10.875c-.621 0-1.125.504-1.125 1.125v12.375c0 .621.504 1.125 1.125 1.125h1.125m10.125 0V14.25m-9 0h9"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex flex-col">
                                                    <p class="text-[12px] font-bold text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">ارسال سریع زاراچرم</p>
                                                    <p class="text-[10px] text-emerald-600 dark:text-emerald-500/80 font-bold">تحویل سفارش در کوتاه‌ترین زمان</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    @php
                                        $pricing=$selectedVariant->priceData();
                                    @endphp

                                    <div class="space-y-2 pt-4 border-t border-gray-200 dark:border-white/5">

                                        @if($pricing['has_discount'])

                                            <div class="flex items-center justify-between">

            <span class="text-[11px] font-bold text-gray-400 line-through tabular-nums">
                {{ number_format($pricing['before_discount']) }}
            </span>

                                                <span class="bg-red-500 text-white text-[10px] font-black px-2 py-0.5 rounded-lg tabular-nums">
                {{ $pricing['discount_percent'] }}٪-
            </span>

                                            </div>

                                        @endif

                                        <div class="flex items-baseline justify-end gap-1.5">

        <span
            id="product-final-price"
            class="text-3xl font-black text-gray-900 dark:text-white tabular-nums"
        >
            {{ number_format($pricing['after_discount']) }}
        </span>

                                            <span class="text-[11px] font-bold text-gray-500">
            تومان
        </span>

                                        </div>

                                    </div>

                                    <!-- Add to cart -->
                                    <button wire:click="addToCart" id="add-to-cart-btn" class="w-full h-14 bg-brown-600 hover:bg-brown-700 text-white rounded-2xl font-black text-sm transition-all shadow-lg shadow-brown-500/20 flex items-center justify-center gap-3 group/btn relative overflow-hidden active:scale-95">
                                        <span class="relative z-10">افزودن به سبد خرید</span>
                                        <svg class="w-5 h-5 relative z-10 group-hover:translate-x-[-4px] transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                    </button>
                                </div>

                                <!-- link -->
                                <div class="flex items-center justify-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                                    <svg class="w-4 h-4 text-secondary-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    بهترین قیمت تضمین شده بازار
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        </section>
        <!-- END CONTENT -->

        <!-- START SECTION SELLER -->

        <!-- START SECTION DETAIL -->
        <section>
            <div class="container">
                <div class="bg-white/40 dark:bg-zinc-900/40 backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[3rem] shadow-lg shadow-gray-200/30 dark:shadow-none">

                    <div class="flex items-center gap-2 p-4 bg-gray-50/50 dark:bg-white/[0.02] border-b rounded-[3rem] border-gray-100 dark:border-white/5 overflow-x-auto no-scrollbar">

                        {{-- بررسی اجمالی --}}
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'overview')"
                            id="btn-overview"
                            class="
            flex items-center gap-2 px-6 py-3 rounded-2xl
            text-sm font-black transition-all whitespace-nowrap
            {{ $activeTab === 'overview'
                ? 'active bg-gray-200 dark:bg-white/10 text-gray-900 dark:text-white'
                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5'
            }}
        "
                        >
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke-width="2"
                                 stroke="currentColor"
                                 class="w-5 h-5">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"
                                />
                            </svg>

                            بررسی اجمالی
                        </button>


                        {{-- بررسی تخصصی --}}
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'expert')"
                            id="btn-expert"
                            class="
            flex items-center gap-2 px-6 py-3 rounded-2xl
            text-sm font-black transition-all whitespace-nowrap
            {{ $activeTab === 'expert'
                ? 'active bg-gray-200 dark:bg-white/10 text-gray-900 dark:text-white'
                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5'
            }}
        "
                        >
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke-width="2"
                                 stroke="currentColor"
                                 class="w-5 h-5">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6"
                                />
                            </svg>

                            بررسی تخصصی
                        </button>


                        {{-- مشخصات فنی --}}
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'specs')"
                            id="btn-specs"
                            class="
            flex items-center gap-2 px-6 py-3 rounded-2xl
            text-sm font-black transition-all whitespace-nowrap
            {{ $activeTab === 'specs'
                ? 'active bg-gray-200 dark:bg-white/10 text-gray-900 dark:text-white'
                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5'
            }}
        "
                        >
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke-width="2"
                                 stroke="currentColor"
                                 class="w-5 h-5">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 12h11.25"
                                />
                            </svg>

                            مشخصات فنی
                        </button>


                        {{-- نظرات کاربران --}}
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'reviews')"
                            id="btn-reviews"
                            class="
            flex items-center gap-2 px-6 py-3 rounded-2xl
            text-sm font-black transition-all whitespace-nowrap
            {{ $activeTab === 'reviews'
                ? 'active bg-gray-200 dark:bg-white/10 text-gray-900 dark:text-white'
                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5'
            }}
        "
                        >
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke-width="2"
                                 stroke="currentColor"
                                 class="w-5 h-5">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"
                                />
                            </svg>

                            نظرات کاربران
                        </button>


                        {{-- پرسش و پاسخ --}}
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'faq')"
                            id="btn-faq"
                            class="
            flex items-center gap-2 px-6 py-3 rounded-2xl
            text-sm font-black transition-all whitespace-nowrap
            {{ $activeTab === 'faq'
                ? 'active bg-gray-200 dark:bg-white/10 text-gray-900 dark:text-white'
                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5'
            }}
        "
                        >
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke-width="2"
                                 stroke="currentColor"
                                 class="w-5 h-5">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"
                                />
                            </svg>

                            پرسش و پاسخ
                        </button>

                    </div>
                    <div class="p-8 lg:p-12">

                        <!--Overview-->
                        <div
                            id="tab-overview"
                            class="tab-content {{ $activeTab === 'overview' ? 'block' : 'hidden' }} animate-fadeIn"
                        >
                            <div class="space-y-16">
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                                    <div class="lg:col-span-7 space-y-6 order-2 lg:order-1">
                                        <div class="flex items-center gap-3">
                                            <span class="w-2 h-8 bg-brown-600 rounded-full shadow-[0_0_15px_rgba(37,99,235,0.6)]"></span>
                                            <h3 class="text-2xl font-black text-zinc-900 dark:text-white">{{$product->title}}</h3>
                                        </div>
                                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300 leading-9 text-justify">
                                            {{$product->short_description}}
                                        </p>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 pt-6 border-t border-white/20 dark:border-white/5">
                                            @foreach($product->specifications ?? [] as $specification)
                                                <div class="flex items-center gap-3 group">
                                                    <div class="w-8 h-8 rounded-lg bg-brown-600/10 flex items-center justify-center text-brown-600 group-hover:bg-brown-600 group-hover:text-white transition-all">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                    </div>
                                                    <span lass="text-xs font-black text-zinc-800 dark:text-zinc-300">{{$specification->title}}: <span class="text-xs font-black text-zinc-700 dark:text-zinc-300">{{$specification->value}}</span></span>
                                                </div>
                                            @endforeach


                                        </div>
                                    </div>

                                    <div class="lg:col-span-5 order-1 lg:order-2 relative group">
                                        <div class="absolute -inset-4 bg-gradient-to-tr from-brown-600/20 to-indigo-600/20 rounded-[3.5rem] blur-md opacity-50 group-hover:opacity-80 transition duration-1000"></div>
                                        <img src="{{$product->featuredImageUrl}}" alt="Smartphone Overview" class="relative rounded-[3rem] w-full h-[450px] object-cover border border-white/30 shadow-2xl">
                                    </div>
                                </div>



                            </div>
                        </div>

                        <!--Expert review-->
                        <div
                            id="tab-expert"
                            class="tab-content {{ $activeTab === 'expert' ? 'block' : 'hidden' }} animate-fadeIn"
                        >
                            <div class="space-y-20">

                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                                    <div class="lg:col-span-7 space-y-6">
                                        <div class="flex items-center gap-3">
                                            <span class="w-12 h-1.5 bg-brown-600 rounded-full shadow-[0_0_15px_rgba(37,99,235,0.4)]"></span>
                                            <h3 class="text-2xl font-black text-zinc-900 dark:text-white">{{$product->title}}</h3>
                                        </div>
                                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300 leading-9 text-justify">
                                            {!! $product->description !!}

                                        </p>

                                    </div>
                                    <div class="lg:col-span-5 relative">
                                        <div class="absolute -inset-4 bg-brown-600/10 rounded-[4rem] -rotate-3 backdrop-blur-sm"></div>
                                        <img src="{{$product->featuredImageUrl}}" class="relative rounded-[3rem] shadow-lg shadow-brown-500/10 object-cover h-[400px] w-full border border-white/20" alt="Titanium Frame Detail">
                                    </div>
                                </div>



                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">

                                    {{-- نقاط قوت و چالش‌ها --}}
                                    <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-2 gap-6">

                                        {{-- نقاط قوت --}}
                                        <div
                                            class="p-10
                   bg-emerald-500/[0.03]
                   backdrop-blur-md
                   rounded-[3rem]
                   border border-emerald-500/20
                   shadow-sm"
                                        >

                                            <h4 class="text-sm font-black text-emerald-600 mb-6 flex items-center gap-2">

                                                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                                                    <svg
                                                        class="w-5 h-5"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2.5"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                        />
                                                    </svg>
                                                </div>

                                                نقاط قوت

                                            </h4>


                                            @if($this->positivePoints->isNotEmpty())

                                                <ul class="space-y-4 text-xs font-bold text-zinc-500 dark:text-zinc-400">

                                                    @foreach($this->positivePoints as $point)

                                                        <li class="flex items-center gap-3">

                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>

                                                            {{ $point }}

                                                        </li>

                                                    @endforeach

                                                </ul>

                                            @else

                                                <p class="text-xs font-bold text-zinc-400">
                                                    هنوز نقطه قوتی برای این محصول ثبت نشده است.
                                                </p>

                                            @endif

                                        </div>


                                        {{-- چالش‌ها --}}
                                        <div
                                            class="p-10
                   bg-brown-500/[0.03]
                   backdrop-blur-md
                   rounded-[3rem]
                   border border-brown-500/20
                   shadow-sm"
                                        >

                                            <h4 class="text-sm font-black text-brown-600 mb-6 flex items-center gap-2">

                                                <div class="w-8 h-8 rounded-xl bg-brown-500/10 flex items-center justify-center">

                                                    <svg
                                                        class="w-5 h-5"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                                        />
                                                    </svg>

                                                </div>

                                                چالش‌های محصول

                                            </h4>


                                            @if($this->negativePoints->isNotEmpty())

                                                <ul class="space-y-4 text-xs font-bold text-zinc-500 dark:text-zinc-400">

                                                    @foreach($this->negativePoints as $point)

                                                        <li class="flex items-center gap-3">

                                                            <span class="w-1.5 h-1.5 rounded-full bg-brown-500/50 shrink-0"></span>

                                                            {{ $point }}

                                                        </li>

                                                    @endforeach

                                                </ul>

                                            @else

                                                <p class="text-xs font-bold text-zinc-400">
                                                    هنوز چالشی برای این محصول ثبت نشده است.
                                                </p>

                                            @endif

                                        </div>

                                    </div>


                                    {{-- امتیاز --}}
                                    <div
                                        class="lg:col-span-4
               bg-zinc-900/90
               dark:bg-black/80
               backdrop-blur-md
               rounded-[3.5rem]
               p-10
               text-center
               flex flex-col
               justify-center
               items-center
               shadow-lg
               border border-white/10
               relative
               overflow-hidden
               group"
                                    >

                                        <div
                                            class="absolute top-0 right-0
                   w-32 h-32
                   bg-brown-600/20
                   blur-[60px]
                   rounded-full"
                                        ></div>


                                        <span
                                            class="text-[10px]
                   font-black
                   text-brown-400
                   uppercase
                   tracking-[0.3em]
                   mb-4
                   relative"
                                        >
            امتیاز کاربران
        </span>


                                        <div
                                            class="text-8xl
                   font-black
                   text-white
                   mb-6
                   tracking-tighter
                   relative
                   group-hover:scale-110
                   transition-transform
                   duration-500"
                                        >
                                            {{ $this->averageRating }}
                                        </div>


                                        {{-- ستاره‌ها --}}
                                        <div
                                            class="flex gap-1
                   mb-8
                   bg-white/5
                   px-4 py-2
                   rounded-2xl
                   backdrop-blur-sm
                   border border-white/5"
                                        >

                                            @for($i = 1; $i <= 5; $i++)

                                                <svg
                                                    class="w-5 h-5 {{ $i <= round($this->averageRating / 2) ? 'text-brown-500' : 'text-zinc-700' }}"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>

                                            @endfor

                                        </div>


                                        <p class="text-xs font-medium text-zinc-400 leading-7 relative">
                                            امتیاز ثبت‌شده بر اساس نظر کاربران این محصول است.
                                        </p>


                                        <button
                                            type="button"
                                            class="mt-8 w-full py-5
                   bg-brown-600
                   text-white
                   text-sm
                   font-black
                   rounded-2xl
                   hover:bg-brown-500
                   shadow-[0_15px_30px_-10px_rgba(120,72,45,0.5)]
                   transition-all
                   active:scale-95
                   relative
                   overflow-hidden"
                                        >
                                            مشاهده قیمت و خرید
                                        </button>

                                    </div>

                                </div>
                            </div>
                        </div>

                        <!--Technical specifications-->
                        <div
                            id="tab-specs"
                            class="tab-content {{ $activeTab === 'specs' ? 'block' : 'hidden' }} animate-fadeIn"
                        >
                            <div class="space-y-16 p-2" dir="rtl">


                                @if($product->specifications->isNotEmpty())
                                    <div class="relative p-8 rounded-[2.5rem]
                bg-white/30 dark:bg-[#0c0c0c]/40
                backdrop-blur-md
                border border-white/50 dark:border-white/10">

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-8 gap-x-12">

                                            @foreach($product->specifications as $specification)
                                                <div
                                                    class="flex items-center justify-between gap-6 pb-4
                           border-b border-zinc-200/50 dark:border-white/5"
                                                >

                                                    {{-- عنوان مشخصات --}}
                                                    <span class="text-[11px] font-bold text-zinc-500
                                 dark:text-zinc-400 uppercase tracking-widest">
                        {{ $specification->title }}
                    </span>

                                                    {{-- مقدار مشخصات --}}
                                                    <span class="text-xs font-black text-zinc-900
                                 dark:text-white text-left">
                        {{ $specification->value }}
                    </span>

                                                </div>
                                            @endforeach

                                        </div>
                                    </div>
                                @endif

                            </div>
                        </div>

                        <!--User comments-->
                        {{-- =========================================================
    PRODUCT REVIEWS
========================================================= --}}
                        <div
                            id="tab-reviews"
                            class="tab-content {{ $activeTab === 'reviews' ? 'block' : 'hidden' }} animate-fadeIn pb-20"
                        >

                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">

                                {{-- =========================
                                    SIDEBAR
                                ========================== --}}
                                <div class="lg:col-span-4 lg:sticky top-10 self-start space-y-6">

                                    @php
                                        $commentsCount = $product->comments->count();

                                        $averageRating = $commentsCount
                                            ? round($product->comments->avg('rating'), 1)
                                            : 0;
                                    @endphp

                                    {{-- Rating --}}
                                    <div class="relative overflow-hidden p-10 bg-white/50 dark:bg-zinc-900/50 backdrop-blur-md border border-white dark:border-white/10 rounded-[3rem] shadow-lg shadow-zinc-200/50 dark:shadow-none">

                                        <div class="absolute top-0 left-0 bg-brown-600 px-5 py-2 rounded-br-[1.8rem] shadow-lg shadow-brown-600/20">
                    <span class="text-[11px] font-black text-white uppercase tracking-tighter">
                        تأیید شده
                    </span>
                                        </div>

                                        <div class="flex flex-col items-center pt-8">

                    <span class="text-[13px] font-black text-zinc-400 mb-3 uppercase tracking-widest">
                        امتیاز کلی محصول
                    </span>

                                            <h4 class="text-8xl font-black text-zinc-900 dark:text-white tracking-tighter mb-5">
                                                {{ $averageRating }}
                                            </h4>

                                            <div class="flex gap-2 mb-6">

                                                @for($i = 1; $i <= 5; $i++)

                                                    <div class="w-10 h-2 rounded-full
                                {{ $i <= round($averageRating)
                                    ? 'bg-brown-600'
                                    : 'bg-zinc-200 dark:bg-zinc-800' }}">
                                                    </div>

                                                @endfor

                                            </div>

                                            <p class="text-[13px] font-bold text-zinc-500 tracking-tight">
                                                بر اساس {{ $commentsCount }} تجربه ثبت شده
                                            </p>

                                        </div>

                                    </div>


                                    {{-- Info --}}
                                    <div class="space-y-6">

                                        <div class="group relative overflow-hidden p-8 bg-white/40 dark:bg-white/[0.03] backdrop-blur-md rounded-[2.5rem] border border-white/80 dark:border-white/10 shadow-[0_20px_40px_rgba(0,0,0,0.03)] transition-all duration-500 hover:shadow-brown-500/5 hover:border-brown-500/20">

                                            <div class="absolute -top-12 -left-12 w-32 h-32 bg-brown-500/5 rounded-full blur-md group-hover:bg-brown-500/10 transition-all"></div>

                                            <div class="relative z-10 flex items-start gap-5">

                                                <div class="flex-shrink-0 w-14 h-14 bg-white dark:bg-zinc-800 rounded-[1.3rem] flex items-center justify-center shadow-sm border border-zinc-100 dark:border-white/5 text-brown-600 group-hover:scale-110 group-hover:bg-brown-600 group-hover:text-white transition-all duration-500">

                                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round"
                                                              stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                                        </path>
                                                    </svg>

                                                </div>

                                                <div class="space-y-2 pt-1">

                                                    <h5 class="text-[15px] font-black text-zinc-900 dark:text-white tracking-tight">
                                                        پایش و صیانت از محتوا
                                                    </h5>

                                                    <p class="text-[12px] font-bold text-zinc-500 dark:text-zinc-400 leading-7">
                                                        دیدگاه‌ها به صورت
                                                        <span class="text-brown-600 dark:text-brown-400">
                                    هوشمند
                                </span>
                                                        پایش شده و پس از تأیید تیم پشتیبانی، برای عموم کاربران نمایش داده خواهند شد.
                                                    </p>

                                                </div>

                                            </div>

                                        </div>


                                        <button
                                            type="button"
                                            wire:click="$refresh"
                                            wire:loading.attr="disabled"
                                            class="relative w-full group overflow-hidden py-5 bg-zinc-900 dark:bg-white rounded-[1.8rem] transition-all duration-500 active:scale-[0.97] shadow-lg shadow-zinc-900/10 dark:shadow-white/5">

                                            <div class="absolute inset-0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000 bg-gradient-to-r from-transparent via-white/20 dark:via-zinc-900/10 to-transparent"></div>

                                            <div class="relative flex items-center justify-center gap-3">

                                                <svg class="w-5 h-5 text-white dark:text-zinc-900"
                                                     fill="none"
                                                     stroke="currentColor"
                                                     viewBox="0 0 24 24">

                                                    <path stroke-linecap="round"
                                                          stroke-linejoin="round"
                                                          stroke-width="2.5"
                                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                    </path>

                                                </svg>

                                                <span class="text-[13px] font-black text-white dark:text-zinc-900 uppercase tracking-[0.2em]">
                            بروزرسانی زنده نظرات
                        </span>

                                            </div>

                                        </button>

                                    </div>

                                </div>


                                {{-- =========================
                                    MAIN
                                ========================== --}}
                                <div class="lg:col-span-8 space-y-12">


                                    {{-- =========================
                                        COMMENT FORM
                                    ========================== --}}
                                    <section class="relative overflow-hidden bg-white/50 dark:bg-zinc-900/50 backdrop-blur-md border border-white dark:border-white/10 rounded-[3rem] p-8 lg:p-12 shadow-lg shadow-zinc-200/50 dark:shadow-none">

                                        <div class="flex items-center gap-4 mb-12">

                                            <span class="w-2.5 h-8 bg-brown-600 rounded-full shadow-[0_0_15px_rgba(37,99,235,0.4)]"></span>

                                            <h3 class="text-[16px] font-black text-zinc-900 dark:text-white uppercase tracking-tighter">
                                                ثبت تجربه و دیدگاه جدید
                                            </h3>

                                        </div>


                                        <form
                                            wire:submit="submitComment"
                                            class="space-y-10">


                                            {{-- =========================
                                                RATING
                                            ========================== --}}
                                            <div class="space-y-4">

                                                <label class="text-[13px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest mr-2">
                                                    امتیاز به محصول
                                                </label>

                                                <div class="flex flex-row-reverse justify-end gap-2 pt-1 text-zinc-300 dark:text-zinc-700">

                                                    @for($i = 5; $i >= 1; $i--)

                                                        <input
                                                            type="radio"
                                                            wire:model="rating"
                                                            value="{{ $i }}"
                                                            id="s{{ $i }}"
                                                            class="hidden peer/r{{ $i }}">

                                                        <label
                                                            for="s{{ $i }}"
                                                            class="cursor-pointer peer-hover/r{{ $i }}:text-brown-600 peer-checked/r{{ $i }}:text-brown-600 transition-all scale-110 hover:scale-125">

                                                            <svg class="w-10 h-10 fill-current"
                                                                 viewBox="0 0 20 20">

                                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292z"/>

                                                            </svg>

                                                        </label>

                                                    @endfor

                                                </div>

                                                @error('rating')
                                                <p class="text-xs font-bold text-red-500">
                                                    {{ $message }}
                                                </p>
                                                @enderror

                                            </div>


                                            {{-- =========================
                                                PROS / CONS
                                            ========================== --}}
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">


                                                {{-- PROS --}}
                                                <div class="space-y-4">

                                                    <label class="text-[13px] font-black text-emerald-600 dark:text-emerald-500 uppercase tracking-widest mr-2">
                                                        ویژگی‌های مثبت
                                                    </label>

                                                    <div class="relative group">

                                                        <input
                                                            type="text"
                                                            id="prosInput"
                                                            wire:model.lazy="prosInput"
                                                            wire:keydown.enter.prevent="addProsFromInput"
                                                            class="w-full bg-white/80 dark:bg-black/40 border border-emerald-500/20 rounded-2xl px-6 py-5 text-[14px] font-bold outline-none focus:border-emerald-500 transition-all shadow-sm"
                                                            placeholder="افزودن ویژگی مثبت + اینتر">

                                                        <button
                                                            type="button"
                                                            wire:click="addProsFromInput"
                                                            class="absolute left-3 top-1/2 -translate-y-1/2 w-11 h-11 bg-emerald-500 text-white rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/30 hover:scale-110 active:scale-95 transition-all">

                                                            <svg class="w-6 h-6"
                                                                 fill="none"
                                                                 stroke="currentColor"
                                                                 viewBox="0 0 24 24">

                                                                <path stroke-linecap="round"
                                                                      stroke-linejoin="round"
                                                                      stroke-width="3"
                                                                      d="M12 6v12M6 12h12">
                                                                </path>

                                                            </svg>

                                                        </button>

                                                    </div>


                                                    <div class="flex flex-wrap gap-3">

                                                        @foreach($pros as $index => $pro)

                                                            <div class="flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">

                                                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>

                                                                <span class="text-[12px] font-black text-emerald-600">
                                            {{ $pro }}
                                        </span>

                                                                <button
                                                                    type="button"
                                                                    wire:click="removePros({{ $index }})"
                                                                    class="mr-1 text-emerald-500 hover:text-red-500">

                                                                    ×

                                                                </button>

                                                            </div>

                                                        @endforeach

                                                    </div>

                                                    @error('pros')
                                                    <p class="text-xs font-bold text-red-500">
                                                        {{ $message }}
                                                    </p>
                                                    @enderror

                                                </div>


                                                {{-- CONS --}}
                                                <div class="space-y-4">

                                                    <label class="text-[13px] font-black text-rose-500 uppercase tracking-widest mr-2">
                                                        نقاط ضعف
                                                    </label>

                                                    <div class="relative group">

                                                        <input
                                                            type="text"
                                                            id="consInput"
                                                            wire:model.lazy="consInput"
                                                            wire:keydown.enter.prevent="addConsFromInput"
                                                            class="w-full bg-white/80 dark:bg-black/40 border border-rose-500/20 rounded-2xl px-6 py-5 text-[14px] font-bold outline-none focus:border-rose-500 transition-all shadow-sm"
                                                            placeholder="افزودن مورد منفی + اینتر">

                                                        <button
                                                            type="button"
                                                            wire:click="addConsFromInput"
                                                            class="absolute left-3 top-1/2 -translate-y-1/2 w-11 h-11 bg-rose-500 text-white rounded-xl flex items-center justify-center shadow-lg shadow-rose-500/30 hover:scale-110 active:scale-95 transition-all">

                                                            <svg class="w-6 h-6"
                                                                 fill="none"
                                                                 stroke="currentColor"
                                                                 viewBox="0 0 24 24">

                                                                <path stroke-linecap="round"
                                                                      stroke-linejoin="round"
                                                                      stroke-width="3"
                                                                      d="M12 6v12M6 12h12">
                                                                </path>

                                                            </svg>

                                                        </button>

                                                    </div>


                                                    <div class="flex flex-wrap gap-3">

                                                        @foreach($cons as $index => $con)

                                                            <div class="flex items-center gap-2 px-4 py-2 bg-rose-500/10 border border-rose-500/20 rounded-xl">

                                                                <span class="w-1.5 h-1.5 bg-rose-500 rounded-full"></span>

                                                                <span class="text-[12px] font-black text-rose-600">
                                            {{ $con }}
                                        </span>

                                                                <button
                                                                    type="button"
                                                                    wire:click="removeCons({{ $index }})"
                                                                    class="mr-1 text-rose-500 hover:text-red-700">

                                                                    ×

                                                                </button>

                                                            </div>

                                                        @endforeach

                                                    </div>

                                                    @error('cons')
                                                    <p class="text-xs font-bold text-red-500">
                                                        {{ $message }}
                                                    </p>
                                                    @enderror

                                                </div>

                                            </div>


                                            {{-- =========================
                                                BODY
                                            ========================== --}}
                                            <div class="space-y-4">

                                                <label class="text-[13px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest mr-2">
                                                    متن دیدگاه شما
                                                </label>

                                                <textarea
                                                    wire:model="commentBody"
                                                    rows="5"
                                                    class="w-full bg-white/80 dark:bg-black/40 border border-zinc-200 dark:border-white/5 rounded-[2.5rem] px-8 py-6 text-[14px] font-bold text-zinc-900 dark:text-white outline-none focus:ring-4 focus:ring-brown-600/10 focus:border-brown-600 transition-all shadow-sm resize-none"
                                                    placeholder="جزئیات تجربه استفاده از محصول را اینجا بنویسید..."></textarea>

                                                @error('commentBody')
                                                <p class="text-xs font-bold text-red-500 mr-2">
                                                    {{ $message }}
                                                </p>
                                                @enderror

                                            </div>


                                            {{-- =========================
                                                SUCCESS
                                            ========================== --}}
                                            @if(session()->has('comment_success'))

                                                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 text-[13px] font-bold">
                                                    {{ session('comment_success') }}
                                                </div>

                                            @endif


                                            {{-- =========================
                                                SUBMIT
                                            ========================== --}}
                                            <button
                                                type="submit"
                                                wire:loading.attr="disabled"
                                                class="w-full py-6
           bg-brown-600
           text-white
           rounded-[2rem]
           font-black
           text-[14px]
           uppercase
           tracking-[0.3em]
           shadow-[0_20px_40px_rgba(120,72,45,0.25)]
           hover:bg-brown-700
           dark:hover:bg-brown-500
           transition-all
           duration-500
           active:scale-[0.97]
           border-none
           outline-none
           disabled:opacity-50">

    <span wire:loading.remove wire:target="submitComment">
        تایید و ثبت نهایی دیدگاه
    </span>

                                                <span wire:loading wire:target="submitComment">
        در حال ثبت دیدگاه...
    </span>

                                            </button>

                                        </form>

                                    </section>


                                    {{-- =================================================
                                        COMMENTS LIST
                                    ================================================== --}}
                                    <div class="space-y-8" dir="rtl">

                                        @forelse($product->comments as $comment)

                                            <div class="group relative p-8 bg-white/40 dark:bg-zinc-900/40 backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-[3rem] transition-all duration-500 hover:shadow-lg hover:-translate-y-1">

                                                {{-- Header --}}
                                                <div class="flex flex-col md:flex-row justify-between items-start gap-6">

                                                    <div class="flex items-center gap-5">

                                                        <div class="relative">

                                                            <div class="w-16 h-16 rounded-[1.5rem] bg-brown-600/10 flex items-center justify-center text-brown-600 font-black text-xl border border-brown-600/20">

                                                                {{ mb_substr($comment->user?->name ?? 'کاربر', 0, 2) }}

                                                            </div>

                                                            @if($comment->is_verified)

                                                                <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-emerald-500 border-4 border-white dark:border-zinc-900 rounded-full flex items-center justify-center">

                                                                    <svg class="w-3 h-3 text-white"
                                                                         fill="none"
                                                                         stroke="currentColor"
                                                                         viewBox="0 0 24 24">

                                                                        <path stroke-linecap="round"
                                                                              stroke-linejoin="round"
                                                                              stroke-width="4"
                                                                              d="M5 13l4 4L19 7">
                                                                        </path>

                                                                    </svg>

                                                                </div>

                                                            @endif

                                                        </div>


                                                        <div>

                                                            <div class="flex items-center gap-3">

                                                                <p class="text-[14px] font-black text-zinc-900 dark:text-white">

                                                                    {{ $comment->user?->name ?? 'کاربر مهمان' }}

                                                                </p>

                                                                @if($comment->is_verified)

                                                                    <span class="px-3 py-1 bg-emerald-500 text-white text-[10px] font-black rounded-lg">
                                                خریدار
                                            </span>

                                                                @endif

                                                            </div>

                                                            <p class="text-[12px] font-bold text-zinc-400 mt-1">

                                                                {{ verta($comment->created_at)->format('d F Y') }}

                                                            </p>

                                                        </div>

                                                    </div>


                                                    {{-- Rating --}}
                                                    <div class="flex gap-1 text-brown-600">

                                                        @for($i = 1; $i <= 5; $i++)

                                                            <svg
                                                                class="w-5 h-5 {{ $i <= $comment->rating ? 'text-brown-600' : 'text-zinc-200 dark:text-zinc-800' }}"
                                                                fill="currentColor"
                                                                viewBox="0 0 20 20">

                                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292z"/>

                                                            </svg>

                                                        @endfor

                                                    </div>

                                                </div>


                                                {{-- Body --}}
                                                <p class="mt-8 text-[14px] font-medium text-zinc-600 dark:text-zinc-300 leading-8">
                                                    {{ $comment->body }}
                                                </p>


                                                {{-- Pros --}}
                                                @if(!empty($comment->pros))

                                                    <div class="mt-8 flex flex-wrap gap-4">

                                                        @foreach($comment->pros as $pro)

                                                            <div class="flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">

                                                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>

                                                                <span class="text-[12px] font-black text-emerald-600">
                                            {{ $pro }}
                                        </span>

                                                            </div>

                                                        @endforeach

                                                    </div>

                                                @endif


                                                {{-- Cons --}}
                                                @if(!empty($comment->cons))

                                                    <div class="mt-4 flex flex-wrap gap-4">

                                                        @foreach($comment->cons as $con)

                                                            <div class="flex items-center gap-2 px-4 py-2 bg-rose-500/10 border border-rose-500/20 rounded-xl">

                                                                <span class="w-1.5 h-1.5 bg-rose-500 rounded-full"></span>

                                                                <span class="text-[12px] font-black text-rose-600">
                                            {{ $con }}
                                        </span>

                                                            </div>

                                                        @endforeach

                                                    </div>

                                                @endif


                                                {{-- Footer --}}
                                                <div class="mt-8 pt-6 border-t border-zinc-100 dark:border-white/5 flex justify-between items-center">

                                                    <button
                                                        type="button"
                                                        class="flex items-center gap-2 text-zinc-400 hover:text-brown-600 transition-colors">

                                                        <svg class="size-5"
                                                             fill="currentColor"
                                                             viewBox="0 0 24 24">

                                                            <path d="M4 21h1V8H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2zM20 8h-7l1.122-3.368A2 2 0 0 0 12.225 2H12L7 7.438V21h11l3.912-8.596L22 12v-2a2 2 0 0 0-2-2z"/>

                                                        </svg>

                                                        <span class="text-[12px] font-bold">
                                    آیا این نظر مفید بود؟
                                </span>

                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="text-[12px] font-black text-zinc-900 dark:text-white hover:text-brown-600">

                                                        پاسخ

                                                    </button>

                                                </div>

                                            </div>

                                        @empty

                                            <div class="p-12 text-center bg-white/40 dark:bg-zinc-900/40 rounded-[3rem] border border-zinc-200 dark:border-white/10">

                                                <p class="text-[14px] font-bold text-zinc-400">
                                                    هنوز نظری برای این محصول ثبت نشده است
                                                </p>

                                            </div>

                                        @endforelse

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!--Questions and Answers-->
                        <div id="tab-faq" class="tab-content hidden animate-fadeIn pb-24 w-full">
                            <div class="w-full space-y-12" dir="rtl">

                                <section class="relative overflow-hidden bg-white/40 dark:bg-zinc-900/40 backdrop-blur-md border-2 border-gray-200 dark:border-white/10 rounded-[3rem] p-8 lg:p-12 shadow-lg shadow-gray-200/50 dark:shadow-none">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-12">
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-3">
                                                <span class="w-2 h-8 bg-brown-600 rounded-full"></span>
                                                <h3 class="text-[20px] font-black text-zinc-900 dark:text-white uppercase">پرسش خود را بنویسید</h3>
                                            </div>
                                            <p class="text-[13px] font-bold text-zinc-400 mr-5">سوالات شما توسط کارشناسان مانا و خریداران پاسخ داده می‌شود.</p>
                                        </div>
                                        <div class="hidden md:flex w-16 h-16 items-center justify-center rounded-2xl bg-brown-600/10 text-brown-600 border border-brown-600/20">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                        </div>
                                    </div>

                                    <form id="faqForm" class="space-y-6">
                                        <div class="relative group">
                                            <textarea rows="4" required="" class="w-full bg-white/60 dark:bg-black/40 border-2 border-gray-200 dark:border-white/5 rounded-[2.5rem] px-8 py-7 text-[15px] font-bold text-zinc-900 dark:text-white outline-none focus:border-brown-600 focus:ring-4 focus:ring-brown-600/10 transition-all placeholder:text-zinc-400 resize-none" placeholder="پرسش خود را اینجا مطرح کنید..."></textarea>
                                        </div>

                                        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                                            <label class="flex items-center gap-3 cursor-pointer select-none">
                                                <input type="checkbox" class="w-5 h-5 rounded-lg border-2 border-gray-200 dark:border-white/10 text-brown-600 focus:ring-brown-600 bg-transparent">
                                                <span class="text-[12px] font-black text-zinc-500 dark:text-zinc-400">ارسال پرسش به صورت ناشناس</span>
                                            </label>
                                            <button type="submit" class="relative overflow-hidden group w-full md:w-auto px-12 py-5 bg-brown-600 text-white rounded-[2rem] font-black text-[14px] shadow-[0_15px_30px_-5px_rgba(37,99,235,0.4)] hover:shadow-[0_25px_50px_-10px_rgba(37,99,235,0.6)] hover:bg-brown-700 transition-all duration-500 active:scale-95 flex items-center justify-center gap-3">

                                                <div class="absolute inset-0 bg-gradient-to-tr from-brown-400/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                                                <span class="relative z-10 tracking-wide">ثبت و انتشار پرسش</span>

                                                <div class="relative z-10 w-6 h-6 rounded-xl bg-white/20 flex items-center justify-center group-hover:rotate-12 group-hover:scale-110 transition-all duration-500">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                            </button>
                                        </div>
                                    </form>
                                </section>

                                <div class="grid grid-cols-1 gap-10">

                                    <div class="relative group p-1 lg:p-2 bg-transparent">
                                        <div class="flex flex-col md:flex-row gap-8">
                                            <div class="flex-shrink-0 flex md:flex-col items-center gap-4">
                                                <div class="w-14 h-14 bg-zinc-900 dark:bg-white text-white dark:text-black rounded-2xl flex items-center justify-center text-[20px] font-black shadow-xl">
                                                    Q
                                                </div>
                                                <div class="w-1 h-16 bg-brown-600/20 rounded-full hidden md:block"></div>
                                            </div>

                                            <div class="flex-1 space-y-6">
                                                <div class="space-y-3">
                                                    <h4 class="text-[18px] font-black text-zinc-900 dark:text-white leading-8">آیا این کالا نسخه گلوبال است یا ریجن خاصی دارد؟</h4>
                                                    <div class="flex items-center gap-4 text-[11px] font-bold text-zinc-400">
                                                        <span class="flex items-center gap-1.5"><i class="far fa-user"></i> امیر رضایی</span>
                                                        <span class="w-1.5 h-1.5 bg-zinc-200 dark:bg-white/10 rounded-full"></span>
                                                        <span class="flex items-center gap-1.5"><i class="far fa-calendar-alt"></i> ۲۴ فروردین ۱۴۰۳</span>
                                                    </div>
                                                </div>

                                                <div class="relative bg-white/40 dark:bg-zinc-900/60 backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-[2.5rem] p-8 lg:p-10 shadow-lg transition-all duration-500 hover:shadow-2xl">
                                                    <div class="flex items-center gap-3 mb-5">
                                                        <div class="px-4 py-1.5 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-[11px] font-black rounded-lg uppercase">
                                                            پاسخ کارشناس مانا
                                                        </div>
                                                    </div>
                                                    <p class="text-[14px] font-bold text-zinc-600 dark:text-zinc-300 leading-8">
                                                        بله، تمامی محصولات ما نسخه گلوبال آنلاک فکتوری هستند. این مدل دارای پشتیبانی کامل از منوی فارسی و سرویس‌های گوگل به صورت پیش‌فرض می‌باشد.
                                                    </p>

                                                    <div class="mt-8 pt-6 border-t border-gray-100 dark:border-white/5 flex items-center justify-between">
                                                        <p class="text-[11px] font-black text-zinc-400">آیا این پاسخ مفید بود؟</p>
                                                        <div class="flex items-center gap-4">
                                                            <button class="flex items-center gap-2 group/btn transition-all">
                                                                <span class="text-[12px] font-black text-zinc-400 group-hover/btn:text-brown-600">۱۸</span>
                                                                <div class="p-2.5 rounded-xl bg-gray-100 dark:bg-white/5 text-zinc-400 group-hover/btn:bg-brown-600/10 group-hover/btn:text-brown-600 border border-transparent group-hover/btn:border-brown-600/20">
                                                                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path d="M4 21h1V8H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2zM20 8h-7l1.122-3.368A2 2 0 0 0 12.225 2H12L7 7.438V21h11l3.912-8.596L22 12v-2a2 2 0 0 0-2-2z" fill="currentColor"></path></svg>                                                            </div>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="relative p-1 lg:p-2 bg-transparent opacity-60">
                                        <div class="flex flex-col md:flex-row gap-8">
                                            <div class="flex-shrink-0 flex md:flex-col items-center gap-4">
                                                <div class="w-14 h-14 bg-gray-100 dark:bg-zinc-800 text-zinc-400 rounded-2xl flex items-center justify-center text-[20px] font-black">
                                                    ?
                                                </div>
                                            </div>
                                            <div class="flex-1 space-y-4 pt-2">
                                                <h4 class="text-[17px] font-black text-zinc-900 dark:text-white leading-8">این محصول شامل چند ماه خدمات پس از فروش است؟</h4>
                                                <div class="flex items-center gap-3">
                                                    <span class="text-[10px] font-black text-brown-600 bg-brown-600/10 px-3 py-1.5 rounded-lg border border-brown-600/20">در انتظار پاسخ کارشناس مانا</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="relative group p-1 lg:p-2 bg-transparent">
                                        <div class="flex flex-col md:flex-row gap-8">
                                            <div class="flex-shrink-0 flex md:flex-col items-center gap-4">
                                                <div class="w-14 h-14 bg-zinc-900 dark:bg-white text-white dark:text-black rounded-2xl flex items-center justify-center text-[20px] font-black shadow-xl">
                                                    Q
                                                </div>
                                                <div class="w-1 h-16 bg-brown-600/20 rounded-full hidden md:block"></div>
                                            </div>

                                            <div class="flex-1 space-y-6">
                                                <div class="space-y-3">
                                                    <h4 class="text-[18px] font-black text-zinc-900 dark:text-white leading-8">اقلام داخل جعبه دقیقاً شامل چه مواردی است؟</h4>
                                                    <div class="flex items-center gap-4 text-[11px] font-bold text-zinc-400 uppercase tracking-tighter">
                                                        <span>کاربر مهمان</span>
                                                        <span class="w-1.5 h-1.5 bg-zinc-200 rounded-full"></span>
                                                        <span>۱ روز پیش</span>
                                                    </div>
                                                </div>

                                                <div class="relative bg-white/40 dark:bg-zinc-900/60 backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-[2.5rem] p-8 lg:p-10 shadow-lg transition-all duration-500">
                                                    <div class="flex items-center gap-3 mb-5">
                                                        <div class="px-4 py-1.5 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-[11px] font-black rounded-lg uppercase">
                                                            پاسخ کارشناس مانا
                                                        </div>
                                                    </div>
                                                    <p class="text-[14px] font-bold text-zinc-600 dark:text-zinc-300 leading-8">
                                                        درون جعبه علاوه بر خود محصول، کابل شارژ اصلی، دفترچه راهنما، سوزن سیم‌کارت و یک عدد کاور ژله‌ای شفاف با کیفیت قرار دارد.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-center pt-10">
                                    <button class="group relative px-14 py-5 bg-brown-600 rounded-[2rem] overflow-hidden transition-all duration-500 shadow-[0_15px_30px_-5px_rgba(37,99,235,0.4)] hover:shadow-[0_20px_40px_-5px_rgba(37,99,235,0.6)] hover:scale-[1.03] active:scale-95">

                                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shine"></div>

                                        <div class="absolute inset-0 rounded-[2rem] border border-white/20 pointer-events-none"></div>

                                        <div class="relative flex items-center justify-center gap-3">
                                            <svg class="w-5 h-5 text-white/80 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                            </svg>

                                            <span class="text-[14px] font-black text-white uppercase tracking-wider">
                                            مشاهده پرسش‌های بیشتر
                                        </span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>
        <!-- END SECTION DETAIL -->

        <!-- SHOP FEATURE -->
        <section class="relative overflow-hidden transition-colors duration-500">
            <div class="container pt-5">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8">

                    <div class="flex flex-col items-center text-center group">
                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">
                            <div class="absolute inset-0 bg-brown-500/10 dark:bg-brown-500/20 blur-md rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all duration-500 group-hover:-translate-y-2 group-hover:border-brown-500/50 group-hover:shadow-lg group-hover:shadow-brown-500/10 flex items-center justify-center">
                                <svg class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-brown-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2 transition-colors group-hover:text-brown-600 dark:group-hover:text-brown-400">ارسال فوق سریع</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">تحویل کالا در کمتر از ۲۴ ساعت در سراسر کشور</p>
                    </div>

                    <div class="flex flex-col items-center text-center group">
                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">
                            <div class="absolute inset-0 bg-secondary-500/10 dark:bg-secondary-500/20 blur-md rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all duration-500 group-hover:-translate-y-2 group-hover:border-secondary-500/50 group-hover:shadow-lg group-hover:shadow-secondary-500/10 flex items-center justify-center">
                                <svg class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-secondary-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2 transition-colors group-hover:text-secondary-600 dark:group-hover:text-secondary-500">۷ روز ضمانت بازگشت</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">امکان بازگشت کالا در صورت عدم رضایت یا نقص</p>
                    </div>

                    <div class="flex flex-col items-center text-center group">
                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">
                            <div class="absolute inset-0 bg-emerald-500/10 dark:bg-emerald-500/20 blur-md rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all duration-500 group-hover:-translate-y-2 group-hover:border-emerald-500/50 group-hover:shadow-lg group-hover:shadow-emerald-500/10 flex items-center justify-center">
                                <svg class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2 transition-colors group-hover:text-emerald-600 dark:group-hover:text-emerald-500">پرداخت امن</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">استفاده از پروتکل‌های امن و درگاه‌های معتبر</p>
                    </div>

                    <div class="flex flex-col items-center text-center group">
                        <div class="relative w-20 h-20 mb-6 flex items-center justify-center">
                            <div class="absolute inset-0 bg-indigo-500/10 dark:bg-indigo-500/20 blur-md rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative z-10 w-full h-full bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all duration-500 group-hover:-translate-y-2 group-hover:border-indigo-500/50 group-hover:shadow-lg group-hover:shadow-indigo-500/10 flex items-center justify-center">
                                <svg class="w-9 h-9 text-gray-700 dark:text-gray-300 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white mb-2 transition-colors group-hover:text-indigo-600 dark:group-hover:text-indigo-400">ضمانت اصالت</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-6 max-w-[150px]">تضمین ۱۰۰٪ کالاها با گارانتی معتبر</p>
                    </div>

                </div>
            </div>
        </section>
        <!-- END SHOP FEATURE -->
    </main>
</div>
