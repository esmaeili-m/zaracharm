<?php

use Livewire\Component;

new class extends Component
{
    public $settings;
    public function mount()
    {
        $this->settings=\App\Models\Setting::pluck('value','key');

    }
};
?>

<div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-24">
        <div class="contact-tile">
            <div class="icon-box bg-blue-500/10 text-blue-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" stroke-width="2"></path></svg></div>
            <div><h4 class="text-xs font-bold text-gray-400 mb-1">شماره تماس</h4><p class="text-sm font-black dark:text-white">{{$this->settings['phone'] ?? ''}}</p></div>
        </div>
        <div class="contact-tile">
            <div class="icon-box bg-indigo-500/10 text-indigo-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"></path></svg></div>
            <div><h4 class="text-xs font-bold text-gray-400 mb-1">ساعات پاسخگویی</h4><p class="text-sm font-black dark:text-white">{{$this->settings['work_hours'] ?? ''}}</p></div>
        </div>
        <div class="contact-tile">
            <div class="icon-box bg-purple-500/10 text-purple-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" stroke-width="2"></path><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2"></path></svg></div>
            <div><h4 class="text-xs font-bold text-gray-400 mb-1">دفتر مرکزی</h4><p class="text-sm font-black dark:text-white">{{$this->settings['address'] ?? ''}}</p></div>
        </div>
        <div class="contact-tile">
            <div class="icon-box bg-emerald-500/10 text-emerald-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-width="2"></path></svg></div>
            <div><h4 class="text-xs font-bold text-gray-400 mb-1">پشتیبانی ایمیلی</h4><p class="text-sm font-black dark:text-white">{{$this->settings['email'] ?? ''}}</p></div>
        </div>
    </div>
</div>
