<?php

use Livewire\Component;
use App\Support\Sections\ReturnPolicy;

new class extends Component
{
    public $data;

    public function mount($data)
    {
        // فیلدهای ذخیره‌نشده با مقادیر پیش‌فرض پر می‌شوند
        $this->data = array_merge(ReturnPolicy::defaults(), array_filter((array) $data, fn ($v) => $v !== null));
    }
};
?>

@php
    $conditions = array_values(array_filter((array) ($data['conditions'] ?? []), 'filled'));
    $exclusions = array_values(array_filter((array) ($data['exclusions'] ?? []), 'filled'));
    $steps = array_values(array_filter((array) ($data['steps'] ?? []), fn ($s) => filled($s['title'] ?? null)));
    $days = (int) ($data['return_days'] ?? 0);
@endphp

<div>
    <section class="relative transition-colors duration-500 overflow-hidden" dir="rtl">
        <div class="lg:container mx-auto relative z-10 space-y-8 pb-6">

            {{-- Header --}}
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 border-r-4 border-brown-600 pr-2 pl-2">
                <div>
                    <h2 class="text-3xl lg:text-4xl font-black text-gray-900 dark:text-white">
                        @if(filled($data['title'] ?? null))
                            {{ $data['title'] }}
                        @else
                            شرایط <span class="text-brown-600">مرجوعی کالا</span>
                        @endif
                    </h2>
                    @if(filled($data['description'] ?? null))
                        <p class="text-gray-500 dark:text-gray-400 mt-2 font-bold text-sm leading-7 max-w-2xl">
                            {{ $data['description'] }}
                        </p>
                    @endif
                </div>

                @if($days > 0)
                    <div class="flex items-center gap-4 w-fit px-5 py-4 bg-white/70 dark:bg-white/[0.03] backdrop-blur-md rounded-[2rem] border border-gray-200 dark:border-white/10 shadow-sm">
                        <span class="w-14 h-14 rounded-2xl bg-brown-600 text-white flex flex-col items-center justify-center shadow-lg shadow-brown-600/20">
                            <span class="text-xl font-black leading-none tabular-nums">{{ $days }}</span>
                            <span class="text-[9px] font-bold mt-0.5">روز</span>
                        </span>
                        <span>
                            <span class="block text-[13px] font-black text-gray-900 dark:text-white">ضمانت بازگشت کالا</span>
                            <span class="block text-[11px] font-bold text-gray-400 mt-1">از زمان تحویل سفارش</span>
                        </span>
                    </div>
                @endif
            </div>

            {{-- Conditions / exclusions --}}
            @if($conditions || $exclusions)
                <div class="grid grid-cols-1 {{ $conditions && $exclusions ? 'lg:grid-cols-2' : '' }} gap-6">

                    @if($conditions)
                        <div class="bg-white/70 dark:bg-white/[0.03] backdrop-blur-md rounded-[2.5rem] border border-gray-200 dark:border-white/10 p-6 lg:p-8">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </span>
                                <h3 class="text-[16px] font-black text-gray-900 dark:text-white">شرایط پذیرش مرجوعی</h3>
                            </div>
                            <ul class="space-y-4">
                                @foreach($conditions as $condition)
                                    <li class="flex items-start gap-3 text-[13px] font-medium text-gray-600 dark:text-gray-300 leading-7">
                                        <svg class="w-4 h-4 mt-1.5 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        {{ $condition }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($exclusions)
                        <div class="bg-white/70 dark:bg-white/[0.03] backdrop-blur-md rounded-[2.5rem] border border-gray-200 dark:border-white/10 p-6 lg:p-8">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-10 h-10 rounded-xl bg-red-500/10 text-red-500 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                </span>
                                <h3 class="text-[16px] font-black text-gray-900 dark:text-white">کالاهای غیرقابل مرجوع</h3>
                            </div>
                            <ul class="space-y-4">
                                @foreach($exclusions as $exclusion)
                                    <li class="flex items-start gap-3 text-[13px] font-medium text-gray-600 dark:text-gray-300 leading-7">
                                        <svg class="w-4 h-4 mt-1.5 flex-shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ $exclusion }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Steps --}}
            @if($steps)
                <div class="bg-white/40 dark:bg-zinc-900/40 backdrop-blur-md border-2 border-gray-200 dark:border-white/10 rounded-[3rem] p-6 lg:p-10">
                    <div class="flex items-center gap-3 mb-8">
                        <span class="w-2 h-8 bg-brown-600 rounded-full"></span>
                        <h3 class="text-[18px] font-black text-gray-900 dark:text-white">مراحل ثبت مرجوعی</h3>
                    </div>

                    <ol class="grid grid-cols-1 sm:grid-cols-2 {{ count($steps) >= 4 ? 'xl:grid-cols-4' : (count($steps) === 3 ? 'xl:grid-cols-3' : '') }} gap-6">
                        @foreach($steps as $step)
                            <li class="relative p-6 bg-white/70 dark:bg-white/[0.03] rounded-[2rem] border border-gray-200 dark:border-white/10 transition-all duration-500 hover:shadow-lg hover:shadow-brown-600/10 hover:-translate-y-1">
                                <span class="w-11 h-11 mb-4 rounded-xl bg-brown-600/10 text-brown-600 border border-brown-600/20 flex items-center justify-center text-[15px] font-black tabular-nums">
                                    {{ $loop->iteration }}
                                </span>
                                <h4 class="text-[14px] font-black text-gray-900 dark:text-white mb-2">{{ $step['title'] }}</h4>
                                @if(filled($step['text'] ?? null))
                                    <p class="text-[12px] font-medium text-gray-500 dark:text-gray-400 leading-7">{{ $step['text'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            {{-- Note --}}
            @if(filled($data['note'] ?? null))
                <div class="flex items-start gap-3 p-5 rounded-[2rem] bg-amber-500/10 border border-amber-500/20 text-[13px] font-bold text-amber-700 dark:text-amber-400 leading-7">
                    <svg class="w-5 h-5 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{!! nl2br(e($data['note'])) !!}</span>
                </div>
            @endif

            {{-- CTA: ثبت مرجوعی از سفارش‌های من (مهمان => ورود، طبق middleware auth) --}}
            @if($data['show_cta'] ?? true)
                <div class="flex justify-center">
                    <a href="{{ route('user.dashboard', ['tab' => 'orders']) }}"
                       class="inline-flex items-center gap-3 px-10 py-4 bg-brown-600 text-white rounded-[2rem] font-black text-[14px] shadow-[0_20px_40px_rgba(120,72,45,0.25)] hover:bg-brown-700 dark:hover:bg-brown-500 transition-all duration-500 active:scale-95">
                        ثبت درخواست مرجوعی از سفارش‌های من
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                </div>
            @endif
        </div>
    </section>
</div>
