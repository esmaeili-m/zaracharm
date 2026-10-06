<?php

use Livewire\Component;
use App\Models\Course;
use App\Models\Rating;

new class extends Component
{
    public \App\Models\Course $course;
    public $lastUpdate;
    public $avgRating;
    public $avgRatingTeacher;
    public $totalRatings = 0;
    public $ratingsCount = [];
    public $body;
    public $rating = 1;
    public $parent_id = null;
    public $comments;
    public $rating1;

    public function mount($slug)
    {
        $this->course = Course::query()
            ->with(['media', 'user', 'sections.lessons.media', 'category'])
            ->withAvg('ratings', 'rating')
            ->where('slug', $slug)
            ->firstOrFail();

        $this->lastUpdate = collect([
            $this->course->updated_at,
            $this->course->sections()->max('updated_at'),
            $this->course->lessons()
                ->withoutGlobalScopes()
                ->max('course_lessons.updated_at'),
        ])->filter()->max();

        $teacher = \App\Models\User::find($this->course->user_id);

        $this->avgRatingTeacher = Rating::query()
            ->where('rateable_type', Course::class)
            ->whereIn('rateable_id', $teacher->courses()->pluck('courses.id'))
            ->avg('rating');

        $this->calculateRatings();

        // ✅ فقط نظرات تایید شده + eager load صحیح
        $this->comments = $this->course->comments()
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')   // ✅ فقط نظرات اصلی (نه reply‌ها)
            ->latest()
            ->get();
    }

    public function calculateRatings()
    {
        $ratings = $this->course->ratings;

        $this->totalRatings = $ratings->count();
        $this->avgRating    = $ratings->avg('rating') ?? 0;

        $this->ratingsCount = [
            5 => $ratings->where('rating', 5)->count(),
            4 => $ratings->where('rating', 4)->count(),
            3 => $ratings->where('rating', 3)->count(),
            2 => $ratings->where('rating', 2)->count(),
            1 => $ratings->where('rating', 1)->count(),
        ];
    }

    public function change_route()
    {
        if (!auth()->check()) {
            return $this->redirect(route('login'));
        }

        $hasCourse = auth()->user()
            ->courses()
            ->where('course_id', $this->course->id)
            ->exists();
        if ($hasCourse) {
            return $this->redirect(
                route('courses.learn', $this->course->slug),
            );
        }

        $cart = auth()->user()->cart()->firstOrCreate([]);

        $exists = $cart->items()
            ->where('course_id', $this->course->id)
            ->exists();

        if (!$exists) {
            $cart->items()->create([
                'course_id' => $this->course->id,
                'price' => $this->course->is_free
                    ? 0
                    : ($this->course->discount_price ?? $this->course->price ?? 0),
                ]);
        }

        return $this->redirect(route('cart.index'));
    }

    public function setRating($value)
    {
        if (!$this->canRate()) return;
        $this->rating1 = $value;
    }

    public function canRate(): bool
    {
        return auth()->check()
            && $this->course->users()
                ->where('user_id', auth()->id())
                ->exists();
    }

    public function saveComment()
    {
        $this->validate([
            'body' => 'required|min:3',
        ], [
            'body.required' => 'لطفاً متن دیدگاه را وارد کنید.',
        ]);

        \App\Models\Comment::create([
            'commentable_type' => get_class($this->course),
            'commentable_id'   => $this->course->id,
            'user_id'          => auth()->id(),
            'parent_id'        => $this->parent_id,
            'body'             => $this->body,
            'is_approved'      => false,
        ]);

        if ($this->canRate() && $this->rating1 > 0) {
            \App\Models\Rating::updateOrCreate(
                [
                    'user_id'       => auth()->id(),
                    'rateable_id'   => $this->course->id,
                    'rateable_type' => get_class($this->course),
                ],
                ['rating' => $this->rating1]
            );
        }

        // ✅ بعد از ثبت نظر، لیست را refresh کن
        $this->comments = $this->course->comments()
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')
            ->latest()
            ->get();

        $this->calculateRatings();

        $this->reset(['body', 'rating', 'parent_id']);
    }

    public function setReply($id)
    {
        $this->parent_id = $id;
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

    </style>

@endpush
<div>
    <section class="course-details mt-5">
        <div class="container">
            <div class="row">
                <div class="col-xl-8 col-lg-7">
                    <div class="course-details__left">
                        <div class="course-details__img">
                            <img src="{{ $course->bannerImageUrl }}" alt="">
                        </div>
                        <div class="course-details__content">
                            @if($course->category)
                                <div class="course-details__tag-box mb-1">
                                    <div class="course-details__tag-shape"></div>
                                    <span class="course-details__tag">{{ $course->category?->name }}</span>
                                </div>
                            @endif

                            @foreach($course->tags()->get() as $tag)
                                <a href="#">
                                    <div class="course-details__tag-box mb-1">
                                        <div class="course-details__tag-shape"></div>
                                        <span class="course-details__tag">{{ $tag->name }}</span>
                                    </div>
                                </a>
                            @endforeach

                            <h3 class="course-details__title">{{ $course->title }}</h3>

                            <div class="course-details__client-and-ratting-box">
                                <div class="course-details__client-box">
                                    <div class="course-details__client-img">
                                        <img src="{{ $course->user->avatarUrl }}" alt="">
                                    </div>
                                    <div class="course-details__client-content">
                                        <p><span class=""></span>نام مدرس</p>
                                        <h4>{{ $course->user->name }}</h4>
                                    </div>
                                </div>
                                <div class="course-details__ratting-box-1">
                                    <ul class="course-details__ratting-list-1 list-unstyled">
                                        <li>
                                            <p>آخرین به روزرسانی</p>
                                            <h4>{{ verta($lastUpdate)->format('Y/m/d') }}</h4>
                                        </li>
                                        <li>
                                            <p>شرکت کنندگان</p>
                                            <h4>{{ $course->users()->count() }} دانشجو</h4>
                                        </li>
                                        <li>
                                            <p>({{ number_format($course->ratings_avg_rating, 1) }} / 5 امتیاز)</p>
                                            <ul class="course-details__ratting list-unstyled">
                                                <ul class="courses-one__ratting list-unstyled">
                                                    @php $rating = round($course->ratings_avg_rating ?? 0); @endphp
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <li>
                                                            <span class="icon-star {{ $i <= $rating ? 'active' : '' }}"></span>
                                                        </li>
                                                    @endfor
                                                </ul>
                                            </ul>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="course-details__main-tab-box tabs-box">
                                <ul class="tab-buttons list-unstyled">
                                    <li data-tab="#overview" class="tab-btn active-btn tab-btn-one">
                                        <p><span class="icon-pen-ruler"></span>توضیحات دوره</p>
                                    </li>
                                    <li data-tab="#curriculum" class="tab-btn tab-btn-two">
                                        <p><span class="icon-book"></span>سرفصل و پیش نیازها</p>
                                    </li>
                                    <li data-tab="#instructor" class="tab-btn tab-btn-three">
                                        <p><span class="icon-graduation-cap"></span>نام مدرس</p>
                                    </li>
                                    <li data-tab="#review" class="tab-btn tab-btn-four">
                                        <p><span class="icon-comments"></span>دیدگاه</p>
                                    </li>
                                </ul>

                                <div class="tabs-content">
                                    {{-- Tab: Overview --}}
                                    <div class="tab active-tab" id="overview">
                                        <div class="course-details__tab-inner">
                                            <div class="course-details__overview">
                                                {!! $course->description !!}
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Tab: Curriculum --}}
                                    <div class="tab" id="curriculum">
                                        <div class="course-details__tab-inner">
                                            <div class="course-details__curriculam">
                                                <h3 class="course-details__curriculam-title">سرفصل ها</h3>
                                                <p class="course-details__curriculam-text">{{ $course->short_description }}</p>
                                                <div class="course-details__curriculam-faq">
                                                    <div class="accrodion-grp" data-grp-name="faq-one-accrodion">
                                                        @foreach($course->sections as $section)
                                                            <div class="accrodion">
                                                                <div class="accrodion-title">
                                                                    <div class="accrodion-title-box">
                                                                        <div class="accrodion-title__count"></div>
                                                                        <div class="accrodion-title-text">
                                                                            <h4>{{ $section->title }}</h4>
                                                                        </div>
                                                                    </div>
                                                                    <ul class="accrodion-meta list-unstyled">
                                                                        <li>
                                                                            <p><span class="icon-book"></span>
                                                                                {{ $section->lessons()->active()->count() }} جلسه
                                                                            </p>
                                                                        </li>
                                                                        <li>
                                                                            <p><span class="icon-clock"></span>
                                                                                {{ $section->lessons()->sum('duration') }} ساعت
                                                                            </p>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                                <div class="accrodion-content">
                                                                    <div class="inner">
                                                                        <h3 class="accrodion-content__title">خلاصه</h3>
                                                                        <p class="accrodion-content__text">{!! nl2br(e($section->description ?? '')) !!}</p>
                                                                        <ul class="accrodion-content__points list-unstyled">
                                                                            @foreach($section->lessons()->active()->get() as $lesson)
                                                                                <li>
                                                                                    <p class="accrodion-content__points-text">
                                                                                        <span class="fal fa-video"></span>{{ $lesson->title }}
                                                                                    </p>
                                                                                    <div class="accrodion-content__icon">
                                                                                        @if($lesson->is_free)
                                                                                            <a class="video-popup"
                                                                                               href="{{ $lesson->media->where('collection','demo')->first()?->file_path
                                                                                            ? asset('storage/' . $lesson->media->where('collection','demo')->first()->file_path)
                                                                                            : $lesson->media->where('collection','demo')->first()?->external_url }}">
                                                                                                <span class="far fa-play-circle mx-3"></span>
                                                                                            </a>
                                                                                            <span class="far fa-unlock-alt"></span>
                                                                                        @else
                                                                                            <span class="far fa-lock-alt"></span>
                                                                                        @endif
                                                                                    </div>
                                                                                </li>
                                                                            @endforeach
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

                                    {{-- Tab: Instructor --}}
                                    <div class="tab" id="instructor">
                                        <div class="course-details__tab-inner">
                                            <div class="course-details__Instructor">
                                                <div class="course-details__Instructor-img">
                                                    <img src="{{ $course->user->avatarUrl }}" alt="">
                                                </div>
                                                <div class="course-details__Instructor-content">
                                                    <div class="course-details__Instructor-client-name-box-and-view">
                                                        <div class="course-details__Instructor-client-name-box">
                                                            <h4>{{ $course->user->name }}</h4>
                                                            <p>مدرس</p>
                                                        </div>
                                                        <div class="course-details__Instructor-view">
                                                            <a href="#">مشاهده دوره ها<span class="far fa-angle-double-left"></span></a>
                                                        </div>
                                                    </div>
                                                    <ul class="course-details__Instructor-ratting-list list-unstyled">
                                                        <li>
                                                            {{-- ✅ ترتیب صحیح: avgRating / 5 --}}
                                                            <p><span class="fas fa-star"></span>({{ number_format($avgRatingTeacher ?? 0, 1) }} / 5 امتیاز)</p>
                                                        </li>
                                                        <li>
                                                            <p><span class="fas fa-play-circle"></span>
                                                                {{ \App\Models\Course::where('user_id', $course->user->id)->count() }} دوره
                                                            </p>
                                                        </li>
                                                    </ul>
                                                    <p class="course-details__Instructor-text">
                                                        {!! nl2br(e($course->user->description ?? '')) !!}
                                                    </p>
                                                    <livewire:main.social-links :socials="$course->user->socials" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Tab: Review --}}
                                    <div class="tab" id="review" wire:ignore.self>
                                        <div class="course-details__tab-inner">
                                            <div class="course-details__ratting-and-review-box">
                                                <ul class="course-details__ratting-box list-unstyled">
                                                    @foreach([5, 4, 3, 2, 1] as $star)
                                                        @php
                                                            $count   = $ratingsCount[$star] ?? 0;
                                                            $percent = $totalRatings ? round(($count / $totalRatings) * 100) : 0;
                                                        @endphp
                                                        <li>
                                                            <div class="course-details__ratting-list">
                                                                @for($i = 1; $i <= 5; $i++)
                                                                    @if($i <= $star)
                                                                        <span class="icon-star"></span>
                                                                    @else
                                                                        <span class="fill-white icon-star"></span>
                                                                    @endif
                                                                @endfor
                                                            </div>
                                                            <div class="progress-levels">
                                                                <div class="progress-box">
                                                                    <div class="bar">
                                                                        <div class="bar-innner">
                                                                            <div class="skill-percent">
                                                                                <span class="count-text">{{ $percent }}</span>
                                                                                <span class="percent">%</span>
                                                                            </div>
                                                                            <div class="bar-fill" data-percent="{{ $percent }}"></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>

                                                <div class="course-details__review-box">
                                                    <div class="course-details__review-count">
                                                        <span class="odometer" data-count="{{ number_format($avgRating, 1) }}">
                                                            {{ number_format($avgRating, 1) }}
                                                        </span>
                                                    </div>
                                                    <div class="course-details__review-content">
                                                        <p>{{ $totalRatings }} دیدگاه</p>
                                                        <ul class="course-details__review-ratting list-unstyled">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                @if($i <= round($avgRating))
                                                                    <li><span class="icon-star"></span></li>
                                                                @else
                                                                    <li><span class="fill-white icon-star"></span></li>
                                                                @endif
                                                            @endfor
                                                        </ul>
                                                        <div class="course-details__review-text">
                                                            <p>
                                                                <span class="icon-star"></span>
                                                                @if($avgRating >= 4.5) عالی
                                                                @elseif($avgRating >= 3.5) خوب
                                                                @elseif($avgRating >= 2.5) متوسط
                                                                @else ضعیف
                                                                @endif
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="comment-one">
                                                {{-- ✅ استفاده از property به جای کوئری مستقیم --}}
                                                <h3 class="comment-one__title">دیدگاه ها ({{ $comments->count() }})</h3>

                                                <ul class="comment-one__single-list list-unstyled">
                                                    @forelse($comments as $comment)
                                                        <li>
                                                            <div class="comment-one__single">
                                                                <div class="comment-one__image-box">
                                                                    <div class="comment-one__image">
                                                                        <img src="{{ $comment->user->avatarUrl }}">
                                                                    </div>
                                                                </div>
                                                                <div class="comment-one__content">
                                                                    <div class="comment-one__name-box">
                                                                        <h4>
                                                                            {{ $comment->user?->name }}
                                                                            <span>مدرس</span>
                                                                        </h4>
                                                                    </div>
                                                                    <p>{{ $comment->body }}</p>
                                                                    <div class="comment-one__btn-box">
                                                                        <a href="javascript:void(0)"
                                                                           wire:click="setReply({{ $comment->id }})"
                                                                           class="comment-one__btn">پاسخ</a>
                                                                        <span>{{ $comment->created_at->format('Y/m/d') }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            @if($comment->replies->isNotEmpty())
                                                                <ul class="comment-one__single-list comment-one__single-list-2 list-unstyled">
                                                                    @foreach($comment->replies as $reply)
                                                                        <li>
                                                                            <div class="comment-one__single">
                                                                                <div class="comment-one__image-box">
                                                                                    <div class="comment-one__image">
                                                                                        <img src="{{ $reply->user->avatarUrl }}">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="comment-one__content">
                                                                                    <h4>
                                                                                        {{ $reply->user?->name }}
                                                                                        <span>مدرس</span>
                                                                                    </h4>
                                                                                    <p>{{ $reply->body }}</p>
                                                                                    <span>{{ $reply->created_at->format('Y/m/d') }}</span>
                                                                                </div>
                                                                            </div>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </li>
                                                    @empty
                                                        <li>
                                                            <p class="text-center text-muted">هنوز دیدگاهی ثبت نشده است</p>
                                                        </li>
                                                    @endforelse
                                                </ul>
                                            </div>

                                            @if(auth()->check())
                                                <div class="comment-form">
                                                    <h3 class="comment-form__title">ثبت دیدگاه</h3>
                                                    <div class="comment-form__text-and-ratting">
                                                        <p class="comment-form__text">امتیاز شما</p>
                                                        <ul class="comment-form__ratting list-unstyled">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                <li wire:key="rat-{{ $i }}" style="cursor: pointer">
                                                                    <span wire:click="setRating({{ $i }})"
                                                                          class="icon-star {{ $i <= $rating1 ? 'is-active' : '' }}"></span>
                                                                </li>
                                                            @endfor
                                                        </ul>
                                                    </div>
                                                    <form wire:submit.prevent="saveComment"
                                                          class="comment-form__form contact-form-validated">
                                                        <div class="row">
                                                            <div class="col-xl-12">
                                                                <div class="comment-form__input-box text-message-box">
                                                                    <textarea wire:model="body"
                                                                              placeholder="متن دیدگاه"></textarea>
                                                                </div>
                                                                @error('body')
                                                                <div class="text-danger">{{ $message }}</div>
                                                                @enderror
                                                                @if(!$this->canRate())
                                                                    <div class="alert alert-warning mt-2">
                                                                        فقط دانشجویانی که این دوره را خریداری کرده‌اند می‌توانند امتیاز ثبت کنند.
                                                                    </div>
                                                                @endif
                                                                <div class="comment-form__btn-box">
                                                                    <button type="submit" class="comment-form__btn">
                                                                        <span class="icon-circle-rightsvg"></span>ثبت
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    <div class="result"></div>
                                                </div>
                                            @endif

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="col-xl-4 col-lg-5">
                    <div class="course-details__right">
                        <div class="course-details__info-box">
                            <div class="course-details__video-link">
                                <div class="course-details__video-link-bg"
                                     style="background-image: url({{ $course->featuredImageUrl }});">
                                </div>
                                @php
                                    $previewLesson = $course->sections
                                        ->flatMap(fn($s) => $s->lessons)
                                        ->first(fn($l) => $l->is_free && $l->media->isNotEmpty());
                                @endphp
                                @if($previewLesson)
                                    <a href="{{ $previewLesson->media->first()?->file_path
        ? asset('storage/' . $previewLesson->media->first()->file_path)
        : $previewLesson->media->first()?->external_url }}"
                                       class="video-popup">
                                        <div class="course-details__video-icon">
                                            <span class="icon-play"></span>
                                            <i class="ripple"></i>
                                        </div>
                                    </a>
                                @endif
                            </div>

                            <div class="course-details__doller-and-btn-box">
                                <h3 class="course-details__doller">
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

                                </h3>
                                <div class="course-details__doller-btn-box">
                                    <a wire:click="change_route()" class="thm-btn-two cursor-pointer">
                                        <span>
                                            {{ auth()->check() && auth()->user()->hasPurchasedCourse($course->id)
                                                ? 'ورود به دوره'
                                                : 'ثبت نام' }}
                                        </span>
                                        <i class="far fa-angle-double-left"></i>
                                    </a>
                                </div>
                            </div>
                            @php
                                $url = urlencode(url()->current());
                                $title = urlencode($course->title);
                            @endphp

                            <div class="course-details__info-list">
                                <h3 class="course-details__info-list-title">اطلاعات دوره</h3>
                                <ul class="course-details__info-list-1 list-unstyled">
                                    <li>
                                        <p><i class="icon-book"></i>تعداد ویدیو</p>
                                        {{-- ✅ تعداد ویدیوهای همین دوره --}}
                                        <span>
                                            {{ $course->sections->flatMap(fn($s) => $s->lessons)->count() }} جلسات
                                        </span>
                                    </li>
                                    <li>
                                        <p><i class="icon-clock"></i>مدت دوره</p>
                                        <span>{{ $course->duration }} ساعت</span>
                                    </li>
                                    @if($course->level)
                                        <li>
                                            <p><i class="icon-chart-simple"></i>سطح مهارت</p>
                                            <span>{{ $course->level_fa }}</span>
                                        </li>
                                    @endif
                                    <li>
                                        <p><i class="icon-globe"></i>زبان</p>
                                        <span>فارسی</span>
                                    </li>
                                    <li>
                                        <p><i class="icon-user-plus"></i>مدرس</p>
                                        <span>{{ $course->user->name }}</span>
                                    </li>
                                </ul>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>
