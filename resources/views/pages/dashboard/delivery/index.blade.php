<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\ShippingSlot;
use App\Services\Delivery\DeliveryScheduleService;
use Illuminate\Validation\Rule;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    public $info = [];

    // ---- قوانین ----
    public $lead_days = 1;
    public $window_days = 7;
    public ?string $cutoff = '14:00';
    public array $working_days = [];
    public $cost = 0;

    // ---- استثنا ----
    public $selectItem;
    public ?string $date = null;          // شمسی ۱۴۰۵/۰۱/۱۵
    public string $type = 'closed';       // closed = تعطیل | open = روز ارسال اضافه
    public ?string $label = null;
    public $exception_cost = 0;
    public bool $showPast = false;

    public ShippingSlot $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(ShippingSlot $model)
    {
        abort_if(!auth()->user()->can('delivery.view'), 403);

        $this->model = $model;
        $this->info['header'] = 'تنظیمات زمان‌بندی ارسال';
        $this->info['create'] = 'افزودن تعطیلی / روز ارسال';
        $this->info['delete'] = 'حذف تاریخ';
        $this->info['table']['headers'] = ['#', 'تاریخ', 'نوع', 'عنوان', 'هزینه', 'وضعیت', 'عملیات'];

        $settings = $this->service()->settings();
        $this->lead_days = $settings['delivery_lead_days'];
        $this->window_days = $settings['delivery_window_days'];
        $this->cutoff = $settings['delivery_cutoff'];
        $this->working_days = array_map('strval', $settings['delivery_working_days']);
        $this->cost = $settings['delivery_cost'];
    }

    protected function service(): DeliveryScheduleService
    {
        return app(DeliveryScheduleService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | قوانین ارسال
    |--------------------------------------------------------------------------
    */
    public function saveSettings()
    {
        abort_if(!auth()->user()->can('delivery.edit'), 403);

        $data = $this->validate([
            'lead_days' => ['required', 'integer', 'min:0', 'max:60'],
            'window_days' => ['required', 'integer', 'min:1', 'max:30'],
            'cutoff' => ['nullable', 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => [Rule::in(array_map('strval', array_keys(DeliveryScheduleService::WEEKDAYS)))],
            'cost' => ['required', 'integer', 'min:0'],
        ], [
            'lead_days.required' => 'زودترین زمان ارسال را وارد کنید.',
            'lead_days.integer' => 'زودترین زمان ارسال باید عدد صحیح باشد.',
            'lead_days.min' => 'زودترین زمان ارسال نمی‌تواند منفی باشد.',
            'lead_days.max' => 'زودترین زمان ارسال حداکثر ۶۰ روز است.',
            'window_days.required' => 'تعداد تاریخ‌های قابل انتخاب را وارد کنید.',
            'window_days.min' => 'حداقل یک تاریخ باید قابل انتخاب باشد.',
            'window_days.max' => 'حداکثر ۳۰ تاریخ قابل نمایش است.',
            'cutoff.regex' => 'ساعت پایان پذیرش سفارش را به شکل ۱۴:۰۰ وارد کنید.',
            'working_days.required' => 'حداقل یک روز کاری انتخاب کنید.',
            'working_days.min' => 'حداقل یک روز کاری انتخاب کنید.',
            'cost.required' => 'هزینه ارسال را وارد کنید (۰ = رایگان).',
            'cost.integer' => 'هزینه ارسال باید عدد باشد.',
            'cost.min' => 'هزینه ارسال نمی‌تواند منفی باشد.',
        ]);

        $this->service()->saveSettings([
            'delivery_lead_days' => (int) $data['lead_days'],
            'delivery_window_days' => (int) $data['window_days'],
            'delivery_cutoff' => (string) ($data['cutoff'] ?? ''),
            'delivery_working_days' => $data['working_days'],
            'delivery_cost' => (int) $data['cost'],
        ]);

        unset($this->preview);

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'تنظیمات ارسال ذخیره شد.');
    }

    // تاریخ‌هایی که الان به مشتری نمایش داده می‌شود (پیش‌نمایش)
    #[Computed]
    public function preview()
    {
        return $this->service()->availableDates();
    }

    /*
    |--------------------------------------------------------------------------
    | استثناها (تعطیلات / روزهای اضافه)
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function data()
    {
        return $this->model->newQuery()
            ->when(!$this->showPast, fn ($q) => $q->where('date', '>=', now(DeliveryScheduleService::TIMEZONE)->toDateString()))
            ->orderBy('date', $this->showPast ? 'desc' : 'asc')
            ->paginate(20);
    }

    public function updatedShowPast(): void
    {
        $this->resetPage();
    }

    protected function parseJalali(?string $value): ?\Carbon\Carbon
    {
        $value = str_replace('-', '/', trim(strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ])));

        if (!preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $value, $m)) {
            return null;
        }

        try {
            return \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $m[1], $m[2], $m[3]));
        } catch (\Throwable) {
            return null;
        }
    }

    public function get_data($id)
    {
        $this->resetErrorBag();
        $this->selectItem = $this->model->findOrFail($id);
        $this->date = verta($this->selectItem->date)->format('Y/m/d');
        $this->type = $this->selectItem->is_holiday ? 'closed' : 'open';
        $this->label = $this->selectItem->label;
        $this->exception_cost = (int) $this->selectItem->cost;
    }

    public function resetData($action = 'create')
    {
        $this->reset(['selectItem', 'date', 'type', 'label', 'exception_cost']);
        $this->resetErrorBag();

        if ($action !== 'create') {
            $this->dispatch('close-modal');
        }
    }

    public function save()
    {
        abort_if(!auth()->user()->can('delivery.edit'), 403);

        $this->validate([
            'date' => ['required', 'string'],
            'type' => ['required', Rule::in(['closed', 'open'])],
            'label' => ['nullable', 'string', 'max:50'],
            'exception_cost' => ['nullable', 'integer', 'min:0'],
        ], [
            'date.required' => 'تاریخ را وارد کنید.',
            'type.in' => 'نوع تاریخ معتبر نیست.',
            'label.max' => 'عنوان نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',
            'exception_cost.integer' => 'هزینه باید عدد باشد.',
            'exception_cost.min' => 'هزینه نمی‌تواند منفی باشد.',
        ]);

        $date = $this->parseJalali($this->date);

        if (!$date) {
            $this->addError('date', 'تاریخ معتبر نیست؛ مثال: ۱۴۰۵/۰۱/۱۵');
            return;
        }

        $exists = $this->model->newQuery()
            ->whereDate('date', $date->toDateString())
            ->when($this->selectItem, fn ($q) => $q->where('id', '!=', $this->selectItem->id))
            ->exists();

        if ($exists) {
            $this->addError('date', 'برای این تاریخ قبلاً تنظیمی ثبت شده است.');
            return;
        }

        $payload = [
            'date' => $date->toDateString(),
            'is_holiday' => $this->type === 'closed',
            'label' => filled($this->label) ? trim($this->label) : null,
            'cost' => $this->type === 'open' ? (int) $this->exception_cost : 0,
            'is_active' => true,
        ];

        $this->selectItem
            ? $this->selectItem->update($payload)
            : $this->model->create($payload);

        unset($this->data, $this->preview);
        $this->resetData('close');

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'تاریخ ذخیره شد.');
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('delivery.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);
        unset($this->data, $this->preview);
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('delivery.edit'), 403);

        if ($this->selectItem) {
            // تاریخ‌هایی که سفارش به آن‌ها متصل است حذف نمی‌شوند، فقط غیرفعال
            if ($this->selectItem->orders()->exists()) {
                $this->selectItem->update(['is_active' => false]);
            } else {
                $this->selectItem->delete();
            }

            unset($this->data, $this->preview);
            $this->resetData('close');
            $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'تاریخ حذف شد.');
        }
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">تاریخ‌های قابل انتخاب در صفحه پرداخت به‌صورت خودکار از این تنظیمات محاسبه می‌شوند.</div>
        </div>
    </div>

    <div class="row">
        {{-- قوانین --}}
        <div class="col-xl-7">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">قوانین ارسال</div>
                </div>
                <form wire:submit="saveSettings">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">زودترین ارسال (روز بعد از ثبت سفارش)</label>
                                <input type="number" min="0" max="60" wire:model="lead_days" class="form-control @error('lead_days') is-invalid @enderror">
                                <div class="form-text">۰ = همان روز، ۱ = فردا و …</div>
                                @error('lead_days') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">تعداد تاریخ‌های قابل انتخاب</label>
                                <input type="number" min="1" max="30" wire:model="window_days" class="form-control @error('window_days') is-invalid @enderror">
                                @error('window_days') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">ساعت پایان پذیرش سفارش (Cut-off)</label>
                                <input type="text" dir="ltr" wire:model="cutoff" placeholder="14:00" class="form-control text-center @error('cutoff') is-invalid @enderror">
                                <div class="form-text">سفارش‌های بعد از این ساعت از روز کاری بعد حساب می‌شوند. خالی = بدون محدودیت.</div>
                                @error('cutoff') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-12">
                                <label class="form-label d-block">روزهای کاری (ارسال)</label>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach(\App\Services\Delivery\DeliveryScheduleService::WEEKDAYS as $value => $name)
                                        <input type="checkbox" class="btn-check" id="wd-{{ $value }}" value="{{ $value }}" wire:model="working_days" autocomplete="off">
                                        <label class="btn btn-sm btn-outline-primary" for="wd-{{ $value }}">{{ $name }}</label>
                                    @endforeach
                                </div>
                                @error('working_days') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">هزینه پیش‌فرض ارسال (تومان)</label>
                                <input type="number" min="0" wire:model="cost" class="form-control @error('cost') is-invalid @enderror">
                                <div class="form-text">۰ = ارسال رایگان</div>
                                @error('cost') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    @can('delivery.edit')
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveSettings">
                                <span wire:loading.remove wire:target="saveSettings">ذخیره تنظیمات</span>
                                <span wire:loading wire:target="saveSettings">در حال ذخیره...</span>
                            </button>
                        </div>
                    @endcan
                </form>
            </div>
        </div>

        {{-- پیش‌نمایش --}}
        <div class="col-xl-5">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">پیش‌نمایش تاریخ‌های قابل انتخاب (همین الان)</div>
                </div>
                <div class="card-body">
                    @if($this->preview->isEmpty())
                        <div class="alert alert-warning mb-0">با تنظیمات فعلی هیچ تاریخی برای ارسال در دسترس نیست.</div>
                    @else
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($this->preview as $option)
                                <div class="border rounded p-2 text-center" style="min-width: 86px" wire:key="preview-{{ $option['date'] }}">
                                    <div class="small text-muted">{{ $option['label'] ?? $option['weekday'] }}</div>
                                    <div class="fw-bold fs-16">{{ $option['day'] }}</div>
                                    <div class="small">{{ $option['month'] }}</div>
                                    <div class="fs-11 {{ $option['cost'] ? 'text-muted' : 'text-success' }}">{{ $option['cost'] ? number_format($option['cost']) : 'رایگان' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- استثناها --}}
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between flex-wrap gap-2">
                    <div class="card-title">تعطیلات و روزهای خاص</div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="show-past" wire:model.live="showPast">
                            <label class="form-check-label small" for="show-past">نمایش تاریخ‌های گذشته</label>
                        </div>
                        @can('delivery.edit')
                            <button type="button" wire:click="resetData()" data-bs-toggle="modal" href="#create" class="btn btn-sm btn-success-light">
                                <i class="ri-add-line align-middle"></i> {{ $info['create'] }}
                            </button>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap align-middle">
                            <thead>
                            <tr>
                                @foreach($info['table']['headers'] as $h)
                                    <th>{{ $h }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($this->data as $item)
                                <tr wire:key="slot-{{ $item->id }}">
                                    <th>{{ $this->data->firstItem() + $loop->index }}</th>
                                    <td>
                                        <span class="fw-semibold">{{ verta($item->date)->format('Y/m/d') }}</span>
                                        <span class="text-muted small ms-1">{{ verta($item->date)->format('l') }}</span>
                                    </td>
                                    <td>
                                        @if($item->is_holiday)
                                            <span class="badge bg-danger-transparent">تعطیل / بدون ارسال</span>
                                        @else
                                            <span class="badge bg-success-transparent">روز ارسال اضافه</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->label ?: '—' }}</td>
                                    <td>{{ $item->is_holiday ? '—' : ($item->cost ? number_format($item->cost) : 'پیش‌فرض') }}</td>
                                    <td>
                                        <span style="cursor: pointer" wire:click="change_status({{ $item->id }})"
                                              class="badge bg-outline-{{ $item->is_active ? 'success' : 'secondary' }}">
                                            {{ $item->is_active ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>
                                    <td>
                                        @can('delivery.edit')
                                            <div class="hstack gap-2">
                                                <a data-bs-toggle="modal" href="#create" wire:click="get_data({{ $item->id }})" class="text-info fs-14 lh-1"><i class="ri-edit-line"></i></a>
                                                <a data-bs-toggle="modal" href="#delete" wire:click="get_data({{ $item->id }})" class="text-danger fs-14 lh-1"><i class="ri-delete-bin-5-line"></i></a>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">تعطیلی یا روز خاصی ثبت نشده است.</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="100">{{ $this->data->links() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- مودال افزودن / ویرایش --}}
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form wire:submit="save">
                    <div class="modal-header">
                        <h6 class="modal-title">{{ $selectItem ? 'ویرایش تاریخ' : $info['create'] }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">تاریخ (شمسی)</label>
                                <input type="text" dir="ltr" wire:model="date" placeholder="1405/01/15" class="form-control text-center @error('date') is-invalid @enderror">
                                @error('date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع</label>
                                <select wire:model.live="type" class="form-select @error('type') is-invalid @enderror">
                                    <option value="closed">تعطیل / بدون ارسال</option>
                                    <option value="open">روز ارسال اضافه</option>
                                </select>
                            </div>
                            <div class="col-md-{{ $type === 'open' ? '6' : '12' }}">
                                <label class="form-label">عنوان (اختیاری)</label>
                                <input type="text" wire:model="label" maxlength="50" placeholder="مثلاً: تعطیلات نوروز" class="form-control @error('label') is-invalid @enderror">
                                @error('label') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            @if($type === 'open')
                                <div class="col-md-6">
                                    <label class="form-label">هزینه ارسال این روز</label>
                                    <input type="number" min="0" wire:model="exception_cost" class="form-control @error('exception_cost') is-invalid @enderror">
                                    <div class="form-text">۰ = همان هزینه پیش‌فرض</div>
                                    @error('exception_cost') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div wire:loading.remove wire:target="save">
                            <button type="submit" class="btn btn-primary">ذخیره</button>
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                        </div>
                        <div wire:loading wire:target="save" class="spinner-grow text-info" role="status"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- مودال حذف --}}
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $info['delete'] }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger mb-0">از حذف این تاریخ مطمئن هستید؟</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" wire:click="delete()">حذف</button>
                    <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
</div>
