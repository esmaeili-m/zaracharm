{{--
    این partial از دو جا include می‌شود: offcanvas موبایل و aside دسکتاپ (کد یکی است، تکراری نمی‌شود).
    متغیرهای در دسترس (چون از کامپوننت والد وایر لایو ارث‌بری می‌شوند): $category, $childCategories,
    $brands, $priceFloor, $priceCeil, $minPrice, $maxPrice, $selectedCategories, $selectedBrands,
    $onlyInStock, $onlyDiscounted, $onlyNew
--}}

{{-- دسته‌بندی --}}
<div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
    <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
        <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-blue-500"></span>
            دسته‌بندی محصولات
        </h3>
        <div class="flex items-center gap-3">
            <span class="text-[10px] font-black text-blue-500 bg-blue-500/10 px-3 py-1 rounded-full uppercase tracking-widest">{{ $category->title }}</span>
            <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
    </div>

    @if($childCategories->isNotEmpty())
        <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
            <ul class="px-7 pb-8 space-y-1">
                @foreach($childCategories as $child)
                    <li class="group/category">
                        <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-white/5 flex items-center justify-center text-gray-500 group-hover/category:bg-blue-500 group-hover/category:text-white transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 8h12l1 12H5L6 8zM9 8a3 3 0 016 0M9 12h.01M15 12h.01"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/category:text-blue-600 transition-colors">
                                    {{ $child->title }}
                                </span>
                            </div>

                            <div class="relative flex items-center">
                                <input
                                    type="checkbox"
                                    value="{{ $child->id }}"
                                    wire:model.live="selectedCategories"
                                    class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer"
                                >
                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 right-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

{{-- محدوده قیمت --}}
@if($priceCeil > 0)
<div class="js-price-range-container relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
    <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
        <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-secondary-500"></span>
            محدوده قیمت
        </h3>
        <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
        </svg>
    </div>
    <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
        <div class="px-7 pb-8 pt-3">
            <div class="relative w-[92%] mx-auto h-1.5 bg-gray-200 dark:bg-white/10 rounded-full mb-10 mt-4">
                <div class="js-slider-track absolute h-full bg-primary-500 rounded-full"></div>
                <input type="range" class="js-min-range range-input absolute w-full h-1.5 bg-transparent appearance-none pointer-events-none cursor-pointer z-20" min="{{ $priceFloor }}" max="{{ $priceCeil }}" wire:model="minPrice" step="10000">
                <input type="range" class="js-max-range range-input absolute w-full h-1.5 bg-transparent appearance-none pointer-events-none cursor-pointer z-20" min="{{ $priceFloor }}" max="{{ $priceCeil }}" wire:model="maxPrice" step="10000">
            </div>
            <div class="space-y-4">
                <div class="group/in relative flex items-center bg-gray-50/50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-[1.25rem] px-4 py-3 transition-all focus-within:border-primary-500/50 focus-within:bg-white dark:focus-within:bg-white/10">
                    <span class="text-[11px] font-bold text-gray-400 ml-3">از</span>
                    <input type="number" wire:model="minPrice" class="js-min-price-input w-full bg-transparent border-none focus:ring-0 text-sm font-black text-gray-700 dark:text-white text-left p-0">
                </div>
                <div class="group/in relative flex items-center bg-gray-50/50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-[1.25rem] px-4 py-3 transition-all focus-within:border-primary-500/50 focus-within:bg-white dark:focus-within:bg-white/10">
                    <span class="text-[11px] font-bold text-gray-400 ml-3">تا</span>
                    <input type="number" wire:model="maxPrice" class="js-max-price-input w-full bg-transparent border-none focus:ring-0 text-sm font-black text-gray-700 dark:text-white text-left p-0">
                </div>
            </div>
            <button wire:click="applyPriceRange" class="w-full mt-8 py-4 bg-primary-500 hover:bg-primary-600 text-white rounded-[1.5rem] text-xs font-black transition-all active:scale-95">تایید محدوده قیمت</button>
        </div>
    </div>
</div>
@endif

