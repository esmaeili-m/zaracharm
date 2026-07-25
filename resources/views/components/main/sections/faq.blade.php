<section class="faq-page" style="padding-top: 140px">
    <div class="container">
        <div class="section-title text-left sec-title-animation animation-style2 text-center">
            <div class="section-title__tagline-box">
                <div class="section-title__tagline-shape"></div>
                <span class="section-title__tagline">سوالات متداول</span>
            </div>
            <h2 class="section-title__title title-animation">پاسخ رایج‌ترین سوالات درباره دوره‌های مامایی
                <span> <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span></h2>
        </div>
        <div class="row">
            <div class="col-xl-12 col-lg-12">
                <div class="faq-page__left">
                    <div class="accrodion-grp faq-one-accrodion faq-one-accrodion-1 row" data-grp-name="faq-one-accrodion-1">
                        @foreach(\App\Models\Faq::active()->orderBy('sort_order')->take(10)->get() as $faq)
                            <div class="col-lg-6 col-xl-6 col-sm-12 mb-2">

                                <div class="accrodion ">
                                    <div class="accrodion-title">
                                        <h4>
                                            {{$faq->question}}
                                        </h4>
                                    </div>
                                    <div class="accrodion-content" style="">
                                        <div class="inner">
                                            <p>
                                                {{$faq->answer}}
                                            </p>
                                        </div><!-- /.inner -->
                                    </div>
                                </div>

                            </div>

                        @endforeach

                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
