<?php

use Livewire\Component;
use App\Models\SocialLink;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

new class extends Component
{
    use \Livewire\WithFileUploads;

    public $info = [];
    public $selectItem;
    public $data;
    public $search;

    public $name = '';
    public $slug;
    public $url;
    public $icon;          // فایل آپلود جدید
    public $currentIcon;   // آیکون فعلی هنگام ویرایش
    public $is_active = true;

    public SocialLink $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(SocialLink $model)
    {
        abort_if(!auth()->user()->can('social-links.view'), 403);

        $this->model = $model;
        $this->info['header'] = 'لیست شبکه‌های اجتماعی';
        $this->info['create'] = 'افزودن شبکه اجتماعی';
        $this->info['delete'] = 'حذف شبکه اجتماعی';
        $this->info['personal'] = 'شبکه اجتماعی';
        $this->info['table']['headers'] = [
            '#',
            'آیکون',
            'نام',
            'لینک',
            'وضعیت',
            'عملیات',
        ];

        $this->loadData();
    }

    public function loadData()
    {
        $query = $this->model->where(function ($query) {
            $query->where('name', 'LIKE', '%' . $this->search . '%')
                ->orWhere('slug', 'LIKE', '%' . $this->search . '%')
                ->orWhere('url', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->orderBy('sort')->get();
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('social-links.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);
        $this->loadData();

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->info['personal'] . ' با موفقیت آپدیت شد.'
        );
    }

    public function get_data($id)
    {
        $this->resetValidation();
        $this->icon = null;

        $this->selectItem = $this->model->findOrFail($id);
        $this->name = $this->selectItem->name;
        $this->slug = $this->selectItem->slug;
        $this->url = $this->selectItem->url;
        $this->is_active = (bool) $this->selectItem->is_active;
        $this->currentIcon = $this->selectItem->icon;
    }

    public function resetData($action = 'create')
    {
        $this->resetValidation();

        if ($action == 'create') {
            $this->resetExcept('model', 'info', 'data');
            $this->is_active = true;
        } else {
            $this->resetExcept(['selectItem', 'model', 'info', 'data']);
            $this->dispatch('close-modal');
        }
    }

    public function updatedName()
    {
        // فقط در حالت ساخت، اسلاگ خودکار پر شود
        if (!$this->selectItem) {
            $this->slug = strtolower(preg_replace('/\s+/', '-', trim($this->name)));
        }
    }

    public function rules()
    {
        $slugRules = [
            'required',
            'string',
            'min:2',
            'max:200',
            'regex:/^[a-zA-Z0-9\-_]+$/',
        ];

        // در حالت ویرایش نباید اسلاگ با رکورد دیگری تکراری شود.
        // در حالت ساخت، اگر اسلاگ وجود داشته باشد آپدیت می‌شود (updateOrCreate).
        if ($this->selectItem) {
            $slugRules[] = Rule::unique('social_links', 'slug')->ignore($this->selectItem->id);
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'slug' => $slugRules,
            'url'  => ['required', 'string', 'url', 'max:500'],
            'is_active' => ['boolean'],
            'icon' => [
                ($this->selectItem && $this->currentIcon) ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp,svg',
                'max:1024',
            ],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'وارد کردن نام الزامی است.',
            'name.string'   => 'نام باید متن باشد.',
            'name.min'      => 'نام باید حداقل ۲ کاراکتر باشد.',
            'name.max'      => 'نام نباید بیشتر از ۲۰۰ کاراکتر باشد.',

            'slug.required' => 'وارد کردن اسلاگ الزامی است.',
            'slug.string'   => 'اسلاگ باید متن باشد.',
            'slug.min'      => 'اسلاگ باید حداقل ۲ کاراکتر باشد.',
            'slug.max'      => 'اسلاگ نباید بیشتر از ۲۰۰ کاراکتر باشد.',
            'slug.regex'    => 'اسلاگ فقط می‌تواند شامل حروف انگلیسی، اعداد، خط تیره (-) و زیرخط (_) باشد.',
            'slug.unique'   => 'این اسلاگ قبلاً ثبت شده است.',

            'url.required' => 'وارد کردن لینک الزامی است.',
            'url.string'   => 'لینک باید متن باشد.',
            'url.url'      => 'لینک وارد شده معتبر نیست (مثلاً https://instagram.com/test).',
            'url.max'      => 'لینک نباید بیشتر از ۵۰۰ کاراکتر باشد.',

            'icon.required' => 'انتخاب آیکون الزامی است.',
            'icon.image'    => 'فایل انتخاب شده برای آیکون معتبر نیست.',
            'icon.mimes'    => 'آیکون باید با فرمت jpg، jpeg، png، webp یا svg باشد.',
            'icon.max'      => 'حجم آیکون نباید بیشتر از ۱ مگابایت باشد.',
        ];
    }

    public function save()
    {
        if ($this->selectItem) {
            abort_if(!auth()->user()->can('social-links.edit'), 403);
        } else {
            abort_if(!auth()->user()->can('social-links.create'), 403);
        }

        $validated = $this->validate();
        unset($validated['icon']);

        $isEdit = (bool) $this->selectItem;

        if ($isEdit) {
            // ویرایش: همان رکورد انتخاب‌شده آپدیت شود
            $item = $this->selectItem;
            $item->update($validated);
        } else {
            // ساخت: اگر اسلاگ وجود داشت آپدیت، وگرنه ایجاد
            $existing = $this->model->where('slug', $validated['slug'])->first();

            if (!$existing) {
                $validated['sort'] = ((int) $this->model->max('sort')) + 1;
            }

            $item = $this->model->updateOrCreate(
                ['slug' => $validated['slug']],
                $validated
            );

            $isEdit = (bool) $existing; // برای پیام نهایی
        }

        // آپلود آیکون
        if ($this->icon) {
            if ($item->icon && Storage::disk('public')->exists($item->icon)) {
                Storage::disk('public')->delete($item->icon);
            }

            $path = $this->icon->store('social-icons', 'public');
            $item->update(['icon' => $path]);
        }

        $this->loadData();
        $this->resetData('close');

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $isEdit
                ? $this->info['personal'] . ' با موفقیت ویرایش شد.'
                : $this->info['personal'] . ' جدید با موفقیت ایجاد شد.',
        );
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('social-links.delete'), 403);

        if ($this->selectItem) {
            $item = $this->model->findOrFail($this->selectItem->id);

            if ($item->icon && Storage::disk('public')->exists($item->icon)) {
                Storage::disk('public')->delete($item->icon);
            }

            $item->delete();
            $this->loadData();

            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: $this->info['personal'] . ' با موفقیت حذف شد.'
            );

            $this->resetData('close');
        }
    }

    #[\Livewire\Attributes\On('updateOrder')]
    public function updateOrder($ids)
    {
        abort_if(!auth()->user()->can('social-links.edit'), 403);

        foreach ($ids as $index => $id) {
            $this->model->where('id', $id)->update([
                'sort' => $index + 1
            ]);
        }
        $this->loadData();
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">
                {{ $info['header'] }}
            </h1>
        </div>
        <div class="btn-list">
            @can('social-links.create')
                <button wire:click="resetData()" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal"
                        href="#create" class="btn btn-success-light btn-wave me-0">
                    <i class="ri-add-line align-middle"></i>
                    {{ $info['create'] }}
                </button>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">
                        {{ $info['header'] }}
                    </div>
                    <div class="header-element header-search d-md-block d-none my-auto">
                        <div class="autoComplete_wrapper" role="combobox" aria-haspopup="true" aria-expanded="false">
                            <input wire:model.lazy="search" autocapitalize="none" autocomplete="off"
                                   class="header-search-bar form-control" id="header-search"
                                   placeholder="جستجو برای نتایج..." spellcheck="false" type="text">
                        </div>
                        <a class="header-search-icon border-0" href="javascript:void(0);">
                            <i wire:click="loadData()" class="bi bi-search"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap">
                            <thead>
                            <tr>
                                @foreach($info['table']['headers'] ?? [] as $h)
                                    <th scope="col">{{ $h }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody id="simple-list">
                            @php($counter = 1)
                            @forelse($data ?? [] as $item)
                                <tr data-id="{{ $item->id }}" wire:key="{{ $item->id }}">
                                    <th scope="row">{{ $counter }}</th>
                                    <td>
                                        @if($item->icon)
                                            <img src="{{ url('/media/' . $item->icon) }}" alt="{{ $item->name }}"
                                                 style="width:32px;height:32px;object-fit:contain">
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->name }}</td>
                                    <td>
                                        <a href="{{ $item->url }}" target="_blank" rel="noopener"
                                           class="text-primary" style="direction:ltr;display:inline-block">
                                            {{ \Illuminate\Support\Str::limit($item->url, 40) }}
                                        </a>
                                    </td>
                                    <td>
                                        @can('social-links.edit')
                                            <span style="cursor: pointer" wire:click="change_status({{ $item->id }})"
                                                  wire:loading.attr="disabled"
                                                  class="badge bg-outline-{{ $item->is_active ? 'success' : 'danger' }}">
                                                <span wire:target="change_status" wire:loading.remove>{{ $item->is_active ? 'فعال' : 'غیرفعال' }}</span>
                                                <span wire:target="change_status" wire:loading>در حال تغییر...</span>
                                            </span>
                                        @endcan
                                    </td>
                                    <td>
                                        <div class="hstack gap-2 flex-wrap">
                                            @can('social-links.edit')
                                                <a data-bs-toggle="modal" href="#create"
                                                   wire:click="get_data({{ $item->id }})"
                                                   class="text-info fs-14 lh-1"><i class="ri-edit-line"></i></a>
                                            @endcan
                                            @can('social-links.delete')
                                                <a data-bs-toggle="modal" href="#delete"
                                                   wire:click="get_data({{ $item->id }})"
                                                   class="text-danger fs-14 lh-1"><i class="ri-delete-bin-5-line"></i></a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @php($counter++)
                            @empty
                                <tr>
                                    <td colspan="{{ count($info['table']['headers'] ?? []) }}"
                                        class="text-center py-5 text-muted">
                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                        <strong>موردی برای نمایش وجود ندارد.</strong>
                                        <div class="small mt-1">
                                            پس از ایجاد اولین آیتم، اطلاعات در این بخش نمایش داده خواهد شد.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- مودال ساخت / ویرایش --}}
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered text-center modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $info['create'] }}</h6>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save()" id="save">
                        <div class="row">
                            <div class="col-xl-6">
                                <label for="sl-name" class="form-label">نام {{ $info['personal'] }}</label>
                                <input wire:model.lazy="name" type="text"
                                       class="form-control @error('name') is-invalid @enderror" id="sl-name"
                                       placeholder="مثلاً Instagram">
                                @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-xl-6">
                                <label for="sl-slug" class="form-label">اسلاگ</label>
                                <input wire:model.lazy="slug" type="text" style="direction:ltr"
                                       class="form-control @error('slug') is-invalid @enderror" id="sl-slug"
                                       placeholder="instagram">
                                @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if(!$selectItem)
                                    <div class="form-text">اگر این اسلاگ قبلاً ثبت شده باشد، همان رکورد آپدیت می‌شود.</div>
                                @endif
                            </div>

                            <div class="col-xl-12 mt-3">
                                <label for="sl-url" class="form-label">لینک {{ $info['personal'] }}</label>
                                <input wire:model.lazy="url" type="text" style="direction:ltr"
                                       class="form-control @error('url') is-invalid @enderror" id="sl-url"
                                       placeholder="https://instagram.com/yourpage">
                                @error('url')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-xl-8 mt-3">
                                <label class="form-label">آیکون</label>
                                <div x-data="{ progress: 0 }"
                                     x-on:livewire-upload-start="progress = 0"
                                     x-on:livewire-upload-finish="progress = 100"
                                     x-on:livewire-upload-error="progress = 0"
                                     x-on:livewire-upload-progress="progress = $event.detail.progress">

                                    <input wire:model.lazy="icon"
                                           class="form-control mb-1 @error('icon') is-invalid @enderror"
                                           type="file">

                                    <div class="progress mt-2" x-show="progress > 0 && progress < 100">
                                        <div class="progress-bar" role="progressbar"
                                             :style="'width: ' + progress + '%'">
                                            <span x-text="progress + '%'"></span>
                                        </div>
                                    </div>
                                </div>
                                @error('icon')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-xl-4 mt-3">
                                <label class="form-label d-block">پیش‌نمایش</label>
                                @if($icon && method_exists($icon, 'temporaryUrl') && !$errors->has('icon'))
                                    <img src="{{ $icon->temporaryUrl() }}" style="width:48px;height:48px;object-fit:contain">
                                @elseif($currentIcon)
                                    <img src="{{ url('/media/' . $currentIcon) }}" style="width:48px;height:48px;object-fit:contain">
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </div>

                            <div class="col-xl-12 mt-3">
                                <div class="form-check form-switch">
                                    <input wire:model="is_active" class="form-check-input" type="checkbox"
                                           id="sl-active">
                                    <label class="form-check-label" for="sl-active">فعال باشد</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="save">
                        <button class="btn btn-info"
                                form="save"
                                wire:loading.attr="disabled"
                                wire:target="icon,save"
                                type="submit">
                            ذخیره تغییرات
                        </button>
                        <button class="btn btn-light" data-bs-dismiss="modal" type="button">
                            بستن
                        </button>
                    </div>

                    <div wire:loading wire:target="save" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال بارگیری...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- مودال حذف --}}
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered text-center" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $info['delete'] . ' ' . $selectItem?->name }}</h6>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="ri-error-warning-line fs-4 me-2"></i>
                        <div>از حذف کردن این آیتم مطمئن هستید؟!</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="delete">
                        <button class="btn btn-info" wire:click="delete()">حذف</button>
                        <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>

                    <div wire:loading wire:target="delete" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال حذف...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('dashboard') }}/libs/sortablejs/Sortable.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const simple = document.getElementById('simple-list');
                new Sortable(simple, {
                    animation: 150,
                    onEnd: function () {
                        const ids = Array.from(simple.children)
                            .map(item => item.dataset.id)
                            .filter(Boolean);

                        Livewire.dispatch('updateOrder', {ids: ids});
                    }
                });
            });
        </script>
    @endpush
</div>
