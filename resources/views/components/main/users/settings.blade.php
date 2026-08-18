<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

new class extends Component
{

    use WithFileUploads;
    use \App\Traits\FileUploadTrait;

    public string $first_name = '';
    public string $last_name = '';
    public string $username = '';
    public string $mobile = '';
    public string $email = '';
    public string $national_code = '';
    public string $birth_date = '';
    public string $gender = '';
    public bool $notificationsEnabled = true;
    public bool $order_updates = true;
    public bool $payment_updates = true;
    public bool $shipping_updates = true;
    public bool $wallet_updates = true;
    public bool $promotions = true;
    public bool $newsletter = false;
    public bool $security_alerts = true;
    public $avatar;

    public ?string $avatarPreview = null;

    public string $current_password = '';

    public string $new_password = '';
    public $user;

    public string $new_password_confirmation = '';
    public function getHasPasswordProperty(): bool
    {
        return filled(auth()->user()->password);
    }


    public function updatePassword()
    {
        $user = Auth::user();

        /*
         |--------------------------------------------------------------------------
         | کاربر قبلاً رمز داشته
         |--------------------------------------------------------------------------
         */
        if ($user->password) {

            $this->validate([
                'current_password' => [
                    'required',
                    'string',
                ],
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'different:current_password',
                ],
            ], [
                'current_password.required' => 'رمز عبور فعلی را وارد کنید.',

                'new_password.required' => 'رمز عبور جدید را وارد کنید.',
                'new_password.min' => 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.',
                'new_password.confirmed' => 'تکرار رمز عبور با رمز جدید مطابقت ندارد.',
                'new_password.different' => 'رمز عبور جدید باید با رمز قبلی متفاوت باشد.',
            ]);


            if (!Hash::check($this->current_password, $user->password)) {

                $this->addError(
                    'current_password',
                    'رمز عبور فعلی صحیح نیست.'
                );

                return;
            }
        }

        /*
         |--------------------------------------------------------------------------
         | کاربر رمز ندارد یا رمز جدید می‌خواهد
         |--------------------------------------------------------------------------
         */

        if (!$user->password) {

            $this->validate([
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ], [
                'new_password.required' => 'رمز عبور را وارد کنید.',
                'new_password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
                'new_password.confirmed' => 'تکرار رمز عبور با رمز اصلی مطابقت ندارد.',
            ]);
        }


        /*
         |--------------------------------------------------------------------------
         | ذخیره رمز
         |--------------------------------------------------------------------------
         */
        $isCreatingPassword = !$user->password;

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);


        /*
         |--------------------------------------------------------------------------
         | پاک کردن فیلدها
         |--------------------------------------------------------------------------
         */

        $this->reset([
            'current_password',
            'new_password',
            'new_password_confirmation',
        ]);


        session()->flash(
            'password_success',
            $isCreatingPassword
                ? 'رمز عبور با موفقیت ایجاد شد.'
                : 'رمز عبور با موفقیت بروزرسانی شد.'
        );
    }
    public function getProfileCompletionProperty(): int
    {
        $user = $this->user;

        $fields = [
            'first_name',
            'last_name',
            'mobile',
            'national_code',
            'email',
            'birth_date',
            'gender',
        ];

        $completed = collect($fields)
            ->filter(fn ($field) => filled($user->{$field}))
            ->count();

        return (int) round(($completed / count($fields)) * 100);
    }
    public function mount($user): void
    {
        $this->user = $user;

        $this->first_name = $user->first_name ?? '';
        $this->last_name = $user->last_name ?? '';
        $this->username = $user->username ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->email = $user->email ?? '';
        $this->national_code = $user->national_code ?? '';
        $this->birth_date = str_replace('-','/',($user->birth_date ?? ''));
        $this->gender = $user->gender ?? '';

        $this->avatarPreview = $user->avatarUrl;
        $preferences = auth()->user()
            ->notificationPreference()
            ->firstOrCreate([], [
                'order_updates' => true,
                'payment_updates' => true,
                'shipping_updates' => true,
                'wallet_updates' => true,
                'promotions' => false,
                'newsletter' => false,
                'security_alerts' => true,
            ]);

        $this->order_updates = $preferences->order_updates;
        $this->payment_updates = $preferences->payment_updates;
        $this->shipping_updates = $preferences->shipping_updates;
        $this->wallet_updates = $preferences->wallet_updates;
        $this->promotions = $preferences->promotions;
        $this->newsletter = $preferences->newsletter;

        // امنیتی همیشه فعال
        $this->security_alerts = true;
        session()->flash(
            'notification_success',
            'تنظیمات اعلان با موفقیت بروزرسانی شد.'
        );
    }

    public function saveNotificationPreferences(): void
    {
        $this->security_alerts = true;

        $this->user->notificationPreferences()->updateOrCreate(
            [
                'user_id' => $this->user->id,
            ],
            [
                'order_updates'   => $this->notificationsEnabled ? $this->order_updates : false,
                'payment_updates' => $this->notificationsEnabled ? $this->payment_updates : false,
                'shipping_updates'=> $this->notificationsEnabled ? $this->shipping_updates : false,
                'wallet_updates'  => true, // در صورت نیاز می‌توانی اجباری نگه داری
                'promotions'      => $this->notificationsEnabled ? $this->promotions : false,
                'newsletter'      => $this->notificationsEnabled ? $this->newsletter : false,
                'security_alerts' => true,
            ]
        );

        session()->flash(
            'notification_success',
            'تنظیمات اعلان‌ها با موفقیت ذخیره شد.'
        );
    }
    public function resetNotificationPreferences()
    {
        $this->order_updates = true;
        $this->payment_updates = true;
        $this->shipping_updates = true;
        $this->wallet_updates = true;
        $this->promotions = false;
        $this->newsletter = false;
        $this->security_alerts = true;

        $this->saveNotificationPreferences();
    }
    protected function rules(): array
    {
        return [

            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                'unique:users,username,' . Auth::id(),
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email,' . Auth::id(),
            ],

            'national_code' => [
                'nullable',
                'digits:10',
                'unique:users,national_code,' . Auth::id(),
            ],

            'birth_date' => [
                'nullable',
                'regex:/^13\d{2}\/(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])$/',
            ],

            'gender' => [
                'nullable',
                'in:male,female',
            ],

            'avatar' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ];
    }


    protected function messages(): array
    {
        return [

            'first_name.required' =>
                'لطفاً نام خود را وارد کنید.',

            'first_name.min' =>
                'نام باید حداقل ۲ کاراکتر باشد.',

            'first_name.max' =>
                'نام نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',

            'last_name.required' =>
                'لطفاً نام خانوادگی خود را وارد کنید.',

            'last_name.min' =>
                'نام خانوادگی باید حداقل ۲ کاراکتر باشد.',

            'last_name.max' =>
                'نام خانوادگی نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',


            'username.min' =>
                'نام کاربری باید حداقل ۳ کاراکتر باشد.',

            'username.max' =>
                'نام کاربری نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',

            'username.alpha_dash' =>
                'نام کاربری فقط می‌تواند شامل حروف، اعداد، خط تیره و زیرخط باشد.',

            'username.unique' =>
                'این نام کاربری قبلاً ثبت شده است.',


            'email.email' =>
                'فرمت ایمیل وارد شده صحیح نیست.',

            'email.unique' =>
                'این ایمیل قبلاً ثبت شده است.',


            'national_code.digits' =>
                'کد ملی باید دقیقاً ۱۰ رقم باشد.',

            'national_code.unique' =>
                'این کد ملی قبلاً ثبت شده است.',


            'birth_date.date' =>
                'تاریخ تولد معتبر نیست.',

            'birth_date.before' =>
                'تاریخ تولد باید قبل از امروز باشد.',

            'birth_date.regex' =>
                'تاریخ تولد باید به صورت ۱۳۸۰/۰۱/۰۱ وارد شود.',

            'gender.in' =>
                'جنسیت انتخاب شده معتبر نیست.',


            'avatar.image' =>
                'فایل انتخاب شده باید تصویر باشد.',

            'avatar.mimes' =>
                'فرمت تصویر باید JPG، PNG یا WEBP باشد.',

            'avatar.max' =>
                'حجم تصویر نمی‌تواند بیشتر از ۲ مگابایت باشد.',
        ];
    }


    public function updatedAvatar(): void
    {
        $this->validateOnly('avatar');

        $this->avatarPreview = $this->avatar->temporaryUrl();
    }


    public function saveProfile(): void
    {
        $validated = $this->validate();

        $user = Auth::user();

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->username = $validated['username'] ?: null;
        $user->email = $validated['email'] ?: null;
        $user->national_code = $validated['national_code'] ?: null;
        $user->birth_date = $validated['birth_date'] ?: null;
        $user->gender = $validated['gender'] ?: null;

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


        $user->save();

        $this->resetValidation();

        $this->dispatch('profile-saved');
    }


};
?>

