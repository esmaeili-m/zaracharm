<div class="relative group overflow-hidden rounded-[2.5rem]">
    <div class="swiper mainHeroSwiper h-full ">
        <div class="swiper-wrapper">
            @foreach(\App\Models\SliderItem::where('slider_id',$data['slider_id'])->with('media')->get() as $slider)
                <div class="swiper-slide relative">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                    <img src="{{ asset('storage/'.$slider->media->first()->file_path)}}" alt="Banner" class="w-full h-full object-cover">
                </div>
            @endforeach


        </div>

        <div class="absolute bottom-4 left-4 md:bottom-8 md:left-8 flex gap-2 md:gap-3 z-10">
            <div class="swiper-button-prev-custom w-9 h-9 md:w-12 md:h-12 rounded-full bg-white/20 backdrop-blur-md border border-white/30 text-white flex items-center justify-center cursor-pointer hover:bg-white hover:text-blue-600 transition-all">
                <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </div>

            <div class="swiper-button-next-custom w-9 h-9 md:w-12 md:h-12 rounded-full bg-white/20 backdrop-blur-md border border-white/30 text-white flex items-center justify-center cursor-pointer hover:bg-white hover:text-blue-600 transition-all">
                <svg class="w-4 h-4 md:w-5 md:h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </div>

        <div class="swiper-pagination !bottom-8 !right-8 !left-auto !w-auto"></div>
    </div>
</div>
