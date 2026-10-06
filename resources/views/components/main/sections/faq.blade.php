<?php

use Livewire\Component;
use App\Models\Faq;

new class extends Component
{
    public $data;
    public $faqs;

    public function mount($data)
    {

        $this->data = $data;
        $this->faqs = collect();

        if (!$data) {
            return;
        }

        $limit = (int) ($data['limit'] ?? 8);

        $query = Faq::active()->select(['id', 'question', 'answer', 'sort_order']);

        $this->faqs = match ($data['mode'] ?? 'latest') {

            'manual' => $query->whereIn('id', $data['faq_ids'] ?? [])
                ->get()
                // حفظ ترتیب انتخاب مدیر
                ->sortBy(fn ($faq) => array_search($faq->id, array_map('intval', $data['faq_ids'] ?? [])))
                ->values(),

            'category' => $query->where('category_id', $data['category_id'] ?? 0)
                ->orderBy('sort_order')
                ->take($limit)
                ->get(),

            // ترتیب تعیین‌شده در داشبورد سوالات متداول
            default => $query->orderBy('sort_order')->orderBy('id')->take($limit)->get(),
        };
    }

    // اسکیما FAQPage برای نتایج غنی گوگل
    public function schema(): ?string
    {
        if ($this->faqs->isEmpty() || !($this->data['schema'] ?? true)) {
            return null;
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $this->faqs->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($faq->answer),
                ],
            ])->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }
};
?>

@php
    $columns = (int) ($data['columns'] ?? 1) === 2 ? 2 : 1;
    $openFirst = (bool) ($data['open_first'] ?? true);
    $showSearch = (bool) ($data['show_search'] ?? false);
@endphp

<div>
    @if($faqs->isNotEmpty())
        <section
            class="relative transition-colors duration-500 overflow-hidden"
            dir="rtl"
            x-data="{
                open: {{ $openFirst ? $faqs->first()->id : 'null' }},
                term: '',
                items: @js($faqs->mapWithKeys(fn ($faq) => [$faq->id => $faq->question . ' ' . $faq->answer])),
                get hasMatches() { return Object.values(this.items).some(text => this.matches(text)) },
                toggle(id) { this.open = this.open === id ? null : id },
                matches(text) {
                    const t = this.term.trim().toLowerCase();
                    return t === '' || text.toLowerCase().includes(t);
                },
            }"
        >
            <div class="lg:container mx-auto relative z-10">

                {{-- Header --}}
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6 border-r-4 border-brown-600 pr-2 pl-2">
                    <div>
                        <h2 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">
                            @if(filled($data['title'] ?? null))
                                {{ $data['title'] }}
                            @else
                                سوالات <span class="text-brown-600">متداول</span>
                            @endif
                        </h2>
                        <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm">
                            {{ filled($data['description'] ?? null) ? $data['description'] : 'پاسخ رایج‌ترین پرسش‌های شما' }}
                        </p>
                    </div>

                    @if($showSearch)
                        <div class="relative w-full md:max-w-xs">
                            <input type="search" x-model="term"
                                   class="w-full bg-white/70 dark:bg-white/[0.03] backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl py-3.5 pr-11 pl-4 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-4 ring-brown-600/10 focus:border-brown-600 transition-all placeholder:text-gray-400 shadow-sm"
                                   placeholder="جستجو در سوالات...">
                            <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Accordion --}}
                <div class="grid grid-cols-1 {{ $columns === 2 ? 'lg:grid-cols-2' : '' }} gap-4 items-start pb-6">
                    @foreach($faqs as $faq)
                        <div
                            wire:key="faq-{{ $faq->id }}"
                            x-show="matches(items[{{ $faq->id }}])"
                            x-transition.opacity
                            :class="open === {{ $faq->id }} ? 'border-brown-600/40 shadow-lg shadow-brown-600/10 bg-white/90 dark:bg-white/[0.06]' : 'border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/[0.03]'"
                            class="group backdrop-blur-md rounded-[2rem] border transition-all duration-500"
                        >
                            <h3>
                                <button
                                    type="button"
                                    @click="toggle({{ $faq->id }})"
                                    :aria-expanded="open === {{ $faq->id }}"
                                    aria-controls="faq-answer-{{ $faq->id }}"
                                    id="faq-question-{{ $faq->id }}"
                                    class="w-full flex items-center justify-between gap-4 p-5 lg:p-6 text-right"
                                >
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span
                                            :class="open === {{ $faq->id }} ? 'bg-brown-600 text-white' : 'bg-brown-600/10 text-brown-600'"
                                            class="w-10 h-10 flex-shrink-0 rounded-xl flex items-center justify-center text-[15px] font-black transition-colors duration-300"
                                        >؟</span>
                                        <span class="text-[14px] lg:text-[15px] font-black text-gray-900 dark:text-white leading-7">
                                            {{ $faq->question }}
                                        </span>
                                    </span>

                                    <span
                                        :class="open === {{ $faq->id }} ? 'rotate-180 bg-brown-600/10 text-brown-600' : 'text-gray-400'"
                                        class="w-8 h-8 flex-shrink-0 rounded-lg flex items-center justify-center transition-all duration-300"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </button>
                            </h3>

                            <div
                                id="faq-answer-{{ $faq->id }}"
                                role="region"
                                aria-labelledby="faq-question-{{ $faq->id }}"
                                x-show="open === {{ $faq->id }}"
                                x-collapse
                                @if(!($openFirst && $loop->first)) style="display: none" @endif
                            >
                                <div class="px-5 lg:px-6 pb-6 pr-[4.75rem] lg:pr-[5.25rem] text-[13px] font-medium text-gray-600 dark:text-gray-300 leading-8">
                                    {!! nl2br(e($faq->answer)) !!}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($showSearch)
                    <p x-cloak
                       x-show="!hasMatches"
                       class="text-center py-10 text-[13px] font-bold text-gray-400">
                        سوالی با این عبارت پیدا نشد.
                    </p>
                @endif
            </div>

            @if($schema = $this->schema())
                <script type="application/ld+json">{!! $schema !!}</script>
            @endif
        </section>
    @endif
</div>
