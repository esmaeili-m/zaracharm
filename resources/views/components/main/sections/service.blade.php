<?php
    $services=\App\Models\Service::where('status',1)->pluck('title');
?>
@push('styles')
    <style>
       .service-sec{
           border-radius: 10px;
           padding: 5px;
           border: 1px solid white;
           margin-bottom: 10px;
       }
    </style>
@endpush

<div>
    <section class="blog-page mt-5">
        <div class="container">
            <div class="section-title text-center sec-title-animation animation-style1">
                <div class="section-title__tagline-box">
                    <div class="section-title__tagline-shape"></div>
                    <span class="section-title__tagline">خدمات ما</span>
                </div>
                <h2 class="section-title__title title-animation">خدمات مركز مامايي مادرانه
                    <span> <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span></h2>
            </div>
            <div class="row">
                <div class="sidebar__single sidebar__category" style="background: linear-gradient(180deg, #faedf9  0%, #FFF 100%);">
                    <div class="sidebar__title-box">
                        <div class="sidebar__title-icon">
                            <img src="assets/images/icon/sidebar-title-icon.png" alt="">
                        </div>
                    </div>
                    <div class="row">
                        @foreach($services as $service)

                            <div class="col-lg-6 col-sm-12  mb-2">
                                <div class="main-menu__btn-boxes">
                                    <div class="main-menu__btn-box-2 w-100">
                                        <a href="#" class="thm-btn w-100 p-3">{{$service}}</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>

            </div>

        </div>
    </section>

</div>
