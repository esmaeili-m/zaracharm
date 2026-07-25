<?php

use Livewire\Component;

new class extends Component
{
    public $settings,$pages;

    public function mount()
    {
        $this->settings = \App\Models\Setting::whereNot('key','logo')->pluck('value', 'key')->toArray();
        $this->pages=\App\Models\Page::get();
    }
};
?>
@push('styles')
    <style>
        @media (max-width: 767.98px) {

            .site-footer__logo-box{
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .site-footer__logo img{
                width: 80px !important;
                height: 80px !important;
            }

            .site-footer__text{
                margin-top: 15px;
                font-size: 14px;
                line-height: 1.9;
            }

            .site-footer__contact-box{
                text-align: center;
                margin-top: 20px;
            }

            .site-footer__contact-info{
                flex-direction: column;
            }

            .site-footer__contact-info-list,
            .site-footer__contact-info-list--two{
                width: 100%;
                margin-bottom: 0;
            }

            .site-footer__contact-info-icon-box{
                padding: 12px 0;
                border-bottom: 1px solid rgba(255,255,255,.1);
            }

            .site-footer__app-and-social-box{
                flex-direction: column;
                gap: 25px;
                align-items: center;
                text-align: center;
                margin-top: 30px;
            }

            .site-footer__app-download-inner{
                justify-content: center;
            }

            .site-footer__social-box-inner{
                justify-content: center;
            }

            .site-footer__top-right{
                margin-top: 40px;
            }

            .site-footer__links-list{
                text-align: center;
            }

            .site-footer__links-list li{
                margin-bottom: 10px;
            }

            .site-footer__bottom{
                text-align: center;
            }

            .site-footer__top{
                padding-top: 40px;
                padding-bottom: 30px;
            }

            .site-footer__logo-and-contact-box-inner{
                padding: 20px 0;
            }
        }
    </style>
@endpush
<div>
    <footer class="site-footer pt-5">

        <div class="container">

            <!-- Top -->
            <div class="row gy-5">

                <!-- About -->
                <div class="col-lg-4 text-center text-lg-start">

                    <img
                        src="{{ \App\Models\Setting::logo() }}"
                        alt="logo"
                        width="90"
                        height="90"
                        class="mb-3">

                    <p style="color:white" class="small lh-lg">
                        {{ $settings['about'] ?? '' }}
                    </p>

                    <div class="d-flex justify-content-center justify-content-lg-start gap-3 mt-4">

                        @if(!empty($settings['instagram']))
                            <a href="{{$settings['instagram']}}">
                                <i class="fab fa-instagram fa-lg"></i>
                            </a>
                        @endif
                        @if(!empty($settings['bale']))
                            <a  href="{{$settings['bale']}}" style="color: white">
                                @include('components.main.socials.bale')
                            </a>
                        @endif
                        @if(!empty($settings['rubika']))
                            <a href="{{$settings['rubika']}}">
                                @include('components.main.socials.rubika')
                            </a>
                        @endif
                        @if(!empty($settings['eita']))
                            <a href="{{$settings['eita']}}">
                                @include('components.main.socials.eitaa')
                            </a>
                        @endif

                        @if(!empty($settings['telegram']))
                            <a href="{{$settings['telegram']}}">
                                <i class="fab fa-telegram fa-lg"></i>
                            </a>
                        @endif

                        @if(!empty($settings['whatsapp']))
                            <a href="{{$settings['whatsapp']}}">
                                <i class="fab fa-whatsapp fa-lg"></i>
                            </a>
                        @endif

                    </div>

                </div>

                <!-- Quick Links -->
                <div class="col-lg-4">

                    <h5 class="mb-4 fw-bold text-center text-lg-start">
                        دسترسی سریع
                    </h5>

                    <div class="row">

                        @php($half = ceil(count($pages) / 2))

                        <div class="col-6">

                            <ul class="list-unstyled">

                                @foreach(collect($pages)->take($half) as $page)

                                    <li class="mb-2">

                                        <a style="color:white" href="{{ route('page.show',$page->slug) }}">
                                            {{ $page->title }}
                                        </a>

                                    </li>

                                @endforeach

                            </ul>

                        </div>

                        <div class="col-6">

                            <ul class="list-unstyled">

                                @foreach(collect($pages)->skip($half) as $page)

                                    <li class="mb-2">

                                        <a style="color:white" href="{{ route('page.show',$page->slug) }}">
                                            {{ $page->title }}
                                        </a>

                                    </li>

                                @endforeach

                            </ul>

                        </div>

                    </div>

                </div>

                <!-- Contact -->
                <div class="col-lg-4">

                    <h5 class="mb-4 fw-bold text-center text-lg-start">
                        اطلاعات تماس
                    </h5>

                    <ul class="list-unstyled">

                        <li class="mb-3">
                            <i style="color:white" class="fas fa-phone me-2"></i>
                            <a style="color:white" href="tel:{{$settings['mobile'] ?? ''}}">
                                {{$settings['mobile'] ?? ''}}
                            </a>
                        </li>

                        <li class="mb-3">
                            <i style="color:white" class="fas fa-envelope me-2"></i>
                            <a href="mailto:{{$settings['email'] ?? ''}}">
                                {{$settings['email'] ?? ''}}
                            </a>
                        </li>

                        <li class="mb-3">
                            <i style="color:white" class="fas fa-location-arrow me-2"></i>
                            {{$settings['address'] ?? ''}}
                        </li>

                        <li>
                            <i style="color:white" class="fas fa-clock me-2"></i>
                            {{$settings['work_hours'] ?? ''}}
                        </li>

                    </ul>

                </div>

            </div>

            <hr class="my-4">

            <!-- Apps -->
            <div class="row align-items-center gy-3">

                <div class="col-lg-6 text-center text-lg-start">

                    <h6 class="mb-3">
                        دانلود اپلیکیشن
                    </h6>

                    <div class="d-flex justify-content-center justify-content-lg-start gap-2">

                        <a href="#">
                            <img
                                src="{{asset('main/images/icon/google-play-icon.png')}}"
                                width="45"
                                alt="">
                        </a>

                        <a href="#">
                            <img
                                src="{{asset('main/images/icon/apple-icon.png')}}"
                                width="45"
                                alt="">
                        </a>

                    </div>

                </div>

                <div class="col-lg-6 text-center text-lg-end">

                    <a href="{{ route('page.show','contact-us') }}"
                       class="btn btn-primary rounded-pill px-4">
                        تماس با ما
                    </a>

                </div>

            </div>

            <hr class="my-4">

            <!-- Copyright -->

            <div class="text-center pb-3">

                <small>
                    © {{ date('Y') }}
                    تمامی حقوق برای
                    <strong>مادرانه</strong>
                    محفوظ است.
                </small>

            </div>

        </div>

    </footer>

</div>
