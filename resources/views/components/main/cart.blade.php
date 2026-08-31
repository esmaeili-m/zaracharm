<?php

use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        // فقط برای re-render شدن کامپوننت
    }

    public function getCartCountProperty(): int
    {
        return auth()->user()?->cart?->items()->count() ?? 0;
    }
};
?>

<button
    id="cart-btn"
    type="button"
    x-on:click="$dispatch('open-cart')"
    class="relative p-2.5 rounded-xl border border-secondary-500/20 bg-secondary-500/10 dark:bg-secondary-500/20 backdrop-blur-md hover:bg-secondary-500 hover:text-white transition-all duration-300 group shadow-lg shadow-secondary-500/10"
>
    <svg
        class="w-6 h-6 text-secondary-600 group-hover:text-white transition-colors"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
    >
        <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.8"
            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
        />
    </svg>

    <span
        class="absolute -top-1.5 -right-1.5 bg-primary-600 text-white text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-lg border-2 border-white dark:border-gray-950 shadow-sm"
    >
        {{ $this->cartCount }}
    </span>
</button>
