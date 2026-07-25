<section class="category-one">
    <div class="category-one__bg-shape"
         style="background-image: url({{asset('main')}}/images/shapes/category-one-bg-shape.png);"></div>
    <div class="category-one__shape-1">
        <img src="{{asset('main')}}/images/shapes/category-one-shape-1.png" alt="">
    </div>
    <div class="category-one__shape-2">
        <img src="{{asset('main')}}/images/shapes/category-one-shape-2.png" alt="">
    </div>
    <div class="category-one__shape-3">
        <img src="{{asset('main')}}/images/shapes/category-one-shape-3.png" alt="">
    </div>
    <div class="container">
        <div class="row">
            <div class="col-xl-6 col-lg-7">
                <div class="category-one__left">
                    <div class="section-title text-left sec-title-animation animation-style2">
                        <div class="section-title__tagline-box">
                            <div class="section-title__tagline-shape"></div>
                            <span class="section-title__tagline">{{$data['tagline'] ?? ''}}</span>
                        </div>
                        <h2 class="section-title__title title-animation">{{$data['title'] ?? ''}}
                            <span> <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span></h2>
                    </div>
                    <ul class="category-one__category-list list-unstyled">
                        @php($counter=1)
                        @foreach(\App\Models\category::active()->withCount('courses')->latest()->take(4)->get() as $item)
                            <li>
                                <div class="category-one__count-and-arrow">
                                    <div class="category-one__count-box">
                                        <div class="category-one__count"></div>
                                        <div class="category-one__count-content">
                                            <h3><a href="{{route('categories.show',$item['slug'])}}">{{$item['name']}}</a></h3>
                                            <p>{{$item['courses_count']}} دوره</p>
                                        </div>
                                    </div>
                                    <div class="category-one__count-arrow">
                                        <a href="{{route('categories.show',$item['slug'])}}"><span
                                                class="icon-arrow-left-up"></span></a>
                                    </div>
                                </div>
                                <div style="border-radius: 15px" class="category-one__hover-icon-and-arrow">
                                    <div class="category-one__hover-icon-box">
                                        <div class="category-one__hover-icon">
                                            <img width="60px" height="60px" src="{{asset('main')}}/images/icon/baby-icon.png" alt="">
                                        </div>
                                        <div class="category-one__hover-content">
                                            <h3><a href="{{route('categories.show',$item['slug'])}}">{{$item['name']}}</a></h3>
                                            <p>{{$item['courses_count']}} دوره</p>
                                        </div>
                                    </div>
                                    <div class="category-one__hover-arrow">
                                        <a href="{{route('categories.show',$item['slug'])}}"><span
                                                class="icon-arrow-left-up"></span></a>
                                    </div>
                                </div>
                            </li>
                        @endforeach

                    </ul>
                </div>
            </div>
            <div class="col-xl-6 col-lg-5 wow slideInLeft" data-wow-delay="100ms" data-wow-duration="2500ms">
                <div class="category-one__right">
                    <div class="category-one__img">
                        <img src="{{$images['image_1'] ?? ''}}" alt="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
