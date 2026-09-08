<?php

use Livewire\Component;

new class extends Component
{
    public $data,$photo;
    public function mount($data,$media)
    {
        $this->data=$data;
        $this->photo=$media->first();
    }
};
?>

<div>
    <section class="container w-full my-20" dir="rtl">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-24 px-6">
            <div class="space-y-8">
                <div class="flex items-center gap-4">
                    <div class="w-2 h-10 bg-blue-600 rounded-full shadow-[0_0_15px_rgba(37,99,235,0.5)]"></div>
                    <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">{{$data['title'] ?? 'داستان زارا؛ فراتر از یک فروشگاه'}}</h1>
                </div>
                <p class="text-sm leading-9 text-gray-500 dark:text-gray-400 text-justify font-medium">
                    {!! $data['description'] ?? '' !!}
                </p>
                <div class="flex flex-wrap gap-4">
                    <div class="px-6 py-3 bg-blue-600/5 border border-blue-600/10 rounded-2xl text-blue-600 text-xs font-black">
                        کیفیت تضمین‌شده
                    </div>

                    <div class="px-6 py-3 bg-emerald-600/5 border border-emerald-600/10 rounded-2xl text-emerald-600 text-xs font-black">
                        ارسال سریع
                    </div>

                    <div class="px-6 py-3 bg-purple-600/5 border border-purple-600/10 rounded-2xl text-purple-600 text-xs font-black">
                        ضمانت اصالت کالا
                    </div>

                    <div class="px-6 py-3 bg-orange-600/5 border border-orange-600/10 rounded-2xl text-orange-600 text-xs font-black">
                        پشتیبانی و پاسخگویی
                    </div>

                    <div class="px-6 py-3 bg-rose-600/5 border border-rose-600/10 rounded-2xl text-rose-600 text-xs font-black">
                        خرید مطمئن و آسان
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-4 bg-gradient-to-tr from-blue-600 to-purple-600 opacity-20 blur-md rounded-full"></div>
                <div class="relative bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[4rem] p-4 shadow-lg overflow-hidden group">
                    <img src="{{asset('storage/'.$this->photo?->file_path)}}" class="rounded-[3.2rem] w-full h-[450px] object-cover grayscale group-hover:grayscale-0 transition-all duration-700" alt="تیم مانا">
                    <div class="absolute bottom-10 right-10 left-10 p-6 bg-white/90 dark:bg-[#0a0a0a]/90 backdrop-blur-md rounded-3xl border border-white/20 shadow-xl">
                        <p class="text-xs font-black text-gray-800 dark:text-white">مجموعه زارا چرم </p>
                        <p class="text-[10px] text-gray-500 mt-1">دفتر مرکزی قم</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-24 px-4">
            <div class="bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] p-10 text-center hover:-translate-y-2 transition-all">
                <h2 class="text-3xl font-black text-blue-600 mb-2">{{$data['client'] ?? ''}}</h2>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">هزار مشتری فعال</p>
            </div>
            <div class="bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] p-10 text-center hover:-translate-y-2 transition-all">
                <h2 class="text-3xl font-black text-emerald-500 mb-2">{{$data['service'] ?? ''}}</h2>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">رضایت از خدمات</p>
            </div>
            <div class="bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] p-10 text-center hover:-translate-y-2 transition-all">
                <h2 class="text-3xl font-black text-purple-600 mb-2">{{$data['shop'] ?? ''}}</h2>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">نمایندگی رسمی</p>
            </div>
            <div class="bg-white/40 dark:bg-white/[0.03] backdrop-blur-md border border-white/40 dark:border-white/10 rounded-[2.5rem] p-10 text-center hover:-translate-y-2 transition-all">
                <h2 class="text-3xl font-black text-amber-500 mb-2">{{$data['support'] ?? ''}}</h2>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">پشتیبانی آنلاین</p>
            </div>
        </div>

        <div class="bg-blue-600 rounded-[4rem] p-12 lg:p-20 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full blur-md -translate-y-1/2 translate-x-1/2"></div>

            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-3 gap-12">
                <div class="space-y-6">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center text-white">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-white">تضمین اصالت کالا</h3>
                    <p class="text-sm leading-7 text-blue-100/80">تمامی محصولات در مانا با ضمانت‌نامه معتبر و کد رهگیری اصالت کالا عرضه می‌شوند تا خیالتان از بابت اورجینال بودن راحت باشد.</p>
                </div>

                <div class="space-y-6">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center text-white">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-white">ارسال اکسپرس هوشمند</h3>
                    <p class="text-sm leading-7 text-blue-100/80">با استفاده از الگوریتم‌های بهینه‌سازی مسیر، سفارشات شما در کوتاه‌ترین زمان ممکن و با کمترین هزینه حمل و نقل به دستتان می‌رسد.</p>
                </div>

                <div class="space-y-6">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center text-white">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-white">قیمت‌گذاری رقابتی</h3>
                    <p class="text-sm leading-7 text-blue-100/80">هدف ما حذف واسطه‌های غیرضروری است تا بتوانیم محصولات روز دنیا را با قیمتی منصفانه و واقعی به دست مصرف‌کننده نهایی برسانیم.</p>
                </div>
            </div>
        </div>

        <div class="mt-20 text-center space-y-6">
            <h3 class="text-2xl font-black dark:text-white">می‌خواهید بخشی از خانواده مانا باشید؟</h3>
            <p class="text-sm text-gray-500 max-w-xl mx-auto">ما همیشه به دنبال استعدادهای درخشان و همکاران خلاق هستیم. رزومه خود را برای ما ارسال کنید.</p>
            <button class="px-10 py-5 bg-blue-600 text-white text-xs font-black rounded-[2rem] shadow-lg shadow-blue-500/25 hover:scale-105 transition-all">مشاهده فرصت‌های شغلی</button>
        </div>

    </section>
</div>
