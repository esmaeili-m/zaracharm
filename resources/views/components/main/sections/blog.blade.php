<?php
$blogs = \App\Models\Article::active()
    ->with(['media', 'category','author'])
    ->withCount([
        'comments'
    ])
    ->take(9)
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
    </style>
@endpush


<section class="blog-one">
    <div class="container">
        <div class="section-title text-center sec-title-animation animation-style1">
            <div class="section-title__tagline-box">
                <div class="section-title__tagline-shape"></div>
                <span class="section-title__tagline">مجله خبری</span>
            </div>
            <h2 class="section-title__title title-animation">{!! nl2br(e($data['title'] ?? '')) !!}
                <span><img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span> </h2>
        </div>
        <div class="blog-one__carousel owl-theme owl-carousel">

            @foreach($blogs as $blog)
                <div class="item">
                    <div class="blog-one__single">
                        <div class="blog-one__img">
                            <img src="{{$blog->featuredImageUrl}}" alt="">
                        </div>
                        <div class="blog-one__content">
                            <ul class="blog-one__meta list-unstyled">
                                    <li>
                                        <a href="{{route('articles.show',$blog->slug)}}"><span class="icon-calendar"></span>
                                                {{ $blog->published_at ?? verta($blog->created_at)->format('Y/n/j') }}
                                        </a>
                                    </li>

                                <li>
                                    <a href="{{route('articles.show',$blog->slug)}}"><span class="icon-comment"></span>{{$blog->comments_count}} دیدگاه</a>
                                </li>
                            </ul>
                            <h3 class="blog-one__title"><a href="{{route('articles.show',$blog->slug)}}">{{$blog->title}}</a></h3>
                            <p class="blog-one__text line-clamp-4">
                                {!! nl2br(e($blog->short_description ?? '')) !!}
                            </p>
                            <div class="blog-one__btn-and-user-box">
                                <div class="blog-one__btn-box">
                                    <a href="{{route('articles.show',$blog->slug)}}" class="thm-btn"><span
                                            class="far fa-angle-double-left"></span>بیشتر</a>
                                </div>
                                <div class="blog-one__user-box">
                                    <div class="blog-one__user-img">
                                        <img src="{{$blog->author->avatarUrl}}" alt="">
                                    </div>
                                    <div class="blog-one__user-content">
                                        <h5 class="blog-one__user-name">{{$blog->author->name}}</h5>
                                        <p class="blog-one__user-sub-title">{{$blog->author->role_label}}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach


        </div>
    </div>
</section>
