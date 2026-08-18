<?php

use Livewire\Component;

new class extends Component
{
    public $settings;
    public $data;

    public function mount($data)
    {
        $this->settings=\App\Models\Setting::pluck('value','key');
        $this->data= $data;

    }
    public string $name = '';
    public string $contact = '';
    public string $subject = '';
    public string $message = '';

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[\p{L}\s\-]+$/u',
            ],

            'contact' => [
                'required',
                'string',
                'max:255',
            ],

            'subject' => [
                'required',
                'string',
                'min:3',
                'max:255',

                // جلوگیری از HTML و تگ
                'not_regex:/<[^>]*>/u',

                // جلوگیری از JavaScript
                'not_regex:/javascript\s*:/iu',

                // جلوگیری از event handler مثل onclick=
                'not_regex:/\bon\w+\s*=/iu',
            ],

            'message' => [
                'required',
                'string',
                'min:10',
                'max:5000',

                // جلوگیری از HTML و تگ
                'not_regex:/<[^>]*>/u',

                // جلوگیری از JavaScript
                'not_regex:/javascript\s*:/iu',

                // جلوگیری از event handler مثل onclick=
                'not_regex:/\bon\w+\s*=/iu',

                // جلوگیری از iframe / script / object / embed
                'not_regex:/<(script|iframe|object|embed|style|form)[^>]*>/iu',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Name
            |--------------------------------------------------------------------------
            */

            'name.required' => 'لطفاً نام و نام خانوادگی خود را وارد کنید.',

            'name.string' => 'نام و نام خانوادگی باید به صورت متن باشد.',

            'name.min' => 'نام و نام خانوادگی باید حداقل ۳ کاراکتر باشد.',

            'name.max' => 'نام و نام خانوادگی نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',

            'name.regex' => 'نام و نام خانوادگی فقط می‌تواند شامل حروف و فاصله باشد.',


            /*
            |--------------------------------------------------------------------------
            | Contact
            |--------------------------------------------------------------------------
            */

            'contact.required' => 'لطفاً شماره تماس یا ایمیل خود را وارد کنید.',

            'contact.string' => 'شماره تماس یا ایمیل باید به صورت متن باشد.',

            'contact.max' => 'شماره تماس یا ایمیل نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',


            /*
            |--------------------------------------------------------------------------
            | Subject
            |--------------------------------------------------------------------------
            */

            'subject.required' => 'لطفاً موضوع پیام را وارد کنید.',

            'subject.string' => 'موضوع پیام باید به صورت متن باشد.',

            'subject.min' => 'موضوع پیام باید حداقل ۳ کاراکتر باشد.',

            'subject.max' => 'موضوع پیام نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',

            'subject.not_regex' => 'موضوع پیام شامل محتوای غیرمجاز است.',


            /*
            |--------------------------------------------------------------------------
            | Message
            |--------------------------------------------------------------------------
            */

            'message.required' => 'لطفاً متن پیام خود را وارد کنید.',

            'message.string' => 'متن پیام باید به صورت متن باشد.',

            'message.min' => 'متن پیام باید حداقل ۱۰ کاراکتر باشد.',

            'message.max' => 'متن پیام نمی‌تواند بیشتر از ۵۰۰۰ کاراکتر باشد.',

            'message.not_regex' => 'متن پیام شامل محتوای غیرمجاز است.',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $contact = trim($validated['contact']);

        $email = null;
        $mobile = null;

        /*
        |--------------------------------------------------------------------------
        | Validate Contact
        |--------------------------------------------------------------------------
        */

        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {

            $email = $contact;

        } elseif (preg_match('/^09\d{9}$/', $contact)) {

            $mobile = $contact;

        } else {

            $this->addError(
                'contact',
                'لطفاً یک ایمیل یا شماره موبایل معتبر وارد کنید.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Create Contact Message
        |--------------------------------------------------------------------------
        */

        \App\Models\Contact::create([
            'name' => trim($validated['name']),
            'email' => $email,
            'mobile' => $mobile,
            'subject' => trim($validated['subject']),
            'message' => trim($validated['message']),

            'type' => 'contact',
            'status' => 'new',

            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Success Message
        |--------------------------------------------------------------------------
        */

        session()->flash(
            'success',
            'پیام شما با موفقیت ثبت شد. در اولین فرصت با شما تماس خواهیم گرفت.'
        );

        /*
        |--------------------------------------------------------------------------
        | Reset Form
        |--------------------------------------------------------------------------
        */

        $this->reset([
            'name',
            'contact',
            'subject',
            'message',
        ]);
    }

};
?>

<div>
    <section class="container w-full my-20" dir="rtl">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 px-6 mb-12">
            <div class="flex items-center gap-4">
                <div class="w-2 h-12 bg-blue-600 rounded-full shadow-[0_0_20px_rgba(37,99,235,0.6)]"></div>
                <div>
                    <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">{{$data['title'] ?? 'تماس  با  زاراچرم'}}</h1>
                    <p class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-[6px] mt-2">Get In Touch With Us</p>
                </div>
            </div>
            <div class="hidden md:flex items-center gap-2 text-gray-400 font-bold text-xs">
                <span>پاسخگویی ۲۴ ساعته</span>
                <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-4 space-y-6">
                <div class="group bg-white/40 dark:bg-white/[0.03] backdrop-blur-[30px] border border-white/40 dark:border-white/10 rounded-[2.8rem] p-8 transition-all duration-500 hover:shadow-lg hover:shadow-blue-500/5">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 bg-blue-600/10 text-blue-600 rounded-2xl flex items-center justify-center border border-blue-600/20 group-hover:bg-blue-600 group-hover:text-white transition-all duration-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">شماره تماس</p>
                            <p class="text-lg font-black text-gray-800 dark:text-white tracking-tighter">{{$settings['phone'] ?? ''}}</p>
                        </div>
                    </div>
                </div>

                <div class="group bg-white/40 dark:bg-white/[0.03] backdrop-blur-[30px] border border-white/40 dark:border-white/10 rounded-[2.8rem] p-8 transition-all duration-500 hover:shadow-lg hover:shadow-purple-500/5">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 bg-purple-600/10 text-purple-600 rounded-2xl flex items-center justify-center border border-purple-600/20 group-hover:bg-purple-600 group-hover:text-white transition-all duration-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">پست الکترونیک</p>
                            <p class="text-sm font-black text-gray-800 dark:text-white tracking-wider">{{$settings['email'] ?? ''}}</p>
                        </div>
                    </div>
                </div>

                <div class="group bg-white/40 dark:bg-white/[0.03] backdrop-blur-[30px] border border-white/40 dark:border-white/10 rounded-[2.8rem] p-8 transition-all duration-500 hover:shadow-lg hover:shadow-amber-500/5">
                    <div class="flex items-start gap-5">
                        <div class="w-14 h-14 bg-amber-500/10 text-amber-600 rounded-2xl flex items-center justify-center flex-shrink-0 border border-amber-500/20 group-hover:bg-amber-500 group-hover:text-white transition-all duration-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">دفتر مرکزی</p>
                            <p class="text-sm leading-7 font-black text-gray-800 dark:text-white">{{$settings['address'] ?? ''}}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8">
                <div class="bg-white/40 dark:bg-white/[0.03] backdrop-blur-[30px] border border-white/40 dark:border-white/10 rounded-[3rem] p-10 h-full shadow-sm">
                    <form wire:submit="save" class="space-y-8">

                        {{-- Success Message --}}
                        @if (session()->has('success'))
                            <div
                                class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400"
                            >
                                <svg
                                    class="h-5 w-5 shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>

                                <span>
                {{ session('success') }}
            </span>
                            </div>
                        @endif


                        {{-- Name & Contact --}}
                        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">

                            {{-- Name --}}
                            <div class="space-y-3">

                                <label
                                    for="name"
                                    class="mr-2 text-[11px] font-black uppercase tracking-[2px] text-gray-400 dark:text-gray-500"
                                >
                                    نام و نام خانوادگی
                                </label>

                                <input
                                    id="name"
                                    type="text"
                                    wire:model.blur="name"
                                    autocomplete="name"
                                    placeholder="مثلا: امیر رضایی"
                                    class="w-full rounded-2xl border bg-white/50 px-6 py-4 text-sm font-bold transition-all placeholder:text-gray-400 focus:border-blue-600 focus:outline-none dark:bg-white/5 dark:text-white
                {{ $errors->has('name')
                    ? 'border-red-500 focus:border-red-500'
                    : 'border-gray-200/50 dark:border-white/5' }}"
                                >

                                @error('name')
                                <p class="mr-2 text-xs font-bold text-red-500">
                                    {{ $message }}
                                </p>
                                @enderror

                            </div>


                            {{-- Contact --}}
                            <div class="space-y-3">

                                <label
                                    for="contact"
                                    class="mr-2 text-[11px] font-black uppercase tracking-[2px] text-gray-400 dark:text-gray-500"
                                >
                                    شماره تماس یا ایمیل
                                </label>

                                <input
                                    id="contact"
                                    type="text"
                                    wire:model.blur="contact"
                                    autocomplete="email"
                                    dir="ltr"
                                    placeholder="0912XXXXXXX یا example@gmail.com"
                                    class="w-full rounded-2xl border bg-white/50 px-6 py-4 text-left text-sm font-bold transition-all placeholder:text-gray-400 focus:border-blue-600 focus:outline-none dark:bg-white/5 dark:text-white
                {{ $errors->has('contact')
                    ? 'border-red-500 focus:border-red-500'
                    : 'border-gray-200/50 dark:border-white/5' }}"
                                >

                                @error('contact')
                                <p class="mr-2 text-xs font-bold text-red-500">
                                    {{ $message }}
                                </p>
                                @enderror

                            </div>

                        </div>


                        {{-- Subject --}}
                        {{-- Subject --}}
                        <div class="space-y-3">

                            <label
                                for="subject"
                                class="mr-2 text-[11px] font-black uppercase tracking-[2px] text-gray-400 dark:text-gray-500"
                            >
                                موضوع پیام
                            </label>

                            <input
                                id="subject"
                                type="text"
                                wire:model.blur="subject"
                                placeholder="مثلا: پیگیری سفارش یا درخواست همکاری"
                                class="w-full rounded-2xl border bg-white/50 px-6 py-4 text-sm font-bold transition-all placeholder:text-gray-400 focus:border-blue-600 focus:outline-none dark:bg-white/5 dark:text-white
        {{ $errors->has('subject')
            ? 'border-red-500 focus:border-red-500'
            : 'border-gray-200/50 dark:border-white/5' }}"
                            >

                            @error('subject')
                            <p class="mr-2 text-xs font-bold text-red-500">
                                {{ $message }}
                            </p>
                            @enderror

                        </div>


                        {{-- Message --}}
                        <div class="space-y-3">

                            <label
                                for="message"
                                class="mr-2 text-[11px] font-black uppercase tracking-[2px] text-gray-400 dark:text-gray-500"
                            >
                                متن پیام شما
                            </label>

                            <textarea
                                id="message"
                                wire:model.blur="message"
                                rows="5"
                                placeholder="چطور می‌توانیم به شما کمک کنیم؟"
                                class="w-full resize-none rounded-[2rem] border bg-white/50 px-6 py-6 text-sm font-bold transition-all placeholder:text-gray-400 focus:border-blue-600 focus:outline-none dark:bg-white/5 dark:text-white
            {{ $errors->has('message')
                ? 'border-red-500 focus:border-red-500'
                : 'border-gray-200/50 dark:border-white/5' }}"
                            ></textarea>

                            @error('message')
                            <p class="mr-2 text-xs font-bold text-red-500">
                                {{ $message }}
                            </p>
                            @enderror

                        </div>


                        {{-- Submit Button --}}
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="group flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-12 py-5 text-sm font-black text-white shadow-lg shadow-blue-500/25 transition-all hover:scale-[1.02] active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 md:w-auto"
                        >

                            {{-- Normal State --}}
                            <span
                                wire:loading.remove
                                wire:target="save"
                            >
            ارسال پیام
        </span>

                            {{-- Loading State --}}
                            <span
                                wire:loading
                                wire:target="save"
                                class="flex items-center gap-2"
                            >
            در حال ارسال

            <svg
                class="h-4 w-4 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                />

                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                />
            </svg>
        </span>


                            {{-- Arrow --}}
                            <svg
                                wire:loading.remove
                                wire:target="save"
                                class="h-5 w-5 transition-transform duration-300 group-hover:-translate-x-1"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2.5"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3"
                                />
                            </svg>

                        </button>

                    </form>
                </div>
            </div>
        </div>

    </section>


</div>
