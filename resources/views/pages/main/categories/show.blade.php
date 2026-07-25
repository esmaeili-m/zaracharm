<?php

use Livewire\Component;

new class extends Component
{
    public $articles,$courses,$category;
    public function mount($slug)
    {
        $this->category = \App\Models\Category::where('slug', $slug)
            ->active()
            ->first();
        if (! $this->category) {
            abort(404);
        }

        $categoryIds = \App\Models\Category::where('parent_id', $this->category->id)
            ->pluck('id')
            ->push($this->category->id);

        $this->articles = \App\Models\Article::active()
            ->whereIn('category_id', $categoryIds)
            ->with('category')
            ->withCount('comments')
            ->latest()
            ->get();

        $this->courses = \App\Models\Course::active()
            ->whereIn('category_id', $categoryIds)
            ->with([
                'media',
                'category'
            ])
            ->withCount([
                'sections',
                'lessons',
                'likes',
                'comments',
            ])
            ->withAvg('ratings', 'rating')
            ->latest()
            ->take(9)
            ->get();
    }
};
?>

@push('styles')
    <style>
        .blog-two__img {
            height: 250px;
            overflow: hidden;
        }

        .blog-two__img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .line-clamp-4 {
            display: -webkit-box;
            -webkit-line-clamp: 4;
            line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .blog-two__single{
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .blog-two__content{
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .blog-two__text{
            flex: 1;
        }
    </style>
    <style>
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
<div>

    @if($courses->count() == 0 && $articles->count() == 0)
        <section class="error-page page-header"
         style="padding-top: 140px; padding-bottom: 100px">
            <div class="container">
                <div class="error-page__inner text-center">
                    <div class="error-page__img float-bob-y">
                        <img src="{{asset('main')}}/images/resources/error-page-img1.png" alt="">
                    </div>

                    <div class="error-page__content">
                        <h2 class="mb-2">داده‌ای در این دسته‌بندی یافت نشد!</h2>
                        <p class="mb-2">متأسفانه هیچ موردی برای این دسته‌بندی وجود ندارد. ممکن است هنوز محتوایی اضافه نشده باشد.</p>
                        <div class="btn-box">
                            <a class="thm-btn" href="{{route('home')}}"> <span class="far fa-angle-double-left"></span> بازگشت به صفحه اصلی</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
        @if($courses->count() > 0)
            <section   class="course-carousel-page mt-5">
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
                             @foreach($courses ?? [] as $course)

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
                </div>
            </div>

        </div>
    </section>
        @endif
        @if($articles->count() > 0)
            <section class="blog-page mt-5">
        <div class="container">
            <div class="section-title text-center sec-title-animation animation-style1">
                <div class="section-title__tagline-box">
                    <div class="section-title__tagline-shape"></div>
                    <span class="section-title__tagline">مقالات ما</span>
                </div>
                <h2 class="section-title__title title-animation">لیست مقالات
                    <span> <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span></h2>
            </div>
            <div class="row g-4">
                @foreach($this->articles as $article)
                    <div class="col-xl-4 col-lg-4 col-md-6 d-flex">
                        <div class="blog-two__single w-100 wow fadeInUp animated" data-wow-delay="100ms" style="visibility: visible; animation-delay: 100ms; animation-name: fadeInUp;">
                            <div class="blog-two__single">
                                <div class="blog-two__img">
                                    <img src="{{$article->featuredImageUrl}}" alt="">
                                    <div class="blog-two__date">
                                        <span class="icon-calendar"></span>
                                        <p>{{($article->published_at)}}</p>
                                    </div>
                                </div>
                                <div class="blog-two__content">
                                    <div class="blog-two__meta-box">
                                        <ul class="blog-two__meta list-unstyled">
                                            <li>
                                                <a href="{{route('articles.show',$article->slug)}}"><span class="icon-tags"></span>{{$article->category?->name}}</a>
                                            </li>
                                            <li>
                                                <a href="{{route('articles.show',$article->slug)}}"><span class="icon-contact"></span>({{$article->views_count}} بازدید)</a>
                                            </li>
                                            <li>
                                                <a href="{{route('articles.show',$article->slug)}}"><span class="icon-comments"></span>({{$article->comments_count}} دیدگاه)</a>
                                            </li>
                                        </ul>
                                    </div>
                                    <h4 class="blog-two__title"><a href="{{route('articles.show',$article->slug)}}">{{$article->title}}</a></h4>
                                    <p class="blog-two__text line-clamp-4">
                                        {!! nl2br(e($article->short_description)) !!}
                                    </p></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
        @endif
</div>
