<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Invoice;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Invoices\InvoiceStockService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    public $info = [];
    public ?Invoice $invoice = null;

    public string $source = 'pos';
    public string $status = 'paid';

    public ?int $user_id = null;
    public string $userSearch = '';
    public ?string $customer_name = null;
    public ?string $customer_mobile = null;
    public ?string $note = null;

    public $discount_amount = 0;
    public $tax_amount = 0;
    public $shipping_amount = 0;

    // [[id?, variant_id, inventory_id, quantity, unit_price, product, variant, stock_deducted], ...]
    public array $lines = [];

    public string $variantSearch = '';

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(?Invoice $invoice = null)
    {
        // صدور = invoices.create ، ویرایش = invoices.edit
        abort_if(!auth()->user()->can($invoice?->exists ? 'invoices.edit' : 'invoices.create'), 403);

        $this->info['header'] = $invoice?->exists ? 'ویرایش فاکتور ' . $invoice->invoice_number : 'صدور فاکتور / ثبت فروش حضوری';

        if (!$invoice?->exists) {
            return;
        }

        abort_unless(app(InvoiceStockService::class)->isEditable($invoice), 403, 'این فاکتور قابل ویرایش نیست.');

        $invoice->load(['items.variant.product', 'user']);
        $this->invoice = $invoice;
        $this->source = $invoice->source;
        $this->status = $invoice->status;
        $this->user_id = $invoice->user_id;
        $this->customer_name = $invoice->customer_name;
        $this->customer_mobile = $invoice->customer_mobile;
        $this->note = $invoice->note;
        $this->discount_amount = (int) $invoice->discount_amount;
        $this->tax_amount = (int) $invoice->tax_amount;
        $this->shipping_amount = (int) $invoice->shipping_amount;

        $this->lines = $invoice->items->map(fn ($item) => [
            'id' => $item->id,
            'variant_id' => $item->variant_id,
            'inventory_id' => $item->inventory_id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (int) $item->unit_price,
            'product' => $item->product_name,
            'variant' => $item->variant_name,
            // مقداری که همین قلم الان از انبار کسر کرده (برای محاسبه سقف تعداد)
            'stock_deducted' => (int) $item->stock_deducted,
        ])->values()->all();
    }

    #[Computed]
    public function inventories()
    {
        return Inventory::where('status', true)->orderBy('sort')->get(['id', 'title']);
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        return $this->user_id ? User::find($this->user_id) : null;
    }

    #[Computed]
    public function userResults()
    {
        $term = trim($this->userSearch);

        if (mb_strlen($term) < 3 || $this->user_id) {
            return collect();
        }

        return User::query()
            ->where(fn ($q) => $q->where('mobile', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%"))
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'mobile']);
    }

    #[Computed]
    public function variantResults()
    {
        $term = trim($this->variantSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return ProductVariant::query()
            ->with(['product:id,title', 'values', 'inventoryItems.inventory'])
            ->whereHas('product', fn ($q) => $q->whereNull('deleted_at'))
            ->where(fn ($q) => $q->where('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhereHas('product', fn ($p) => $p->where('title', 'like', "%{$term}%")))
            ->limit(10)
            ->get();
    }

    /**
     * موجودی قابل فروش هر ردیف در انبار انتخاب‌شده + مقداری که خود این فاکتور قبلاً کسر کرده
     */
    public function lineAvailable(array $line): int
    {
        if (empty($line['variant_id']) || empty($line['inventory_id'])) {
            return 0;
        }

        $available = app(InventoryService::class)->available((int) $line['variant_id'], (int) $line['inventory_id']);

        // در ویرایش، مقدار کسرشده‌ی همین قلم از همین انبار دوباره قابل استفاده است
        if (!empty($line['id']) && $this->invoice) {
            $item = $this->invoice->items->firstWhere('id', $line['id']);
            if ($item && (int) $item->inventory_id === (int) $line['inventory_id'] && (int) $item->variant_id === (int) $line['variant_id']) {
                $available += (int) $item->stock_deducted;
            }
        }

        return $available;
    }

    public function selectUser(int $id): void
    {
        $user = User::findOrFail($id);
        $this->user_id = $user->id;
        $this->customer_name = trim($user->full_name) ?: $this->customer_name;
        $this->customer_mobile = $user->mobile;
        $this->userSearch = '';
    }

    public function clearUser(): void
    {
        $this->user_id = null;
    }

    public function addLine(int $variantId): void
    {
        $variant = ProductVariant::with(['product:id,title', 'values'])->findOrFail($variantId);

        // ردیف تکراری => افزایش تعداد
        foreach ($this->lines as $i => $line) {
            if ((int) $line['variant_id'] === $variant->id && empty($line['id'])) {
                $this->lines[$i]['quantity'] = (int) $line['quantity'] + 1;
                $this->variantSearch = '';
                return;
            }
        }

        $pricing = $variant->priceData();

        $this->lines[] = [
            'id' => null,
            'variant_id' => $variant->id,
            'inventory_id' => app(InventoryService::class)->suggestInventoryId($variant->id) ?? $this->inventories->first()?->id,
            'quantity' => 1,
            'unit_price' => (int) ($pricing['after_discount'] ?? $variant->price ?? 0),
            'product' => $variant->product?->title ?? 'محصول',
            'variant' => $variant->label,
            'stock_deducted' => 0,
        ];

        $this->variantSearch = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->resetValidation();
    }

    #[Computed]
    public function subtotal(): int
    {
        return (int) collect($this->lines)->sum(fn ($l) => max(0, (int) $l['unit_price']) * max(0, (int) $l['quantity']));
    }

    #[Computed]
    public function total(): int
    {
        $discount = min($this->subtotal, max(0, (int) $this->discount_amount));

        return max(0, $this->subtotal - $discount + max(0, (int) $this->tax_amount) + max(0, (int) $this->shipping_amount));
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['manual', 'pos'])],
            'status' => [$this->invoice ? 'nullable' : 'required', Rule::in(['draft', 'unpaid', 'paid'])],
            'user_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_mobile' => ['nullable', 'regex:/^09\d{9}$/'],
            'note' => ['nullable', 'string', 'max:2000'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'tax_amount' => ['nullable', 'integer', 'min:0'],
            'shipping_amount' => ['nullable', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.variant_id' => ['required', 'exists:product_variants,id'],
            'lines.*.inventory_id' => ['required', 'exists:inventories,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'source.required' => 'نوع فاکتور را انتخاب کنید.',
            'source.in' => 'نوع فاکتور معتبر نیست.',
            'status.required' => 'وضعیت فاکتور را انتخاب کنید.',
            'status.in' => 'وضعیت فاکتور معتبر نیست.',
            'user_id.exists' => 'کاربر انتخاب‌شده معتبر نیست.',
            'customer_name.max' => 'نام مشتری نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'customer_mobile.regex' => 'شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.',
            'note.max' => 'توضیحات نمی‌تواند بیشتر از ۲۰۰۰ کاراکتر باشد.',
            'discount_amount.integer' => 'مبلغ تخفیف باید عدد باشد.',
            'discount_amount.min' => 'مبلغ تخفیف نمی‌تواند منفی باشد.',
            'tax_amount.integer' => 'مالیات باید عدد باشد.',
            'tax_amount.min' => 'مالیات نمی‌تواند منفی باشد.',
            'shipping_amount.integer' => 'هزینه ارسال باید عدد باشد.',
            'shipping_amount.min' => 'هزینه ارسال نمی‌تواند منفی باشد.',
            'lines.required' => 'حداقل یک کالا به فاکتور اضافه کنید.',
            'lines.min' => 'حداقل یک کالا به فاکتور اضافه کنید.',
            'lines.*.inventory_id.required' => 'انبار را انتخاب کنید.',
            'lines.*.inventory_id.exists' => 'انبار انتخاب‌شده معتبر نیست.',
            'lines.*.quantity.required' => 'تعداد را وارد کنید.',
            'lines.*.quantity.integer' => 'تعداد باید عدد صحیح باشد.',
            'lines.*.quantity.min' => 'تعداد باید حداقل ۱ باشد.',
            'lines.*.unit_price.required' => 'قیمت واحد را وارد کنید.',
            'lines.*.unit_price.integer' => 'قیمت واحد باید عدد باشد.',
            'lines.*.unit_price.min' => 'قیمت واحد نمی‌تواند منفی باشد.',
        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can($this->invoice ? 'invoices.edit' : 'invoices.create'), 403);

        $this->validate();

        if (!$this->user_id && blank($this->customer_name) && blank($this->customer_mobile)) {
            $this->addError('customer_name', 'یک کاربر را انتخاب کنید یا نام / موبایل مشتری را وارد کنید.');
            return;
        }

        $payload = [
            'source' => $this->source,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'customer_name' => filled($this->customer_name) ? trim($this->customer_name) : null,
            'customer_mobile' => filled($this->customer_mobile) ? trim($this->customer_mobile) : null,
            'note' => filled($this->note) ? trim($this->note) : null,
            'discount_amount' => (int) $this->discount_amount,
            'tax_amount' => (int) $this->tax_amount,
            'shipping_amount' => (int) $this->shipping_amount,
            'items' => $this->lines,
        ];

        $service = app(InvoiceStockService::class);

        try {
            $invoice = $this->invoice
                ? $service->updateManual($this->invoice->id, $payload)
                : $service->createManual($payload);
        } catch (ValidationException $e) {
            // خطای موجودی کافی نیست و ...
            $this->addError('lines', collect($e->errors())->flatten()->first());
            return;
        }

        session()->flash('invoice_saved', $invoice->invoice_number);

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->invoice ? 'فاکتور ویرایش و موجودی انبار اصلاح شد.' : 'فاکتور ' . $invoice->invoice_number . ' صادر شد.'
        );

        return $this->redirectRoute('invoices.index');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">کالاهای فاکتور مستقیماً از موجودی انبار انتخاب‌شده کسر می‌شوند.</div>
        </div>
        <div class="btn-list">
            <a href="{{ route('invoices.index') }}" class="btn btn-warning-light btn-wave">
                <i class="bx bx-undo align-middle"></i>
                بازگشت
            </a>
        </div>
    </div>

    <form wire:submit="save">
        <div class="row">

            {{-- Lines --}}
            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">اقلام فاکتور</div>
                    </div>
                    <div class="card-body">

                        {{-- Variant search --}}
                        <div class="position-relative mb-4">
                            <input type="search"
                                   wire:model.live.debounce.400ms="variantSearch"
                                   class="form-control"
                                   autocomplete="off"
                                   placeholder="جستجوی کالا با نام محصول، SKU یا بارکد...">

                            @if($this->variantResults->isNotEmpty())
                                <div class="list-group position-absolute w-100 shadow mt-1" style="z-index: 20; max-height: 320px; overflow-y: auto">
                                    @foreach($this->variantResults as $variant)
                                        @php($stock = $variant->availableStock())
                                        <button type="button"
                                                wire:key="variant-result-{{ $variant->id }}"
                                                wire:click="addLine({{ $variant->id }})"
                                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <span>
                                                <span class="fw-semibold">{{ $variant->product?->title }}</span>
                                                <small class="text-muted ms-1">{{ $variant->label }}</small>
                                            </span>
                                            <span class="d-flex gap-2 align-items-center">
                                                <small class="text-muted">{{ number_format((int) $variant->price) }} تومان</small>
                                                <span class="badge bg-{{ $stock > 0 ? 'success' : 'danger' }}-transparent">موجودی: {{ $stock }}</span>
                                            </span>
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
                                    <th style="width: 180px">انبار</th>
                                    <th style="width: 110px">تعداد</th>
                                    <th style="width: 150px">قیمت واحد</th>
                                    <th>جمع</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($lines as $index => $line)
                                    @php($available = $this->lineAvailable($line))
                                    <tr wire:key="line-{{ $line['id'] ?? 'new' }}-{{ $line['variant_id'] }}-{{ $index }}">
                                        <td>
                                            <div class="fw-semibold">{{ $line['product'] }}</div>
                                            <small class="text-muted">{{ $line['variant'] }}</small>
                                        </td>
                                        <td>
                                            <select wire:model.live="lines.{{ $index }}.inventory_id" class="form-select form-select-sm @error('lines.' . $index . '.inventory_id') is-invalid @enderror">
                                                @foreach($this->inventories as $inventory)
                                                    <option value="{{ $inventory->id }}">{{ $inventory->title }}</option>
                                                @endforeach
                                            </select>
                                            <small class="{{ $available >= (int) $line['quantity'] ? 'text-muted' : 'text-danger fw-semibold' }}">
                                                قابل فروش: {{ $available }}
                                            </small>
                                        </td>
                                        <td>
                                            <input type="number" min="1" wire:model.live.debounce.400ms="lines.{{ $index }}.quantity" class="form-control form-control-sm @error('lines.' . $index . '.quantity') is-invalid @enderror">
                                        </td>
                                        <td>
                                            <input type="number" min="0" wire:model.live.debounce.400ms="lines.{{ $index }}.unit_price" class="form-control form-control-sm @error('lines.' . $index . '.unit_price') is-invalid @enderror">
                                        </td>
                                        <td class="fw-semibold">{{ number_format(max(0, (int) $line['unit_price']) * max(0, (int) $line['quantity'])) }}</td>
                                        <td>
                                            <button type="button" wire:click="removeLine({{ $index }})" class="btn btn-sm btn-danger-light" title="حذف">
                                                <i class="ri-delete-bin-5-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @foreach(['inventory_id', 'quantity', 'unit_price'] as $field)
                                        @error('lines.' . $index . '.' . $field)
                                        <tr><td colspan="6" class="text-danger small py-1">{{ $message }}</td></tr>
                                        @enderror
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            کالایی اضافه نشده است؛ از جستجوی بالا کالا را انتخاب کنید.
                                        </td>
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

            {{-- Invoice info --}}
            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">مشخصات فاکتور</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">نوع</label>
                                <select wire:model="source" class="form-select @error('source') is-invalid @enderror">
                                    <option value="pos">فروش حضوری</option>
                                    <option value="manual">فاکتور دستی</option>
                                </select>
                            </div>

                            @unless($invoice)
                                <div class="col-6">
                                    <label class="form-label">وضعیت</label>
                                    <select wire:model="status" class="form-select @error('status') is-invalid @enderror">
                                        <option value="paid">پرداخت‌شده</option>
                                        <option value="unpaid">پرداخت‌نشده</option>
                                        <option value="draft">پیش‌نویس (بدون کسر موجودی)</option>
                                    </select>
                                </div>
                            @else
                                <div class="col-6">
                                    <label class="form-label">وضعیت</label>
                                    <div class="form-control-plaintext fw-semibold">{{ \App\Models\Invoice::STATUSES[$invoice->status] ?? $invoice->status }}</div>
                                </div>
                            @endunless

                            {{-- Customer --}}
                            <div class="col-12">
                                <label class="form-label">مشتری</label>
                                @if($this->selectedUser)
                                    <div class="d-flex align-items-center justify-content-between border rounded p-2">
                                        <span>
                                            <i class="ri-user-line me-1"></i>
                                            {{ $this->selectedUser->mobile }} {{ trim($this->selectedUser->full_name) ? ' - ' . $this->selectedUser->full_name : '' }}
                                        </span>
                                        <button type="button" wire:click="clearUser" class="btn btn-sm btn-light">تغییر</button>
                                    </div>
                                @else
                                    <div class="position-relative">
                                        <input type="search" wire:model.live.debounce.400ms="userSearch" class="form-control" autocomplete="off" placeholder="جستجوی کاربر سایت (اختیاری)...">
                                        @if($this->userResults->isNotEmpty())
                                            <div class="list-group position-absolute w-100 shadow mt-1" style="z-index: 20">
                                                @foreach($this->userResults as $u)
                                                    <button type="button" wire:key="user-{{ $u->id }}" wire:click="selectUser({{ $u->id }})" class="list-group-item list-group-item-action">
                                                        {{ $u->mobile }} {{ trim($u->full_name) ? ' - ' . $u->full_name : '' }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="col-6">
                                <label class="form-label">نام مشتری</label>
                                <input type="text" wire:model="customer_name" class="form-control @error('customer_name') is-invalid @enderror">
                            </div>
                            <div class="col-6">
                                <label class="form-label">موبایل</label>
                                <input type="text" wire:model="customer_mobile" dir="ltr" class="form-control @error('customer_mobile') is-invalid @enderror" placeholder="09xxxxxxxxx">
                            </div>
                            @error('customer_name')
                            <div class="col-12 text-danger small">{{ $message }}</div>
                            @enderror
                            @error('customer_mobile')
                            <div class="col-12 text-danger small">{{ $message }}</div>
                            @enderror

                            <div class="col-12">
                                <label class="form-label">توضیحات</label>
                                <textarea wire:model="note" rows="2" class="form-control @error('note') is-invalid @enderror"></textarea>
                            </div>

                            {{-- Amounts --}}
                            <div class="col-4">
                                <label class="form-label">تخفیف</label>
                                <input type="number" min="0" wire:model.live.debounce.400ms="discount_amount" class="form-control @error('discount_amount') is-invalid @enderror">
                            </div>
                            <div class="col-4">
                                <label class="form-label">مالیات</label>
                                <input type="number" min="0" wire:model.live.debounce.400ms="tax_amount" class="form-control @error('tax_amount') is-invalid @enderror">
                            </div>
                            <div class="col-4">
                                <label class="form-label">ارسال</label>
                                <input type="number" min="0" wire:model.live.debounce.400ms="shipping_amount" class="form-control @error('shipping_amount') is-invalid @enderror">
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
                        <div wire:loading.remove wire:target="save">
                            <button type="submit" class="btn btn-primary w-100">
                                {{ $invoice ? 'ذخیره تغییرات' : 'صدور فاکتور' }}
                            </button>
                        </div>
                        <div wire:loading wire:target="save" class="text-center w-100">
                            <div class="spinner-grow text-info" role="status"><span class="visually-hidden">در حال ثبت...</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
