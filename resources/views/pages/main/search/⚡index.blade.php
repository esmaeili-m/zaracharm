<?php

use Livewire\Component;

new class extends Component
{
    public $articles,$courses,$category;
    public function mount()
    {
        $categorySlug = request()->get('category_id');
        $search = request()->get('search');

        $this->category = null;

        if ($categorySlug) {
            $this->category = \App\Models\Category::active()
                ->where('slug', $categorySlug)
                ->first();
        }

        $articlesQuery = \App\Models\Article::active()
            ->with('category')
            ->withCount('comments');

        $coursesQuery = \App\Models\Course::active()
            ->with(['media', 'category'])
            ->withCount([
                'sections',
                'lessons',
                'likes',
                'comments'
            ])
            ->withAvg('ratings', 'rating');

        if ($this->category) {
            $articlesQuery->where('category_id', $this->category->id);
            $coursesQuery->where('category_id', $this->category->id);
        }

        if ($search) {
            $articlesQuery->where('title', 'like', "%{$search}%");

            $coursesQuery->where('title', 'like', "%{$search}%");
        }

        $this->articles = $articlesQuery->get();

        $this->courses = $coursesQuery->get();
    }
};
?>

<div>
    @if($courses->count())
        <section class="course-carousel-page mt-5">
            <div class="container">
                <div class="section-title text-center sec-title-animation animation-style1">
                    <div class="section-title__tagline-box">
                        <div class="section-title__tagline-shape"></div>
                        <span class="section-title__tagline">دوره های ما</span>
                    </div>
                    <h2 class="section-title__title title-animation">لیست دوره ها {{' - '. $category->name}}
                        <span> <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span></h2>
                </div>
                <div class="course-carousel-style owl-carousel owl-theme carousel-dot-style">
                    @foreach($courses ?? [] as $course)
                        <div class="item">
                            <div class="courses-two__single">
                                <div class="courses-two__img-box">
                                    <div class="courses-two__img">
                                        <img src="{{$course->featuredImageUrl}}" alt="">
                                    </div>
                                    <div class="courses-two__heart">
                                        <a href="course-details.html"><span class="icon-heart"></span></a>
                                    </div>
                                </div>
                                <div class="courses-two__content">
                                    <div class="courses-two__doller-and-review">
                                        <div class="courses-two__doller">
                                            <p>{{number_format($course->price)}} تومان</p>
                                        </div>
                                        <div class="courses-two__review">
                                            <p><i class="icon-star"></i> {{$course->ratings_avg_rating ?? 0}} <span>({{$course->comments_count}} دیدگاه)</span></p>
                                        </div>
                                    </div>
                                    <h3 class="courses-two__title"><a href="course-details.html">{{$course->title}}</a></h3>
                                    <div class="courses-two__btn-and-client-box">
                                        <div class="courses-two__btn-box">
                                            <a href="course-details.html" class="thm-btn-two">
                                                <span>ثبت نام</span>
                                                <i class="far fa-angle-double-left"></i>
                                            </a>
                                        </div>
                                        <div class="courses-two__client-box">
                                            <div class="courses-two__client-img">
                                                <img src="{{$course->user->avatarUrl}}" alt="">
                                            </div>
                                            <div class="courses-two__client-content">
                                                <h4>{{$course->user->name}}</h4>
                                                <p>
                                                    مدرس دوره</p>
                                            </div>
                                        </div>
                                    </div>
                                    <ul class="courses-two__meta list-unstyled">
                                        <li>
                                            <div class="icon">
                                                <span class="icon-chart-simple"></span>
                                            </div>
                                            <p>{{$course->level_fa}}</p>
                                        </li>
                                        <li>
                                            <div class="icon">
                                                <span class="icon-book"></span>
                                            </div>
                                            <p>{{$course->lessons_count}}  جلسه </p>
                                        </li>
                                        <li>
                                            <div class="icon">
                                                <span class="icon-clock"></span>
                                            </div>
                                            <p>{{$course->duration}} ساعت </p>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

    @endif
    @if($articles->count())
        <section class="blog-page mt-5">
            <div class="container">
                <div class="section-title text-center sec-title-animation animation-style1">
                    <div class="section-title__tagline-box">
                        <div class="section-title__tagline-shape"></div>
                        <span class="section-title__tagline">مقالات ما</span>
                    </div>
                    <h2 class="section-title__title title-animation">    لیست مقالات{{' - '. $category->name}}
                        <span> <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span></h2>
                </div>
                <div class="row">
                    @foreach($this->articles as $article)
                        <div class="col-xl-4 col-lg-4 col-md-6 wow fadeInUp animated"
                             data-wow-delay="100ms" style="visibility: visible; animation-delay: 100ms; animation-name: fadeInUp;">
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

                    @endforeach

                </div>

            </div>
        </section>
    @endif

</div>
