
<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
new class extends Component
{
    use WithPagination;
    public $blogs;

    #[Computed]
    public function courses()
    {
        return \App\Models\Course::active()
            ->with(['media', 'category'])
            ->withCount([
                'sections',
                'lessons',
                'likes',
                'comments'
            ])
            ->withAvg('ratings', 'rating')
            ->orderBy('sort')
            ->paginate(9);
    }


};
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
            color: #999;
            margin-left: 8px;
            font-size: 14px;
        }

        .new-price{
            color: #984695;
            font-weight: 700;
            font-size: 18px;
        }
        .course-carousel-style .item {
            height: 100%;
        }

        .courses-two__single {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .courses-two__img {
            height: 240px;
            overflow: hidden;
        }

        .courses-two__img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .courses-two__content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .courses-two__title {
            min-height: 60px;
            margin-bottom: 15px;
        }

        .courses-two__title a {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .courses-two__btn-and-client-box {
            margin-top: auto;
        }

        .courses-two__client-content h4 {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }
    </style>

@endpush
<section class="course-carousel-page mt-5 ">
    <div class="container">

        <div class="section-title text-center sec-title-animation animation-style1">
            <div class="section-title__tagline-box">
                <div class="section-title__tagline-shape"></div>
                <span class="section-title__tagline">دوره های ما</span>
            </div>

            <h2 class="section-title__title title-animation">
                لیست دوره ها
                <span>
                    <img src="{{ asset('main/images/shapes/section-title-shape-1.png') }}" alt="">
                </span>
            </h2>
        </div>
        <div class="col-xl-12 col-lg-12">
            <div class="course-grid__right">
                <div class="course-grid__right-content-box">
                    <div class="row">
                        @foreach($this->courses ?? [] as $course)

                        <!--Courses Two Single Start-->
                        <div class="col-xl-4 mb-2">
                            <div class="courses-two__single">
                                <div class="courses-two__img-box">
                                    <div class="courses-two__img">
                                        <img src="{{ $course->featuredImageUrl }}" alt="">
                                    </div>
                                    <div class="courses-two__heart">
                                        <a href="{{ route('courses.show', $course->slug) }}"><span class="icon-heart"></span></a>
                                    </div>
                                </div>
                                <div class="courses-two__content">
                                    <div class="courses-two__doller-and-review">
                                        <div class="courses-two__doller">
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
                                        <div class="courses-two__review">
                                            <p>
                                                <i class="icon-star"></i>
                                                {{ round($course->ratings_avg_rating ?? 0, 1) }}
                                                <span>
                                            ({{ $course->comments_count }} دیدگاه)
                                        </span>
                                            </p>
                                        </div>
                                    </div>
                                    <h3 class="courses-two__title"><a href="{{ route('courses.show', $course->slug) }}">{{ $course->title }}</a></h3>
                                    <div class="courses-two__btn-and-client-box">
                                        <div class="courses-two__btn-box">
                                            <a href="{{ route('courses.show', $course->slug) }}" class="thm-btn-two">
                                                <span>ثبت نام</span>
                                                <i class="far fa-angle-double-left"></i>
                                            </a>
                                        </div>
                                        <div class="courses-two__client-box">

                                            <div class="courses-two__client-img">
                                                <img src="{{ $course->user->avatarUrl }}"
                                                     alt="{{ $course->user->name }}">
                                            </div>

                                            <div class="courses-two__client-content">
                                                <h4>{{ $course->user->name }}</h4>
                                                <p>مدرس دوره</p>
                                            </div>

                                        </div>
                                    </div>
                                    <ul class="courses-two__meta list-unstyled">

                                        <li>
                                            <div class="icon">
                                                <span class="icon-chart-simple"></span>
                                            </div>
                                            <p>{{ $course->level_fa }}</p>
                                        </li>

                                        <li>
                                            <div class="icon">
                                                <span class="icon-book"></span>
                                            </div>
                                            <p>{{ $course->lessons_count }} جلسه</p>
                                        </li>

                                        <li>
                                            <div class="icon">
                                                <span class="icon-clock"></span>
                                            </div>
                                            <p>{{ $course->duration }} ساعت</p>
                                        </li>

                                    </ul>
                                </div>
                            </div>
                        </div>
                        @endforeach

                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-12">
                        {{ $this->courses->links('vendor/livewire.custom-pagination') }}

                    </div>
                </div>
            </div>
        </div>

    </div>
</section>


