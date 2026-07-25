<?php

use Livewire\Component;

new class extends Component
{
    public $categories;
    public $data;
    public $images;
    public $category_id;
    public $search;

    public function mount($data = null, $images = null)
    {
        $this->categories = \App\Models\Category::active()
            ->withCount('courses')
            ->orderByDesc('courses_count')
            ->get();
        $this->data = $data;
        $this->images = $images;

    }

    public function fillter()
    {
        $data=$this->validate(
            [
                'category_id' => 'nullable|exists:categories,slug',
                'search' => 'nullable|string|min:3',
            ],
            [
                'category_id.exists' => 'دسته‌بندی انتخاب‌شده معتبر نیست.',

                'search.string' => 'عبارت جستجو باید متن باشد.',
                'search.min' => 'عبارت جستجو باید حداقل ۳ کاراکتر باشد.',
            ]
        );
        return redirect()->route('search',['category_id' => $this->category_id,'search' => $this->search]);
    }
};
?>

<div>
    <section class="banner-one">
        <div class="banner-one__bg-shape-1"
             style="background-image: url({{asset('main')}}/images/shapes/banner-one-bg-shape-1.png);"></div>
        <div class="banner-one__icon-1 img-bounce">
            <img src="{{asset('main')}}/images/icon/idea-bulb.png" alt="">
        </div>
        <div class="banner-one__icon-2 float-bob-x">
            <img src="{{asset('main')}}/images/icon/3d-alarm.png" alt="">
        </div>
        <div class="banner-one__icon-3 float-bob-y">
            <img src="{{asset('main')}}/images/icon/linke-icon.png" alt="">
        </div>
        <div class="banner-one__shape-4 float-bob-x">
            <img src="{{asset('main')}}/images/shapes/banner-one-shape-4.png" alt="">
        </div>
        <div class="container">
            <div class="row">
                <div class="col-xl-6">
                    <div class="banner-one__left">
                        <div class="banner-one__title-box">
                            <div class="banner-one__title-box-shape">
                                <img src="{{asset('main')}}/images/shapes/banner-one-title-box-shape-1.png" alt="">
                            </div>
                            <h2 class="banner-one__title" style="color: #4d244c">
                                {{ $data['title'] ?? '' }}
                            </h2>
                            <span class="">{{ $data['subtitle'] ?? '' }}</span>
                        </div>
                        <p class="banner-one__text"><br>
                            {{ $data['description'] ?? '' }}
                        </p>
                        <div class="banner-one__thm-and-other-btn-box">
                            <div class="banner-one__btn-box">
                                <a href="{{route('login')}}" class="thm-btn"><span
                                        class="far fa-angle-double-left"></span>ثبت نام</a>
                            </div>
                            <div class="banner-one__other-btn-box">
                                <a href="{{route('page.show','about-us')}}"
                                   class="banner-one__other-btn-1 video-popup"><span
                                        class="icon-user-plus"></span>درباره من</a>
                                <a href="{{route('page.show','contact-us')}}" class="banner-one__other-btn-1 banner-one__other-btn-2"><span
                                        class="icon-phone"></span>مشاوره با من</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="banner-one__right">
                        <div class="banner-one__img-box">
                            <div class="banner-one__img">
                                <img src="{{ $images['image_1'] ?? ''  }}" alt="">
                                <div class="banner-one__img-shape-box rotate-me">
                                    <div class="banner-one__img-shape-1">
                                        <div class="banner-one__img-shape-2"></div>
                                    </div>
                                    <div class="banner-one__shape-1">
                                        <img src="{{asset('main')}}/images/shapes/banner-one-shape-1.png" alt="">
                                    </div>
                                    <div class="banner-one__shape-2 rotate-me">
                                        <img src="{{asset('main')}}/images/shapes/banner-one-shape-2.png" alt="">
                                    </div>
                                    <div class="banner-one__shape-3">
                                        <img src="{{asset('main')}}/images/shapes/banner-one-shape-3.png" alt="">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-xl-12">
                    <div class="banner-one__category-search-box">
                        <div class="banner-one__category-search-inner">
                            <div class="banner-one__category-select-box">
                                <div  wire:ignore class="select-box">
                                    <select onchange="change_value(this.value)" wire:model.lazy="category_id" class="wide">

                                        <option  data-display="دسته بندی">دسته بندی</option>
                                        @foreach($categories->take(4) ?? [] as $category)
                                            <option value="{{$category->slug}}">{{ $category->name }}</option>
                                        @endforeach

                                    </select>
                                </div>
                            </div>
                            <form wire:submit.prevent="fillter()" class="banner-one__category-form">
                                <div class="banner-one__category-input">
                                    <input wire:model.defer="search" type="search" placeholder="نام دوره">
                                </div>
                                <button type="submit" class="banner-one__category-btn">جستجو</button>
                            </form>
                        </div>
                        <div class="banner-one__tags">
                            @foreach($categories->take(4) ?? [] as $category)
                                <a href="{{route('categories.show',$category->slug)}}">{{ $category->name }}</a>
                            @endforeach

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @push('scripts')
        <script>
            function change_value(value){
                @this.set('category_id',value);
            }
        </script>

    @endpush
</div>
