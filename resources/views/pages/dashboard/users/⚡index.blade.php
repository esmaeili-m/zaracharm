<?php

use Livewire\Component;
use \App\Models\User;
use \App\Services\FileUploadService;
use Illuminate\Validation\Rule;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $name;
    public $mobile;
    public $email;
    public $password;
    public $image;
    public $file;
    public $role;
    public $search;
    public $platform;
    public $url;
    public User $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(User $model)
    {
        abort_if(!auth()->user()->can('users.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست کاربران';
        $this->info['create']='افزودن کاربر';
        $this->info['delete']='حذف کاربر';
        $this->info['table']['headers']=[
            '#',
            'شماره همراه',
            'نام',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('users.edit'), 403);
        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->loadData();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'کاربر با موفقیت آپدیت شد.'
        );
    }
    public function delete()
    {
        abort_if(!auth()->user()->can('users.delete'), 403);
        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: 'کاربر با موفقیت حذف شد.'
            );
            $this->loadData();
            $this->resetData('close');
        }

    }
    public function loadData()
    {
        $query = $this->model->query();
        if ($this->search) {
            $query->where('name', 'LIKE' ,'%'.$this->search.'%')
                ->orWhere('email', 'LIKE' ,'%'.$this->search.'%')
                ->orWhere('mobile', 'LIKE' ,'%'.$this->search.'%');
        }
        $this->data = $query->get();
    }


    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->name=$this->selectItem->name;
        $this->email=$this->selectItem->email;
        $this->mobile=$this->selectItem->mobile;
        $this->role = $this->selectItem
            ->getRoleNames()
            ->first();
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['model','info','data']);
            $this->dispatch('close-modal');
        }
    }
    public function saveSocial()
    {
        abort_if(!auth()->user()->can('users.create'), 403);

        $this->validate( [
            'platform' => ['required'],
            'url' => ['required', 'url'],
        ],[
            'platform.required' => 'لطفاً شبکه اجتماعی را انتخاب کنید.',

            'url.required' => 'لطفاً لینک شبکه اجتماعی را وارد کنید.',
            'url.url' => 'لینک وارد شده معتبر نیست.',
        ]);

        \App\Models\TeacherSocial::updateOrCreate(
            [
                'teacher_id' => $this->selectItem->id,
                'platform'   => $this->platform,
            ],
            [
                'url' => $this->url,
            ]
        );

        $this->reset([
            'platform',
            'url',
        ]);
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'کاربر با موفقیت آپدیت شد.'
        );
        $this->dispatch('close-modal');

    }
    public function rules()
    {
        $userId = $this->selectItem?->id;

        return [

            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'mobile' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
                Rule::unique('users', 'mobile')->ignore($userId),
            ],

            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'password' => [
                'nullable',
                'string',
                'min:6',
                'max:255',
            ],

            'image' => [
                'nullable',
                'string',
            ],

            'role' => [
                'required',
                'string',
                'exists:roles,name',
            ],

        ];
    }

    public function messages()
    {
        return [

            // name
            'name.string' => 'نام باید به صورت متن وارد شود.',
            'name.max' => 'نام نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',

            // mobile
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex' => 'فرمت شماره موبایل صحیح نیست. مثال: 09123456789',
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',

            // email
            'email.email' => 'فرمت ایمیل صحیح نیست.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',

            // role
            'role.required' => 'انتخاب نقش الزامی است.',
            'role.integer' => 'نقش انتخاب شده معتبر نیست.',
            'role.exists' => 'نقش انتخاب شده در سیستم وجود ندارد.',

            // password
            'password.string' => 'رمز عبور باید متن باشد.',
            'password.min' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'password.max' => 'رمز عبور نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',

            // image
            'image.image' => 'فایل انتخاب شده باید تصویر باشد.',
            'image.mimes' => 'فرمت تصویر باید jpg, jpeg, png یا webp باشد.',
            'image.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',

        ];
    }
    public function deleteSocial($id)
    {
        \App\Models\TeacherSocial::where('id', $id)
            ->where('teacher_id', $this->selectItem->id)
            ->delete();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: ' اطلاعات کاربر با موفقیت آپدیت شد.'
        );
        $this->get_data($this->selectItem->id);
    }
    public function save()
    {
        abort_if(!auth()->user()->can('users.create'), 403);
        $data = $this->validate();
        if (!empty($data['password'])) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        unset($data['role']);

        $user = $this->selectItem
            ? tap($this->selectItem)->update($data)
            : $this->model->create($data);

        if ($this->role) {
            $user->syncRoles([$this->role]);
        }

        if ($this->file) {
            $user->media()
                ->where('collection', 'avatar')
                ->delete();
            $this->upload(
                $this->file,
                $user,
                'avatar'
            );
        }
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: ' اطلاعات کاربر با موفقیت آپدیت شد.'
        );
        $this->loadData();
        $this->resetData('close');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">
                {{$info['header']}}
            </h1>

        </div>
        <div class="btn-list">
            @can('users.view')
                <a href="{{route('users.trash')}}" class="btn btn-warning-light btn-wave me-2">
                    <i class="bx bx-trash align-middle">
                    </i>
                    سطل آشغال
                </a>
            @endcan
            @can('users.create')
                <button wire:click="resetData()" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave me-0">
                    <i class="ri-add-line align-middle">
                    </i>
                    {{$info['create']}}
                </button>
             @endcan

        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">
                        {{$info['header']}}
                    </div>
                    <div class="header-element header-search d-md-block d-none my-auto">
                        <div class="autoComplete_wrapper" role="combobox" aria-owns="autoComplete_list_1" aria-haspopup="true" aria-expanded="false"><input wire:model.laz="search" autocapitalize="none" autocomplete="off" class="header-search-bar form-control" id="header-search" placeholder="جستجو برای نتایج..." spellcheck="false" type="text" aria-controls="autoComplete_list_1" aria-autocomplete="both"><ul id="autoComplete_list_1" role="listbox" hidden=""></ul></div>
                        <a class="header-search-icon border-0" href="javascript:void(0);">
                            <i wire:click="loadData()" class="bi bi-search">
                            </i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap">
                            <thead>
                            <tr>
                                @foreach($info['table']['headers'] ?? [] as $h)
                                    <th scope="col">
                                        {{$h}}
                                    </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @php($counter=1)
                            @foreach($data ?? [] as $item)
                                <tr wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>
                                        {{$item->mobile}}
                                    </td>
                                    <td>
                                        {{$item->name}}
                                    </td>
                                    <td>
                                        @if($item->id != 1)

                                     <span style="cursor: pointer" wire:click="change_status({{$item->id}})"
                                           wire:loading.attr="disabled"
                                           class="badge bg-outline-{{$item->status == 1 ? 'success' : 'danger'}}">
                                            <span wire:target="change_status" wire:loading.remove>{{$item->status == 1 ? 'فعال' : 'غیرفعال'}}</span>
                                            <span wire:target="change_status" wire:loading>در حال تغییر...</span>
                                     </span>
                                            @endif
                                    </td>
                                    <td>
                                        @if($item->id != 1)
                                            <div class="hstack gap-2 flex-wrap">
                                                @can('users.create')
                                                    <a data-bs-toggle="modal" href="#createSocial" wire:click="get_data({{$item->id}})"  class="text-warning fs-14 lh-1"><i
                                                            class="ri-instagram-line"></i></a>
                                                @endcan
                                                @can('users.edit')

                                                    <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                            class="ri-edit-line"></i></a>
                                                @endcan
                                                @can('users.delete')

                                                    <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                            class="ri-delete-bin-5-line"></i></a>
                                                @endcan

                                            </div>
                                        @endif

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
    <div wire:ignore.self class="modal fade" id="createSocial">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content modal-content-demo">

                <hr>
                <form wire:submit="saveSocial">

                    <div class="modal-header">
                        <h6 class="modal-title">
                            افزودن شبکه اجتماعی مدرس
                        </h6>

                        <button aria-label="Close"
                                class="btn-close"
                                data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body text-start">

                        <div class="row">

                            {{-- ستون چپ: لیست --}}
                            <div class="col-md-6 border-end">

                                <h6 class="mb-3">شبکه‌های ثبت شده</h6>

                                <div class="table-responsive">

                                    <table class="table table-sm text-nowrap">

                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>پلتفرم</th>
                                            <th>لینک</th>
                                            <th></th>
                                        </tr>
                                        </thead>

                                        <tbody>

                                        @php($counter = 1)

                                        @foreach(optional($selectItem)->socials ?? [] as $social)

                                            <tr wire:key="social-{{ $social->id }}">

                                                <td>{{ $counter }}</td>

                                                <td>{{ $social->platform }}</td>

                                                <td>
                                                    <a href="{{ $social->url }}" target="_blank">
                                                        لینک
                                                    </a>
                                                </td>

                                                <td>
                                                    <a wire:click="deleteSocial({{ $social->id }})"
                                                       onclick="return confirm('حذف شود؟')"
                                                       class="text-danger">
                                                        حذف
                                                    </a>
                                                </td>

                                            </tr>

                                            @php($counter++)
                                        @endforeach

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                            {{-- ستون راست: فرم --}}
                            <div class="col-md-6">

                                <h6 class="mb-3">افزودن شبکه اجتماعی</h6>

                                <form wire:submit="saveSocial">

                                    <div class="mb-3">

                                        <label class="form-label">شبکه اجتماعی</label>

                                        <select wire:model.lazy="platform"
                                                class="form-control @error('platform') is-invalid @enderror">

                                            <option value="">انتخاب کنید</option>

                                            <option value="instagram">اینستاگرام</option>
                                            <option value="telegram">تلگرام</option>
                                            <option value="linkedin">لینکدین</option>
                                            <option value="youtube">یوتیوب</option>
                                            <option value="x">ایکس</option>
                                            <option value="eitaa">ایتا</option>
                                            <option value="bale">بله</option>
                                            <option value="rubika">روبیکا</option>
                                            <option value="aparat">آپارات</option>
                                            <option value="website">وب سایت</option>

                                        </select>

                                        @error('platform')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                    </div>

                                    <div class="mb-3">

                                        <label class="form-label">لینک</label>

                                        <input wire:model.lazy="url"
                                               type="text"
                                               class="form-control @error('url') is-invalid @enderror"
                                               placeholder="https://...">

                                        @error('url')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror

                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        ذخیره
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <div wire:loading.remove wire:target="saveSocial">

                            <button type="submit" class="btn btn-primary">
                                ذخیره
                            </button>

                            <button class="btn btn-light"
                                    data-bs-dismiss="modal"
                                    type="button">
                                بستن
                            </button>

                        </div>

                        <div wire:loading
                             wire:target="saveSocial"
                             class="spinner-grow text-info"
                             role="status">

                        <span class="visually-hidden">
                            در حال بارگذاری...
                        </span>

                        </div>

                    </div>

                </form>

            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered text-center modal-lg" role="document">
            <div class="modal-content modal-content-demo">
                <form wire:submit="save()">
                    <div class="modal-header">
                        <h6 class="modal-title">{{$info['create']}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                            <div class="row">
                                <div class="col-xl-6">
                                    <label  for="input-rounded" class="form-label ">نام کاربر</label>
                                        <input wire:model.lazy="name" type="text" class="form-control @error('name') is-invalid @enderror" id="input-rounded" placeholder="لطفا نام کاربر را وارد کنید">
                                    @error('name')
                                        <div  class="invalid-feedback">
                                            {{$message}}
                                        </div>
                                    @enderror
                                </div>
                                <div class="col-xl-6">
                                    <label  for="input-rounded" class="form-label">شماره همراه کاربر</label>
                                    <input wire:model.lazy="mobile" type="text" class="form-control @error('mobile') is-invalid @enderror" id="input-rounded" placeholder="لطفا شماره همراه کاربر را وارد کنید">
                                    @error('mobile')
                                        <div  class="invalid-feedback">
                                            {{$message}}
                                        </div>
                                    @enderror
                                </div>
                                <div class="col-xl-6 mt-3">
                                    <label for="input-rounded" class="form-label">ایمیل کاربر</label>
                                    <input wire:model.lazy="email"  type="email" class="form-control @error('email') is-invalid @enderror" id="input-rounded" placeholder="لطفا ایمیل کاربر را وارد کنید">
                                    @error('email')
                                        <div  class="invalid-feedback">
                                            {{$message}}
                                        </div>
                                    @enderror
                                </div>
                                <div class="col-xl-6 mt-3">
                                    <label  for="input-rounded" class="form-label">رمز کاربر</label>
                                    <input wire:model.lazy="password" type="password" class="form-control @error('password') is-invalid @enderror" id="input-rounded" placeholder="لطفا رمز کاربر را وارد کنید">
                                    @error('password')
                                        <div  class="invalid-feedback">
                                            {{$message}}
                                        </div>
                                    @enderror
                                </div>
                                <div class="col-xl-6 mt-3">
                                    <label for="formFile" class="form-label">تصویر کاربر</label>
                                    <input wire:model.lazy="file" class="form-control @error('file') is-invalid @enderror" type="file" id="formFile">
                                    @error('file')
                                    <div  class="invalid-feedback">
                                        {{$message}}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-xl-6 mt-3">
                                    <label for="formFile" class="form-label">نقش کاربر</label>
                                    <select wire:model.lazy="role" class="form-control @error('role') is-invalid @enderror" data-trigger="" id="choices-single-default" name="choices-single-default">
                                        <option value="">
                                            نقش کاربر را انتخاب کنید
                                        </option>
                                        @foreach(\App\Models\Role::pluck('name','id') as $key => $role)
                                            <option value="{{$role}}">
                                                {{$role}}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role')
                                    <div  class="invalid-feedback">
                                        {{$message}}
                                    </div>
                                    @enderror

                                </div>

                            </div>
                    </div>
                    <div class="modal-footer">
                        <div wire:loading.remove wire:target="save">
                            <button class="btn btn-primary" wire:click="save">
                                ذخیره تغییرات
                            </button>
                            <button class="btn btn-light" data-bs-dismiss="modal" type="button">
                                بستن
                            </button>
                        </div>

                        <!-- اسپینر لودینگ Livewire -->
                        <div wire:loading wire:target="save" class="spinner-grow text-info" role="status">
                            <span class="visually-hidden">در حال بارگیری...</span>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered text-center modal-lg" role="document">
            <div class="modal-content modal-content-demo">
                    <div class="modal-header">
                        <h6 class="modal-title">{{$info['delete'] .' ' .$selectItem?->name}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">

                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <svg class="flex-shrink-0 me-2 svg-danger" xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="1.5rem" viewBox="0 0 24 24" width="1.5><0rem" fill="1.5rem" fill="1.5rem"00" height="24" width="24"/></g><g><g><g><path d="M15.73,3H8.27L3,8.27v7.46L8.27,21h7.46L21,15.73V8.27L15.73,3z M19,14.9L14.9,19H9.1L5,14.9V9.1L9.1,5h5.8L19,9.1V14.9z"/><rect height="6" width="2" x="11" y="7"/><rect height="2" width="2"><g="11">
                                            <div>
                                                از حذف کردن این ایتم مطمین هستید ؟!
                                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div wire:loading.remove wire:target="delete">
                            <button class="btn btn-primary" wire:click="delete()">
                                حذف
                            </button>
                            <button class="btn btn-light" data-bs-dismiss="modal">
                                بستن
                            </button>
                        </div>

                        <!-- اسپینر لودینگ Livewire -->
                        <div wire:loading wire:target="delete" class="spinner-grow text-info" role="status">
                            <span class="visually-hidden">در حال حذف...</span>
                        </div>
                    </div>

            </div>
        </div>
    </div>

</div>
