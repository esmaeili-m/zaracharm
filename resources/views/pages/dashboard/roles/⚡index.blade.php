<?php

use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;

    public $info = [];
    public $selectItem;
    public $data;
    public $name;
    public $label;
    public $search;
    public $selectedPermissions = [];
    public $allPermissions = [];
    public Role $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Role $model)
    {
        abort_if(!auth()->user()->can('roles.view'), 403);

        $this->model = $model;
        $this->info['header'] = 'لیست نقش ها';
        $this->info['create'] = 'افزودن نقش';
        $this->info['delete'] = 'حذف نقش';
        $this->info['person'] = ' نقش';
        $this->info['table']['headers'] = [
            '#',
            'نام',
            'لیبل',
            'عملیات',
        ];
        $this->loadData();
        $this->loadPermissions();

    }
    public function loadPermissions()
    {
        // گروه‌بندی permission ها بر اساس پیشوند
        $groups = [
            'کاربران'          => ['users', 'roles'],
            'محتوا'            => ['posts', 'articles', 'pages', 'sections', 'galleries'],
            'آموزش و خدمات'    => ['courses', 'services'],
            'سازمان‌دهی'       => ['categories', 'tags', 'menus'],
            'ارتباطات'         => ['messages', 'comments', 'tickets', 'faq'],
            'مالی و سئو'       => ['invoices', 'seo'],
            'سیستم'            => ['settings'],
        ];

        $permissions = \Spatie\Permission\Models\Permission::all()->groupBy(function ($p) {
            return explode('.', $p->name)[0];
        });
        $grouped = [];
        foreach ($groups as $label => $prefixes) {
            $grouped[$label] = [];
            foreach ($prefixes as $prefix) {
                if (isset($permissions[$prefix])) {
                    foreach ($permissions[$prefix] as $perm) {
                        $grouped[$label][] = $perm;
                    }
                }
            }
        }

        $this->allPermissions = $grouped;
    }

    public function get_permissions($id)
    {
        $this->selectItem = $this->model->findOrFail($id);
        $this->selectedPermissions = $this->selectItem->permissions->pluck('name')->toArray();
    }

    public function toggleGroup($groupLabel)
    {
        $groupPermissions = collect($this->allPermissions[$groupLabel] ?? [])->pluck('name')->toArray();
        $allSelected = collect($groupPermissions)->every(fn($n) => in_array($n, $this->selectedPermissions));

        if ($allSelected) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPermissions));
        } else {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
        }
    }

    public function savePermissions()
    {
        $this->authorize('roles.edit');

        $this->selectItem->syncPermissions($this->selectedPermissions);

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: "دسترسی‌های نقش با موفقیت به‌روز شد."
        );
        $this->dispatch('close-permission-modal');
    }
    public function delete()
    {
        $this->authorize('roles.delete');
        if ($this->selectItem) {
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: "{$this->info['person']} با موفقیت حذف شد."
            );
            $this->loadData();
            $this->resetData('close');
        }
    }

    public function loadData()
    {
        $query = $this->model->query();
        if ($this->search) {
            $query->where('name', 'LIKE', '%' . $this->search . '%');
        }
        $this->data = $query->get();
    }

    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);
        $this->name = $this->selectItem->name;
        $this->label = $this->selectItem->label;
    }

    public function resetData($action = 'create')
    {
        if ($action == 'create') {
            $this->resetExcept('model', 'info', 'data');
        } else {
            $this->resetExcept(['selectItem', 'model', 'info', 'data']);
            $this->dispatch('close-modal');
        }
    }

    public function rules()
    {
        $id = $this->selectItem?->id;
        return [
            'name'  => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('roles', 'name')->ignore($id)],
            'label' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'name.required'  => 'وارد کردن نام نقش الزامی است.',
            'label.required' => 'وارد کردن لیبل الزامی است.',
            'name.string'    => 'نام نقش باید از نوع متن باشد.',
            'name.max'       => 'نام نقش نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'name.unique'    => 'این نام نقش قبلاً ثبت شده است. لطفاً نام دیگری انتخاب کنید.',
        ];
    }

    public function save()
    {
        if ($this->selectItem) {
            abort_if(!auth()->user()->can('roles.edit'), 403);
        } else {
            abort_if(!auth()->user()->can('roles.create'), 403);
        }

        $data = $this->validate();

        // باگ‌فیکس: قبل از reset چک می‌کنیم ویرایش بود یا ایجاد
        $isEdit = (bool) $this->selectItem;

        if ($isEdit) {
            $this->selectItem->update($data);
        } else {
            $this->model->create($data);
        }

        $this->loadData();
        $this->resetData('close');

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $isEdit
                ? "{$this->info['person']} با موفقیت ویرایش شد."
                : "{$this->info['person']} جدید با موفقیت ایجاد شد."
        );
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">{{ $info['header'] ?? '' }}</h1>
        </div>
        <div class="btn-list">
            @can('roles.create')
                <button wire:click="resetData()"
                        data-bs-effect="effect-flip-horizontal"
                        data-bs-toggle="modal"
                        href="#create"
                        class="btn btn-success-light btn-wave me-0">
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
                    <div class="card-title">{{ $info['header'] }}</div>
                    <div class="header-element header-search d-md-block d-none my-auto">
                        <div class="autoComplete_wrapper">
                            <input wire:model.lazy="search"
                                   autocomplete="off"
                                   class="header-search-bar form-control"
                                   placeholder="جستجو برای نتایج..."
                                   type="text">
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
                            <tbody>
                            @php($counter = 1)
                            @foreach($data ?? [] as $item)
                                <tr wire:key="{{ $item->id }}">
                                    <th scope="row">{{ $counter }}</th>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->label }}</td>
                                    <td>
                                        <div class="hstack gap-2 flex-wrap">
                                            @can('roles.edit')
                                                <a data-bs-toggle="modal"
                                                   href="#permission"
                                                   wire:click="get_permissions({{ $item->id }})"
                                                   class="text-info fs-14 lh-1"
                                                   title="دسترسی‌ها">
                                                    <i class="ri-lock-fill"></i>
                                                </a>
                                            @endcan

                                            @if(!in_array($item->id, [1, 2, 3]))
                                                @can('roles.edit')
                                                    <a data-bs-toggle="modal"
                                                       href="#create"
                                                       wire:click="get_data({{ $item->id }})"
                                                       class="text-info fs-14 lh-1"
                                                       title="ویرایش">
                                                        <i class="ri-edit-line"></i>
                                                    </a>
                                                @endcan

                                                @can('roles.delete')
                                                    <a data-bs-toggle="modal"
                                                       href="#delete"
                                                       wire:click="get_data({{ $item->id }})"
                                                       class="text-danger fs-14 lh-1"
                                                       title="حذف">
                                                        <i class="ri-delete-bin-5-line"></i>
                                                    </a>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @php($counter++)
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: ایجاد / ویرایش --}}
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content modal-content-demo">
                <form wire:submit="save()">
                    <div class="modal-header">
                        <h6 class="modal-title">{{ $info['create'] }}</h6>
                        <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="row">
                            <div class="col-xl-12">
                                <label class="form-label">نام نقش</label>
                                <input wire:model.lazy="name"
                                       type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       placeholder="مثال: content-manager">
                                @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-xl-12 mt-2">
                                <label class="form-label">لیبل نقش</label>
                                <input wire:model.lazy="label"
                                       type="text"
                                       class="form-control @error('label') is-invalid @enderror"
                                       placeholder="مثال: مدیر محتوا">
                                @error('label')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div wire:loading.remove wire:target="save">
                            <button class="btn btn-primary" type="submit">ذخیره تغییرات</button>
                            <button class="btn btn-light" data-bs-dismiss="modal" type="button">بستن</button>
                        </div>
                        <div wire:loading wire:target="save" class="spinner-grow text-info" role="status">
                            <span class="visually-hidden">در حال بارگیری...</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="permission">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">
                        <i class="ri-lock-2-line me-1"></i>
                        مدیریت دسترسی‌های نقش:
                        <span class="text-primary">{{ $selectItem?->label }}</span>
                    </h6>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">

                    {{-- انتخاب همه / هیچکدام --}}
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                        <button type="button" class="btn btn-sm btn-light"
                                wire:click="$set('selectedPermissions', {{ json_encode(collect($allPermissions)->flatten()->pluck('name')->values()) }})">
                            <i class="ri-checkbox-multiple-line me-1"></i> انتخاب همه
                        </button>
                        <button type="button" class="btn btn-sm btn-light"
                                wire:click="$set('selectedPermissions', [])">
                            <i class="ri-checkbox-multiple-blank-line me-1"></i> هیچکدام
                        </button>
                        <span class="text-muted fs-12 me-auto">
                            {{ count($selectedPermissions) }} دسترسی انتخاب شده
                        </span>
                    </div>

                    {{-- گروه‌ها --}}
                    <div class="row g-3">
                        @foreach($allPermissions as $groupLabel => $permissions)
                            @if(count($permissions) > 0)
                                <div class="col-xl-4 col-md-6">
                                    <div class="card border mb-0 h-100">
                                        <div class="card-header py-2 px-3 bg-light d-flex align-items-center justify-content-between">
                                            <span class="fw-medium fs-13">{{ $groupLabel }}</span>
                                            {{-- انتخاب همه گروه --}}
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   title="انتخاب همه {{ $groupLabel }}"
                                                   wire:click="toggleGroup('{{ $groupLabel }}')"
                                                @checked(collect($permissions)->pluck('name')->every(fn($n) => in_array($n, $selectedPermissions)))>
                                        </div>
                                        <div class="card-body py-2 px-3">
                                            @foreach($permissions as $permission)
                                                <?php
                                                    $parts = explode('.', $permission->name);
                                                    $action = $parts[1] ?? $permission->name;
                                                    $actionLabels = [
                                                        'view'   => ['label' => 'مشاهده',  'class' => 'text-info',    'icon' => 'ri-eye-line'],
                                                        'create' => ['label' => 'ایجاد',   'class' => 'text-success', 'icon' => 'ri-add-circle-line'],
                                                        'edit'   => ['label' => 'ویرایش',  'class' => 'text-warning', 'icon' => 'ri-edit-line'],
                                                        'delete' => ['label' => 'حذف',     'class' => 'text-danger',  'icon' => 'ri-delete-bin-line'],
                                                        'reply'  => ['label' => 'پاسخ',    'class' => 'text-primary', 'icon' => 'ri-reply-line'],
                                                        'close'  => ['label' => 'بستن',    'class' => 'text-secondary','icon' => 'ri-close-circle-line'],
                                                    ];
                                                    $meta = $actionLabels[$action] ?? ['label' => $action, 'class' => 'text-secondary', 'icon' => 'ri-key-line'];
                                                ?>
                                                <div class="form-check d-flex align-items-center gap-2 py-1">
                                                    <input class="form-check-input"
                                                           type="checkbox"
                                                           id="perm_{{ $permission->id }}"
                                                           value="{{ $permission->name }}"
                                                           wire:model="selectedPermissions">
                                                    <label class="form-check-label d-flex align-items-center gap-1 cursor-pointer"
                                                           for="perm_{{ $permission->id }}">
                                                        <i class="{{ $meta['icon'] }} {{ $meta['class'] }} fs-14"></i>
                                                        <span class="fs-13">{{ $meta['label'] }}</span>
                                                        <small class="text-muted">({{ $permission->name }})</small>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="savePermissions">
                        <button class="btn btn-primary" wire:click="savePermissions()">
                            <i class="ri-save-line me-1"></i> ذخیره دسترسی‌ها
                        </button>
                        <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="savePermissions" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال ذخیره...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: حذف --}}
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $info['delete'] . ' ' . $selectItem?->label }}</h6>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                        <i class="ri-error-warning-line fs-20"></i>
                        <div>از حذف این نقش مطمئن هستید؟</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="delete">
                        <button class="btn btn-danger" wire:click="delete()">حذف</button>
                        <button class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                    <div wire:loading wire:target="delete" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال حذف...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
