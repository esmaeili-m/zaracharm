{{--
    پنل فیلترهای صفحه دسته‌بندی — از offcanvas موبایل و aside دسکتاپ include می‌شود (کد یکی است).
    فیلترهای ویژگی‌ها از «ویژگی‌های دسته‌بندی» در پنل مدیریت ساخته می‌شوند ($this->attributeOptions)
    و تعداد هر گزینه با توجه به بقیه فیلترهای انتخاب‌شده محاسبه می‌شود ($this->facets).
--}}
@php
    $facets = $this->facets;
    $bounds = $this->priceBounds;
    $card = 'relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] overflow-hidden shadow-lg shadow-gray-200/50 dark:shadow-none';
    $count = 'text-[10px] font-black tabular-nums text-gray-400 bg-gray-100 dark:bg-white/5 px-2 py-0.5 rounded-lg';
@endphp

{{-- ===================== زیر‌دسته‌ها ===================== --}}
@if($childCategories->isNotEmpty())
    <div class="{{ $card }}" x-data="{ open: true }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-7 select-none">
            <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-brown-500"></span> دسته‌بندی</h3>
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <ul x-show="open" x-collapse class="px-7 pb-7 space-y-1">
            @foreach($childCategories as $child)
                @php $n = $facets['categories'][$child->id] ?? 0; @endphp
                <li wire:key="fc-{{ $child->id }}">
                    <label class="flex items-center justify-between gap-3 p-3 rounded-2xl hover:bg-brown-500/5 cursor-pointer transition-all {{ !$n && !in_array($child->id, array_map('intval', $selectedCategories), true) ? 'opacity-40' : '' }}">
                        <span class="flex items-center gap-3">
                            <input type="checkbox" value="{{ $child->id }}" wire:model.live="selectedCategories" class="w-4 h-4 rounded accent-brown-600">
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $child->title }}</span>
                        </span>
                        <span class="{{ $count }}">{{ $n }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ===================== محدوده قیمت ===================== --}}
@if($bounds['max'] > $bounds['min'])
    <div class="{{ $card }}" x-data="{ open: true }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-7 select-none">
            <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-secondary-500"></span> محدوده قیمت</h3>
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-collapse class="px-7 pb-8">
            @include('components.main.sections.partials.range-filter', [
                'rangeKey' => 'price-' . ($minPrice ?? 'x') . '-' . ($maxPrice ?? 'x'),
                'floor' => $bounds['min'],
                'ceil' => $bounds['max'],
                'from' => $minPrice ?? $bounds['min'],
                'to' => $maxPrice ?? $bounds['max'],
                'step' => max(1000, (int) (round(($bounds['max'] - $bounds['min']) / 100 / 1000) * 1000)),
                'call' => 'updatePriceRange',
                'args' => [],
                'unit' => 'تومان',
            ])
        </div>
    </div>
@endif

{{-- ===================== وضعیت کالا ===================== --}}
<div class="{{ $card }}" x-data="{ open: true }">
    <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-7 select-none">
        <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-emerald-500"></span> وضعیت کالا</h3>
        <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="open" x-collapse class="px-7 pb-7 space-y-5">
        @foreach([
            ['onlyInStock', 'فقط کالاهای موجود', $facets['stock'], 'peer-checked:bg-emerald-500'],
            ['onlyDiscounted', 'فقط کالاهای تخفیف‌دار', $facets['discount'], 'peer-checked:bg-rose-500'],
            ['onlyNew', 'محصولات جدید (۳۰ روز اخیر)', $facets['new'], 'peer-checked:bg-secondary-500'],
        ] as [$prop, $label, $n, $color])
            <label class="flex items-center justify-between gap-3 cursor-pointer {{ !$n && !$this->{$prop} ? 'opacity-40' : '' }}">
                <span class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $label }}</span>
                    <span class="{{ $count }}">{{ $n }}</span>
                </span>
                <span class="relative inline-flex items-center">
                    <input type="checkbox" wire:model.live="{{ $prop }}" class="sr-only peer">
                    <span class="w-11 h-6 bg-gray-200 dark:bg-white/10 rounded-full peer {{ $color }} after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:-translate-x-full"></span>
                </span>
            </label>
        @endforeach
    </div>
