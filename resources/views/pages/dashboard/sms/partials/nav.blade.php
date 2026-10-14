{{-- نوار ناوبری بخش پیامک؛ @include(..., ['active' => 'route.name']) (روی درخواست‌های Livewire نام روت در دسترس نیست) --}}
@php
    $smsTabs = [
        ['route' => 'sms.index', 'title' => 'داشبورد', 'icon' => 'ri-dashboard-line', 'can' => 'sms.view'],
        ['route' => 'sms.send', 'title' => 'ارسال پیامک', 'icon' => 'ri-send-plane-2-line', 'can' => 'sms.send'],
        ['route' => 'sms.templates', 'title' => 'الگوها', 'icon' => 'ri-file-list-3-line', 'can' => 'sms.view'],
        ['route' => 'sms.contacts', 'title' => 'مخاطبان و لیست‌ها', 'icon' => 'ri-contacts-book-2-line', 'can' => 'sms.contacts'],
        ['route' => 'sms.reports', 'title' => 'گزارش پیامک‌ها', 'icon' => 'ri-file-chart-line', 'can' => 'sms.view'],
    ];
@endphp

<div class="card custom-card mb-3">
    <div class="card-body py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <nav class="nav nav-pills flex-nowrap overflow-auto gap-1">
            @foreach($smsTabs as $tab)
                @can($tab['can'])
                    <a href="{{ route($tab['route']) }}"
                       class="nav-link text-nowrap {{ ($active ?? '') === $tab['route'] ? 'active' : '' }}">
                        <i class="{{ $tab['icon'] }} me-1 align-middle"></i>{{ $tab['title'] }}
                    </a>
                @endcan
            @endforeach
        </nav>
        @if(config('sms.sending') !== 'live')
            <span class="badge bg-warning-transparent text-wrap" title="SMS_SENDING=fake در فایل .env">
                <i class="ri-flask-line align-middle"></i> حالت شبیه‌سازی: هیچ پیامکی واقعاً ارسال نمی‌شود
            </span>
        @else
            <span class="badge bg-success-transparent"><i class="ri-broadcast-line align-middle"></i> ارسال واقعی فعال است</span>
        @endif
    </div>
</div>
