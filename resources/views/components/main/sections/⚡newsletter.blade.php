<?php

use Livewire\Component;

new class extends Component
{
    public $email;

    protected function rules()
    {
        return [
            'email' => ['required', 'email', 'unique:newsletters,email'],
        ];
    }

    public function subscribe()
    {
        $this->validate();

        \App\Models\Newsletter::create([
            'email' => $this->email,
            'subscribed_at' => now(),
        ]);

        $this->reset('email');

        session()->flash('success', 'عضویت شما با موفقیت ثبت شد.');
    }
    protected function messages()
    {
        return [
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً در خبرنامه عضو شده است.',
        ];
    }
};
?>

<div>
    <section class="container" dir="rtl">
        <div class="relative overflow-hidden bg-white/40 dark:bg-white/[0.03] backdrop-blur-md rounded-[3rem] border border-white/60 dark:border-white/10 p-8 md:p-12 shadow-[0_25px_50px_-12px_rgba(0,0,0,0.05)]">

            <div class="absolute -top-12 -right-12 w-64 h-64 bg-rose-500/10 rounded-full blur-[80px]"></div>

            <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-10">

                <div class="flex flex-col md:flex-row items-center gap-6 text-center md:text-right">
                    <div class="w-20 h-20 bg-gradient-to-tr from-rose-500 to-rose-600 rounded-[2rem] flex items-center justify-center shadow-lg shadow-rose-500/30 transform -rotate-12 group-hover:rotate-0 transition-transform duration-500">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 dark:text-white mb-2">همیشه به‌روز بمانید</h2>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-bold">
                            از جدیدترین محصولات، مقالات و پیشنهادهای ویژه باخبر شوید.
                        </p>


                    </div>
                </div>

                <div class="w-full lg:w-[450px]">
                    <form wire:submit.prevent="subscribe" class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1 group">
                            <input wire:model.defer="email" type="email" placeholder="آدرس ایمیل شما" class="w-full h-14 bg-white/60 dark:bg-black/20 border border-gray-200 dark:border-white/10 rounded-2xl px-6 text-sm font-bold text-gray-800 dark:text-white outline-none focus:border-rose-500/50 focus:ring-4 focus:ring-rose-500/5 transition-all">
                            @error('email')
                                <p class="text-red-500 mt-2 text-[12px] mx-5">{{$message}}</p>
                            @enderror
                        </div>
                        <button type="submit" class="h-14 px-8 bg-rose-600 hover:bg-rose-700 text-white font-black text-sm rounded-2xl shadow-lg shadow-rose-600/20 transition-all active:scale-95 whitespace-nowrap">
                            ثبت ایمیل
                        </button>
                    </form>
                    @if(session('success'))
                        <div class="flex items-center mt-2 gap-3 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M5 13l4 4L19 7"/>
                            </svg>

                            <span class="text-sm font-medium">
                                ایمیل شما با موفقیت ثبت شد.
                            </span>
                        </div>
                    @endif
                    <p class="mt-4 mr-2 text-[10px] font-bold text-gray-400 dark:text-gray-500 flex items-center gap-2">
                        <i class="fas fa-info-circle text-rose-500"></i>
                        می‌توانید هر زمان که خواستید لغو عضویت کنید.
                    </p>
                </div>

            </div>
        </div>
    </section>

</div>
