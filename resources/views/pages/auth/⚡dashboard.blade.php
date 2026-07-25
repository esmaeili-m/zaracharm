<?php

use Livewire\Component;
use App\Models\Ticket;
use App\Models\TicketMessage;


new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $selectedInvoice = null;
    public $showModal = false;
    protected $queryString = [
        'type' => ['except' => null],
    ];
    public $type=1;
    public $avatar;
    public $name;
    public $mobile;
    public $email;
    public $description;
    public $selectedTicket = null;
    public $message;
    public $title;
    public $attachment;
    public function mount()
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        $this->name = $user->name;
        $this->mobile = $user->mobile;
        $this->email = $user->email;
        $this->description = $user->description;
        $this->type=request()->input('type') ?? 1;
    }
    public function showInvoice($id)
    {
        $this->selectedInvoice = \App\Models\Invoice::with('items')->findOrFail($id);
        $this->showModal = true;
    }
    protected function rules()
    {
        return [
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:min_width=200,min_height=200,max_width=5000,max_height=5000',
            ],

            'name' => [
                'nullable',
                'string',
                'min:3',
                'max:100',
            ],

            'mobile' => [
                'required',
                'regex:/^09[0-9]{9}$/',
            ],

            'email' => [
                'nullable',
                'email:rfc,dns',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore(\Illuminate\Support\Facades\Auth::id()),
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
                'not_regex:/<[^>]*>/',
                'not_regex:/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            ],
        ];
    }

    protected function messages()
    {
        return [
            'avatar.image' => 'فایل انتخاب شده تصویر معتبر نیست.',
            'avatar.mimes' => 'فقط تصاویر JPG، PNG و WEBP مجاز هستند.',
            'avatar.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
            'avatar.dimensions' => 'ابعاد تصویر معتبر نیست.',

            'name.required' => 'نام و نام خانوادگی الزامی است.',
            'name.min' => 'نام و نام خانوادگی حداقل ۳ کاراکتر باشد.',
            'name.max' => 'نام و نام خانوادگی بیش از حد طولانی است.',

            'mobile.required' => 'شماره همراه الزامی است.',
            'mobile.regex' => 'شماره همراه معتبر نیست.',

            'email.email' => 'ایمیل وارد شده معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',

            'description.max' => 'توضیحات حداکثر ۱۰۰۰ کاراکتر مجاز است.',
            'description.not_regex' => 'استفاده از کد HTML یا اسکریپت مجاز نیست.',
        ];
    }
    public function change_type($type)
    {
        $this->type = $type;
        $this->selectedTicket=null;
    }
    public function createTicket()
    {
        $this->validate([

            'title' => [
                'required',
                'string',
                'min:5',
                'max:150',
            ],

            'description' => [
                'required',
                'string',
                'min:10',
                'max:5000',
                'not_regex:/<[^>]*>/',
            ],

            'attachment' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png',
                'max:2120',
            ],

        ],[

            'title.required' => 'عنوان تیکت الزامی است.',
            'title.min' => 'عنوان حداقل ۵ کاراکتر باشد.',
            'title.max' => 'عنوان بیش از حد طولانی است.',

            'description.required' => 'توضیحات الزامی است.',
            'description.min' => 'توضیحات حداقل ۱۰ کاراکتر باشد.',
            'description.max' => 'توضیحات بیش از حد طولانی است.',

            'attachment.mimes' => 'فرمت فایل مجاز نیست.',
            'attachment.max' => 'حجم فایل نباید بیشتر از 2 مگابایت باشد.',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () {

            $ticket = Ticket::create([
                'user_id' => auth()->id(),
                'ticket_number' => Ticket::generateNumber(),
                'title' => trim($this->title),
                'status' => 'open',
                'last_reply_at' => now(),
            ]);

            $message = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'message' => strip_tags($this->description),
                'is_admin' => false,
            ]);

            if ($this->attachment) {

                $this->upload(
                    $this->attachment,
                    $message,
                    'attachment'
                );
            }
        });

        $this->reset([
            'title',
            'description',
            'attachment',
        ]);

        session()->flash(
            'success',
            'تیکت شما با موفقیت ثبت شد.'
        );

        $this->dispatch('close-modal');
    }
    public function selectTicket($id)
    {
        $this->selectedTicket = Ticket::with('messages.user')->findOrFail($id);
    }
    public function sendMessage()
    {
        $this->validate([
            'message' => 'required|string|min:2',
            'attachment' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png',
                'max:2120',
            ],
        ]);

        $msg = $this->selectedTicket->messages()->create([
            'user_id' => auth()->id(),
            'message' => strip_tags($this->message),
            'is_admin' => false,
        ]);
        $this->selectedTicket->update([
            'last_reply_at' => now(),
        ]);
        if ($this->attachment) {
            $this->upload($this->attachment, $msg, 'attachment');
        }

        $this->message = '';
        $this->attachment = null;

        $this->selectedTicket->refresh();
    }
    public function save()
    {
        $data = $this->validate();

        $user = \Illuminate\Support\Facades\Auth::user();

        $user->update([
            'name' => trim($this->name),
            'mobile' => $this->mobile,
            'email' => $this->email,
            'description' => strip_tags($this->description),
        ]);

        if ($this->avatar) {

            $user->media()
                ->where('collection', 'avatar')
                ->delete();

            $this->upload(
                $this->avatar,
                $user,
                'avatar'
            );
        }
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'ویرایش موفق',
            text: 'اطلاعات با موفقیت بروزرسانی شد.'
        );

    }

    public function logout()
    {
        auth()->logout();
        return redirect()->route('home');

    }
};
?>
@push('styles')
    <style>
        .courses-two__img img{
            width: 100%;
            height: 200px;
            object-fit: cover;
        }


        .courses-two__title a{
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .courses-two__single{
            display: flex;
            flex-direction: column;
        }

        .courses-two__content{
            flex: 1;
            display: flex;
            flex-direction: column;
        }


    </style>
@endpush
<div>
    <section class="blog-details mt-5 mx-5">
            <div class="row">
                <div class="col-xl-3 col-lg-5">
                    <div class="sidebar">
                        <div class="sidebar__single sidebar__category">
                            <div class="sidebar__title-box">
                                <div class="sidebar__title-icon">
                                    <img src="assets/images/icon/sidebar-title-icon.png" alt="">
                                </div>
                                <h3 class="sidebar__title">{{auth()->user()->name ?? auth()->user()->mobile}} </h3>
                            </div>
                            <ul class="sidebar__category-list list-unstyled">
                                <li class="{{$type == 1 ? 'active' : ''}}">
                                    <a href="#" wire:click="change_type(1)">داشبورد<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                                <li class="{{$type == 2 ? 'active' : ''}}">
                                    <a href="#" wire:click="change_type(2)">دوره های من<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                                <li  class="{{$type == 3 ? 'active' : ''}}">
                                    <a href="#" wire:click="change_type(3)">کیف پول<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                                <li class="{{$type == 4 ? 'active' : ''}}">
                                    <a href="#" wire:click="change_type(4)">ویرایش حساب<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                                <li class="{{$type == 5 ? 'active' : ''}}">
                                    <a href="#" wire:click="change_type(5)">درخواست مشاوره<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                                <li class="{{$type == 6 ? 'active' : ''}}">
                                    <a href="#" wire:click="change_type(6)"> تیکت ها<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                                <li class="{{$type == 7 ? 'active' : ''}}">
                                    <a href="#" wire:click="change_type(7)"> فاکتور ها<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                                <li class="{{$type == 8 ? 'active' : ''}}">
                                    <a href="#" wire:click="change_type(8)"> علاقه مندی ها<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                                @can('settings.dashboard')
                                    <li class="{{ $type == 9 ? 'active' : '' }}">
                                        <a href="{{ route('dashboard') }}">
                                            داشبورد مدیریت
                                            <span class="fas fa-arrow-left"></span>
                                        </a>
                                    </li>
                                @endcan
                                <li class="{{$type == 8 ? 'active' : ''}}">
                                    <a href="#" wire:click="logout()"> خروج<span
                                            class="fas fa-arrow-left"></span></a>
                                </li>
                            </ul>
                        </div>

                    </div>
                </div>

                <div class="col-xl-9 col-lg-7">
                    @if($type == 1)
                        <div class="row">
                            <h2 class="mb-3">داشبورد</h2>
                            @if(!auth()->user()->name)
                                <div class="alert alert-primary" role="alert">
                                    سلام 👋
                                    <br>
                                    لطفاً چند دقیقه زمان بگذارید و اطلاعات حساب کاربری خود را به‌روزرسانی کنید تا بتوانید بدون مشکل از تمامی امکانات سایت استفاده کنید.
                                    <br>

                                    با تشکر از همراهی شما 🌹

                                </div>
                            @endif
                            @foreach(\App\Models\Announcement::where('is_active', true)->latest()->get() as $announcement)
                                <div class="alert alert-warning mb-3">
                                    <h6 class="mb-1">{{ $announcement->title }}</h6>
                                    <p class="mb-0">
                                        {{ $announcement->content }}
                                    </p>
                                </div>
                            @endforeach
                            <h5 class="mb-2 mt-2 text-muted">دوره های پیشنهادی</h5>

                            @foreach(\App\Models\Course::active()->inRandomOrder() ->with(['media', 'category'])
                                        ->withCount([
                                            'comments','lessons'
                                        ])
                                        ->withAvg('ratings', 'rating')->limit(6)->get() as $course)

                                <div class="col-xl-4 col-sm-12 mb-1">
                                    <div class="courses-two__single">
                                        <div class="courses-two__img-box">
                                            <div class="courses-two__img">
                                                <img src="{{ $course->featuredImageUrl ?? asset('media/images/resources/courses-2-1.jpg') }}" alt="{{ $course->title }}">
                                            </div>
                                            <div class="courses-two__heart">
                                                <a href="{{ route('courses.show', $course->slug) }}">
                                                    <span class="icon-heart"></span>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="courses-two__content">
                                            <div class="courses-two__doller-and-review">
                                                <div class="courses-two__doller">
                                                    <p style="color: #984695"> @if($course->is_free)
                                                            رایگان
                                                        @elseif($course->has_discount)
                                                            <del>{{ number_format($course->price) }}</del>
                                                            {{ number_format($course->final_price) }} تومان
                                                        @else
                                                            {{ number_format($course->price) }} تومان
                                                        @endif</p>
                                                </div>
                                                <div class="courses-two__review">
                                                    <p><i class="icon-star"></i> {{round($course->ratings_avg_rating,1) }} <span>({{$course->comments()->count()}} دیدگاه)</span></p>
                                                </div>
                                            </div>

                                            <h3 class="courses-two__title">
                                                <a href="{{ route('courses.show', $course->slug) }}">
                                                    {{ $course->title }}
                                                </a>
                                            </h3>



                                            <ul class="courses-two__meta list-unstyled">
                                                <li>
                                                    <div class="icon">
                                                        <span class="icon-chart-simple"></span>
                                                    </div>
                                                    <p>{{ $course->level_fa ?? 'مبتدی' }}</p>
                                                </li>

                                                <li>
                                                    <div class="icon">
                                                        <span class="icon-book"></span>
                                                    </div>
                                                    <p>{{ $course->lessons_count ?? 0 }} جلسه</p>
                                                </li>

                                                <li>
                                                    <div class="icon">
                                                        <span class="icon-clock"></span>
                                                    </div>
                                                    <p>{{ $course->duration ?? '-' }}</p>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                            @endforeach
                        </div>

                    @elseif($type == 2)
                        <div class="row">
                            @forelse(
                                auth()->user()->courses()
                                    ->active()
                                    ->inRandomOrder()
                                    ->with(['media', 'category'])
                                    ->withCount(['comments', 'lessons'])
                                    ->withAvg('ratings', 'rating')
                                    ->limit(6)
                                    ->get()
                                as $course
                            )

                                <div class="col-xl-4 col-sm-12 mb-1">
                                    <div class="courses-two__single">
                                        <div class="courses-two__img-box">
                                            <div class="courses-two__img">
                                                <img src="{{ $course->featuredImageUrl ?? asset('media/images/resources/courses-2-1.jpg') }}" alt="{{ $course->title }}">
                                            </div>
                                            <div class="courses-two__heart">
                                                <a href="{{ route('courses.show', $course->slug) }}">
                                                    <span class="icon-heart"></span>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="courses-two__content">
                                            <div class="courses-two__doller-and-review">
                                                <div class="courses-two__doller">
                                                    <p style="color: #984695"> @if($course->is_free)
                                                            رایگان
                                                        @elseif($course->has_discount)
                                                            <del>{{ number_format($course->price) }}</del>
                                                            {{ number_format($course->final_price) }} تومان
                                                        @else
                                                            {{ number_format($course->price) }} تومان
                                                        @endif</p>
                                                </div>
                                                <div class="courses-two__review">
                                                    <p><i class="icon-star"></i> {{round($course->ratings_avg_rating,1) }} <span>({{$course->comments()->count()}} دیدگاه)</span></p>
                                                </div>
                                            </div>

                                            <h3 class="courses-two__title">
                                                <a href="{{ route('courses.show', $course->slug) }}">
                                                    {{ $course->title }}
                                                </a>
                                            </h3>



                                            <ul class="courses-two__meta list-unstyled">
                                                <li>
                                                    <div class="icon">
                                                        <span class="icon-chart-simple"></span>
                                                    </div>
                                                    <p>{{ $course->level_fa ?? 'مبتدی' }}</p>
                                                </li>

                                                <li>
                                                    <div class="icon">
                                                        <span class="icon-book"></span>
                                                    </div>
                                                    <p>{{ $course->lessons_count ?? 0 }} جلسه</p>
                                                </li>

                                                <li>
                                                    <div class="icon">
                                                        <span class="icon-clock"></span>
                                                    </div>
                                                    <p>{{ $course->duration ?? '-' }}</p>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>


                            @empty

                                <div class="col-12">

                                    <div class="alert alert-primary text-center d-flex flex-column justify-content-center align-items-center"
                                         style="height:350px;">

                                        <h5 class="mb-2">
                                            هیچ دوره‌ای ندارید
                                        </h5>

                                        <p class="mb-0 text-muted">
                                            هنوز خریدی ثبت نشده است
                                        </p>

                                    </div>

                                </div>

                            @endforelse
                        </div>
                    @elseif($type == 3)
                        <section class="cart-page mt-2">
                            <div class="container">
                                <div class="row">
                                    <div class="col-12">
                                        <h3>کیف پول</h3>
                                    </div>
                                    <div class="col-12 my-3">
                                        <div class="d-flex flex-column flex-md-row gap-3 justify-content-between">                                            <p>موجودی کیف پول: <span class="text-primary fw-bold">{{ number_format(auth()->user()->wallet->balance ?? 0) }} تومان</span></p>
                                            <div class="cart-page__buttons">
                                                <a  data-bs-toggle="modal"
                                                   data-bs-target="#walletModal" class="thm-btn ">
                                                   <i class="fa fa-plus"></i> افزایش موجودی
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table cart-table">

                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>مبلغ</th>
                                            <th>نوع</th>
                                            <th>تاریخ</th>
                                            <th>توضیحات</th>
                                        </tr>
                                        </thead>

                                        <tbody>

                                        @forelse(auth()->user()->transactions()->latest()->get() as $index => $tx)

                                            <tr>
                                                <td>{{ $index + 1 }}</td>

                                                <td>
                                                    {{ number_format($tx->amount) }} تومان
                                                </td>

                                                <td>
                                                    @if($tx->type == 1)
                                                        <span class="badge bg-success">شارژ کیف پول</span>

                                                    @elseif($tx->type == 2)
                                                        <span class="badge bg-danger">خرید دوره</span>

                                                    @elseif($tx->type == 3)
                                                        <span class="badge bg-warning">برداشت</span>

                                                    @elseif($tx->type == 4)
                                                        <span class="badge bg-info">بازگشت وجه</span>

                                                    @else
                                                        <span class="badge bg-secondary">نامشخص</span>
                                                    @endif
                                                </td>

                                                <td>
                                                    {{ $tx->paid_at ? verta($tx->paid_at)->format('Y/m/d') : '-' }}
                                                </td>

                                                <td>
                                                    {{ $tx->description ?? '-' }}
                                                </td>
                                            </tr>

                                        @empty

                                            <tr>
                                                <td colspan="5" class="text-center">
                                                    هنوز هیچ تراکنشی ثبت نشده است
                                                </td>
                                            </tr>

                                        @endforelse

                                        </tbody>
                                    </table>
                                </div>



                            </div>
                        </section>
                    @elseif($type == 4)
                        <section class="cart-page mt-2">
                            <div class="container">
                                <div class="row">
                                    <div class="col-12">
                                        <h3>ویرایش حساب</h3>
                                    </div>
                                    <div class="col-12 my-3">
                                        <form wire:submit.prevent="save" id="profile-form">

                                            <div class="row">

                                                {{-- تصویر پروفایل --}}
                                                <div class="col-12 mb-4">

                                                    <label class="form-label d-block">
                                                        تصویر پروفایل
                                                    </label>

                                                    <div class="profile-upload">

                                                        <label for="avatar-upload" class="avatar-wrapper">

                                                            @if ($avatar)
                                                                <img
                                                                    src="{{ $avatar->temporaryUrl() }}"
                                                                    class="avatar-preview"
                                                                    alt="avatar"
                                                                >
                                                            @elseif(auth()->user()->avatar)
                                                                <img
                                                                    src="{{ (auth()->user()->avatarUrl) }}"
                                                                    class="avatar-preview"
                                                                    alt="avatar"
                                                                >
                                                            @else
                                                                <div class="avatar-placeholder">
                                                                    <i class="fas fa-camera"></i>
                                                                </div>
                                                            @endif

                                                        </label>

                                                        <input
                                                            type="file"
                                                            id="avatar-upload"
                                                            wire:model="avatar"
                                                            accept=".jpg,.jpeg,.png,.webp"
                                                            hidden
                                                        >

                                                        <div class="small text-muted mt-2">
                                                            فرمت‌های مجاز: JPG، PNG، WEBP
                                                            <br>
                                                            حداکثر حجم: 2 مگابایت
                                                        </div>

                                                        <div wire:loading wire:target="avatar" class="mt-2 text-primary">
                                                            در حال آپلود تصویر...
                                                        </div>

                                                        @error('avatar')
                                                        <div class="text-danger mt-2">
                                                            {{ $message }}
                                                        </div>
                                                        @enderror

                                                    </div>

                                                </div>

                                                {{-- نام --}}
                                                <div class="col-xl-6 col-sm-12 mb-3">

                                                    <label class="form-label">
                                                        نام و نام خانوادگی
                                                    </label>

                                                    <input
                                                        type="text"
                                                        wire:model.defer="name"
                                                        class="form-control"
                                                        placeholder="نام و نام خانوادگی"
                                                    >

                                                    @error('name')
                                                    <div class="text-danger mt-1">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror

                                                </div>

                                                {{-- موبایل --}}
                                                <div class="col-xl-6 col-sm-12 mb-3">

                                                    <label class="form-label">
                                                        شماره همراه
                                                    </label>

                                                    <input
                                                        type="text"
                                                        disabled
                                                        wire:model.defer="mobile"
                                                        class="form-control"
                                                        placeholder="09123456789"
                                                        dir="ltr"
                                                    >

                                                    @error('mobile')
                                                    <div class="text-danger mt-1">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror

                                                </div>

                                                {{-- ایمیل --}}
                                                <div class="col-xl-6 col-sm-12 mb-3">

                                                    <label class="form-label">
                                                        ایمیل
                                                    </label>

                                                    <input
                                                        type="email"
                                                        wire:model.defer="email"
                                                        class="form-control"
                                                        placeholder="example@gmail.com"
                                                        dir="ltr"
                                                    >

                                                    @error('email')
                                                    <div class="text-danger mt-1">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror

                                                </div>

                                                {{-- توضیحات --}}
                                                <div class="col-12 mb-3">

                                                    <label class="form-label">
                                                        توضیحات
                                                    </label>

                                                    <textarea
                                                        wire:model.defer="description"
                                                        class="form-control"
                                                        rows="5"
                                                        maxlength="1000"
                                                        placeholder="درباره خودتان بنویسید..."
                                                    ></textarea>

                                                    <small class="text-muted">
                                                        حداکثر 1000 کاراکتر
                                                    </small>

                                                    @error('description')
                                                    <div class="text-danger mt-1">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror

                                                </div>

                                                {{-- دکمه ذخیره --}}
                                                <div class="col-12">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-primary"
                                                        wire:loading.attr="disabled"
                                                    >

                <span wire:loading.remove wire:target="save">
                    ذخیره اطلاعات
                </span>

                                                        <span wire:loading wire:target="save">
                    در حال ذخیره...
                </span>

                                                    </button>

                                                </div>

                                            </div>

                                        </form>
                                    </div>
                                </div>




                            </div>
                        </section>


                    @elseif($type == 5)
                    @elseif($type == 6)
                        <section class="cart-page mt-2">
                            @if($selectedTicket)
                                <a href="#" wire:click="change_type(6)" class="thm-btn mb-3">لیست تیکت ها</a>

                                <div class="chat-wrapper">

                                    @if($selectedTicket)

                                        {{-- HEADER --}}
                                        <div class="chat-header">

                                            <div>
                                                <div class="chat-title">
                                                    {{ $selectedTicket->title }}
                                                </div>

                                                <div class="chat-subtitle">
                                                    {{ $selectedTicket->ticket_number }}
                                                </div>
                                            </div>

                                        </div>

                                        {{-- BODY --}}
                                        <div class="chat-body">

                                            @foreach($selectedTicket->messages as $msg)

                                                <div class="msg {{ $msg->user_id == auth()->id() ? 'me' : 'other' }}">

                                                    <div class="bubble">

                                                        <div class="meta">
                                                        <span class="badge {{ $msg->is_admin ? 'bg-success' : 'bg-primary text-white' }}">
                                                            {{ $msg->is_admin ? 'پشتیبان' : 'شما' }}
                                                        </span>

                                                            <span class="time">
                                                                {{ $msg->created_at->format('H:i') }}
                                                            </span>
                                                        </div>

                                                        <div class="text">
                                                            {{ $msg->message }}
                                                        </div>

                                                        @php
                                                            $file = $msg->media()->where('collection','attachment')->first();
                                                        @endphp

                                                        @if($file)
                                                            <a href="{{ asset('media/'.$file->file_path) }}" target="_blank" class="file">
                                                                📎 دانلود فایل ضمیمه
                                                            </a>
                                                        @endif

                                                    </div>

                                                </div>

                                            @endforeach

                                        </div>

                                        {{-- FOOTER --}}
                                        <div class="chat-footer">

                                            <form wire:submit.prevent="sendMessage" class="chat-form">

                                                <input
                                                    type="text"
                                                    wire:model.defer="message"
                                                    placeholder="پیام خود را بنویسید..."
                                                >

                                                <input
                                                    type="file"
                                                    wire:model="attachment"
                                                    class="file"
                                                >

                                                <button class="btn btn-primary" type="submit">
                                                    ارسال
                                                </button>

                                            </form>

                                        </div>

                                    @else

                                        <div class="empty-chat">
                                            یک تیکت را انتخاب کنید
                                        </div>

                                    @endif

                                </div>                                @else
                                <div class="container">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="d-flex flex-column flex-md-row gap-3 justify-content-between">

                                                <h3>تیکت های من</h3>
                                                <div class="cart-page__buttons">
                                                    <a  data-bs-toggle="modal"
                                                        data-bs-target="#createTicket" class="thm-btn ">
                                                        <i class="fa fa-plus"></i> تیکت جدید
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table cart-table">

                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>شماره تیکت</th>
                                                <th>عنوان</th>
                                                <th>وضعیت</th>
                                                <th>تاریخ ثبت</th>
                                                <th>عملیات</th>
                                            </tr>
                                            </thead>

                                            <tbody>

                                            @forelse(auth()->user()->tickets()->latest()->get() as $index => $ticket)

                                                <tr wire:key="ticket_{{$ticket->id}}">

                                                    <td>
                                                        {{ $index + 1 }}
                                                    </td>

                                                    <td>
                                                        <strong>
                                                            {{ $ticket->ticket_number }}
                                                        </strong>
                                                    </td>

                                                    <td>
                                                        {{ $ticket->title }}
                                                    </td>

                                                    <td>

                                                        @switch($ticket->status)

                                                            @case('open')
                                                                <span class="badge bg-warning">
                                                                در انتظار پاسخ
                                                            </span>
                                                                @break

                                                            @case('answered')
                                                                <span class="badge bg-success">
                                                                پاسخ داده شده
                                                            </span>
                                                                @break

                                                            @case('closed')
                                                                <span class="badge bg-danger">
                                                                بسته شده
                                                            </span>
                                                                @break

                                                            @default
                                                                <span class="badge bg-secondary">
                                                                نامشخص
                                                            </span>

                                                        @endswitch

                                                    </td>

                                                    <td>
                                                        {{ verta($ticket->created_at)->format('Y/m/d H:i') }}
                                                    </td>

                                                    <td>

                                                        <a
                                                            wire:click="selectTicket({{ $ticket->id }})"
                                                            class="btn btn-sm btn-primary"
                                                        >
                                                            مشاهده
                                                        </a>

                                                    </td>

                                                </tr>

                                            @empty

                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        هنوز هیچ تیکتی ثبت نشده است.
                                                    </td>
                                                </tr>

                                            @endforelse

                                            </tbody>

                                        </table>
                                    </div>
                                </div>
                            @endif
                        </section>

                    @elseif($type == 7)
                        <section class="cart-page mt-2">
                            <div class="container">
                                <div class="row">
                                    <div class="col-12">
                                        <h3>لیست فاکتور ها</h3>
                                    </div>
                                    <div class="col-12 my-3">
                                         <div class="table-responsive">

                            <table class="table table-striped table-hover align-middle mb-0">

                                <thead class="table-light">

                                <tr>
                                    <th>#</th>
                                    <th>شماره فاکتور</th>
                                    <th>مبلغ</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ</th>
                                    <th class="text-center">عملیات</th>
                                </tr>

                                </thead>

                                <tbody>

                                @forelse(auth()->user()->invoices()->get() as $invoice)

                                    <tr>

                                        <td>{{ $loop->iteration }}</td>

                                        <td>
                            <span class="fw-bold">
                                {{ $invoice->invoice_number }}
                            </span>
                                        </td>

                                        <td>
                                            {{ number_format($invoice->total_amount) }} تومان
                                        </td>

                                        <td>

                                            @switch($invoice->status)

                                                @case('paid')
                                                    <span class="badge text-white bg-success">
                                        پرداخت شده
                                    </span>
                                                    @break

                                                @case('pending')
                                                    <span class="badge bg-warning text-dark">
                                        در انتظار پرداخت
                                    </span>
                                                    @break

                                                @case('failed')
                                                    <span class="badge text-white bg-danger">
                                        ناموفق
                                    </span>
                                                    @break

                                                @case('refunded')
                                                    <span class="badge bg-info">
                                        برگشت وجه
                                    </span>
                                                    @break

                                                @default
                                                    <span class="badge bg-secondary">
                                        نامشخص
                                    </span>

                                            @endswitch

                                        </td>

                                        <td>
                                            {{ $invoice->created_at->format('Y/m/d') }}
                                        </td>

                                        <td class="text-center">

                                            <a wire:click.prevent="showInvoice({{ $invoice->id }})"
                                               class="btn btn-sm btn-outline-primary">

                                                مشاهده

                                            </a>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            هنوز هیچ فاکتوری ثبت نشده است
                                        </td>
                                    </tr>

                                @endforelse

                                </tbody>

                            </table>

                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @elseif($type == 8)
                        <div class="row">
                            @forelse(
                                auth()->user()->wishlistCourses()
                                    ->active()
                                    ->with(['media', 'category'])
                                    ->withCount(['comments', 'lessons'])
                                    ->withAvg('ratings', 'rating')
                                    ->limit(6)
                                    ->get()
                                as $course
                            )

                                <div class="col-xl-4">
                                    <!-- کارت دوره -->
                                </div>

                            @empty
                                <div class="col-12">

                                    <div class="alert alert-primary text-center d-flex flex-column justify-content-center align-items-center"
                                         style="height:350px;">

                                        <h5 class="mb-2">
                                            هنوز علاقه‌مندی‌ای ثبت نشده
                                        </h5>

                                        <p class="mb-0 text-muted">
                                            دوره‌های مورد علاقه‌تان را با کلیک روی ❤️ اینجا ذخیره کنید.
                                        </p>

                                    </div>

                                </div>


                            @endforelse
                        </div>


                    @endif
                </div>
            </div>
    </section>
    <div wire:ignore.self class="modal fade" id="walletModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">افزایش کیف پول</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form method="POST" action="">
                    @csrf

                    <div class="modal-body">

                        <label class="form-label">مبلغ (تومان)</label>

                        <input
                            type="number"
                            name="amount"
                            class="form-control"
                            placeholder="مثلاً 100000"
                            min="10000"
                            required
                        >

                        <small class="text-muted">
                            حداقل مبلغ ۱۰,۰۰۰ تومان است
                        </small>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            بستن
                        </button>
                        <button style="border-radius: 10px" type="submit" class="btn btn-primary rounded-3 p-2">
                             انتقال به درگاه پرداخت
                        </button>

                    </div>

                </form>

            </div>

        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="createTicket" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        ثبت تیکت جدید
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <form wire:submit.prevent="createTicket">

                    <div class="modal-body">

                        {{-- عنوان --}}
                        <div class="mb-3">

                            <label class="form-label">
                                عنوان تیکت <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                wire:model.defer="title"
                                class="form-control"
                                placeholder="موضوع تیکت را وارد کنید"
                            >

                            @error('title')
                            <div class="text-danger mt-1">
                                {{ $message }}
                            </div>
                            @enderror

                        </div>

                        {{-- توضیحات --}}
                        <div class="mb-3">

                            <label class="form-label">
                                توضیحات <span class="text-danger">*</span>
                            </label>

                            <textarea
                                wire:model.defer="description"
                                class="form-control"
                                rows="6"
                                placeholder="مشکل یا درخواست خود را کامل توضیح دهید"
                            ></textarea>

                            @error('description')
                            <div class="text-danger mt-1">
                                {{ $message }}
                            </div>
                            @enderror

                        </div>

                        {{-- فایل --}}
                        <div class="mb-3">

                            <label class="form-label">
                                فایل ضمیمه
                            </label>

                            <input
                                type="file"
                                wire:model="attachment"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.pdf,.zip,.rar"
                            >

                            <small class="text-muted">
                                فرمت‌های مجاز:
                                JPG ،PNG ،PDF ،ZIP ،RAR
                                <br>
                                حداکثر حجم: ۵ مگابایت
                            </small>

                            <div
                                wire:loading
                                wire:target="attachment"
                                class="text-primary mt-2"
                            >
                                در حال آپلود فایل...
                            </div>

                            @error('attachment')
                            <div class="text-danger mt-1">
                                {{ $message }}
                            </div>
                            @enderror

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            انصراف
                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary rounded-3 px-4"
                            wire:loading.attr="disabled"
                        >
                <span wire:loading.remove>
                    ثبت تیکت
                </span>

                            <span wire:loading>
                    در حال ثبت...
                </span>
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </div>
    <div class="modal fade @if($showModal) show d-block @endif" tabindex="-1" style="background: rgba(0,0,0,0.5);">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        جزئیات فاکتور
                    </h5>

                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>

                <div class="modal-body">

                    @if($selectedInvoice)

                        <p><strong>شماره:</strong> {{ $selectedInvoice->invoice_number }}</p>
                        <p><strong>مبلغ:</strong> {{ number_format($selectedInvoice->total_amount) }} تومان</p>
                        <p><strong>تاریخ:</strong> {{ verta($selectedInvoice->created_at)->format('Y/m/d') }}</p>

                        <hr>

                        <h6 class="mb-2">آیتم‌ها</h6>

                        <ul class="list-group">

                            @foreach($selectedInvoice->items as $item)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>{{ $item->title }}</span>
                                    <span>{{ number_format($item->total) }} تومان</span>
                                </li>
                            @endforeach

                        </ul>

                    @endif

                </div>

            </div>

        </div>

    </div>
    @push('styles')
        <style>
            /* ===== Ticket Chat Styles ===== */

            .chat-wrapper {
                display: flex;
                flex-direction: column;
                height: 600px;
                background: #fff;
                border-radius: 12px;
                border: 0.5px solid #e5e7eb;
                overflow: hidden;
            }

            /* Header */
            .chat-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 14px 18px;
                border-bottom: 0.5px solid #e5e7eb;
                background: #f9fafb;
            }
            .chat-title { font-size: 15px; font-weight: 500; color: #111; }
            .chat-subtitle { font-size: 12px; color: #6b7280; margin-top: 2px; }

            /* Body */
            .chat-body {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 10px;
                scroll-behavior: smooth;
            }
            .chat-body::-webkit-scrollbar { width: 4px; }
            .chat-body::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

            /* Messages */
            .msg { display: flex; flex-direction: column; max-width: 72%; }
            .msg.me  { align-self: flex-end;   align-items: flex-end; }
            .msg.other { align-self: flex-start; align-items: flex-start; }

            .bubble {
                padding: 10px 14px;
                border-radius: 16px;
                font-size: 13.5px;
                line-height: 1.6;
                color: #111;
            }
            .msg.me    .bubble { background: #E8F4FF; border: 0.5px solid #B5D4F4; border-bottom-right-radius: 4px; }
            .msg.other .bubble { background: #f3f4f6; border: 0.5px solid #e5e7eb; border-bottom-left-radius: 4px; }

            /* Meta (badge + time) */
            .meta { display: flex; align-items: center; gap: 6px; margin-bottom: 5px; }
            .meta .badge { font-size: 11px; padding: 2px 8px; border-radius: 20px; }
            .badge.bg-success { background: #EAF3DE; color: #3B6D11; border: 0.5px solid #C0DD97; }
            .badge.bg-primary { background: #E6F1FB; color: #185FA5; border: 0.5px solid #B5D4F4; }
            .time { font-size: 11px; color: #9ca3af; }

            /* File attachment */
            .file {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                margin-top: 6px;
                font-size: 12px;
                color: #185FA5;
                text-decoration: none;
                padding: 4px 10px;
                background: #E6F1FB;
                border-radius: 8px;
                border: 0.5px solid #B5D4F4;
            }
            .file:hover { background: #B5D4F4; }

            /* Footer */
            .chat-footer {
                padding: 12px 14px;
                border-top: 0.5px solid #e5e7eb;
                background: #f9fafb;
            }
            .chat-form { display: flex; align-items: center; gap: 8px; }

            .chat-form input[type="text"] {
                flex: 1;
                padding: 9px 12px;
                border-radius: 8px;
                border: 0.5px solid #d1d5db;
                background: #fff;
                font-size: 13px;
                font-family: Tahoma, Arial, sans-serif;
                outline: none;
            }
            .chat-form input[type="text"]:focus {
                border-color: #378ADD;
                box-shadow: 0 0 0 3px rgba(55,138,221,0.12);
            }

            .chat-form input[type="file"] {
                font-size: 12px;
                color: #6b7280;
                max-width: 140px;
            }

            .chat-form button[type="submit"] {
                padding: 0 18px;
                height: 36px;
                background: #378ADD;
                color: #fff;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-size: 13px;
                font-family: Tahoma, Arial, sans-serif;
                font-weight: 500;
                flex-shrink: 0;
            }
            .chat-form button[type="submit"]:hover  { background: #185FA5; }
            .chat-form button[type="submit"]:active { transform: scale(0.97); }

            /* Empty state */
            .empty-chat {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100%;
                color: #9ca3af;
                font-size: 14px;
            }
            #profile-form input,textarea {
                background-color: #f3f3f3;
            }
            .avatar-wrapper{
                width:140px;
                height:140px;
                display:flex;
                justify-content:center;
                align-items:center;
                cursor:pointer;
                border-radius:50%;
                overflow:hidden;
                border:3px solid #e5e7eb;
                margin:auto;
                transition:.3s;
            }

            .avatar-wrapper:hover{
                transform:scale(1.03);
            }

            .avatar-preview{
                width:100%;
                height:100%;
                object-fit:cover;
            }

            .avatar-placeholder{
                width:100%;
                height:100%;
                display:flex;
                align-items:center;
                justify-content:center;
                background:#f5f5f5;
                font-size:40px;
            }

            .profile-upload{
                text-align:center;
            }

        </style>
    @endpush
    <script>
        document.addEventListener('livewire:load', () => {
            const body = document.querySelector('.chat-body');
            if (body) body.scrollTop = body.scrollHeight;
            Livewire.hook('message.processed', () => {
                if (body) body.scrollTop = body.scrollHeight;
            });
        });

    </script>
    <script>
        document.addEventListener('livewire:initialized', () => {

            Livewire.on('close-modal', () => {

                document.querySelectorAll('.modal.show').forEach((modalEl) => {

                    const modal = bootstrap.Modal.getInstance(modalEl);

                    if (modal) modal.hide();

                });

            });

        });
    </script>

</div>
