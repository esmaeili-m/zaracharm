<?php

use Livewire\Component;
use Livewire\Attributes\On;

// شمارنده سبد خرید (نوار پایین موبایل)؛ با رویداد cart-updated بروز می‌شود
new class extends Component
{
    #[On('cart-updated')]
    public function refreshCount(): void
    {
        // فقط برای رندر مجدد
    }

    public function getCountProperty(): int
    {
        return app(\App\Services\Cart\CartService::class)->count(auth()->id());
    }
};
?>

<span class="{{ $this->count ? '' : 'hidden' }} absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 bg-white text-brown-700 text-[10px] font-black flex items-center justify-center rounded-lg border-2 border-brown-600 shadow-sm tabular-nums">
    {{ $this->count > 99 ? '99+' : $this->count }}
</span>
