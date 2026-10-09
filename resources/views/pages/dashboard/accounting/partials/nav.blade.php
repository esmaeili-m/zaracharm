{{-- نوار ناوبری بخش حسابداری؛ @include(..., ['active' => 'route.name']) (روی درخواست‌های Livewire نام روت در دسترس نیست) --}}
@php
    $accountingTabs = [
        ['route' => 'accounting.index', 'title' => 'سود و زیان', 'icon' => 'ri-line-chart-line', 'can' => 'accounting.view'],
        ['route' => 'accounting.entries', 'title' => 'دریافت و پرداخت', 'icon' => 'ri-exchange-funds-line', 'can' => 'accounting.view'],
        ['route' => 'accounting.accounts', 'title' => 'حساب‌ها', 'icon' => 'ri-bank-line', 'can' => 'accounting.view'],
        ['route' => 'accounting.categories', 'title' => 'دسته‌ها', 'icon' => 'ri-price-tag-3-line', 'can' => 'accounting.view'],
        ['route' => 'purchases.index', 'title' => 'خریدها', 'icon' => 'ri-shopping-basket-2-line', 'can' => 'purchases.view'],
        ['route' => 'suppliers.index', 'title' => 'تأمین‌کنندگان', 'icon' => 'ri-truck-line', 'can' => 'purchases.view'],
    ];
@endphp

<div class="card custom-card mb-3">
    <div class="card-body py-2">
        <nav class="nav nav-pills flex-nowrap overflow-auto gap-1">
            @foreach($accountingTabs as $tab)
                @can($tab['can'])
                    <a href="{{ route($tab['route']) }}"
                       class="nav-link text-nowrap {{ ($active ?? '') === $tab['route'] ? 'active' : '' }}">
                        <i class="{{ $tab['icon'] }} me-1 align-middle"></i>{{ $tab['title'] }}
                    </a>
                @endcan
            @endforeach
        </nav>
    </div>
</div>
