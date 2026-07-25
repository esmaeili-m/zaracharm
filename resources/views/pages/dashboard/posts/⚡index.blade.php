<?php

use Livewire\Component;
use \App\Models\User;
use \App\Services\FileUploadService;
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
    public $role_id;
    public $search;
    public User $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(User $model)
    {
        $this->model=$model;
        $this->info['header']='لیست پست';
        $this->info['create']='افزودن پست';
        $this->info['delete']='حذف پست';
        $this->info['personal']='پست';
        $this->info['table']['headers']=[
            '#',
            'تصویر',
            'نام',
            'ایمیل',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();
    }

    public function change_status($id)
    {
        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->loadData();
    }
    public function delete()
    {
        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
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
    public function updatedFile()
    {
        $this->image = $this->upload(
            $this->file,
            $this->image_path($this->model),
            $this->selectItem?->image,
        );
    }

    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->name=$this->selectItem->name;
        $this->email=$this->selectItem->email;
        $this->mobile=$this->selectItem->mobile;
        $this->image=$this->selectItem->image;
        $this->role_id=$this->selectItem->role_id;
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['selectItem','model','info','data']);
            $this->js("const modalElement = document.getElementById('create');
                    const modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    const deleteModalElement = document.getElementById('delete');
                    const deleteModalInstance = bootstrap.Modal.getInstance(deleteModalElement);
                    if (deleteModalInstance) {
                        deleteModalInstance.hide();
                    }");
        }
    }
    public function image_path()
    {
        $classNameWithNamespace = get_class($this->model);
        $simpleClassName = basename($classNameWithNamespace);
        $lowercaseClassName = strtolower($simpleClassName);
        return trim('uploads/' . $lowercaseClassName, '/');

    }
    public function rules()
    {
        $userId = $this->selectItem?->id;
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => [
                'nullable',
                'string',
                'regex:/^(\+98|0)?9\d{9}$/',
                \Illuminate\Validation\Rule::unique('users', 'mobile')->ignore($userId),
            ],
            'image' => ['nullable'],
            'role_id' => ['required', 'exists:roles,id', 'integer'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            ],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'نام الزامی است.',
            'mobile.regex' => 'فرمت شماره موبایل معتبر نیست. (مثال: 09123456789)',
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'role_id.required' => 'نقش کاربر الزامی است.',
            'role_id.exists' => 'نقش انتخاب شده نامعتبر است.',
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل نامعتبر است.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'password.required' => 'رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
            'password.regex' => 'رمز عبور باید شامل حداقل یک حرف بزرگ، یک حرف کوچک، یک عدد و یک علامت خاص باشد.',
            'password.same' => 'رمز عبور و تأیید آن مطابقت ندارند.',
            'password_confirmation.required' => 'تأیید رمز عبور الزامی است.',
            'password_confirmation.same' => 'تأیید رمز عبور و رمز عبور مطابقت ندارند.',
        ];
    }

    public function save(){
        $data= $this->validate();
        if ($data['password']){
            $data['password']=\Illuminate\Support\Facades\Hash::make($data['password']);
        }else{
            unset($data['password']);
        }

        if ($data['image']){
            $data['image']=$this->image;
        }
        if ($this->selectItem){

            $this->selectItem->update($data);
            $this->loadData();
            $this->resetData('close');

        }else{

            $this->model->create($data);
            $this->loadData();
            $this->resetData('close');
        }
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
            <a href="{{route('users.trash')}}" class="btn btn-warning-light btn-wave me-2">
                <i class="bx bx-trash align-middle">
                </i>
                سطل آشغال
            </a>
            <button wire:click="resetData()" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave me-0">
                <i class="ri-add-line align-middle">
                </i>
                {{$info['create']}}
            </button>
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
                                        {{$item->name}}
                                    </td>
                                    <td>
                                        {{$item->email}}
                                    </td>
                                    <td>
                                     <span style="cursor: pointer" wire:click="change_status({{$item->id}})"
                                           wire:loading.attr="disabled"
                                           class="badge bg-outline-{{$item->status == 1 ? 'success' : 'danger'}}">
                                            <span wire:target="change_status" wire:loading.remove>{{$item->status == 1 ? 'فعال' : 'غیرفعال'}}</span>
                                            <span wire:target="change_status" wire:loading>در حال تغییر...</span>
                                     </span>
                                    </td>
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-edit-line"></i></a>
                                            <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                    class="ri-delete-bin-5-line"></i></a>
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
                                <select wire:model.lazy="role_id" class="form-control @error('role_id') is-invalid @enderror" data-trigger="" id="choices-single-default" name="choices-single-default">
                                    <option value="">
                                        نقش کاربر را انتخاب کنید
                                    </option>
                                    @foreach(\App\Models\Role::where('status',1)->pluck('name','id') as $key => $role)
                                        <option value="{{$key}}">
                                            {{$role}}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role_id')
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
    @push('scripts')
        <script>
            window.addEventListener('livewire:initialized', () => {
                alert('a')
                Livewire.on('close-modal', () => {
                    const modalElement = document.getElementById('create');
                    const modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    const deleteModalElement = document.getElementById('delete');
                    const deleteModalInstance = bootstrap.Modal.getInstance(deleteModalElement);
                    if (deleteModalInstance) {
                        deleteModalInstance.hide();
                    }
                });

            });
        </script>

    @endpush
</div>