</div>

{{-- ===================== امتیاز ===================== --}}
@if(array_sum($facets['rating']) > 0 || $minRating)
    <div class="{{ $card }}" x-data="{ open: true }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-7 select-none">
            <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-amber-400"></span> امتیاز کاربران</h3>
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-collapse class="px-7 pb-7 space-y-2">
            @foreach($facets['rating'] as $stars => $n)
                <button type="button" wire:click="setRating({{ $stars }})" @disabled(!$n && $minRating !== $stars)
                        class="w-full flex items-center justify-between gap-3 p-3 rounded-2xl border transition-all disabled:opacity-40
                        {{ $minRating === $stars ? 'border-brown-500 bg-brown-500/10' : 'border-transparent hover:bg-brown-500/5' }}">
                    <span class="flex items-center gap-2">
                        <span class="flex" dir="ltr">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-4 h-4 {{ $i <= $stars ? 'text-amber-400' : 'text-gray-200 dark:text-white/10' }}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            @endfor
                        </span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $stars }} و بالاتر</span>
                    </span>
                    <span class="{{ $count }}">{{ $n }}</span>
                </button>
            @endforeach
        </div>
    </div>
@endif

{{-- ===================== برند ===================== --}}
@if($this->brands->isNotEmpty())
    @php $brandCounts = $facets['brands']; @endphp
    <div class="{{ $card }}" x-data="{ open: true, q: '', more: false }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-7 select-none">
            <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-brown-500"></span> برند</h3>
            <span class="flex items-center gap-3">
                @if($selectedBrands)<span class="text-[10px] font-black text-brown-500 bg-brown-500/10 px-3 py-1 rounded-full">{{ count($selectedBrands) }} انتخاب</span>@endif
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>
        <div x-show="open" x-collapse class="px-7 pb-7 space-y-3">
            @if($this->brands->count() > 6)
                <input type="text" x-model="q" placeholder="جستجوی برند..." class="w-full bg-white/60 dark:bg-white/10 border border-gray-200 dark:border-white/20 rounded-2xl py-3 px-4 text-xs font-bold text-gray-800 dark:text-white outline-none focus:border-brown-500/50">
            @endif
            <ul class="space-y-1 max-h-72 overflow-y-auto custom-scrollbar">
                @foreach($this->brands->sortByDesc(fn ($b) => $brandCounts[$b->id] ?? 0)->values() as $i => $brand)
                    @php
                        $n = $brandCounts[$brand->id] ?? 0;
                        $checked = in_array($brand->id, array_map('intval', $selectedBrands), true);
                    @endphp
                    <li wire:key="fb-{{ $brand->id }}" x-show="(q === '' || @js(mb_strtolower($brand->title)).includes(q.toLowerCase())) && (more || q !== '' || {{ $i < 8 || $checked ? 'true' : 'false' }})">
                        <label class="flex items-center justify-between gap-3 p-3 rounded-2xl hover:bg-brown-500/5 cursor-pointer transition-all {{ !$n && !$checked ? 'opacity-40' : '' }}">
                            <span class="flex items-center gap-3">
                                <input type="checkbox" value="{{ $brand->id }}" wire:model.live="selectedBrands" class="w-4 h-4 rounded accent-brown-600">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $brand->title }}</span>
                            </span>
                            <span class="{{ $count }}">{{ $n }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
            @if($this->brands->count() > 8)
                <button type="button" x-show="q === ''" @click="more = !more" class="text-[11px] font-black text-brown-600" x-text="more ? 'نمایش کمتر' : 'نمایش همه ({{ $this->brands->count() }})'"></button>
            @endif
        </div>
    </div>
@endif

{{-- ===================== ویژگی‌های دسته (از پنل مدیریت) ===================== --}}
@foreach($this->attributeOptions as $key => $facet)
    @php
        $definition = $facet['definition'];
        $values = $facet['values'];
        $display = $definition['display'];
        $id = $definition['id'];
        $isOption = $definition['kind'] === 'option';
        $selectedCount = $isOption ? count((array) ($options[$id] ?? [])) : (is_array($specs[$id] ?? null) && array_is_list($specs[$id]) ? count($specs[$id]) : (!empty($specs[$id]) ? 1 : 0));
    @endphp
    <div class="{{ $card }}" wire:key="fa-{{ $key }}" x-data="{ open: {{ $loop->index < 4 || $selectedCount ? 'true' : 'false' }}, q: '', more: false }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-7 select-none">
            <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-brown-400"></span> {{ $definition['title'] }}</h3>
            <span class="flex items-center gap-3">
                @if($selectedCount)<span class="text-[10px] font-black text-brown-500 bg-brown-500/10 px-3 py-1 rounded-full">{{ $selectedCount }} انتخاب</span>@endif
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>

        <div x-show="open" x-collapse class="px-7 pb-7">
            @if($display === 'color')
                {{-- انتخابگر رنگ --}}
                <div class="flex flex-wrap gap-3">
                    @foreach($values as $v)
                        <button type="button" wire:click="toggleOption({{ $id }}, {{ $v['value'] }})" wire:key="fo-{{ $key }}-{{ $v['value'] }}"
                                title="{{ $v['label'] }} ({{ $v['count'] }})" aria-pressed="{{ $v['selected'] ? 'true' : 'false' }}"
                                @disabled(!$v['count'] && !$v['selected'])
                                class="group/color flex flex-col items-center gap-1.5 disabled:opacity-30">
                            <span class="relative w-9 h-9 rounded-full border-2 transition-all {{ $v['selected'] ? 'border-brown-600 ring-2 ring-brown-500/30 scale-110' : 'border-white dark:border-white/20 shadow-sm group-hover/color:scale-105' }}"
                                  style="background: {{ $v['color'] ?? 'conic-gradient(#f87171,#facc15,#4ade80,#60a5fa,#c084fc,#f87171)' }}">
                                @if($v['selected'])
                                    <svg class="absolute inset-0 m-auto w-4 h-4 {{ in_array(strtolower((string) $v['color']), ['#ffffff', '#fff', '#f5e6c8', '#facc15', '#c0c0c0']) ? 'text-gray-900' : 'text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </span>
                            <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400">{{ $v['label'] }} <span class="tabular-nums">({{ $v['count'] }})</span></span>
                        </button>
                    @endforeach
                </div>

            @elseif($display === 'buttons')
                {{-- گزینه‌های دکمه‌ای (مثل سایز) --}}
                <div class="flex flex-wrap gap-2">
                    @foreach($values as $v)
                        <button type="button" wire:click="toggleOption({{ $id }}, {{ $v['value'] }})" wire:key="fo-{{ $key }}-{{ $v['value'] }}"
                                @disabled(!$v['count'] && !$v['selected'])
                                class="min-w-11 px-3 py-2 rounded-xl border-2 text-xs font-black transition-all disabled:opacity-30
                                {{ $v['selected'] ? 'border-brown-600 bg-brown-600 text-white' : 'border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 hover:border-brown-500/50' }}">
                            {{ $v['label'] }} <span class="text-[9px] opacity-60 tabular-nums">{{ $v['count'] }}</span>
                        </button>
                    @endforeach
                </div>

            @elseif($display === 'select')
                {{-- لیست کشویی (تک‌انتخابی) --}}
                @php $current = $isOption ? (string) (($options[$id] ?? [])[0] ?? '') : (string) (($specs[$id] ?? [])[0] ?? ''); @endphp
                <select class="w-full bg-white/60 dark:bg-white/10 border border-gray-200 dark:border-white/20 rounded-2xl py-3 px-4 text-xs font-bold text-gray-800 dark:text-white outline-none focus:border-brown-500/50"
                        x-on:change="$event.target.value === ''
                            ? $wire.removeFilter('{{ $isOption ? 'option' : 'spec' }}', {{ $id }}, @js($current))
                            : {{ $isOption ? "\$wire.call('toggleOptionSingle', $id, parseInt(\$event.target.value))" : "\$wire.toggleSpecValue($id, \$event.target.value, true)" }}">
                    <option value="">همه</option>
                    @foreach($values as $v)
                        <option value="{{ $v['value'] }}" @selected($v['selected']) @disabled(!$v['count'] && !$v['selected'])>{{ $v['label'] }} ({{ $v['count'] }})</option>
                    @endforeach
                </select>

            @elseif($display === 'range')
                {{-- بازه عددی --}}
                @php
                    $sel = is_array($specs[$id] ?? null) && !array_is_list($specs[$id]) ? $specs[$id] : [];
                    $span = max(0.0001, (float) $values['max'] - (float) $values['min']);
                    $step = $span <= 10 ? 0.1 : ($span <= 200 ? 1 : 10 ** (strlen((string) (int) $span) - 2));
                @endphp
                @include('components.main.sections.partials.range-filter', [
                    'rangeKey' => $key . '-' . md5(json_encode($sel)),
                    'floor' => (float) $values['min'],
                    'ceil' => (float) $values['max'],
                    'from' => (float) ($sel['min'] ?? $values['min']),
                    'to' => (float) ($sel['max'] ?? $values['max']),
                    'step' => $step,
                    'call' => 'setSpecRange',
                    'args' => [$id],
                    'unit' => null,
                ])

            @elseif($display === 'toggle')
                {{-- بله / خیر --}}
                <label class="flex items-center justify-between gap-3 cursor-pointer">
                    <span class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300">فقط کالاهای دارای «{{ $definition['title'] }}»</span>
                        <span class="{{ $count }}">{{ (int) $values }}</span>
                    </span>
                    <span class="relative inline-flex items-center">
                        <input type="checkbox" @checked(!empty($specs[$id])) wire:click="toggleSpecFlag({{ $id }})" class="sr-only peer">
                        <span class="w-11 h-6 bg-gray-200 dark:bg-white/10 rounded-full peer peer-checked:bg-brown-500 after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:-translate-x-full"></span>
                    </span>
                </label>

            @else
                {{-- چندانتخابی (checkbox) یا تک‌انتخابی (radio) --}}
                @php $single = $display === 'radio'; @endphp
                @if(count($values) > 8)
                    <input type="text" x-model="q" placeholder="جستجو در {{ $definition['title'] }}..." class="mb-3 w-full bg-white/60 dark:bg-white/10 border border-gray-200 dark:border-white/20 rounded-2xl py-3 px-4 text-xs font-bold text-gray-800 dark:text-white outline-none focus:border-brown-500/50">
                @endif
                <ul class="space-y-1 max-h-72 overflow-y-auto custom-scrollbar">
                    @foreach($values as $i => $v)
                        <li wire:key="fs-{{ $key }}-{{ md5($v['value']) }}"
                            x-show="(q === '' || @js(mb_strtolower($v['label'])).includes(q.toLowerCase())) && (more || q !== '' || {{ $i < 8 || $v['selected'] ? 'true' : 'false' }})">
                            <button type="button"
                                    wire:click="{{ $isOption ? 'toggleOption(' . $id . ', ' . (int) $v['value'] . ')' : 'toggleSpecValue(' . $id . ', ' . \Illuminate\Support\Js::from($v['value']) . ', ' . ($single ? 'true' : 'false') . ')' }}"
                                    @disabled(!$v['count'] && !$v['selected'])
                                    class="w-full flex items-center justify-between gap-3 p-3 rounded-2xl hover:bg-brown-500/5 transition-all disabled:opacity-40 text-right">
                                <span class="flex items-center gap-3">
                                    <span class="w-4 h-4 shrink-0 border-2 flex items-center justify-center {{ $single ? 'rounded-full' : 'rounded' }} {{ $v['selected'] ? 'border-brown-600 bg-brown-600' : 'border-gray-300 dark:border-white/20' }}">
                                        @if($v['selected'])<span class="w-1.5 h-1.5 bg-white {{ $single ? 'rounded-full' : 'rounded-sm' }}"></span>@endif
                                    </span>
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $v['label'] }}</span>
                                </span>
                                <span class="{{ $count }}">{{ $v['count'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                @if(count($values) > 8)
                    <button type="button" x-show="q === ''" @click="more = !more" class="mt-2 text-[11px] font-black text-brown-600" x-text="more ? 'نمایش کمتر' : 'نمایش همه ({{ count($values) }})'"></button>
                @endif
            @endif
        </div>
    </div>
@endforeach
