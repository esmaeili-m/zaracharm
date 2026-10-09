{{-- دکمه پخش / توقف ویدیوی هیرو (دسترس‌پذیری: ویدیوی خودکار باید قابل توقف باشد) --}}
<button type="button" data-zc-video-toggle data-playing="1" aria-label="پخش / توقف ویدیو"
        class="group/vt absolute {{ $position }} z-10 w-10 h-10 rounded-full bg-black/35 hover:bg-black/55 backdrop-blur-md border border-white/25 text-white flex items-center justify-center transition-colors">
    {{-- در حال پخش => آیکن توقف --}}
    <svg class="w-4 h-4 group-data-[playing=0]/vt:hidden" fill="currentColor" viewBox="0 0 24 24"><path d="M7 5h3v14H7zM14 5h3v14h-3z"/></svg>
    <svg class="w-4 h-4 hidden group-data-[playing=0]/vt:block translate-x-[-1px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5.14v13.72a1 1 0 001.5.86l11-6.86a1 1 0 000-1.72l-11-6.86A1 1 0 008 5.14z"/></svg>
</button>
