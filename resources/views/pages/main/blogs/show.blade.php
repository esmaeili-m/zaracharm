<?php

use Livewire\Component;
use App\Models\Article;
use App\Models\Category;
new class extends Component
{
    public $article,$tags,$last_articles,$categories,$search='',$email,$name,$body;
    public function mount($slug)
    {
        $this->article = Article::where('slug', $slug)->withCount('comments')->with('comments')->first();
        $this->last_articles = Article::active()->latest()->take(4)->with(['category'])->get();
        $this->categories = Category::active()->inRandomOrder()->whereNull('parent_id')->take(4)->get();
        $this->tags = \App\Models\Tag::inRandomOrder()->take(10)->get();

    }

    public function fillter()
    {
        $data = $this->validate(
            [
                'search' => 'required|string|min:2|max:100',
            ],
            [
                'search.required' => 'لطفاً عبارت جستجو را وارد کنید.',
                'search.string'   => 'عبارت جستجو معتبر نیست.',
            ]
        );
        return redirect()->route('search' ,['search' => $this->search]);
    }
    public function subscribe()
    {
        $this->validate();

        \App\Models\Newsletter::create([
            'email' => $this->email,
            'subscribed_at' => now(),
        ]);

        $this->reset('email');

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عضویت موفق',
            text: 'شما با موفقیت در خبرنامه عضو شدید.'
        );
    }
    protected function messages()
    {
        return [
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً در خبرنامه عضو شده است.',
        ];
    }
    protected function rules()
    {
        return [
            'email' => ['required', 'email', 'unique:newsletters,email'],
        ];
    }
    public function saveComment()
    {
        $this->validate(
            [
                'name' => 'required|string|min:2|max:100',
                'body' => 'required|string|min:10|max:2000',
            ],
            [
                'name.required' => 'لطفاً نام خود را وارد کنید.',
                'name.string'   => 'نام وارد شده معتبر نیست.',
                'name.min'      => 'نام باید حداقل ۲ کاراکتر باشد.',
                'name.max'      => 'نام نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',

                'body.required' => 'لطفاً متن دیدگاه را وارد کنید.',
                'body.string'   => 'متن دیدگاه معتبر نیست.',
                'body.min'      => 'متن دیدگاه باید حداقل ۱۰ کاراکتر باشد.',
                'body.max'      => 'متن دیدگاه نمی‌تواند بیشتر از ۲۰۰۰ کاراکتر باشد.',
            ]
        );

        \App\Models\Comment::create([
            'commentable_type' => get_class($this->article),
            'commentable_id'   => $this->article->id,
            'user_id'          => auth()->id(),
            'name'             => $this->name,
            'body'             => $this->body,
            'is_approved'      => false,
        ]);


        $this->reset(['body', 'name']);
    }

};
?>

