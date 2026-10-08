{{-- لیست نتایج یک نوع در صفحه جستجو؛ محصول و برند با کارت‌های مشترک پروژه --}}
@if($itemsType === 'products')
    <div class="grid pb-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
        @foreach($items as $product)
            <x-main.products.card
                wire:key="search-product-{{ $product->id }}"
                :product="$product"
                :wishlisted="$this->isWishlisted($product->id)"
            />
        @endforeach
    </div>
@elseif($itemsType === 'brands')
    <div class="grid pb-6 grid-cols-2 lg:grid-cols-3 gap-3 md:gap-6 lg:gap-8">
        @foreach($items as $brand)
            <x-main.brands.card
                wire:key="search-brand-{{ $brand->id }}"
                :brand="$brand"
                :products-count="$brand->active_products_count"
            />
        @endforeach
    </div>
@else
    <div class="grid pb-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($items as $item)
            <x-main.search.result-card wire:key="search-item-{{ $item['id'] }}" :item="$item" />
        @endforeach
    </div>
@endif
