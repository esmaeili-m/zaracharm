{{-- دکمه قلب کارت محصول؛ داخل کامپوننت Livewire که از HandlesWishlist استفاده می‌کند --}}
@props([
    'productId',
    'active' => false,
    'activeClass' => 'text-red-500',
    'inactiveClass' => 'text-gray-900 dark:text-white hover:text-red-500',
    'iconClass' => 'w-5 h-5',
])

<button
    type="button"
    wire:click.prevent.stop="toggleWishlist({{ (int) $productId }})"
    wire:loading.attr="disabled"
    wire:target="toggleWishlist({{ (int) $productId }})"
    aria-pressed="{{ $active ? 'true' : 'false' }}"
    title="{{ $active ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی' }}"
    {{ $attributes->class([$active ? $activeClass : $inactiveClass, 'disabled:opacity-50']) }}
>
    <svg class="{{ $iconClass }}" fill="{{ $active ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
    </svg>
</button>