<div>
    <main class="flex-1 space-y-6 relative z-10">

        <div class="space-y-8" x-data="{ activeTab: 'profile' }" dir="rtl">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-6 shadow-sm">
                        <nav class="space-y-2">

                            {{-- اطلاعات کاربری --}}
                            <a
                                href="#"
                                @click.prevent="activeTab = 'profile'"
                                :class="activeTab === 'profile'
            ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20'
            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5'"
                                class="flex items-center gap-4 px-4 py-4 rounded-2xl transition-all"
                            >

                                <svg
                                    class="w-5 h-5 shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>

                                <span class="text-[13px] font-black">
            اطلاعات کاربری
        </span>

                            </a>


                            {{-- امنیت و رمز عبور --}}
                            <a
                                href="#"
                                @click.prevent="activeTab = 'security'"
                                :class="activeTab === 'security'
            ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20'
            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5'"
                                class="flex items-center gap-4 px-4 py-4 rounded-2xl transition-all"
                            >

                                <svg
                                    class="w-5 h-5 shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6
                   a2 2 0 00-2-2H6a2 2 0 00-2 2v6
                   a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                    />
                                </svg>

                                <span class="text-[13px] font-black">
            امنیت و رمز عبور
        </span>

                            </a>


                            {{-- اطلاع‌رسانی‌ها --}}
                            <a
                                href="#"
                                @click.prevent="activeTab = 'notifications'"
                                :class="activeTab === 'notifications'
            ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20'
            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5'"
                                class="flex items-center gap-4 px-4 py-4 rounded-2xl transition-all"
                            >

                                <svg
                                    class="w-5 h-5 shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11
                   a6.002 6.002 0 00-4-5.659V5
                   a2 2 0 10-4 0v.341
                   C7.67 6.165 6 8.388 6 11v3.159
                   c0 .538-.214 1.055-.595 1.436L4 17h5
                   m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                    />
                                </svg>

                                <span class="text-[13px] font-black">
            اطلاع‌رسانی‌ها
        </span>

                            </a>

                        </nav>
                    </div>

                    <div class="bg-primary-500/5 border border-primary-500/10 rounded-[2rem] p-6">
                        <div class="flex justify-between items-center mb-4">
        <span class="text-[11px] font-black text-gray-500">
            تکمیل پروفایل
        </span>

                            <span class="text-[11px] font-black text-primary-500">
            {{ $this->profileCompletion }}٪
        </span>
                        </div>

                        <div class="h-2 bg-gray-100 dark:bg-white/5 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-primary-500 rounded-full transition-all duration-700"
                                style="width: {{ $this->profileCompletion }}%"
                            ></div>
                        </div>
                    </div>
                </div>

                <div  x-show="activeTab === 'profile'"
                      x-cloak class="lg:col-span-2 space-y-8">
                    <div
                        x-data="{ show: false }"
                        x-on:profile-saved.window="
        show = true;
        setTimeout(() => show = false, 3000)
    "
                        x-show="show"
                        x-transition
                        class="mb-6
           px-5 py-4
           rounded-2xl
           bg-emerald-500/10
           border border-emerald-500/20
           text-emerald-600
           dark:text-emerald-400
           text-xs
           font-black"
                    >
                        تغییرات پروفایل با موفقیت ذخیره شد.
                    </div>
                    <div
                        id="profile-tab"
                        class="tab-pane block space-y-8 animate-fadeIn"
                        dir="rtl"
                    >

                        {{-- Profile Card --}}
                        <div
                            class="relative overflow-hidden
               bg-white/40 dark:bg-gray-950/60
               backdrop-blur-md
               border border-white/60 dark:border-white/10
               rounded-[2.5rem]
               p-8
               shadow-[0_20px_50px_rgba(0,0,0,0.05)]
               dark:shadow-none"
                        >

                            {{-- Glow --}}
                            <div
                                class="absolute
                   -top-24
                   -right-24
                   w-48
                   h-48
                   bg-primary-500/10
                   rounded-full
                   blur-3xl"
                            ></div>


                            <div class="relative">

                                {{-- Header --}}
                                <div
                                    class="flex items-center gap-4
                       mb-10
                       pb-6
                       border-b
                       border-gray-200/30
                       dark:border-white/5"
                                >

                                    <div
                                        class="w-2 h-8
                           bg-primary-500
                           rounded-full
                           shadow-[0_0_15px_rgba(59,130,246,0.5)]"
                                    ></div>

                                    <h3
                                        class="text-lg
                           font-black
                           text-gray-900
                           dark:text-white"
                                    >
                                        تنظیمات پروفایل
                                    </h3>

                                </div>


                                {{-- Form --}}
                                <form
                                    wire:submit="saveProfile"
                                    class="grid grid-cols-1 md:grid-cols-2 gap-8"
                                >

                                    {{-- Avatar --}}
                                    <div class="md:col-span-2 flex items-center gap-6 mb-4">

                                        <div
                                            class="relative group cursor-pointer"
                                            onclick="document.getElementById('profile-avatar').click()"
                                        >

                                            <div
                                                class="w-24 h-24
                                   rounded-[2rem]
                                   bg-gradient-to-tr
                                   from-primary-500/20
                                   to-primary-500/5
                                   p-1
                                   backdrop-blur-md
                                   border border-white/50
                                   dark:border-white/10
                                   shadow-lg
                                   transition-transform
                                   group-hover:scale-105"
                                            >

                                                @if($avatar)
                                                    <img
                                                        src="{{ $avatar->temporaryUrl() }}"
                                                        class="w-full h-full rounded-[1.8rem] object-cover"
                                                        alt="تصویر کاربر"
                                                    >
                                                @else
                                                    <img
                                                        src="{{ $avatarPreview }}"
                                                        class="w-full h-full rounded-[1.8rem] object-cover"
                                                        alt="تصویر کاربر"
                                                    >
                                                @endif

                                            </div>


                                            {{-- Camera --}}
                                            <div
                                                class="absolute
                                   -bottom-1
                                   -right-1
                                   w-8
                                   h-8
                                   rounded-xl
                                   bg-primary-500
                                   text-white
                                   flex
                                   items-center
                                   justify-center
                                   shadow-lg
                                   border-4
                                   border-white
                                   dark:border-gray-900
                                   group-hover:rotate-12
                                   transition-all"
                                            >

                                                <svg
                                                    class="w-4 h-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0118.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                                        stroke-width="2.5"
                                                    />
                                                </svg>

                                            </div>

                                        </div>


                                        <div>

                                            <h4
                                                class="text-sm
                                   font-black
                                   text-gray-800
                                   dark:text-white"
                                            >
                                                تصویر کاربری شما
                                            </h4>

                                            <p
                                                class="text-[10px]
                                   font-bold
                                   text-gray-400
                                   mt-1"
                                            >
                                                JPG, PNG, WEBP — حداکثر ۲ مگابایت
                                            </p>

                                            @error('avatar')
                                            <span
                                                class="block
                                       text-[10px]
                                       font-bold
                                       text-red-500
                                       mt-2"
                                            >
                                {{ $message }}
                            </span>
                                            @enderror

                                        </div>


                                        <input
                                            id="profile-avatar"
                                            type="file"
                                            wire:model="avatar"
                                            class="hidden"
                                            accept="image/jpeg,image/png,image/webp"
                                        >

                                    </div>


                                    {{-- First Name --}}
                                    <div class="space-y-3">

                                        <label
                                            class="text-[11px]
                               font-black
                               text-gray-500
                               dark:text-gray-400
                               mr-2"
                                        >
                                            نام
                                        </label>

                                        <input
                                            type="text"
                                            wire:model="first_name"
                                            class="w-full
                               bg-white/50
                               dark:bg-white/[0.03]
                               border
                               border-gray-100
                               dark:border-white/10
                               rounded-2xl
                               px-6
                               py-4
                               text-xs
                               font-black
                               text-gray-800
                               dark:text-white
                               outline-none
                               focus:border-primary-500
                               focus:ring-4
                               focus:ring-primary-500/5
                               transition-all
                               @error('first_name')
                                   border-red-500
                               @enderror"
                                        >

                                        @error('first_name')
                                        <p class="text-[10px] font-bold text-red-500 mr-2">
                                            {{ $message }}
                                        </p>
                                        @enderror

                                    </div>

                                    {{-- Last Name --}}
                                    <div class="space-y-3">

                                        <label
                                            class="text-[11px]
                               font-black
                               text-gray-500
                               dark:text-gray-400
                               mr-2"
                                        >
                                            نام خانوادگی
                                        </label>

                                        <input
                                            type="text"
                                            wire:model="last_name"
                                            class="w-full
                               bg-white/50
                               dark:bg-white/[0.03]
                               border
                               border-gray-100
                               dark:border-white/10
                               rounded-2xl
                               px-6
                               py-4
                               text-xs
                               font-black
                               text-gray-800
                               dark:text-white
                               outline-none
                               focus:border-primary-500
                               focus:ring-4
                               focus:ring-primary-500/5
                               transition-all
                               @error('last_name')
                                   border-red-500
                               @enderror"
                                        >

                                        @error('last_name')
                                        <p class="text-[10px] font-bold text-red-500 mr-2">
                                            {{ $message }}
                                        </p>
                                        @enderror

                                    </div>


                                    {{-- Mobile --}}
                                    <div class="space-y-3">

                                        <label
                                            class="text-[11px]
                               font-black
                               text-gray-500
                               dark:text-gray-400
                               mr-2"
                                        >
                                            شماره موبایل (تایید شده)
                                        </label>

                                        <div class="relative">

                                            <input
                                                type="text"
                                                wire:model="mobile"
                                                disabled
                                                class="w-full
                                   bg-gray-50/50
                                   dark:bg-white/[0.01]
                                   border
                                   border-gray-100
                                   dark:border-white/10
                                   rounded-2xl
                                   px-6
                                   py-4
                                   text-xs
                                   font-black
                                   text-gray-400
                                   tabular-nums
                                   cursor-not-allowed"
                                            >

                                            <svg
                                                class="absolute left-5 top-1/2 -translate-y-1/2
                                   w-4 h-4
                                   text-emerald-500"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>

                                        </div>

                                    </div>


                                    {{-- Email --}}
                                    <div class="space-y-3">

                                        <label
                                            class="text-[11px]
                               font-black
                               text-gray-500
                               dark:text-gray-400
                               mr-2"
                                        >
                                            آدرس ایمیل
                                        </label>

                                        <input
                                            type="email"
                                            wire:model="email"
                                            dir="ltr"
                                            class="w-full
                               bg-white/50
                               dark:bg-white/[0.03]
                               border
                               border-gray-100
                               dark:border-white/10
                               rounded-2xl
                               px-6
                               py-4
                               text-xs
                               font-black
                               text-gray-800
                               dark:text-white
                               outline-none
                               focus:border-primary-500
                               transition-all
                               @error('email')
                                   border-red-500
                               @enderror"
                                        >

                                        @error('email')
                                        <p class="text-[10px] font-bold text-red-500 mr-2">
                                            {{ $message }}
                                        </p>
                                        @enderror

                                    </div>


                                    {{-- National Code --}}
                                    <div class="space-y-3">

                                        <label
                                            class="text-[11px]
                               font-black
                               text-gray-500
                               dark:text-gray-400
                               mr-2"
                                        >
                                            کد ملی
                                        </label>

                                        <input
                                            type="text"
                                            wire:model="national_code"
                                            inputmode="numeric"
                                            maxlength="10"
                                            class="w-full
                               bg-white/50
                               dark:bg-white/[0.03]
                               border
                               border-gray-100
                               dark:border-white/10
                               rounded-2xl
                               px-6
                               py-4
                               text-xs
                               font-black
                               text-gray-800
                               dark:text-white
                               outline-none
                               focus:border-primary-500
                               transition-all
                               tabular-nums
                               @error('national_code')
                                   border-red-500
                               @enderror"
                                        >

                                        @error('national_code')
                                        <p class="text-[10px] font-bold text-red-500 mr-2">
                                            {{ $message }}
                                        </p>
                                        @enderror

                                    </div>

                                    {{-- Birth Date --}}
                                    <div class="space-y-3">

                                        <label
                                            class="text-[11px]
               font-black
               text-gray-500
               dark:text-gray-400
               mr-2"
                                        >
                                            تاریخ تولد
                                        </label>

                                        <input
                                            type="text"
                                            wire:model="birth_date"
                                            inputmode="numeric"
                                            maxlength="10"
                                            placeholder="1380/01/01"
                                            dir="ltr"
                                            class="w-full
               bg-white/50
               dark:bg-white/[0.03]
               border
               border-gray-100
               dark:border-white/10
               rounded-2xl
               px-6
               py-4
               text-xs
               font-black
               text-gray-800
               dark:text-white
               outline-none
               focus:border-primary-500
               focus:ring-4
               focus:ring-primary-500/5
               transition-all
               tabular-nums
               text-left
               @error('birth_date')
                   border-red-500
               @enderror"
                                        >

                                        @error('birth_date')
                                        <p class="text-[10px] font-bold text-red-500 mr-2">
                                            {{ $message }}
                                        </p>
                                        @enderror

                                    </div>

                                    {{-- Birth Date --}}
                                    <div class="space-y-3">

                                        <label
                                            class="text-[11px]
               font-black
               text-gray-500
               dark:text-gray-400
               mr-2"
                                        >
                                            جنسیت
                                        </label>

                                        <div class="grid grid-cols-2 gap-3">

                                            {{-- مرد --}}
                                            <label class="cursor-pointer">

                                                <input
                                                    type="radio"
                                                    wire:model="gender"
                                                    value="male"
                                                    class="hidden peer"
                                                >

                                                <div
                                                    class="w-full
                       py-4
                       rounded-2xl
                       border
                       border-gray-100
                       dark:border-white/10
                       bg-white/50
                       dark:bg-white/[0.03]
                       text-center
                       text-[11px]
                       font-black
                       text-gray-500
                       dark:text-gray-400
                       transition-all
                       peer-checked:bg-blue-500/10
                       peer-checked:border-blue-500/30
                       peer-checked:text-blue-500
                       hover:border-blue-500/30"
                                                >
                                                    مرد
                                                </div>

                                            </label>


                                            {{-- زن --}}
                                            <label class="cursor-pointer">

                                                <input
                                                    type="radio"
                                                    wire:model="gender"
                                                    value="female"
                                                    class="hidden peer"
                                                >

                                                <div
                                                    class="w-full
                       py-4
                       rounded-2xl
                       border
                       border-gray-100
                       dark:border-white/10
                       bg-white/50
                       dark:bg-white/[0.03]
                       text-center
                       text-[11px]
                       font-black
                       text-gray-500
                       dark:text-gray-400
                       transition-all
                       peer-checked:bg-pink-500/10
                       peer-checked:border-pink-500/30
                       peer-checked:text-pink-500
                       hover:border-pink-500/30"
                                                >
                                                    زن
                                                </div>

                                            </label>

                                        </div>

                                        @error('gender')
                                        <p class="text-[10px] font-bold text-red-500 mr-2">
                                            {{ $message }}
                                        </p>
                                        @enderror

                                    </div>

                                    {{-- Save --}}
                                    <div
                                        class="md:col-span-2
                           mt-4
                           flex
                           justify-end"
                                    >

                                        <button
                                            type="submit"
                                            wire:loading.attr="disabled"
                                            wire:target="saveProfile"
                                            class="px-12
                               py-4
                               bg-primary-500
                               text-white
                               text-xs
                               font-black
                               rounded-2xl
                               shadow-lg
                               shadow-primary-500/30
                               hover:scale-[1.02]
                               active:scale-95
                               transition-all
                               flex
                               items-center
                               gap-3
                               disabled:opacity-60
                               disabled:cursor-not-allowed"
                                        >

                        <span wire:loading.remove wire:target="saveProfile">
                            ذخیره تغییرات پروفایل
                        </span>

                                            <span
                                                wire:loading
                                                wire:target="saveProfile"
                                            >
                            در حال ذخیره...
                        </span>


                                            <svg
                                                wire:loading.remove
                                                wire:target="saveProfile"
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>

                                        </button>

                                    </div>

                                </form>

                            </div>

                        </div>


                        {{-- Delete Account --}}
                        <div
                            class="relative overflow-hidden
               bg-red-500/5
               border border-red-500/10
               rounded-[2.5rem]
               p-8
               group"
                        >

                            <div
                                class="absolute
                   -bottom-10
                   -left-10
                   w-32
                   h-32
                   bg-red-500/10
                   rounded-full
                   blur-md
                   group-hover:scale-110
                   transition-all
                   duration-1000"
                            ></div>

                            <div
                                class="relative z-10
                   flex
                   flex-col
                   md:flex-row
                   items-center
                   justify-between
                   gap-6
                   text-center
                   md:text-right"
                            >

                                <div
                                    class="flex
                       flex-col
                       md:flex-row
                       items-center
                       gap-5"
                                >

                                    <div
                                        class="w-14 h-14
                           rounded-2xl
                           bg-white
                           dark:bg-white/5
                           flex
                           items-center
                           justify-center
                           text-red-500
                           shadow-sm
                           border border-red-500/20"
                                    >

                                        <svg
                                            class="w-7 h-7"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>

                                    </div>

                                    <div>

                                        <h4
                                            class="text-sm
                               font-black
                               text-red-600
                               dark:text-red-400"
                                        >
                                            حذف حساب کاربری
                                        </h4>

                                        <p
                                            class="text-[10px]
                               font-bold
                               text-red-500/60
                               mt-1
                               max-w-xs"
                                        >
                                            با حذف حساب، تمام تاریخچه سفارشات و اطلاعات حساب شما حذف خواهد شد.
                                        </p>

                                    </div>

                                </div>


                                <button
                                    type="button"
                                    class="px-8
                       py-4
                       bg-white
                       dark:bg-white/5
                       border border-red-500/20
                       text-red-500
                       text-[11px]
                       font-black
                       rounded-xl
                       hover:bg-red-500
                       hover:text-white
                       transition-all"
                                >
                                    حذف دائمی اکانت
                                </button>

                            </div>

                        </div>

                    </div>
                </div>
                <div
                    id="security-tab"
                    x-show="activeTab === 'security'"
                    x-cloak
                    class="space-y-6 animate-fadeIn lg:col-span-2"
                    dir="rtl"
                >
                    @if (session()->has('password_success'))
                        <div
                            class="mb-6 flex items-center gap-3 p-4 rounded-2xl
               bg-emerald-500/10
               border border-emerald-500/20
               text-emerald-600 dark:text-emerald-400"
                        >
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>

                            <span class="text-[11px] font-black">
            {{ session('password_success') }}
        </span>
                        </div>
                    @endif
                    {{-- ============================= --}}
                    {{-- Password Card --}}
                    {{-- ============================= --}}

                    <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-8 shadow-[0_20px_50px_rgba(0,0,0,0.05)] dark:shadow-none">

                        <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl"></div>

                        <div class="relative">

                            {{-- Header --}}
                            <div class="flex items-center gap-5 pb-8 mb-8 border-b border-gray-200/30 dark:border-white/5">

                                <div class="w-14 h-14 rounded-[1.8rem] bg-gradient-to-tr from-amber-500/20 to-amber-500/5 backdrop-blur-md border border-white/50 dark:border-white/10 flex items-center justify-center">

                                    <svg
                                        class="w-7 h-7 text-amber-600 dark:text-amber-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                                        />
                                    </svg>

                                </div>

                                <div>

                                    <h3 class="text-base font-black text-gray-900 dark:text-white">

                                        {{ $this->hasPassword
                                            ? 'تغییر رمز عبور'
                                            : 'ایجاد رمز عبور'
                                        }}

                                    </h3>

                                    <p class="text-[10px] font-bold text-gray-400 mt-1">

                                        {{ $this->hasPassword
                                            ? 'رمز عبور حساب خود را مدیریت کنید'
                                            : 'برای افزایش امنیت حساب خود یک رمز عبور ایجاد کنید'
                                        }}

                                    </p>

                                </div>

                            </div>


                            {{-- Form --}}
                            <form wire:submit="updatePassword">

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">


                                    {{-- ============================= --}}
                                    {{-- Inputs --}}
                                    {{-- ============================= --}}

                                    <div class="space-y-6">

                                        {{-- Current Password --}}
                                        @if($this->hasPassword)

                                            <div>

                                                <label class="block text-[11px] font-black text-gray-500 dark:text-gray-400 mb-2 mr-2">
                                                    رمز عبور فعلی
                                                </label>

                                                <input
                                                    type="password"
                                                    wire:model="current_password"
                                                    autocomplete="current-password"
                                                    placeholder="••••••••"
                                                    class="w-full bg-white/50 dark:bg-white/[0.03] border rounded-2xl px-6 py-4 text-sm font-bold text-gray-800 dark:text-white outline-none transition-all
                                    @error('current_password')
                                        border-red-500 focus:border-red-500
                                    @else
                                        border-gray-100 dark:border-white/10 focus:border-primary-500/50 focus:ring-4 focus:ring-primary-500/5
                                    @enderror"
                                                >

                                                @error('current_password')
                                                <p class="text-[10px] font-bold text-red-500 mt-2 mr-2">
                                                    {{ $message }}
                                                </p>
                                                @enderror

                                            </div>

                                        @endif


                                        {{-- New Password --}}
                                        <div>

                                            <label class="block text-[11px] font-black text-gray-500 dark:text-gray-400 mb-2 mr-2">
                                                رمز عبور جدید
                                            </label>

                                            <input
                                                type="password"
                                                wire:model.live="new_password"
                                                autocomplete="new-password"
                                                placeholder="••••••••"
                                                class="w-full bg-white/50 dark:bg-white/[0.03] border rounded-2xl px-6 py-4 text-sm font-bold text-gray-800 dark:text-white outline-none transition-all
                                @error('new_password')
                                    border-red-500 focus:border-red-500
                                @else
                                    border-gray-100 dark:border-white/10 focus:border-primary-500/50 focus:ring-4 focus:ring-primary-500/5
                                @enderror"
                                            >

                                            @error('new_password')
                                            <p class="text-[10px] font-bold text-red-500 mt-2 mr-2">
                                                {{ $message }}
                                            </p>
                                            @enderror


                                            {{-- Password Strength --}}
                                            @php

                                                $password = $new_password;

                                                $score = 0;

                                                if (strlen($password) >= 8) {
                                                    $score++;
                                                }

                                                if (preg_match('/[a-z]/', $password)) {
                                                    $score++;
                                                }

                                                if (preg_match('/[A-Z]/', $password)) {
                                                    $score++;
                                                }

                                                if (preg_match('/[\W_]/', $password)) {
                                                    $score++;
                                                }

                                                $strength = match ($score) {

                                                    0, 1 => [
                                                        'title' => 'ضعیف',
                                                        'percent' => 0,
                                                        'color' => 'bg-red-500',
                                                        'text' => 'text-red-500',
                                                    ],

                                                    2 => [
                                                        'title' => 'متوسط',
                                                        'percent' => 50,
                                                        'color' => 'bg-amber-500',
                                                        'text' => 'text-amber-500',
                                                    ],

                                                    3 => [
                                                        'title' => 'خوب',
                                                        'percent' => 75,
                                                        'color' => 'bg-blue-500',
                                                        'text' => 'text-blue-500',
                                                    ],

                                                    4 => [
                                                        'title' => 'عالی',
                                                        'percent' => 100,
                                                        'color' => 'bg-emerald-500',
                                                        'text' => 'text-emerald-500',
                                                    ],

                                                };
                                            @endphp


                                            <div class="mt-4 p-4 rounded-2xl bg-gray-50/50 dark:bg-white/[0.02] border border-gray-100/50 dark:border-white/5">

                                                <div class="flex justify-between items-center mb-2">

                                    <span class="text-[10px] font-black {{ $strength['text'] }}">
                                        امنیت: {{ $strength['title'] }}
                                    </span>

                                                    <span class="text-[10px] font-black {{ $strength['text'] }}">
                                        {{ $strength['percent'] }}٪
                                    </span>

                                                </div>

                                                <div class="h-1.5 w-full bg-gray-200/50 dark:bg-white/5 rounded-full overflow-hidden">

                                                    <div
                                                        class="h-full {{ $strength['color'] }} rounded-full transition-all duration-500"
                                                        style="width: {{ $strength['percent'] }}%"
                                                    ></div>

                                                </div>

                                            </div>

                                        </div>


                                        {{-- Confirm Password --}}
                                        <div>

                                            <label class="block text-[11px] font-black text-gray-500 dark:text-gray-400 mb-2 mr-2">
                                                تکرار رمز عبور جدید
                                            </label>

                                            <input
                                                type="password"
                                                wire:model="new_password_confirmation"
                                                autocomplete="new-password"
                                                placeholder="••••••••"
                                                class="w-full bg-white/50 dark:bg-white/[0.03] border rounded-2xl px-6 py-4 text-sm font-bold text-gray-800 dark:text-white outline-none transition-all
                                @error('new_password_confirmation')
                                    border-red-500
                                @else
                                    border-gray-100 dark:border-white/10 focus:border-primary-500/50 focus:ring-4 focus:ring-primary-500/5
                                @enderror"
                                            >

                                            @error('new_password_confirmation')
                                            <p class="text-[10px] font-bold text-red-500 mt-2 mr-2">
                                                {{ $message }}
                                            </p>
                                            @enderror

                                        </div>

                                    </div>


                                    {{-- ============================= --}}
                                    {{-- Password Rules --}}
                                    {{-- ============================= --}}

                                    <div class="p-6 rounded-[2rem] bg-primary-500/5 border border-primary-500/10 relative overflow-hidden">

                                        <div class="absolute -bottom-10 -left-10 w-28 h-28 bg-primary-500/10 rounded-full blur-2xl"></div>

                                        <div class="relative">

                                            <h4 class="text-xs font-black text-gray-800 dark:text-white mb-6 flex items-center gap-2">

                                                <svg
                                                    class="w-4 h-4 text-primary-500"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>

                                                قوانین رمز عبور امن

                                            </h4>


                                            <div class="space-y-4">

                                                {{-- Length --}}
                                                @php
                                                    $hasLength = strlen($new_password) >= 8;
                                                    $hasLower = preg_match('/[a-z]/', $new_password);
                                                    $hasUpper = preg_match('/[A-Z]/', $new_password);
                                                    $hasSpecial = preg_match('/[\W_]/', $new_password);
                                                @endphp


                                                <div class="flex items-center gap-3">

                                                    <div
                                                        @class([
                                                            'w-6 h-6 rounded-xl flex items-center justify-center border transition-all',
                                                            'bg-emerald-500/15 border-emerald-500/30' => $hasLength,
                                                            'bg-white/70 dark:bg-white/5 border-gray-200 dark:border-white/10' => !$hasLength,
                                                        ])
                                                    >

                                                        @if($hasLength)

                                                            <svg
                                                                class="w-3.5 h-3.5 text-emerald-500"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    stroke-width="3"
                                                                    d="M5 13l4 4L19 7"
                                                                />
                                                            </svg>

                                                        @endif

                                                    </div>

                                                    <span class="text-[11px] font-black text-gray-500 dark:text-gray-400">
                                        حداقل ۸ کاراکتر
                                    </span>

                                                </div>


                                                {{-- Upper + Lower --}}
                                                <div class="flex items-center gap-3">

                                                    <div
                                                        @class([
                                                            'w-6 h-6 rounded-xl flex items-center justify-center border transition-all',
                                                            'bg-emerald-500/15 border-emerald-500/30' => $hasLower && $hasUpper,
                                                            'bg-white/70 dark:bg-white/5 border-gray-200 dark:border-white/10' => !($hasLower && $hasUpper),
                                                        ])
                                                    >

                                                        @if($hasLower && $hasUpper)

                                                            <svg
                                                                class="w-3.5 h-3.5 text-emerald-500"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    stroke-width="3"
                                                                    d="M5 13l4 4L19 7"
                                                                />
                                                            </svg>

                                                        @endif

                                                    </div>

                                                    <span class="text-[11px] font-black text-gray-500 dark:text-gray-400">
                                        حروف بزرگ و کوچک انگلیسی
                                    </span>

                                                </div>


                                                {{-- Special --}}
                                                <div class="flex items-center gap-3">

                                                    <div
                                                        @class([
                                                            'w-6 h-6 rounded-xl flex items-center justify-center border transition-all',
                                                            'bg-emerald-500/15 border-emerald-500/30' => $hasSpecial,
                                                            'bg-white/70 dark:bg-white/5 border-gray-200 dark:border-white/10' => !$hasSpecial,
                                                        ])
                                                    >

                                                        @if($hasSpecial)

                                                            <svg
                                                                class="w-3.5 h-3.5 text-emerald-500"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    stroke-width="3"
                                                                    d="M5 13l4 4L19 7"
                                                                />
                                                            </svg>

                                                        @endif

                                                    </div>

                                                    <span class="text-[11px] font-black text-gray-500 dark:text-gray-400">
                                        حداقل یک نماد خاص مثل @، # یا !
                                    </span>

                                                </div>

                                            </div>


                                            <div class="mt-8 pt-6 border-t border-primary-500/10">

                                                <p class="text-[10px] font-bold text-gray-400 leading-6">
                                                    استفاده از ترکیب حروف بزرگ و کوچک، اعداد و نمادهای خاص باعث افزایش امنیت حساب شما می‌شود.
                                                </p>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                {{-- Submit --}}
                                <div class="mt-10 flex justify-end">

                                    <button
                                        type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="updatePassword"
                                        class="px-10 py-4 bg-primary-500 text-white text-xs font-black rounded-2xl shadow-lg shadow-primary-500/30 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >

                        <span wire:loading.remove wire:target="updatePassword">

                            {{ $this->hasPassword
                                ? 'بروزرسانی رمز عبور'
                                : 'ایجاد رمز عبور'
                            }}

                        </span>

                                        <span wire:loading wire:target="updatePassword">
                            در حال پردازش...
                        </span>

                                        <svg
                                            wire:loading.remove
                                            wire:target="updatePassword"
                                            class="w-4 h-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>

                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>


                    {{-- ============================= --}}
                    {{-- Success Message --}}
                    {{-- ============================= --}}

                    {{-- ============================= --}}
                    {{-- Two Factor --}}
                    {{-- ============================= --}}

                    <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-6 shadow-sm">

                        <div class="flex flex-col md:flex-row items-center justify-between gap-6">

                            <div class="flex items-center gap-5">

                                <div class="w-14 h-14 rounded-3xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 border border-emerald-500/20">

                                    <svg
                                        class="w-7 h-7"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                        />
                                    </svg>

                                </div>

                                <div class="text-center md:text-right">

                                    <h4 class="text-sm font-black text-gray-900 dark:text-white">
                                        تایید دو مرحله‌ای پیامکی
                                    </h4>

                                    <p class="text-[10px] font-bold text-emerald-500 mt-1">
                                        ورود با رمز یکبار مصرف برای حساب شما فعال است
                                    </p>

                                </div>

                            </div>

                            <span class="px-5 py-3 rounded-xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-[10px] font-black">
                فعال
            </span>

                        </div>

                    </div>

                </div>
                <div
                    id="notifications-tab"
                    x-show="activeTab === 'notifications'"
                    x-cloak
                    class="lg:col-span-2 space-y-5"
                    dir="rtl"
                >
                    @if (session()->has('notification_success'))
                        <div
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-5 py-4"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-500">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                </div>

                                <span class="text-[11px] font-black text-emerald-600 dark:text-emerald-400">
                {{ session('notification_success') }}
            </span>
                            </div>

                            <button
                                type="button"
                                @click="show = false"
                                class="text-emerald-500/60 hover:text-emerald-500 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    @endif
                    <div class="relative overflow-hidden bg-white/40 dark:bg-gray-950/60 backdrop-blur-md border border-white/60 dark:border-white/10 rounded-[2.5rem] p-8 shadow-[0_20px_50px_rgba(0,0,0,0.05)]">

                        <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl"></div>

                        {{-- Header --}}
                        <div class="relative flex items-center justify-between mb-10 pb-6 border-b border-gray-200/30 dark:border-white/5">

                            <div class="flex items-center gap-4">

                                <div class="w-12 h-12 rounded-2xl bg-primary-500/10 flex items-center justify-center text-primary-500">

                                    <svg
                                        class="w-6 h-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                        />
                                    </svg>

                                </div>

                                <div>

                                    <h3 class="text-base font-black text-gray-900 dark:text-white">
                                        مدیریت اعلان‌ها
                                    </h3>

                                    <p class="text-[10px] font-bold text-gray-400 mt-1 uppercase">
                                        Notification & Alerts Preferences
                                    </p>

                                </div>

                            </div>

                            {{-- همه اعلان‌ها --}}
                            <label class="relative inline-flex items-center cursor-pointer">

                                <input
                                    type="checkbox"
                                    wire:model="notificationsEnabled"
                                    class="sr-only peer"
                                >

                                <div
                                    class="w-11 h-6
                    bg-gray-200
                    peer-focus:outline-none
                    rounded-full
                    dark:bg-white/10
                    peer-checked:after:-translate-x-full
                    after:content-['']
                    after:absolute
                    after:top-[2px]
                    after:right-[2px]
                    after:bg-white
                    after:border-gray-300
                    after:border
                    after:rounded-full
                    after:h-5
                    after:w-5
                    after:transition-all
                    peer-checked:bg-primary-500
                    shadow-sm"
                                ></div>

                            </label>

                        </div>


                        {{-- Notification Items --}}
                        <div class="relative space-y-4">

                            {{-- وضعیت سفارش --}}
                            <div class="p-6 rounded-[2rem] bg-white/30 dark:bg-white/[0.02] border border-white/50 dark:border-white/5 group hover:border-primary-500/30 transition-all">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-4">

                                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center">

                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"
                                                />
                                                <path
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    d="M9 5a3 3 0 006 0H9z"
                                                />
                                            </svg>

                                        </div>

                                        <div>

                                            <h4 class="text-xs font-black text-gray-800 dark:text-white">
                                                بروزرسانی سفارش‌ها
                                            </h4>

                                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">
                                                تغییر وضعیت سفارش و ثبت سفارش جدید
                                            </p>

                                        </div>

                                    </div>

                                    <label class="relative inline-flex items-center cursor-pointer scale-90">

                                        <input
                                            type="checkbox"
                                            wire:model="order_updates"
                                            class="sr-only peer"
                                        >

                                        <div
                                            class="w-10 h-5
                            bg-gray-200
                            rounded-full
                            dark:bg-white/10
                            peer-checked:after:-translate-x-5
                            after:content-['']
                            after:absolute
                            after:top-[2px]
                            after:right-[2px]
                            after:bg-white
                            after:rounded-full
                            after:h-4
                            after:w-4
                            after:transition-all
                            peer-checked:bg-blue-500"
                                        ></div>

                                    </label>

                                </div>

                            </div>


                            {{-- پرداخت --}}
                            <div class="p-6 rounded-[2rem] bg-white/30 dark:bg-white/[0.02] border border-white/50 dark:border-white/5 group hover:border-primary-500/30 transition-all">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-4">

                                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">

                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>

                                        </div>

                                        <div>

                                            <h4 class="text-xs font-black text-gray-800 dark:text-white">
                                                تراکنش‌های مالی
                                            </h4>

                                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">
                                                اعلان پرداخت، واریز و تراکنش‌های مالی
                                            </p>

                                        </div>

                                    </div>

                                    <label class="relative inline-flex items-center cursor-pointer scale-90">

                                        <input
                                            type="checkbox"
                                            wire:model="payment_updates"
                                            class="sr-only peer"
                                        >

                                        <div
                                            class="w-10 h-5
                            bg-gray-200
                            rounded-full
                            dark:bg-white/10
                            peer-checked:after:-translate-x-5
                            after:content-['']
                            after:absolute
                            after:top-[2px]
                            after:right-[2px]
                            after:bg-white
                            after:rounded-full
                            after:h-4
                            after:w-4
                            after:transition-all
                            peer-checked:bg-emerald-500"
                                        ></div>

                                    </label>

                                </div>

                            </div>


                            {{-- ارسال --}}
                            <div class="p-6 rounded-[2rem] bg-white/30 dark:bg-white/[0.02] border border-white/50 dark:border-white/5 group hover:border-primary-500/30 transition-all">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-4">

                                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">

                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 19a2 2 0 100-4 2 2 0 000 4zm11 0a2 2 0 100-4 2 2 0 000 4z"
                                                />
                                            </svg>

                                        </div>

                                        <div>

                                            <h4 class="text-xs font-black text-gray-800 dark:text-white">
                                                وضعیت ارسال سفارش
                                            </h4>

                                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">
                                                آماده‌سازی، ارسال و تحویل مرسوله
                                            </p>

                                        </div>

                                    </div>

                                    <label class="relative inline-flex items-center cursor-pointer scale-90">

                                        <input
                                            type="checkbox"
                                            wire:model="shipping_updates"
                                            class="sr-only peer"
                                        >

                                        <div
                                            class="w-10 h-5
                            bg-gray-200
                            rounded-full
                            dark:bg-white/10
                            peer-checked:after:-translate-x-5
                            after:content-['']
                            after:absolute
                            after:top-[2px]
                            after:right-[2px]
                            after:bg-white
                            after:rounded-full
                            after:h-4
                            after:w-4
                            after:transition-all
                            peer-checked:bg-indigo-500"
                                        ></div>

                                    </label>

                                </div>

                            </div>


                            {{-- کیف پول --}}
                            <div class="p-6 rounded-[2rem] bg-white/30 dark:bg-white/[0.02] border border-white/50 dark:border-white/5 group hover:border-primary-500/30 transition-all">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-4">

                                        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-500 flex items-center justify-center">

                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    d="M3 7h18v10H3zM16 12h2"
                                                />
                                            </svg>

                                        </div>

                                        <div>

                                            <h4 class="text-xs font-black text-gray-800 dark:text-white">
                                                کیف پول
                                            </h4>

                                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">
                                                افزایش موجودی، برداشت و تغییرات کیف پول
                                            </p>

                                        </div>

                                    </div>

                                    <label class="relative inline-flex items-center cursor-pointer scale-90">

                                        <input
                                            type="checkbox"
                                            wire:model="wallet_updates"
                                            class="sr-only peer"
                                        >

                                        <div
                                            class="w-10 h-5
                            bg-gray-200
                            rounded-full
                            dark:bg-white/10
                            peer-checked:after:-translate-x-5
                            after:content-['']
                            after:absolute
                            after:top-[2px]
                            after:right-[2px]
                            after:bg-white
                            after:rounded-full
                            after:h-4
                            after:w-4
                            after:transition-all
                            peer-checked:bg-cyan-500"
                                        ></div>

                                    </label>

                                </div>

                            </div>


                            {{-- هشدار امنیتی --}}
                            <div class="p-6 rounded-[2rem] bg-white/30 dark:bg-white/[0.02] border border-white/50 dark:border-white/5 group hover:border-primary-500/30 transition-all">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-4">

                                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">

                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    d="M12 9v2m0 4h.01M5 20h14a2 2 0 001.732-3L13.732 4a2 2 0 00-3.464 0L3.268 17A2 2 0 005 20z"
                                                />
                                            </svg>

                                        </div>

                                        <div>

                                            <h4 class="text-xs font-black text-gray-800 dark:text-white">
                                                هشدارهای امنیتی
                                            </h4>

                                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">
                                                ورود جدید، تغییر رمز و رویدادهای امنیتی
                                            </p>

                                        </div>

                                    </div>

                                    {{-- امنیتی را بهتر است کاربر نتواند خاموش کند --}}
                                    <span class="text-[9px] font-black text-emerald-500 px-3 py-1.5 rounded-lg bg-emerald-500/10">
                        همیشه فعال
                    </span>

                                </div>

                            </div>


                            {{-- کمپین --}}
                            <div class="p-6 rounded-[2rem] bg-white/30 dark:bg-white/[0.02] border border-white/50 dark:border-white/5 group hover:border-primary-500/30 transition-all">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-4">

                                        <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center">

                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"
                                                />
                                            </svg>

                                        </div>

                                        <div>

                                            <h4 class="text-xs font-black text-gray-800 dark:text-white">
                                                کمپین‌ها و تخفیف‌ها
                                            </h4>

                                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">
                                                جشنواره‌ها، تخفیف‌ها و کدهای هدیه
                                            </p>

                                        </div>

                                    </div>

                                    <label class="relative inline-flex items-center cursor-pointer scale-90">

                                        <input
                                            type="checkbox"
                                            wire:model="promotions"
                                            class="sr-only peer"
                                        >

                                        <div
                                            class="w-10 h-5
                            bg-gray-200
                            rounded-full
                            dark:bg-white/10
                            peer-checked:after:-translate-x-5
                            after:content-['']
                            after:absolute
                            after:top-[2px]
                            after:right-[2px]
                            after:bg-white
                            after:rounded-full
                            after:h-4
                            after:w-4
                            after:transition-all
                            peer-checked:bg-purple-500"
                                        ></div>

                                    </label>

                                </div>

                            </div>


                            {{-- خبرنامه --}}
                            <div class="p-6 rounded-[2rem] bg-white/30 dark:bg-white/[0.02] border border-white/50 dark:border-white/5 group hover:border-primary-500/30 transition-all">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center gap-4">

                                        <div class="w-10 h-10 rounded-xl bg-pink-500/10 text-pink-500 flex items-center justify-center">

                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                                />
                                            </svg>

                                        </div>

                                        <div>

                                            <h4 class="text-xs font-black text-gray-800 dark:text-white">
                                                خبرنامه
                                            </h4>

                                            <p class="text-[10px] font-bold text-gray-400 mt-0.5">
                                                اخبار، مطالب و اطلاع‌رسانی‌های دوره‌ای
                                            </p>

                                        </div>

                                    </div>

                                    <label class="relative inline-flex items-center cursor-pointer scale-90">

                                        <input
                                            type="checkbox"
                                            wire:model="newsletter"
                                            class="sr-only peer"
                                        >

                                        <div
                                            class="w-10 h-5
                            bg-gray-200
                            rounded-full
                            dark:bg-white/10
                            peer-checked:after:-translate-x-5
                            after:content-['']
                            after:absolute
                            after:top-[2px]
                            after:right-[2px]
                            after:bg-white
                            after:rounded-full
                            after:h-4
                            after:w-4
                            after:transition-all
                            peer-checked:bg-pink-500"
                                        ></div>

                                    </label>

                                </div>

                            </div>

                        </div>


                        {{-- Buttons --}}
                        <div class="mt-10 flex justify-end gap-4 border-t border-gray-200/30 dark:border-white/5 pt-8">

                            <button
                                type="button"
                                wire:click="resetNotificationPreferences"
                                class="px-8 py-4 bg-gray-100 dark:bg-white/5 text-gray-500 text-[11px] font-black rounded-2xl hover:bg-gray-200 dark:hover:bg-white/10 transition-all"
                            >
                                تنظیمات پیش‌فرض
                            </button>

                            <button
                                type="button"
                                wire:click="saveNotificationPreferences"
                                wire:loading.attr="disabled"
                                class="px-10 py-4 bg-primary-500 text-white text-[11px] font-black rounded-2xl shadow-lg shadow-primary-500/30 hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50"
                            >
                <span wire:loading.remove wire:target="saveNotificationPreferences">
                    ذخیره تنظیمات اعلان
                </span>

                                <span wire:loading wire:target="saveNotificationPreferences">
                    در حال ذخیره...
                </span>
                            </button>

                        </div>

                    </div>


                    {{-- توضیح --}}
                    <div class="p-6 rounded-[2rem] bg-primary-500/5 border border-primary-500/10 flex items-start gap-4">

                        <svg
                            class="w-5 h-5 text-primary-500 mt-0.5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke="currentColor"
                                stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>

                        <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400 leading-5">
                            اعلان‌های امنیتی و رویدادهای مهم حساب برای حفظ امنیت شما همیشه فعال هستند
                            و امکان غیرفعال‌سازی آن‌ها وجود ندارد.
                        </p>

                    </div>

                </div>
            </div>
        </div>

    </main>
</div>
