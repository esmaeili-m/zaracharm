<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
new class extends Component
{
    use WithPagination;
    public $blogs;

    #[Computed]
    public function articles()
    {
        return \App\Models\Article::active()->with('category')->withCount('comments')->paginate(9);
    }


};
?>

<div>
    <section class="blog-page mt-5">
        <div class="container">
            <div class="row">
                @foreach($this->articles ?? [] as $article)
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
                <!--Blog Two Single Start -->
                <!--Blog Two Single End -->
                    <div class="row">
                        <div class="col-xl-12">
                            {{ $this->articles->links('vendor/livewire.custom-pagination') }}

                        </div>
                    </div>
            </div>

        </div>
    </section>
</div>
