<?php
$hospitals = \App\Models\Hospital::active()
    ->with(['media'])
    ->get();

?>
@push('styles')
    <style>
        .blog-one__single{
            height:100%;
            display:flex;
            flex-direction:column;
        }

        .blog-one__content{
            flex:1;
            display:flex;
            flex-direction:column;
        }

        .blog-one__btn-and-user-box{
            margin-top:auto;
        }
        .blog-one__img{
            height:250px;
            overflow:hidden;
        }

        .blog-one__img img{
            width:100%;
            height:100%;
            object-fit:cover;
        }
        .blog-one__text{
            height: 96px;
            overflow: hidden;
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
@endpush


<section class="blog-one">
    <div class="container">
        <div class="section-title text-center sec-title-animation animation-style1">
            <div class="section-title__tagline-box">
                <div class="section-title__tagline-shape"></div>
                <span class="section-title__tagline">معرفی بیمارستانهای قم برای زایمان</span>
            </div>
            <h2 class="section-title__title title-animation">{!! nl2br(e($data['title'] ?? '')) !!}
                <span><img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span> </h2>
        </div>
        <div class="blog-one__carousel owl-theme owl-carousel">

            @foreach($hospitals as $hospital)
                <div class="item">
                    <div class="blog-one__single h-100">

                        <div class="blog-one__img">
                            <img src="{{ $hospital->featuredImageUrl }}" alt="{{ $hospital->name }}">
                        </div>

                        <div class="blog-one__content">

                            <h3 class="blog-one__title mb-2">
                                <a href="#">
                                    {{ $hospital->name }}
                                </a>
                            </h3>

                            <div class="small text-muted mb-3">

                                @if($hospital->city)
                                    <div class="mb-1">
                                        <i class="fa fa-map-marker-alt text-danger me-1"></i>
                                        {{ $hospital->city }}
                                    </div>
                                @endif

                                @if($hospital->phone)
                                    <div class="mb-1">
                                        <i class="fa fa-phone text-success me-1"></i>
                                        {{ $hospital->phone }}
                                    </div>
                                @endif

                                @if($hospital->address)
                                    <div class="line-clamp-2">
                                        <i class="fa fa-location text-primary me-1"></i>
                                        {{ \Illuminate\Support\Str::limit($hospital->address, 120) }}
                                    </div>
                                @endif

                            </div>

                            <div class="mb-3">

                                @if($hospital->supports_natural_birth)
                                    <span class="badge bg-success me-1 mb-1">
                            زایمان طبیعی
                        </span>
                                @endif

                                @if($hospital->supports_c_section)
                                    <span class="badge bg-primary me-1 mb-1">
                                        سزارین
                                    </span>
                                @endif

                                @if($hospital->has_nicu)
                                    <span class="badge bg-warning text-dark me-1 mb-1">
                            NICU
                        </span>
                                @endif

                                @if($hospital->high_risk_pregnancy)
                                    <span class="badge bg-danger me-1 mb-1">
                            بارداری پرخطر
                        </span>
                                @endif

                            </div>
                            @if($hospital->description)
                                <p class="blog-one__text line-clamp-4">
                                    {!! nl2br(e($hospital->description ?? '')) !!}
                                </p>
                            @endif

                        </div>
                    </div>
                </div>
            @endforeach


        </div>
    </div>
</section>
