<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Accounting\PurchaseService;
use App\Support\JalaliDate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    public $info = [];
    public ?Purchase $purchase = null;

    public $supplier_id;
    public $inventory_id;
    public $purchase_date;
    public $supplier_invoice_number;
    public $discount_amount = 0;
    public $update_cost_price = true;
    public $note;

    // [[variant_id, quantity, unit_cost, product, variant, current_cost], ...]
    public array $lines = [];
    public string $variantSearch = '';

    // افزودن سریع تأمین‌کننده
    public $newSupplierTitle = '';

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(?Purchase $purchase = null)
    {
        abort_if(!auth()->user()->can($purchase?->exists ? 'purchases.edit' : 'purchases.create'), 403);

        $this->info['header'] = $purchase?->exists ? 'ویرایش خرید ' . $purchase->purchase_number : 'ثبت خرید کالا';
        $this->purchase_date = JalaliDate::today();
        $this->inventory_id = $this->inventories->first()?->id;

        if (!$purchase?->exists) {
            return;
        }

        abort_unless($purchase->status === 'draft', 403, 'فقط خرید پیش‌نویس قابل ویرایش است.');

        $purchase->load('items.variant');
        $this->purchase = $purchase;
        $this->supplier_id = $purchase->supplier_id;
        $this->inventory_id = $purchase->inventory_id;
        $this->purchase_date = $purchase->jalali_date;
        $this->supplier_invoice_number = $purchase->supplier_invoice_number;
        $this->discount_amount = (int) $purchase->discount_amount;
        $this->update_cost_price = (bool) $purchase->update_cost_price;
        $this->note = $purchase->note;

        $this->lines = $purchase->items->map(fn ($item) => [
            'variant_id' => $item->variant_id,
            'quantity' => (int) $item->quantity,
            'unit_cost' => (int) $item->unit_cost,
            'product' => $item->product_name,
            'variant' => $item->variant_name,
            'current_cost' => (int) ($item->variant?->cost_price ?? 0),
        ])->filter(fn ($line) => $line['variant_id'])->values()->all();
    }

    #[Computed]
    public function inventories()
    {
        return Inventory::where('status', true)->orderBy('sort')->get(['id', 'title']);
    }

    #[Computed]
    public function suppliers()
    {
        return Supplier::active()->orderBy('title')->get(['id', 'title']);
    }

    #[Computed]
    public function variantResults()
    {
        $term = trim($this->variantSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return ProductVariant::query()
            ->with(['product:id,title', 'values'])
            ->whereHas('product', fn ($q) => $q->whereNull('deleted_at'))
            ->where(fn ($q) => $q->where('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhereHas('product', fn ($p) => $p->where('title', 'like', "%{$term}%")))
            ->limit(10)
            ->get();
    }

    public function addLine(int $variantId): void
    {
        $variant = ProductVariant::with(['product:id,title', 'values'])->findOrFail($variantId);

        foreach ($this->lines as $i => $line) {
            if ((int) $line['variant_id'] === $variant->id) {
                $this->lines[$i]['quantity'] = (int) $line['quantity'] + 1;
                $this->variantSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'variant_id' => $variant->id,
            'quantity' => 1,
            'unit_cost' => (int) ($variant->cost_price ?? 0),
            'product' => $variant->product?->title ?? 'محصول',
            'variant' => $variant->label,
            'current_cost' => (int) ($variant->cost_price ?? 0),
        ];

        $this->variantSearch = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->resetValidation();
    }

    public function addSupplier(): void
    {
        abort_if(!auth()->user()->can('purchases.create'), 403);

        $this->validate(['newSupplierTitle' => ['required', 'string', 'min:2', 'max:200']], [
            'newSupplierTitle.required' => 'نام تأمین‌کننده را وارد کنید.',
            'newSupplierTitle.min' => 'نام تأمین‌کننده باید حداقل ۲ کاراکتر باشد.',
            'newSupplierTitle.max' => 'نام تأمین‌کننده نباید بیشتر از ۲۰۰ کاراکتر باشد.',
        ]);

        $supplier = Supplier::create(['title' => trim($this->newSupplierTitle), 'status' => true]);
        unset($this->suppliers);
        $this->supplier_id = $supplier->id;
        $this->newSupplierTitle = '';
    }

    #[Computed]
    public function subtotal(): int
    {
        return (int) collect($this->lines)->sum(fn ($l) => max(0, (int) $l['unit_cost']) * max(0, (int) $l['quantity']));
    }

    #[Computed]
    public function total(): int
    {
        return max(0, $this->subtotal - min($this->subtotal, max(0, (int) $this->discount_amount)));
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'inventory_id' => ['required', Rule::exists('inventories', 'id')->where('status', true)],
            'purchase_date' => ['required', 'regex:' . JalaliDate::PATTERN],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'update_cost_price' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.variant_id' => ['required', 'exists:product_variants,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'تأمین‌کننده را انتخاب کنید.',
            'supplier_id.exists' => 'تأمین‌کننده انتخاب‌شده معتبر نیست.',
            'inventory_id.required' => 'انبار مقصد را انتخاب کنید.',
            'inventory_id.exists' => 'انبار انتخاب‌شده معتبر یا فعال نیست.',
            'purchase_date.required' => 'تاریخ خرید را وارد کنید.',
            'purchase_date.regex' => 'تاریخ را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید.',
            'supplier_invoice_number.max' => 'شماره فاکتور فروشنده نباید بیشتر از ۱۰۰ کاراکتر باشد.',
            'discount_amount.integer' => 'تخفیف باید عدد صحیح باشد.',
            'discount_amount.min' => 'تخفیف نمی‌تواند منفی باشد.',
            'note.max' => 'توضیحات نباید بیشتر از ۲۰۰۰ کاراکتر باشد.',
            'lines.required' => 'حداقل یک کالا به خرید اضافه کنید.',
            'lines.min' => 'حداقل یک کالا به خرید اضافه کنید.',
            'lines.*.variant_id.exists' => 'کالای انتخاب‌شده معتبر نیست.',
            'lines.*.quantity.required' => 'تعداد را وارد کنید.',
            'lines.*.quantity.integer' => 'تعداد باید عدد صحیح باشد.',
            'lines.*.quantity.min' => 'تعداد باید حداقل ۱ باشد.',
            'lines.*.unit_cost.required' => 'قیمت خرید واحد را وارد کنید.',
            'lines.*.unit_cost.integer' => 'قیمت خرید باید عدد صحیح باشد.',
            'lines.*.unit_cost.min' => 'قیمت خرید نمی‌تواند منفی باشد.',
        ];
    }

    public function save(bool $receive = false)
    {
        abort_if(!auth()->user()->can($this->purchase ? 'purchases.edit' : 'purchases.create'), 403);

        $this->validate();

        $date = JalaliDate::toCarbon($this->purchase_date);
        if (!$date) {
            $this->addError('purchase_date', 'تاریخ وارد شده معتبر نیست.');
            return;
        }

        try {
            $purchase = app(PurchaseService::class)->save([
                'supplier_id' => (int) $this->supplier_id,
                'inventory_id' => (int) $this->inventory_id,
                'purchase_date' => $date,
                'supplier_invoice_number' => filled($this->supplier_invoice_number) ? trim($this->supplier_invoice_number) : null,
                'discount_amount' => (int) $this->discount_amount,
                'update_cost_price' => (bool) $this->update_cost_price,
                'note' => filled($this->note) ? trim($this->note) : null,
                'receive' => $receive,
                'lines' => $this->lines,
            ], $this->purchase?->id);
        } catch (ValidationException $e) {
            $this->addError('lines', collect($e->errors())->flatten()->first());
            return;
        }

        session()->flash('purchase_saved', $receive
            ? 'خرید ' . $purchase->purchase_number . ' ثبت و کالاها به انبار اضافه شد.'
            : 'خرید ' . $purchase->purchase_number . ' به‌صورت پیش‌نویس ذخیره شد.');

        return $this->redirectRoute('purchases.index');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">پیش‌نویس روی انبار اثری ندارد؛ با «ثبت و دریافت» کالاها به انبار اضافه می‌شوند.</div>
        </div>
        <div class="btn-list">
            <a href="{{ route('purchases.index') }}" class="btn btn-warning-light btn-wave">
                <i class="bx bx-undo align-middle"></i> بازگشت
            </a>
        </div>
    </div>

    <form wire:submit="save(false)">
        <div class="row">
            {{-- اقلام --}}
            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">اقلام خرید</div></div>
                    <div class="card-body">
                        <div class="position-relative mb-4">
                            <input type="search" wire:model.live.debounce.400ms="variantSearch" class="form-control" autocomplete="off"
                                   placeholder="جستجوی کالا با نام محصول، SKU یا بارکد...">
                            @if($this->variantResults->isNotEmpty())
                                <div class="list-group position-absolute w-100 shadow mt-1" style="z-index: 20; max-height: 320px; overflow-y: auto">
                                    @foreach($this->variantResults as $variant)
                                        <button type="button" wire:key="variant-result-{{ $variant->id }}" wire:click="addLine({{ $variant->id }})"
                                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <span>
                                                <span class="fw-semibold">{{ $variant->product?->title }}</span>
                                                <small class="text-muted ms-1">{{ $variant->label }}</small>
                                            </span>
                                            <small class="text-muted">قیمت خرید فعلی: {{ $variant->cost_price ? number_format($variant->cost_price) : '—' }}</small>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-nowrap mb-0">
                                <thead>
                                <tr>
                                    <th>کالا</th>
                                    <th style="width: 110px">تعداد</th>
                                    <th style="width: 170px">قیمت خرید واحد</th>
                                    <th>جمع</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($lines as $index => $line)
                                    <tr wire:key="line-{{ $line['variant_id'] }}-{{ $index }}">
                                        <td>
                                            <div class="fw-semibold">{{ $line['product'] }}</div>
                                            <small class="text-muted">{{ $line['variant'] }}</small>
                                        </td>
                                        <td>
                                            <input type="number" min="1" wire:model.live.debounce.400ms="lines.{{ $index }}.quantity" class="form-control form-control-sm @error('lines.' . $index . '.quantity') is-invalid @enderror">
                                        </td>
                                        <td>
                                            <input type="number" min="0" wire:model.live.debounce.400ms="lines.{{ $index }}.unit_cost" class="form-control form-control-sm @error('lines.' . $index . '.unit_cost') is-invalid @enderror">
                                            @if($line['current_cost'] && (int) $line['unit_cost'] !== (int) $line['current_cost'])
                                                <small class="text-muted">قبلی: {{ number_format($line['current_cost']) }}</small>
                                            @endif
                                        </td>
                                        <td class="fw-semibold">{{ number_format(max(0, (int) $line['unit_cost']) * max(0, (int) $line['quantity'])) }}</td>
                                        <td>
                                            <button type="button" wire:click="removeLine({{ $index }})" class="btn btn-sm btn-danger-light" title="حذف">
                                                <i class="ri-delete-bin-5-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @foreach(['quantity', 'unit_cost'] as $field)
                                        @error('lines.' . $index . '.' . $field)
                                        <tr><td colspan="5" class="text-danger small py-1">{{ $message }}</td></tr>
                                        @enderror
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">کالایی اضافه نشده است؛ از جستجوی بالا کالا را انتخاب کنید.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        @error('lines')
                        <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- مشخصات --}}
            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">مشخصات خرید</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">تأمین‌کننده</label>
                                <select wire:model="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                                    <option value="">انتخاب کنید</option>
                                    @foreach($this->suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->title }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="input-group input-group-sm mt-2">
                                    <input wire:model="newSupplierTitle" type="text" class="form-control @error('newSupplierTitle') is-invalid @enderror" placeholder="تأمین‌کننده جدید...">
                                    <button type="button" wire:click="addSupplier" class="btn btn-outline-primary">افزودن</button>
                                </div>
                                @error('newSupplierTitle') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label">انبار مقصد</label>
                                <select wire:model="inventory_id" class="form-select @error('inventory_id') is-invalid @enderror">
                                    @foreach($this->inventories as $inventory)
                                        <option value="{{ $inventory->id }}">{{ $inventory->title }}</option>
                                    @endforeach
                                </select>
                                @error('inventory_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label">تاریخ خرید</label>
                                <input data-jdp wire:model.lazy="purchase_date" type="text" dir="ltr" class="form-control @error('purchase_date') is-invalid @enderror">
                                @error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label">شماره فاکتور فروشنده</label>
                                <input wire:model="supplier_invoice_number" type="text" class="form-control @error('supplier_invoice_number') is-invalid @enderror">
                                @error('supplier_invoice_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label">تخفیف</label>
                                <input wire:model.live.debounce.400ms="discount_amount" type="number" min="0" class="form-control @error('discount_amount') is-invalid @enderror">
                                @error('discount_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input wire:model="update_cost_price" class="form-check-input" type="checkbox" id="update_cost_price">
                                    <label class="form-check-label" for="update_cost_price">قیمت خرید محصولات با این خرید بروز شود</label>
                                </div>
                                <div class="form-text">برای محاسبه درست سود فروش‌های بعدی پیشنهاد می‌شود روشن باشد.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیحات</label>
                                <textarea wire:model="note" rows="2" class="form-control @error('note') is-invalid @enderror"></textarea>
                                @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between"><span>جمع اقلام</span><span>{{ number_format($this->subtotal) }} تومان</span></li>
                                    <li class="list-group-item d-flex justify-content-between fw-bold"><span>مبلغ نهایی</span><span>{{ number_format($this->total) }} تومان</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div wire:loading.remove wire:target="save" class="d-grid gap-2">
                            <button type="button" wire:click="save(true)" class="btn btn-success">ثبت و دریافت (ورود به انبار)</button>
                            <button type="submit" class="btn btn-outline-primary">ذخیره پیش‌نویس</button>
                        </div>
                        <div wire:loading wire:target="save" class="text-center w-100">
                            <div class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال ثبت...</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.js') }}"></script>
        <script>jalaliDatepicker.startWatch();</script>
    @endpush
</div>