<div>
    <section class="blog-details mt-5">
        <div class="container">
            <div class="row">
                <div class="col-xl-8 col-lg-7">
                    <div class="blog-details__left">
                        <div class="blog-details__img-box">
                            <div class="blog-details__img">
                                <img src="{{$article->bannerImageUrl}}" alt="">
                            </div>
                        </div>
                        <div class="blog-details__content">
                            <h3 class="blog-details__title-1"> {{$article->title}}</h3>
                            <div class="blog-details__client-and-meta">
                                <div class="blog-details__client-box">
                                    <div class="blog-details__client-img">
                                        <img src="{{$article->author->avatarUrl}}" alt="">
                                    </div>
                                    <div class="blog-details__client-content">
                                        <p>نویسنده</p>
                                        <h4>{{$article->author->name}}</h4>
                                    </div>
                                </div>
                                <ul class="blog-details__client-meta list-unstyled">
                                    @if($article->published_at)
                                        <li>
                                            <div class="icon">
                                                <span class="icon-calendar"></span>
                                            </div>
                                            <p>
                                                {{\Hekmatinasser\Verta\Facades\Verta::parse($article->published_at)->format('%e %B، %Y')}}
                                            </p>
                                        </li>
                                    @endif
                                    @if($article->category)
                                            <li>
                                                <div class="icon">
                                                    <span class="icon-tags"></span>
                                                </div>
                                                <p>{{$article->category->name}}</p>
                                            </li>
                                    @endif
                                    <li>
                                        <div class="icon">
                                            <span class="icon-comments"></span>
                                        </div>
                                        <p>({{$article->comments_count ?? 0}} دیدگاه)</p>
                                    </li>
                                </ul>
                            </div>
                          {!! $article->description !!}
                            <div class="blog-details__tag-and-share">
                                <div class="blog-details__tag">
                                    <span>تگ:</span>
                                    @foreach($article->tags ?? [] as $tag)
                                        <a href="{{$tag->slug}}">{{$tag->name}}</a>

                                    @endforeach
                                </div>
                                <div class="blog-details__share">
                                    <span>اشتراک</span>
                                    <a href="#"><i class="fab fa-pinterest-p"></i></a>
                                    <a href="#"><i class="fab fa-instagram"></i></a>
                                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                                </div>
                            </div>
                            <div class="comment-one">
                                <h3 class="comment-one__title">دیدگاه ها ({{$article->comments_count}})</h3>
                                 @foreach($article->comments ?? [] as $comment)
                                    <ul class="comment-one__single-list list-unstyled">
                                        <li>
                                            <div class="comment-one__single">
                                                <div class="comment-one__image-box">
                                                    <div class="comment-one__image">
                                                        <img src="assets/images/blog/comment-1-1.jpg" alt="">
                                                    </div>
                                                </div>
                                                <div class="comment-one__content">
                                                    <div class="comment-one__name-box">
                                                        <h4>{{$comment->user?->name ?? $comment->name}}</h4>
                                                    </div>
                                                    <p>{{$comment->body}}</p>
                                                    <div class="comment-one__btn-box">
                                                        <a href="#" class="comment-one__btn">پاسخ</a>
                                                        <span>{{ verta($comment->created_at)->format('%d %B %Y') }}</span>                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                        @if($comment->replies->isNotEmpty())
                                            @foreach($comment->replies as $reply)
                                                <li>
                                                <div class="comment-one__single">
                                                    <div class="comment-one__image-box">
                                                        <div class="comment-one__image">
                                                            <img src="assets/images/blog/comment-1-2.jpg" alt="">
                                                        </div>
                                                    </div>
                                                    <div class="comment-one__content">
                                                        <div class="comment-one__name-box">
                                                            <h4>{{$reply->user?->name ?? $reply->name}}</h4>
                                                        </div>
                                                        <p>{{$reply->body}}</p>
                                                        <div class="comment-one__btn-box">
                                                            <a href="#" class="comment-one__btn">پاسخ</a>
                                                            <span>{{ verta($reply->created_at)->format('%d %B %Y') }}</span>                                                    </div>

                                                    </div>
                                                    </div>
                                                </div>
                                            </li>
                                            @endforeach
                                        @endif
                                    </ul>

                                 @endforeach
                            </div>
                            <div class="comment-form">
                                <h3 class="comment-form__title">ثبت دیدگاه</h3>
                                <form wire:submit="saveComment()"
                                      class="comment-form__form " >
                                    <div class="row">
                                        <div class="col-xl-12 col-lg-12">
                                            <div class="comment-form__input-box">
                                                <input wire:model.defer="name" type="text" placeholder="نام کامل" name="name">
                                                @error('name')
                                                    <p class="mt-1 text-danger">{{$message}}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-xl-12">
                                            <div class="comment-form__input-box text-message-box">
                                                <textarea wire:model.defer="body" name="message" placeholder="متن دیدگاه"></textarea>
                                                @error('body')
                                                    <p class="mt-1 text-danger ">{{$message}}</p>
                                                @enderror
                                            </div>
                                            <div class="comment-form__btn-box">
                                                <button type="submit" class="comment-form__btn"><span
                                                        class="icon-circle-rightsvg"></span>ثبت</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <div class="result"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-5">
                    <div class="sidebar">
                        <div class="sidebar__single sidebar__`search`">
                            <div class="sidebar__title-box">
                                <div class="sidebar__title-icon">
                                    <img src="{{asset('main')}}/images/icon/sidebar-title-icon.png" alt="">
                                </div>
                                <h3 class="sidebar__title">جستجو </h3>
                            </div>
                            <p class="sidebar__search-text">در وبلاگ ما، نکات کاربردی، آموزش‌ها و تازه‌ترین مطالب تخصصی را بخوانید.</p>
                            <form wire:submit.prevent="fillter" action="#" class="sidebar__search-form">
                                <input wire:model.defer="search" type="search" placeholder="جستجو...">
                                <button type="submit"><i class="icon-search"></i></button>
                            </form>
                            @error('search')
                            <p class="mt-1 text-danger">{{$message}}</p>
                            @enderror
                        </div>
                        <div class="sidebar__single sidebar__category">
                            <div class="sidebar__title-box">
                                <div class="sidebar__title-icon">
                                    <img src="{{asset('main')}}/images/icon/sidebar-title-icon.png" alt="">
                                </div>
                                <h3 class="sidebar__title">دسته بندی ها</h3>
                            </div>
                            <ul class="sidebar__category-list list-unstyled">
                                @foreach($categories ?? [] as $cat)
                                    <li>
                                        <a href="{{route('categories.show',$cat->slug)}}">{{$cat->name}}<span
                                                class="fas fa-arrow-left"></span></a>
                                    </li>
                                @endforeach


                            </ul>
                        </div>
                        <div class="sidebar__single sidebar__post">
                            <div class="sidebar__title-box">
                                <div class="sidebar__title-icon">
                                    <img src="assets/images/icon/sidebar-title-icon.png" alt="">
                                </div>
                                <h3 class="sidebar__title">اخرین مقالات</h3>
                            </div>
                            <ul class="sidebar__post-list list-unstyled">
                                @foreach($last_articles ?? [] as $a)
                                    <li>
                                        <div class="sidebar__post-image">
                                            <img src="{{$a->featuredImageUrl}}" alt="">
                                        </div>
                                        <div class="sidebar__post-content">
                                            <ul class="sidebar__post-meta list-unstyled">
                                                @if($a->category)
                                                    <li>
                                                        <p><span class="icon-tags"></span>{{$a->category->name}}</p>
                                                    </li>
                                                @endif

                                            </ul>
                                            <h3 class="sidebar__post-title"><a href="{{route('articles.show',$a->slug)}}">{{$a->title}}</a></h3>
                                        </div>
                                    </li>

                                @endforeach
                            </ul>
                        </div>
                        <div class="sidebar__single sidebar__tag">
                            <div class="sidebar__title-box">
                                <div class="sidebar__title-icon">
                                    <img src="assets/images/icon/sidebar-title-icon.png" alt="">
                                </div>
                                <h3 class="sidebar__title">کلمات کلیدی</h3>
                            </div>
                            <div class="sidebar__tag-list">
                                @foreach($tags ?? [] as $t)
                                    <a href="{{route('tags.show',$t->slug)}}">{{$t->name}}</a>
                                @endforeach

                            </div>
                        </div>
                        <div class="sidebar__single sidebar__newsletter">
                            <div class="sidebar__title-box">
                                <div class="sidebar__title-icon">
                                    <img src="assets/images/icon/sidebar-title-icon.png" alt="">
                                </div>
                                <h3 class="sidebar__title">خبرنامه </h3>
                            </div>
                            <p class="sidebar__newsletter-text">به جمع همراهان ما بپیوندید و جدیدترین آموزش‌ها، نکات تخصصی و پیشنهادهای ویژه را مستقیماً در ایمیل خود دریافت کنید.
                            </p>
                            <form wire:submit.prevent="subscribe" action="#" class="sidebar__newsletter-form">
                                <input wire:model.defer="email" type="search" placeholder="ایمیل">
                                @error('email')
                                <p class="text-danger text-center mt-2">{{$message}}</p>
                                @enderror
                                <button type="submit">عضویت<i class="icon-circle-rightsvg"></i></button>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
