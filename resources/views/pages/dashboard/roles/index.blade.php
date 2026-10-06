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
        // ساختار: [گروه => [بخش => ['label' => ..., 'items' => [['name' => ..., 'action' => ...]]]]]
        $db = \Spatie\Permission\Models\Permission::orderBy('name')->pluck('name')->flip();
        $grouped = [];

        foreach (\App\Support\Permissions::grouped() as $groupLabel => $areas) {
            foreach ($areas as $area => $meta) {
                $items = [];
                foreach ($meta['permissions'] as $name => $actionLabel) {
                    if ($db->has($name)) {
                        $items[] = ['name' => $name, 'action' => $actionLabel];
                        $db->forget($name);
                    }
                }
                if ($items) {
                    $grouped[$groupLabel][$area] = ['label' => $meta['label'], 'items' => $items];
                }
            }
        }

        // دسترسی‌های سفارشی که در فهرست مرجع نیستند
        if ($db->isNotEmpty()) {
            $grouped['سایر']['custom'] = [
                'label' => 'دسترسی‌های سفارشی',
                'items' => $db->keys()->map(fn ($name) => ['name' => $name, 'action' => $name])->all(),
            ];
        }

        $this->allPermissions = $grouped;
    }

    // نام همه دسترسی‌ها (یا یک گروه / یک بخش)
    protected function permissionNames(?string $group = null, ?string $area = null): array
    {
        return collect($this->allPermissions)
            ->when($group !== null, fn ($c) => $c->only([$group]))
            ->flatMap(fn ($areas) => $area !== null ? array_intersect_key($areas, [$area => true]) : $areas)
            ->flatMap(fn ($meta) => array_column($meta['items'], 'name'))
            ->values()
            ->all();
    }

    protected function toggleNames(array $names): void
    {
        $allSelected = $names && collect($names)->every(fn ($n) => in_array($n, $this->selectedPermissions, true));

        $this->selectedPermissions = $allSelected
            ? array_values(array_diff($this->selectedPermissions, $names))
            : array_values(array_unique(array_merge($this->selectedPermissions, $names)));
    }

    public function selectAllPermissions(): void
    {
        $this->selectedPermissions = $this->permissionNames();
    }

    public function toggleArea($groupLabel, $area): void
    {
        $this->toggleNames($this->permissionNames($groupLabel, $area));
    }

    // تعداد دسترسی‌های فهرست مرجع که در دیتابیس ساخته نشده‌اند (یا منسوخ‌های باقی‌مانده)
    #[\Livewire\Attributes\Computed]
    public function outOfSync(): int
    {
        return \App\Support\Permissions::outOfSync();
    }

    // همان کار RoleTableSeeder (غیرمخرب)
    public function syncRegistry()
    {
        abort_if(!auth()->user()->can('roles.edit'), 403);

        $result = \App\Support\Permissions::sync();

        unset($this->outOfSync);
        $this->loadPermissions();
        if ($this->selectItem) {
            $this->get_permissions($this->selectItem->id);
        }

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: "همگام‌سازی انجام شد: {$result['created']} دسترسی جدید، {$result['removed']} دسترسی منسوخ حذف شد."
        );
    }

    public function get_permissions($id)
    {
        $this->selectItem = $this->model->findOrFail($id);
        $this->selectedPermissions = $this->selectItem->permissions->pluck('name')->toArray();
    }

    public function toggleGroup($groupLabel)
    {
        $this->toggleNames($this->permissionNames($groupLabel));
    }

    // نقش‌های پایه: admin همیشه همه دسترسی‌ها را دارد، user (مشتری) نباید دسترسی پنل بگیرد
    protected const PROTECTED_ROLES = ['admin', 'user'];

    protected function guardProtected(string $message): bool
    {
        if ($this->selectItem && in_array($this->selectItem->name, self::PROTECTED_ROLES, true)) {
            $this->dispatch('alert', type: 'error', title: 'غیرمجاز', text: $message);
            return true;
        }

        return false;
    }

    public function savePermissions()
    {
        $this->authorize('roles.edit');

        if ($this->guardProtected('دسترسی‌های نقش‌های پایه (مدیر کل / کاربر عادی) از طریق seeder مدیریت می‌شوند.')) {
            return;
        }

        // فقط دسترسی‌های موجود؛ و کسی بیشتر از دسترسی‌های خودش واگذار نکند
        $this->selectedPermissions = collect($this->selectedPermissions)
            ->filter(fn ($name) => \Spatie\Permission\Models\Permission::where('name', $name)->exists())
            ->filter(fn ($name) => auth()->user()->can($name))
            ->values()
            ->all();

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

        if ($this->guardProtected('نقش‌های پایه قابل حذف نیستند.')) {
            return;
        }

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

        // نام نقش‌های پایه در کد استفاده می‌شود و نباید تغییر کند
        if ($isEdit && in_array($this->selectItem->name, self::PROTECTED_ROLES, true) && ($data['name'] ?? $this->selectItem->name) !== $this->selectItem->name) {
            $this->addError('name', 'نام نقش‌های پایه قابل تغییر نیست.');
            return;
        }

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
            @can('roles.edit')
                <button type="button"
                        wire:click="syncRegistry"
                        wire:loading.attr="disabled"
                        wire:target="syncRegistry"
                        class="btn {{ $this->outOfSync ? 'btn-warning' : 'btn-light' }} btn-wave me-2"
                        title="ساخت دسترسی‌های جدید بخش‌ها و حذف دسترسی‌های منسوخ">
                    <i class="ri-refresh-line align-middle" wire:loading.class="spin" wire:target="syncRegistry"></i>
                    همگام‌سازی دسترسی‌ها
                    @if($this->outOfSync)
                        <span class="badge bg-white text-warning ms-1">{{ $this->outOfSync }}</span>
                    @endif
                </button>
            @endcan
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

    @if($this->outOfSync)
        <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span>
                <i class="ri-error-warning-line me-1"></i>
                {{ $this->outOfSync }} دسترسی از بخش‌های پنل هنوز در دیتابیس ساخته نشده یا منسوخ است؛
                تا همگام‌سازی نکنید در فهرست دسترسی‌های نقش‌ها نمایش داده نمی‌شوند.
            </span>
            @can('roles.edit')
                <button type="button" wire:click="syncRegistry" class="btn btn-sm btn-warning">همگام‌سازی الان</button>
            @endcan
        </div>
    @endif

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
                            @php
                                $counter = 1;
                            @endphp
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
                                @php
                                    $counter++;
                                @endphp
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
                                wire:click="selectAllPermissions">
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

                    @if(in_array($selectItem?->name, ['admin'], true))
                        <div class="alert alert-info">
                            <i class="ri-shield-star-line me-1"></i>
                            نقش «مدیر کل» همیشه به همه بخش‌ها دسترسی دارد و دسترسی‌هایش از اینجا تغییر نمی‌کند.
                        </div>
                    @endif

                    {{-- گروه‌ها --}}
                    @php
                        $actionStyles = [
                            'مشاهده' => 'info', 'ایجاد' => 'success', 'ویرایش' => 'warning', 'حذف' => 'danger', 'ورود' => 'primary',
                        ];
                    @endphp
                    <div class="row g-3">
                        @foreach($allPermissions as $groupLabel => $areas)
                            @php
                                $groupNames = collect($areas)->flatMap(fn ($a) => array_column($a['items'], 'name'));
                                $groupSelected = $groupNames->filter(fn ($n) => in_array($n, $selectedPermissions, true))->count();
                            @endphp
                            <div class="col-xl-6" wire:key="perm-group-{{ md5($groupLabel) }}">
                                <div class="card border mb-0 h-100">
                                    <div class="card-header py-2 px-3 bg-light d-flex align-items-center justify-content-between">
                                        <span class="fw-semibold fs-13">
                                            {{ $groupLabel }}
                                            <span class="badge bg-{{ $groupSelected === $groupNames->count() ? 'success' : ($groupSelected ? 'warning' : 'secondary') }}-transparent ms-1">
                                                {{ $groupSelected }} / {{ $groupNames->count() }}
                                            </span>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-link p-0 fs-12" wire:click="toggleGroup('{{ $groupLabel }}')">
                                            {{ $groupSelected === $groupNames->count() ? 'حذف همه' : 'انتخاب همه' }}
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-sm align-middle mb-0">
                                            <tbody>
                                            @foreach($areas as $area => $meta)
                                                @php
                                                    $areaNames = array_column($meta['items'], 'name');
                                                    $areaAll = collect($areaNames)->every(fn ($n) => in_array($n, $selectedPermissions, true));
                                                @endphp
                                                <tr wire:key="perm-area-{{ $area }}">
                                                    <td class="ps-3" style="width: 38%">
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input" type="checkbox" id="area_{{ $area }}"
                                                                   wire:click="toggleArea('{{ $groupLabel }}', '{{ $area }}')"
                                                                   @checked($areaAll)>
                                                            <label class="form-check-label fw-medium fs-13" for="area_{{ $area }}">{{ $meta['label'] }}</label>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-3">
                                                            @foreach($meta['items'] as $item)
                                                                <div class="form-check mb-0" title="{{ $item['name'] }}">
                                                                    <input class="form-check-input" type="checkbox"
                                                                           id="perm_{{ md5($item['name']) }}"
                                                                           value="{{ $item['name'] }}"
                                                                           wire:model.live="selectedPermissions">
                                                                    <label class="form-check-label fs-12 text-{{ $actionStyles[$item['action']] ?? 'secondary' }}"
                                                                           for="perm_{{ md5($item['name']) }}">{{ $item['action'] }}</label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="savePermissions">
                        <button class="btn btn-primary" wire:click="savePermissions()" @disabled($selectItem?->name === 'admin')>
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
