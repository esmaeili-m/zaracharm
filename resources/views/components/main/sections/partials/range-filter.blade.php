{{--
    اسلایدر بازه دو‌دستگیره (قیمت و ویژگی‌های عددی)
    props: rangeKey, floor, ceil, from, to, step, call (متد Livewire), args (آرگومان‌های قبل از min/max), unit
    - در حین درگ فقط نمایش تغییر می‌کند؛ با رها کردن دستگیره یا تغییر اینپوت یک درخواست ارسال می‌شود.
    - wire:key وابسته به مقدار انتخاب‌شده است تا پس از «حذف فیلتر» اسلایدر با مقدار تازه ساخته شود.
--}}
<div wire:key="range-{{ $rangeKey }}"
     x-data="{
        floor: {{ (float) $floor }}, ceil: {{ (float) $ceil }}, step: {{ (float) $step }},
        min: {{ (float) $from }}, max: {{ (float) $to }}, dragging: null,
        get span() { return Math.max(0.0001, this.ceil - this.floor) },
        get minPercent() { return (this.min - this.floor) / this.span * 100 },
        get maxPercent() { return (this.max - this.floor) / this.span * 100 },
        clamp(v) { return Math.min(this.ceil, Math.max(this.floor, v)) },
        round(v) { const r = Math.round(v / this.step) * this.step; return Number(r.toFixed(4)) },
        fmt(v) { return Number(v).toLocaleString('fa-IR') },
        commit() { $wire.call(@js($call), ...@js(array_values($args)), this.min, this.max) },
        valueAt(clientX) {
            const rect = this.$refs.track.getBoundingClientRect();
            const p = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
            return this.clamp(this.round(this.floor + p * this.span));
        },
        start(handle, e) {
            e.preventDefault();
            this.dragging = handle;
            const move = (ev) => {
                const v = this.valueAt(ev.touches ? ev.touches[0].clientX : ev.clientX);
                if (this.dragging === 'min') this.min = Math.min(v, this.max); else this.max = Math.max(v, this.min);
            };
            const stop = () => {
                this.dragging = null;
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', stop);
                this.commit();
            };
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', stop);
        },
        setFromInput(which, value) {
            const v = this.clamp(Number(String(value).replace(/[^\d.]/g, '')) || (which === 'min' ? this.floor : this.ceil));
            if (which === 'min') this.min = Math.min(v, this.max); else this.max = Math.max(v, this.min);
            this.commit();
        },
     }">
    <div x-ref="track" dir="ltr" class="relative w-full h-1.5 bg-gray-200 dark:bg-white/10 rounded-full mt-4 mb-8 select-none">
        <div class="absolute h-full bg-brown-500 rounded-full pointer-events-none" :style="`left: ${minPercent}%; right: ${100 - maxPercent}%`"></div>
        <div class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-6 h-6 bg-white dark:bg-zinc-900 border-2 border-brown-500 rounded-full shadow-md cursor-grab active:cursor-grabbing z-20 touch-none"
             :style="`left: ${minPercent}%`" @pointerdown="start('min', $event)" role="slider" aria-label="حداقل" :aria-valuenow="min"></div>
        <div class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-6 h-6 bg-white dark:bg-zinc-900 border-2 border-brown-500 rounded-full shadow-md cursor-grab active:cursor-grabbing z-20 touch-none"
             :style="`left: ${maxPercent}%`" @pointerdown="start('max', $event)" role="slider" aria-label="حداکثر" :aria-valuenow="max"></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <label class="flex items-center gap-2 bg-gray-50/50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-[1.25rem] px-3 py-2.5 focus-within:border-brown-500/50">
            <span class="text-[10px] font-bold text-gray-400">از</span>
            <input type="text" inputmode="decimal" :value="fmt(min)" @change="setFromInput('min', $event.target.value.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))"
                   class="w-full bg-transparent border-none focus:ring-0 text-xs font-black text-gray-700 dark:text-white p-0 text-left tabular-nums" dir="ltr">
        </label>
        <label class="flex items-center gap-2 bg-gray-50/50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-[1.25rem] px-3 py-2.5 focus-within:border-brown-500/50">
            <span class="text-[10px] font-bold text-gray-400">تا</span>
            <input type="text" inputmode="decimal" :value="fmt(max)" @change="setFromInput('max', $event.target.value.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))"
                   class="w-full bg-transparent border-none focus:ring-0 text-xs font-black text-gray-700 dark:text-white p-0 text-left tabular-nums" dir="ltr">
        </label>
    </div>
    @if($unit)
        <div class="mt-2 text-[10px] font-bold text-gray-400 text-center">{{ $unit }}</div>
    @endif
</div>
