<section class="contact-two my-5">
    <div class="container">
        <ul class="row list-unstyled">
            <li class="col-xl-3 col-lg-6 col-md-6 wow fadeInLeft animated" data-wow-delay="100ms" style="visibility: visible; animation-delay: 100ms; animation-name: fadeInLeft;">
                <div class="contact-two__single">
                    <div class="contact-two__icon">
                        <img src="{{asset('main')}}/images/icon/contact-two-icon-1.png" alt="">
                    </div>
                    <h3 class="contact-two__title">دفتر مرکزی</h3>
                    <p>{!! nl2br(e($data['location'] ?? '')) !!}</p>
                </div>
            </li>
            <li class="col-xl-3 col-lg-6 col-md-6 wow fadeInLeft animated" data-wow-delay="200ms" style="visibility: visible; animation-delay: 200ms; animation-name: fadeInLeft;">
                <div class="contact-two__single">
                    <div class="contact-two__icon">
                        <img src="{{asset('main')}}/images/icon/contact-two-icon-2.png" alt="">
                    </div>
                    <h3 class="contact-two__title">شماره تماس</h3>
                    <p>{!! nl2br(e($data['mobile'] ?? '')) !!}</p>
                </div>
            </li>
            <li class="col-xl-3 col-lg-6 col-md-6 wow fadeInRight animated" data-wow-delay="300ms" style="visibility: visible; animation-delay: 300ms; animation-name: fadeInRight;">
                <div class="contact-two__single">
                    <div class="contact-two__icon">
                        <img src="{{asset('main')}}/images/icon/contact-two-icon-3.png" alt="">
                    </div>
                    <h3 class="contact-two__title">ایمیل</h3>
                    <p>{!! nl2br(e($data['email'] ?? '')) !!}</p>
                </div>
            </li>
            <li class="col-xl-3 col-lg-6 col-md-6 wow fadeInRight animated" data-wow-delay="400ms" style="visibility: visible; animation-delay: 400ms; animation-name: fadeInRight;">
                <div class="contact-two__single">
                    <div class="contact-two__icon">
                        <img src="{{asset('main')}}/images/icon/contact-two-icon-4.png" alt="">
                    </div>
                    <h3 class="contact-two__title">ساعات کاری</h3>
                    <p>{!! nl2br(e($data['work_hours'] ?? '')) !!}</p>
                </div>
            </li>
        </ul>
    </div>
</section>
