
<section class="about-one">
    <div class="about-one__shape-1">
        <img src="{{asset('main')}}/images/shapes/about-one-shape-1.png" alt="">
    </div>
    <div class="about-one__shape-2">
        <img src="{{asset('main')}}/images/shapes/about-one-shape-2.png" alt="">
    </div>
    <div class="container">
        <div class="row">
            <div class="col-xl-6 wow slideInRight" data-wow-delay="100ms" data-wow-duration="2500ms">
                <div class="about-one__left">
                    <div class="about-one__left-shape-1 rotate-me"></div>
                    <div class="row">
                        <div class="col-xl-6 col-lg-6 col-md-6">
                            <div class="about-one__img-box">
                                <div class="about-one__img">
                                    <img src="{{$images['image_1'] ?? ''}}" alt="">
                                </div>
                            </div>

                        </div>
                        <div class="col-xl-6 col-lg-6 col-md-6">
                            <div class="about-one__experience-box">

                                <div class="about-one__experience-box-shape"></div>
                            </div>
                            <div class="about-one__img-box-2">
                                <div class="about-one__img-2">
                                    <img src="{{$images['image_2'] ?? ''}}" alt="">
                                </div>
                                <div class="about-one__img-shape-1 float-bob-y">
                                    <img src="{{asset('main')}}/images/shapes/about-one-img-shape-1.png" alt="">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="about-one__right">
                    <div class="section-title text-left sec-title-animation animation-style2">
                        <div class="section-title__tagline-box">
                            <div class="section-title__tagline-shape"></div>
                            <span class="section-title__tagline">درباره ما</span>
                        </div>
                        <h2 class="section-title__title title-animation">{{$data['title'] ?? ''}}<span>
                                      <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png"
                                                alt=""></span> </h2>
                    </div>
                    <p class="about-one__text">
                    {{$data['description'] ?? ''}}
                    <div class="about-one__btn-and-live-class mt-2">
                        <div class="about-one__btn-box">
                            <a href="{{route('page.show','about-us')}}" class="about-one__btn thm-btn"><span
                                    class="far fa-angle-double-left"></span>بیشتر بدانید</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
