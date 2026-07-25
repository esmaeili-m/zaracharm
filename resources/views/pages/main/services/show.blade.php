<?php

use Livewire\Component;
use App\Models\Service;
use App\Models\Category;
new class extends Component
{
    public $article,$tags,$last_articles,$categories,$search='',$email,$name,$body;
    public function mount($slug)
    {
        $this->article = Service::where('slug', $slug)->withCount('comments')->with(['comments','tags'])->first();
        $this->last_articles = Service::whereNot('id',$this->article->id)->latest()->take(6)->with(['category'])->get();
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
                            <br>
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
                                <img src="{{asset('main')}}/images/icon/sidebar-title-icon.png" alt="">
                            </div>
                            <h3 class="sidebar__title">خدمات ما</h3>
                        </div>
                        <ul class="sidebar__post-list list-unstyled">
                            @foreach($last_articles ?? [] as $a)
                                <li>
                                    <div class="sidebar__post-image">
                                        <img src="{{$a->bannerImageUrl}}" alt="">
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
