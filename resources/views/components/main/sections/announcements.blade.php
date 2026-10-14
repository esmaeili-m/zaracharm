<?php

use App\Models\Announcement;
use Livewire\Component;

/**
 * سکشن اطلاعیه‌های سایت (صفحه‌ساز) — توضیح data در App\Support\Sections\Announcements
 * فقط اطلاعیه‌های فعال و در بازه نمایش؛ سنجاق‌شده‌ها اول. بستن اطلاعیه در مرورگر کاربر (localStorage) ذخیره می‌شود.
 */
new class extends Component
{
    public ?array $data = []; // سکشن تازه‌اضافه‌شده (بدون تنظیمات) data = null دارد
    public $items;

    public function mount($data = [], $media = null)
    {
        $this->data = is_array($data) ? $data : [];
        $types = array_values(array_intersect((array) ($this->data['types'] ?? []), array_keys(Announcement::TYPES)));

        $this->items = Announcement::visible()
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->orderByDesc('is_pinned')
            ->orderByRaw('coalesce(starts_at, created_at) desc')
            ->limit(max(1, min(20, (int) ($this->data['limit'] ?? 5))))
            ->get();
    }
};
?>

@php
    $style = in_array($data['style'] ?? 'list', ['bar', 'list', 'cards'], true) ? ($data['style'] ?? 'list') : 'list';
    $showDate = (bool) ($data['show_date'] ?? true);
    $dismissible = (bool) ($data['dismissible'] ?? true);

    // کلاس‌های کامل Tailwind برای هر نوع (رنگ پویا ساخته نمی‌شود)
    $tones = [
        'info' => ['box' => 'bg-sky-50 border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/20', 'icon' => 'bg-sky-500/15 text-sky-600 dark:text-sky-400', 'bar' => 'bg-sky-600', 'link' => 'text-sky-700 dark:text-sky-300'],
        'success' => ['box' => 'bg-emerald-50 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20', 'icon' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400', 'bar' => 'bg-emerald-600', 'link' => 'text-emerald-700 dark:text-emerald-300'],
        'warning' => ['box' => 'bg-amber-50 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20', 'icon' => 'bg-amber-500/15 text-amber-600 dark:text-amber-400', 'bar' => 'bg-amber-600', 'link' => 'text-amber-700 dark:text-amber-300'],
        'danger' => ['box' => 'bg-red-50 border-red-200 dark:bg-red-500/10 dark:border-red-500/20', 'icon' => 'bg-red-500/15 text-red-600 dark:text-red-400', 'bar' => 'bg-red-600', 'link' => 'text-red-700 dark:text-red-300'],
    ];
    $icons = [
        'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'warning' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
        'danger' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    ];
    // کلید بستن شامل زمان ویرایش است تا اطلاعیه ویرایش‌شده دوباره نمایش داده شود
    $keys = $items->mapWithKeys(fn ($a) => [$a->id => $a->id . '-' . $a->updated_at?->timestamp])->all();
@endphp

<div>
    @if($items->isNotEmpty())
        <section dir="rtl"
                 x-data="{
                    keys: @js($keys),
                    dismissed: [],
                    current: 0,
                    init() {
                        try { this.dismissed = JSON.parse(localStorage.getItem('zc-ann-dismissed') || '[]') } catch (e) { this.dismissed = [] }
                        @if($style === 'bar')
                            if (this.visibleIds().length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                setInterval(() => { const ids = this.visibleIds(); if (ids.length) this.current = (this.current + 1) % ids.length }, 5000);
                            }
                        @endif
                    },
                    isVisible(id) { return !this.dismissed.includes(this.keys[id]) },
                    visibleIds() { return Object.keys(this.keys).map(Number).filter(id => this.isVisible(id)) },
                    isCurrent(id) { const ids = this.visibleIds(); return ids[this.current % Math.max(ids.length, 1)] === id },
                    dismiss(id) {
                        this.dismissed = [...this.dismissed, this.keys[id]].slice(-100);
                        try { localStorage.setItem('zc-ann-dismissed', JSON.stringify(this.dismissed)) } catch (e) {}
                    },
                 }"
                 x-show="visibleIds().length > 0"
                 aria-label="اطلاعیه‌های سایت"
        >
            @if($style === 'bar')
                {{-- نوار باریک چرخشی --}}
                <div class="relative overflow-hidden rounded-2xl">
                    @foreach($items as $item)
                        @php $tone = $tones[$item->type] ?? $tones['info']; @endphp
                        <div x-show="isCurrent({{ $item->id }})" x-transition.opacity.duration.400ms wire:key="ann-bar-{{ $item->id }}" @unless($loop->first) style="display: none" @endunless
                             class="{{ $tone['bar'] }} text-white flex items-center gap-3 px-4 py-2.5 text-sm" role="status">
                            <svg class="w-5 h-5 flex-shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$item->type] ?? $icons['info'] }}"/></svg>
                            <p class="flex-1 min-w-0 truncate font-bold">
                                <span class="font-black">{{ $item->title }}</span>
                                <span class="hidden sm:inline opacity-90"> — {{ \Illuminate\Support\Str::limit($item->content, 140) }}</span>
                            </p>
                            @if($link = $item->safeLink())
                                <a href="{{ $link }}" class="flex-shrink-0 rounded-lg bg-white/20 hover:bg-white/30 px-3 py-1 text-xs font-black transition-colors">{{ $item->link_text ?: 'مشاهده' }}</a>
                            @endif
                            @if($dismissible)
                                <button type="button" @click="dismiss({{ $item->id }})" class="flex-shrink-0 opacity-80 hover:opacity-100" aria-label="بستن اطلاعیه">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="lg:container mx-auto">
                    {{-- Header --}}
                    <div class="mb-6 border-r-4 border-brown-600 pr-2 pl-2">
                        <h2 class="text-2xl lg:text-3xl font-black text-gray-900 dark:text-white">
                            @if(filled($data['title'] ?? null)){{ $data['title'] }}@else اطلاعیه‌های <span class="text-brown-600">سایت</span>@endif
                        </h2>
                        @if(filled($data['description'] ?? null))
                            <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm">{{ $data['description'] }}</p>
                        @endif
                    </div>

                    <div class="grid gap-4 {{ $style === 'cards' ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3' : 'grid-cols-1' }}">
                        @foreach($items as $item)
                            @php $tone = $tones[$item->type] ?? $tones['info']; @endphp
                            <article x-show="isVisible({{ $item->id }})" x-transition.opacity wire:key="ann-{{ $item->id }}"
                                     class="relative flex {{ $style === 'cards' ? 'flex-col' : 'items-start' }} gap-4 rounded-[1.5rem] border p-5 {{ $tone['box'] }}">
                                <span class="w-11 h-11 flex-shrink-0 rounded-xl flex items-center justify-center {{ $tone['icon'] }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$item->type] ?? $icons['info'] }}"/></svg>
                                </span>
                                <div class="flex-1 min-w-0 {{ $dismissible ? 'pl-6' : '' }}">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-[15px] font-black text-gray-900 dark:text-white">{{ $item->title }}</h3>
                                        @if($item->is_pinned)
                                            <span class="text-[10px] font-black rounded-full px-2 py-0.5 bg-brown-600/10 text-brown-600">مهم</span>
                                        @endif
                                    </div>
                                    @if($showDate)
                                        <time datetime="{{ ($item->starts_at ?? $item->created_at)?->toDateString() }}" class="block text-[11px] font-bold text-gray-400 mt-1">
                                            {{ verta($item->starts_at ?? $item->created_at)->format('j F Y') }}
                                        </time>
                                    @endif
                                    <p class="text-sm leading-7 text-gray-600 dark:text-gray-300 mt-2">{!! nl2br(e($item->content)) !!}</p>
                                    @if($link = $item->safeLink())
                                        <a href="{{ $link }}" class="inline-flex items-center gap-1 mt-3 text-xs font-black {{ $tone['link'] }} hover:gap-2 transition-all">
                                            {{ $item->link_text ?: 'مشاهده جزئیات' }}
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
                                        </a>
                                    @endif
                                </div>
                                @if($dismissible)
                                    <button type="button" @click="dismiss({{ $item->id }})" class="absolute top-4 left-4 text-gray-400 hover:text-gray-700 dark:hover:text-white" aria-label="بستن اطلاعیه">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif
</div>
