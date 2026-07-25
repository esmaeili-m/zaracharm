<?php
$courses = \App\Models\Course::active()
    ->with(['media', 'category'])
    ->withCount([
        'sections',
        'likes',
        'lessons',
        'comments'
    ])
    ->withAvg('ratings', 'rating')
    ->take(9)->orderBy('sort')
    ->get();
?>
@push('styles')
<style>
    .price{
        font-size: 18px;
        font-weight: 700;
        color: #984695 !important;
    }

    .old-price{
        text-decoration: line-through;
        color: #999 !important;
        margin-left: 8px;
        font-size: 14px !important;
    }

    .new-price{
        color: #984695 !important;
        font-weight: 700;
        font-size: 18px;
    }
    .courses-one__img-box {
        width: 100%;
        height: 220px;
        overflow: hidden;
        border-radius: 10px;
        position: relative;
        background: #f5f5f5;
    }

    .courses-one__img-box img {
        width: 100%;
        height: 100%;
        object-fit: cover; /* مهم‌ترین اصلاح */
        display: block;
    }

    .courses-one__single {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .courses-one__content {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .courses-one__tag-and-meta {
        margin-bottom: 10px;
    }

    .courses-one__title {
        min-height: 48px; /* جلوگیری از بهم ریختگی عنوان */
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .courses-one__ratting-and-heart-box {
        margin-top: auto; /* می‌بره پایین کارت */
    }

    .courses-one__btn-and-doller-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
    }

    .price, .old-price, .new-price {
        white-space: nowrap;
    }
</style>
@endpush

<section class="courses-one">
    <div class="container">
        <div class="section-title text-center sec-title-animation animation-style1">
            <div class="section-title__tagline-box">
                <div class="section-title__tagline-shape"></div>
                <span class="section-title__tagline">دوره های ما</span>
            </div>
            <h2 class="section-title__title title-animation">{{$data['title'] ?? ''}}
                <span> <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span></h2>
        </div>
        <div class="courses-one__carousel owl-theme owl-carousel">
            @foreach($courses ?? [] as $course)
                <div class="item">
                    <div class="courses-one__single">
                        <div class="courses-one__img-box">
                            <div class="courses-one__img">
                                @if($course->featuredImage)
                                    <img src="{{ asset('storage/' . $course->featuredImage->file_path) }}" alt="">
                                @endif
                            </div>
                        </div>
                        <div class="courses-one__content">
                            <div class="courses-one__tag-and-meta">
                                <div class="courses-one__tag">
                                    <span>{{$course->category?->name}}</span>
                                </div>
                                <ul class="courses-one__meta list-unstyled">
                                    <li>
                                        <div class="icon">
                                            <span class="icon-book"></span>
                                        </div>
                                        <p> {{$course->lessons_count}} جلسه</p>
                                    </li>
                                    <li>
                                        <div class="icon">
                                            <span class="icon-clock"></span>
                                        </div>
                                        <p>{{$course->duration}} ساعت</p>
                                    </li>
                                </ul>
                            </div>
                            <h6 class="courses-one__title"><a href="{{route('courses.show',$course->slug)}}">{{$course->title}}</a></h6>
                            <div class="courses-one__ratting-and-heart-box">
                                <div class="courses-one__ratting-box">

                                    <ul class="courses-one__ratting list-unstyled">
                                        @php
                                            $rating = round($course->ratings_avg_rating ?? 0);
                                        @endphp
                                        @for($i = 1; $i <= 5; $i++)
                                            <li>
                                                <span class="icon-star {{ $i <= $rating ? 'active' : '' }}"></span>
                                            </li>
                                        @endfor
                                    </ul>
                                    <p class="courses-one__ratting-text">{{$course->comments_count}} دیدگاه</p>
                                </div>
                                <div class="courses-one__heart">
                                    <a href="{{route('courses.show',$course->slug)}}"><span class="icon-heart"></span></a>
                                </div>
                            </div>
                            <div class="courses-one__btn-and-doller-box">
                                <div class="courses-one__btn-box">
                                    <a href="{{ route('courses.show', $course->slug) }}" class="courses-one__btn thm-btn">
                                        <span class="far fa-angle-double-left"></span>
                                        خرید
                                    </a>
                                </div>

                                @if($course->is_free)


                                    <span class="price">رایگان</span>
                                @elseif($course->discount_price && $course->discount_price < $course->price)

                                    <span class="old-price">
                                        {{ number_format($course->price) }} تومان
                                    </span>

                                    <span class="new-price">
        {{ number_format($course->discount_price) }} تومان
    </span>

                                @else

                                    <span class="price">
        {{ number_format($course->price) }} تومان
    </span>

                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            @endforeach

        </div>
    </div>
</section>
