<?php
$users = \App\Models\User::active()->teachers()->with(['roles', 'media'])->get();
?>
@if($users)
    <section class="team-two">
        <div class="team-two__shape-1 float-bob-y">
            <img src="{{asset('main')}}/images/shapes/team-two-shape-1.png" alt="">
        </div>
        <div class="team-two__shape-2 float-bob-x">
            <img src="{{asset('main')}}/images/shapes/team-two-shape-2.png" alt="">
        </div>
        <div class="container">
            <div class="section-title-two text-left sec-title-animation animation-style2">
                <div class="section-title-two__tagline-box">
                    <div class="section-title-two__tagline-shape">
                        <img src="{{asset('main')}}/images/shapes/section-title-two-shape-2.png" alt="">
                    </div>
                    <span class="section-title-two__tagline">مدرسین ما</span>
                </div>
                <h2 class="section-title-two__title title-animation" style="perspective: 400px;">
                    {!! nl2br(e($data['title'] ?? '')) !!}
                </h2>
            </div>
            <div class="team-two__carousel owl-theme owl-carousel owl-rtl owl-loaded owl-drag">

                <div class="owl-stage-outer">
                    <div class="owl-stage"
                         style="transform: translate3d(2628px, 0px, 0px); transition: 0.5s; width: 5256px;">
                        @foreach($users ?? [] as $user)
                            <div class="owl-item " style="width: 414px; margin-left: 24px;">
                                <div class="item">
                                    <div class="team-two__single">
                                        <div class="team-two__img-1">
                                            <img src="{{$user->avatarUrl}}" alt="">
                                        </div>
                                        <div class="team-two__arrow">
                                            <a href="#"><span class="icon-circle-rightsvg"></span></a>
                                        </div>
                                        <div class="team-two__content">
                                            <p class="team-two__sub-title">{{$user->role_label}}</p>
                                            <h3 class="team-two__name"><a
                                                    href="#">{{$user->name}}</a></h3>
                                        </div>
                                        <div class="team-two__social-box">
                                            <div class="team-two__plus">
                                                <i><span class="icon-plus"></span></i>
                                            </div>
                                            <div class="team-two__social-list">
                                                    <livewire:main.social-links :socials="$user->socials" />

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        @endforeach

                    </div>
                </div>
                <div class="owl-nav">
                    <button type="button" role="presentation" class="owl-prev"><span class="icon-arrow-up-right-2"></span>
                    </button>
                    <button type="button" role="presentation" class="owl-next"><span class="icon-arrow-left-up"></span>
                    </button>
                </div>
                <div class="owl-dots disabled"></div>
            </div>
        </div>
    </section>

@endif


