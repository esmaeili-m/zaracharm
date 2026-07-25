<?php

use Livewire\Component;
use App\Models\Invoice;
new class extends Component
{
    public Invoice $invoice;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount($id)
    {
        abort_if(!auth()->user()->can('invoices.view'), 403);

        $this->invoice = Invoice::with([
            'items',
            'payments',
            'user'
        ])->findOrFail($id);
    }
};
?>

<div>
    <div>

        {{-- HEADER --}}
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="page-title fs-18 fw-bold">
                    فاکتور #{{ $invoice->invoice_number }}
                </h1>
                <p class="text-muted">
                    جزئیات کامل فاکتور
                </p>
            </div>
        </div>

        {{-- INFO CARDS --}}
        <div class="row">

            <div class="col-md-3">
                <div class="card p-3">
                    <div>وضعیت</div>
                    <h5 class="mt-2">
                    <span class="badge bg-{{ $invoice->status == 'paid' ? 'success' : ($invoice->status == 'pending' ? 'warning' : 'danger') }}">
                        {{ $invoice->status }}
                    </span>
                    </h5>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3">
                    <div>مبلغ کل</div>
                    <h5 class="mt-2">{{ number_format($invoice->total_amount) }} تومان</h5>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3">
                    <div>پرداخت شده</div>
                    <h5  class="mt-2">{{ number_format($invoice->paid_amount) }} تومان</h5>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3">
                    <div>تاریخ پرداخت</div>
                    <h5 class="mt-2">
                        {{ $invoice->paid_at ? \Hekmatinasser\Verta\Verta::instance($invoice->paid_at)->formatJalaliDate() : '-' }}                    </h5>
                </div>
            </div>

        </div>

        {{-- ITEMS --}}
        <div class="card mt-4 p-3">
            <div class="card-header">
                <h5>آیتم‌های فاکتور</h5>
            </div>

            <div class="card-body table-responsive">

                <table class="table text-nowrap">

                    <thead>
                    <tr>
                        <th>#</th>
                        <th>عنوان</th>
                        <th>قیمت</th>
                        <th>تعداد</th>
                        <th>جمع</th>
                    </tr>
                    </thead>

                    <tbody>

                    @foreach($invoice->items as $index => $item)

                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->title }}</td>
                            <td>{{ number_format($item->price) }}</td>
                            <td>{{ $item->qty }}</td>
                            <td>{{ number_format($item->total) }}</td>
                        </tr>

                    @endforeach

                    </tbody>

                </table>

            </div>
        </div>

        {{-- PAYMENTS --}}
        <div class="card mt-4 p-3">

            <div class="card-header">
                <h5>پرداخت‌ها</h5>
            </div>

            <div class="card-body table-responsive">

                <table class="table">

                    <thead>
                    <tr>
                        <th>درگاه</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>تاریخ</th>
                    </tr>
                    </thead>

                    <tbody>

                    @foreach($invoice->payments as $payment)

                        <tr>
                            <td>{{ $payment->gateway }}</td>
                            <td>{{ number_format($payment->amount) }}</td>
                            <td>
                            <span class="badge bg-{{ $payment->status == 'success' ? 'success' : 'danger' }}">
                                {{ $payment->status }}
                            </span>
                            </td>
                            <td>
                                {{ $invoice->paid_at ? \Hekmatinasser\Verta\Verta::instance($invoice->paid_at)->formatJalaliDate() : '-' }}
                            </td>
                        </tr>

                    @endforeach

                    </tbody>

                </table>

            </div>
        </div>

    </div>
</div>
