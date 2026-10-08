{{-- پیش‌نمایش اثر تخفیف کمپین (قیمت اصلی ← تخفیف ← قیمت نهایی) --}}
@php $stats = $preview['stats']; @endphp

<div class="card custom-card">
    <div class="card-header justify-content-between flex-wrap gap-2">
        <div>
            <div class="card-title">پیش‌نمایش قیمت‌ها</div>
            <div class="small text-muted mt-1">
                {{ number_format($stats['applied']) }} تنوع تخفیف می‌گیرد
                @if($stats['total_discount'])
                    · مجموع تخفیف (یک عدد از هر تنوع): {{ number_format($stats['total_discount']) }} تومان
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1 small">
            @if($stats['loss'])<span class="badge bg-danger-transparent">{{ $stats['loss'] }} زیان‌ده</span>@endif
            @if($stats['overridden'])<span class="badge bg-info-transparent">{{ $stats['overridden'] }} با تخفیف بزرگ‌تر دیگر</span>@endif
            @if($stats['excluded'])<span class="badge bg-light text-muted">{{ $stats['excluded'] }} خارج از شرط قیمت</span>@endif
        </div>
    </div>
    <div class="card-body">
        @if($preview['rows']->isEmpty())
            <div class="text-center text-muted py-4">
                @if(!$stats['variants'])
                    محصولی با قیمت در این کمپین نیست (مرحله ۲ را بررسی کنید).
                @else
                    مقدار تخفیف را وارد کنید تا پیش‌نمایش نمایش داده شود.
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle text-nowrap mb-0">
                    <thead>
                    <tr class="small">
                        <th>کالا</th>
                        <th>قیمت اصلی</th>
                        <th>تخفیف</th>
                        <th>قیمت نهایی</th>
                        <th>قیمت خرید</th>
                        <th>وضعیت</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($preview['rows'] as $row)
                        <tr wire:key="pv-{{ $row['variant_id'] }}" class="{{ $row['loss'] ? 'table-danger' : '' }}">
                            <td class="text-wrap" style="max-width: 260px">
                                <div class="fw-semibold small">{{ $row['product'] }}</div>
                                @if($row['variant'])<div class="small text-muted">{{ $row['variant'] }}</div>@endif
                            </td>
                            <td class="small">{{ number_format($row['price']) }}</td>
                            <td class="small {{ $row['amount'] && !$row['overridden'] ? 'text-danger fw-semibold' : 'text-muted' }}">
                                @if($row['amount'])
                                    {{ $row['percent'] }}٪ <span class="text-muted">({{ number_format($row['amount']) }})</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small fw-bold {{ $row['amount'] && !$row['overridden'] ? 'text-success' : '' }}">
                                {{ number_format($row['overridden'] ? $row['price'] - $row['competitor']['amount'] : $row['final']) }}
                            </td>
                            <td class="small text-muted">{{ $row['cost'] !== null ? number_format($row['cost']) : '—' }}</td>
                            <td class="small">
                                @if(!$row['eligible'])
                                    <span class="badge bg-light text-muted">خارج از شرط قیمت</span>
                                @elseif($row['overridden'])
                                    <span class="badge bg-info-transparent" title="فقط بزرگ‌ترین تخفیف اعمال می‌شود">{{ $row['competitor']['title'] }} بزرگ‌تر است</span>
                                @elseif($row['loss'])
                                    <span class="badge bg-danger">⚠️ زیر قیمت خرید</span>
                                @elseif($row['amount'] && $row['final'] === 0)
                                    <span class="badge bg-danger">رایگان!</span>
                                @elseif($row['amount'])
                                    <span class="badge bg-success-transparent">اعمال می‌شود</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($stats['variants'] > $preview['rows']->count())
                <div class="small text-muted mt-2">{{ $preview['rows']->count() }} ردیف از {{ number_format($stats['variants']) }} تنوع نمایش داده شده است؛ آمار بالا برای همه تنوع‌هاست.</div>
            @endif
        @endif
    </div>
</div>
