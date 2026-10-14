<?php

use App\Models\Announcement;
use App\Support\JalaliDate;
use Livewire\Component;

new class extends Component
{
    public $info = [];
    public $selectItem;
    public $data;
    public $search = '';
    public string $state = '';

    public string $title = '';
    public string $content = '';
    public string $type = 'info';
    public string $link = '';
    public string $link_text = '';
    public bool $is_active = true;
    public bool $is_pinned = false;
    public string $starts_at = '';
    public string $ends_at = '';

    public Announcement $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Announcement $model)
    {
        abort_if(!auth()->user()->can('announcements.view'), 403);

        $this->model = $model;
        $this->info['header'] = 'اطلاعیه‌های سایت';
        $this->info['create'] = 'افزودن اطلاعیه';
        $this->info['delete'] = 'حذف اطلاعیه';
        $this->info['personal'] = 'اطلاعیه';
        $this->info['table']['headers'] = ['#', 'عنوان', 'نوع', 'بازه نمایش', 'وضعیت', 'عملیات'];
        $this->loadData();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'state'], true)) {
            $this->loadData();
        }
    }

    public function loadData()
    {
        $term = trim((string) $this->search);

        $this->data = $this->model->newQuery()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('content', 'like', "%{$term}%")))
            ->when($this->state === 'visible', fn ($q) => $q->visible())
            ->when($this->state === 'scheduled', fn ($q) => $q->where('is_active', true)->where('starts_at', '>', now()))
            ->when($this->state === 'expired', fn ($q) => $q->where('ends_at', '<', now()))
            ->when($this->state === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_pinned')
            ->latest('id')
            ->get();
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('announcements.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);
        $this->loadData();
    }

    public function get_data($id)
    {
        $this->resetValidation();
        $this->selectItem = $this->model->findOrFail($id);
        $this->title = $this->selectItem->title;
        $this->content = $this->selectItem->content;
        $this->type = $this->selectItem->type;
        $this->link = (string) $this->selectItem->link;
        $this->link_text = (string) $this->selectItem->link_text;
        $this->is_active = $this->selectItem->is_active;
        $this->is_pinned = (bool) $this->selectItem->is_pinned;
        $this->starts_at = JalaliDate::format($this->selectItem->starts_at);
        $this->ends_at = JalaliDate::format($this->selectItem->ends_at);
    }

    public function resetData($action = 'create')
    {
        $this->resetExcept(['model', 'info', 'data', 'search', 'state']);
        $this->resetValidation();

        if ($action !== 'create') {
            $this->dispatch('close-modal');
        }
    }

    public function rules()
    {
        $jalali = 'regex:' . JalaliDate::PATTERN;

        return [
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'in:' . implode(',', array_keys(Announcement::TYPES))],
            'link' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/|#)/i'],
            'link_text' => ['nullable', 'string', 'max:60'],
            'is_active' => ['boolean'],
            'is_pinned' => ['boolean'],
            'starts_at' => ['nullable', $jalali],
            'ends_at' => ['nullable', $jalali],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان اطلاعیه الزامی است.',
            'title.max' => 'عنوان نباید بیشتر از ۱۵۰ کاراکتر باشد.',
            'content.required' => 'متن اطلاعیه الزامی است.',
            'content.max' => 'متن نباید بیشتر از ۲۰۰۰ کاراکتر باشد.',
            'type.in' => 'نوع اطلاعیه نامعتبر است.',
            'link.regex' => 'لینک باید با http(s)://، / یا # شروع شود.',
            'link.max' => 'لینک بیش از حد طولانی است.',
            'link_text.max' => 'متن لینک نباید بیشتر از ۶۰ کاراکتر باشد.',
            'starts_at.regex' => 'تاریخ شروع را به شکل ۱۴۰۵/۰۱/۰۱ وارد کنید.',
            'ends_at.regex' => 'تاریخ پایان را به شکل ۱۴۰۵/۰۱/۰۱ وارد کنید.',
        ];
    }

    public function save()
    {
        abort_if(!auth()->user()->can($this->selectItem ? 'announcements.edit' : 'announcements.create'), 403);

        $data = $this->validate();

        $starts = filled($data['starts_at']) ? JalaliDate::toCarbon($data['starts_at']) : null;
        $ends = filled($data['ends_at']) ? JalaliDate::toCarbon($data['ends_at'])?->endOfDay() : null;

        if (filled($data['starts_at']) && !$starts) {
            $this->addError('starts_at', 'تاریخ شروع نامعتبر است.');
            return;
        }
        if (filled($data['ends_at']) && !$ends) {
            $this->addError('ends_at', 'تاریخ پایان نامعتبر است.');
            return;
        }
        if ($starts && $ends && $ends->lt($starts)) {
            $this->addError('ends_at', 'تاریخ پایان باید بعد از تاریخ شروع باشد.');
            return;
        }

        $payload = [
            'title' => trim($data['title']),
            'content' => trim($data['content']),
            'type' => $data['type'],
            'link' => filled($data['link']) ? trim($data['link']) : null,
            'link_text' => filled($data['link']) && filled($data['link_text']) ? trim($data['link_text']) : null,
            'is_active' => $data['is_active'],
            'is_pinned' => $data['is_pinned'],
            'starts_at' => $starts,
            'ends_at' => $ends,
        ];

        $this->selectItem
            ? tap($this->selectItem)->update($payload)
            : $this->model->create($payload + ['created_by' => auth()->id()]);

        $edited = (bool) $this->selectItem;
        $this->resetData('close');
        $this->loadData();

        $this->dispatch('alert', type: 'success', title: 'عملیات موفق',
            text: $edited ? 'اطلاعیه با موفقیت ویرایش شد.' : 'اطلاعیه جدید با موفقیت ایجاد شد.');
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('announcements.delete'), 403);

        if ($this->selectItem) {
            $this->model->findOrFail($this->selectItem->id)->delete();
            $this->resetData('close');
            $this->loadData();
            $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'اطلاعیه حذف شد.');
        }
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">{{ $info['header'] }}</h1>
            <div class="text-muted small">اطلاعیه‌های فعال در سکشن «اطلاعیه‌های سایت» صفحه‌ساز نمایش داده می‌شوند.</div>
        </div>
        @can('announcements.create')
            <button wire:click="resetData()" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave">
                <i class="ri-add-line align-middle"></i> {{ $info['create'] }}
            </button>
        @endcan
    </div>

    <div class="card custom-card">
        <div class="card-header justify-content-between flex-wrap gap-2">
            <div class="card-title">{{ $info['header'] }}</div>
            <div class="d-flex gap-2">
                <select wire:model.live="state" class="form-select form-select-sm w-auto">
                    <option value="">همه</option>
                    <option value="visible">در حال نمایش</option>
                    <option value="scheduled">زمان‌بندی‌شده</option>
                    <option value="expired">منقضی‌شده</option>
                    <option value="inactive">غیرفعال</option>
                </select>
                <input wire:model.live.debounce.400ms="search" type="text" class="form-control form-control-sm" placeholder="جستجو در عنوان و متن...">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table text-nowrap align-middle">
                    <thead>
                    <tr>@foreach($info['table']['headers'] as $h)<th scope="col">{{ $h }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                    @forelse($data ?? [] as $item)
                        @php
                            $typeMeta = \App\Models\Announcement::TYPES[$item->type] ?? \App\Models\Announcement::TYPES['info'];
                            $state = $item->state();
                        @endphp
                        <tr wire:key="ann-{{ $item->id }}">
                            <th scope="row">{{ $loop->iteration }}</th>
                            <td>
                                @if($item->is_pinned)<i class="ri-pushpin-2-fill text-warning" title="سنجاق‌شده"></i>@endif
                                <span class="fw-medium">{{ $item->title }}</span>
                                <div class="fs-11 text-muted text-truncate" style="max-width: 320px">{{ \Illuminate\Support\Str::limit($item->content, 80) }}</div>
                            </td>
                            <td><span class="badge bg-{{ $typeMeta['color'] }}-transparent"><i class="{{ $typeMeta['icon'] }}"></i> {{ $typeMeta['title'] }}</span></td>
                            <td class="small">
                                {{ $item->starts_at ? verta($item->starts_at)->format('Y/m/d') : 'از الان' }}
                                <i class="ri-arrow-left-line text-muted"></i>
                                {{ $item->ends_at ? verta($item->ends_at)->format('Y/m/d') : 'بدون پایان' }}
                            </td>
                            <td>
                                <span @can('announcements.edit') role="button" wire:click="change_status({{ $item->id }})" @endcan
                                      class="badge bg-outline-{{ ['active' => 'success', 'scheduled' => 'info', 'expired' => 'secondary', 'inactive' => 'danger'][$state] }}">
                                    {{ ['active' => 'در حال نمایش', 'scheduled' => 'زمان‌بندی‌شده', 'expired' => 'منقضی', 'inactive' => 'غیرفعال'][$state] }}
                                </span>
                            </td>
                            <td>
                                <div class="hstack gap-2">
                                    @can('announcements.edit')
                                        <a data-bs-toggle="modal" href="#create" wire:click="get_data({{ $item->id }})" class="text-info fs-14"><i class="ri-edit-line"></i></a>
                                    @endcan
                                    @can('announcements.delete')
                                        <a data-bs-toggle="modal" href="#delete" wire:click="get_data({{ $item->id }})" class="text-danger fs-14"><i class="ri-delete-bin-5-line"></i></a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="ri-notification-off-line fs-32 d-block mb-2"></i>اطلاعیه‌ای ثبت نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- فرم ایجاد/ویرایش --}}
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <form wire:submit="save">
                    <div class="modal-header">
                        <h6 class="modal-title">{{ $selectItem ? 'ویرایش اطلاعیه' : $info['create'] }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">عنوان <span class="text-danger">*</span></label>
                                <input wire:model="title" type="text" maxlength="150" class="form-control @error('title') is-invalid @enderror" placeholder="مثلاً: ارسال رایگان سفارش‌های بالای ۲ میلیون تومان">
                                @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">نوع</label>
                                <select wire:model="type" class="form-select @error('type') is-invalid @enderror">
                                    @foreach(\App\Models\Announcement::TYPES as $key => $meta)
                                        <option value="{{ $key }}">{{ $meta['title'] }}</option>
                                    @endforeach
                                </select>
                                @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">متن اطلاعیه <span class="text-danger">*</span></label>
                                <textarea wire:model="content" rows="4" maxlength="2000" class="form-control @error('content') is-invalid @enderror"></textarea>
                                @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">لینک (اختیاری)</label>
                                <input wire:model="link" type="text" dir="ltr" class="form-control @error('link') is-invalid @enderror" placeholder="/categories/bags">
                                @error('link')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">متن دکمه لینک</label>
                                <input wire:model="link_text" type="text" maxlength="60" class="form-control @error('link_text') is-invalid @enderror" placeholder="مشاهده">
                                @error('link_text')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">شروع نمایش</label>
                                <input data-jdp wire:model.lazy="starts_at" type="text" dir="ltr" class="form-control @error('starts_at') is-invalid @enderror" placeholder="خالی = از همین حالا">
                                @error('starts_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">پایان نمایش</label>
                                <input data-jdp wire:model.lazy="ends_at" type="text" dir="ltr" class="form-control @error('ends_at') is-invalid @enderror" placeholder="خالی = بدون پایان">
                                @error('ends_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="annActive" wire:model="is_active">
                                    <label class="form-check-label" for="annActive">فعال</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="annPinned" wire:model="is_pinned">
                                    <label class="form-check-label" for="annPinned">سنجاق (اول نمایش داده شود)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"></span> ذخیره
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- حذف --}}
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $info['delete'] }} {{ $selectItem?->title }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger mb-0">از حذف این اطلاعیه مطمئن هستید؟</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    <button class="btn btn-danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">حذف</button>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.js') }}"></script>
        <script>jalaliDatepicker.startWatch();</script>
    @endpush
</div>
