{{-- انتخاب مشتریان سایت (داخل کامپوننت send؛ فیلتر، شمارش و صفحه‌بندی در سرور) --}}
<div x-data="{ advanced: false }">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1">جستجوی نام یا موبایل</label>
            <input type="text" class="form-control form-control-sm" wire:model.live.debounce.400ms="filters.q" placeholder="مثلاً علی یا 0912...">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">سفارش</label>
            <select class="form-select form-select-sm" wire:model.live="filters.orders">
                <option value="">همه</option>
                <option value="with">دارای سفارش</option>
                <option value="without">بدون سفارش</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">وضعیت حساب</label>
            <select class="form-select form-select-sm" wire:model.live="filters.status">
                <option value="">همه</option>
                <option value="1">فعال</option>
                <option value="0">غیرفعال</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="button" class="btn btn-sm btn-light flex-fill" @click="advanced = !advanced"><i class="ri-filter-3-line"></i> فیلتر پیشرفته</button>
            <button type="button" class="btn btn-sm btn-light" wire:click="resetFilters" title="پاک‌کردن فیلترها"><i class="ri-filter-off-line"></i></button>
        </div>
    </div>

    <div class="border rounded p-3 mt-2" x-show="advanced" x-collapse>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label small mb-1">ترکیب شرط‌ها</label>
                <select class="form-select form-select-sm" wire:model.live="filters.match">
                    <option value="all">همه شرط‌ها (و)</option>
                    <option value="any">حداقل یک شرط (یا)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">ثبت‌نام از تاریخ</label>
                <input data-jdp type="text" dir="ltr" class="form-control form-control-sm" wire:model.lazy="filters.registered_from" placeholder="1405/01/01">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">ثبت‌نام تا تاریخ</label>
                <input data-jdp type="text" dir="ltr" class="form-control form-control-sm" wire:model.lazy="filters.registered_to">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">رضایت پیامک تبلیغاتی</label>
                <select class="form-select form-select-sm" wire:model.live="filters.consent">
                    <option value="">همه</option>
                    <option value="yes">دارای رضایت</option>
                    <option value="no">بدون رضایت</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">حداقل تعداد سفارش پرداخت‌شده</label>
                <input type="number" min="0" class="form-control form-control-sm" wire:model.live.debounce.500ms="filters.min_orders">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">حداکثر تعداد سفارش</label>
                <input type="number" min="0" class="form-control form-control-sm" wire:model.live.debounce.500ms="filters.max_orders">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">حداقل مجموع خرید (تومان)</label>
                <input type="number" min="0" class="form-control form-control-sm" wire:model.live.debounce.500ms="filters.min_spent">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">حداکثر مجموع خرید (تومان)</label>
                <input type="number" min="0" class="form-control form-control-sm" wire:model.live.debounce.500ms="filters.max_spent">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">آخرین سفارش از تاریخ</label>
                <input data-jdp type="text" dir="ltr" class="form-control form-control-sm" wire:model.lazy="filters.last_order_from">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">آخرین سفارش تا تاریخ</label>
                <input data-jdp type="text" dir="ltr" class="form-control form-control-sm" wire:model.lazy="filters.last_order_to">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">دارای سفارش با وضعیت</label>
                <select class="form-select form-select-sm" wire:model.live="filters.order_status">
                    <option value="">همه</option>
                    @foreach(\App\Support\OrderStatus::STATUSES as $key => $status)
                        <option value="{{ $key }}">{{ $status['title'] }} ({{ $key }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="onlyCustomers" wire:model.live="filters.only_customers">
                    <label class="form-check-label small" for="onlyCustomers">فقط مشتریان (بدون کارکنان پنل)</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">استان (نشانی)</label>
                <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="filters.province">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">شهر (نشانی)</label>
                <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="filters.city">
            </div>
        </div>
        <div class="fs-11 text-muted mt-2">«سفارش» یعنی سفارش پرداخت‌شده. جستجوی نام/موبایل همیشه با بقیه شرط‌ها «و» می‌شود.</div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 p-2 rounded bg-light">
        <div class="small">
            <strong>{{ number_format($this->matchingCount) }}</strong> مشتری منطبق با فیلتر —
            <strong class="text-primary">{{ number_format($this->selectedCount()) }}</strong> انتخاب‌شده
            @if($selection === 'all')
                <span class="badge bg-primary-transparent">همه نتایج فیلتر@if($excludedIds) به‌جز {{ count($excludedIds) }} نفر@endif</span>
            @endif
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="selectAllMatching" @disabled(!$this->matchingCount)>انتخاب همه {{ number_format($this->matchingCount) }} نتیجه</button>
            <button type="button" class="btn btn-sm btn-light" wire:click="selectPage">انتخاب این صفحه</button>
            <button type="button" class="btn btn-sm btn-light text-danger" wire:click="clearSelection" @disabled(!$this->selectedCount())>لغو انتخاب همه</button>
        </div>
    </div>

    <div class="table-responsive mt-2 position-relative">
        <div wire:loading.flex wire:target="filters,gotoPage,nextPage,previousPage,resetFilters" class="position-absolute top-0 start-0 w-100 h-100 bg-body bg-opacity-50 align-items-center justify-content-center" style="z-index: 2">
            <span class="spinner-border text-primary"></span>
        </div>
        <table class="table table-sm table-hover align-middle text-nowrap mb-0">
            <thead><tr><th style="width: 36px"></th><th>نام</th><th>موبایل</th><th>سفارش پرداخت‌شده</th><th>وضعیت</th><th>ثبت‌نام</th></tr></thead>
            <tbody>
            @forelse($this->customers as $customer)
                @php
                    $checked = $selection === 'all' ? !in_array($customer->id, $excludedIds, true) : in_array($customer->id, $selectedIds, true);
                @endphp
                <tr wire:key="c-{{ $customer->id }}" role="button" wire:click="toggleCustomer({{ $customer->id }})" class="{{ $checked ? 'table-primary' : '' }}">
                    <td><input type="checkbox" class="form-check-input" @checked($checked) tabindex="-1" style="pointer-events: none"></td>
                    <td>{{ trim($customer->first_name . ' ' . $customer->last_name) ?: '—' }}</td>
                    <td dir="ltr" class="text-end">{{ $customer->mobile }}
                        @unless(\App\Services\Sms\PhoneNumber::isValid($customer->mobile))<span class="badge bg-danger-transparent">نامعتبر</span>@endunless
                    </td>
                    <td>{{ number_format($customer->orders_count) }}</td>
                    <td><span class="badge {{ $customer->status ? 'bg-success-transparent' : 'bg-danger-transparent' }}">{{ $customer->status ? 'فعال' : 'غیرفعال' }}</span></td>
                    <td class="small text-muted">{{ verta($customer->created_at)->format('Y/m/d') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">مشتری‌ای با این فیلترها یافت نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">{{ $this->customers->links() }}</div>
</div>