{{-- وضعیت کالا (فقط مواردی که در دیتابیس قابل محاسبه‌اند) --}}
<div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
    <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
        <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>
            وضعیت کالا
        </h3>
        <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
        </svg>
    </div>

    <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
        <div class="px-7 pb-8 space-y-6">

            <label class="flex items-center justify-between cursor-pointer group/sw">
                <div class="flex flex-col gap-1.5">
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-emerald-500 transition-colors">فقط کالاهای موجود</span>
                    <span class="text-[10px] text-gray-400 font-medium">حذف کالاهای ناموجود از لیست</span>
                </div>
                <div class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="onlyInStock" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 shadow-sm"></div>
                </div>
            </label>

            <label class="flex items-center justify-between cursor-pointer group/sw">
                <div class="flex flex-col gap-1.5">
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-rose-500 transition-colors">فقط کالاهای تخفیف‌دار</span>
                    <span class="text-[10px] text-gray-400 font-medium">نمایش پیشنهادات ویژه و شگفت‌انگیز</span>
                </div>
                <div class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="onlyDiscounted" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                </div>
            </label>

            <label class="flex items-center justify-between cursor-pointer group/sw">
                <div class="flex flex-col gap-1.5">
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/sw:text-secondary-500 transition-colors">جدیدترین محصولات</span>
                    <span class="text-[10px] text-gray-400 font-medium">محصولات اضافه شده در ۷ روز اخیر</span>
                </div>
                <div class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="onlyNew" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-secondary-500"></div>
                </div>
            </label>

            {{--
                توجه: تاگل‌های «ارسال فوری (مانا جت)» و «کالاهای با گارانتی اصلی» عمداً حذف شدند
                چون هیچ ستونی در دیتابیس (products / product_variants / inventories) پشتیبانشان نمی‌کند.
                اگر بعداً این ویژگی‌ها را اضافه کردید (مثلاً یک ستون is_fast_shipping یا has_official_warranty)
                کافیست همین بلاک را برگردانید و به فیلتر کوئری وصل کنید.
            --}}

        </div>
    </div>
</div>

{{-- برندها (واقعی، از جدول brands، فقط برندهایی که در این دسته محصول دارند) --}}
@if($brands->isNotEmpty())
<div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none transition-all duration-500">
    <div class="js-collapse-header flex items-center justify-between p-7 cursor-pointer select-none group/header">
        <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-blue-500"></span>
            برندهای محبوب
        </h3>
        <div class="flex items-center gap-3">
            <span class="text-[10px] font-black text-blue-500 bg-blue-500/10 px-3 py-1 rounded-full uppercase tracking-widest">
                {{ count($selectedBrands) ? count($selectedBrands) . ' انتخاب' : 'همه' }}
            </span>
            <svg class="js-collapse-icon w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
    </div>

    <div class="js-collapse-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: 1000px; opacity: 1;">
        <div class="px-7 pb-8 space-y-5">
            <div class="relative group/search">
                <input type="text" class="js-brand-search w-full bg-white/60 dark:bg-white/10 border border-gray-200 dark:border-white/20 rounded-2xl py-3.5 pr-11 pl-4 text-xs font-bold text-gray-800 dark:text-white outline-none transition-all focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/5" placeholder="جستجوی برند...">
                <div class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            <ul class="js-brands-list space-y-1 max-h-72 overflow-y-auto custom-scrollbar pl-2">
                @foreach($brands as $brand)
                    <li class="group/brand">
                        <label class="flex items-center justify-between p-3 rounded-2xl hover:bg-blue-500/5 dark:hover:bg-blue-500/10 cursor-pointer transition-all border border-transparent hover:border-blue-500/20">
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover/brand:text-blue-600 transition-colors">{{ $brand->title }}</span>
                            <div class="relative flex items-center">
                                <input
                                    type="checkbox"
                                    value="{{ $brand->id }}"
                                    wire:model.live="selectedBrands"
                                    class="peer appearance-none w-5 h-5 rounded-lg border-2 border-gray-300/70 dark:border-white/20 checked:bg-blue-500 checked:border-blue-500 transition-all cursor-pointer"
                                >
                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 left-1 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M5 13l4 4L19 7" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endif
