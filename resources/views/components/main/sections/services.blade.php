<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
new class extends Component
{
    use WithPagination;
    public $blogs;

    #[Computed]
    public function services()
    {
        return \App\Models\Service::with('category')->withCount('comments')->paginate(9);
    }


};
?>
@push('styles')
    <style>
        .blog-two__img img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
    </style>
@endpush
<div>
    <section class="blog-page mt-5">
        <div class="container">
            <div class="section-title text-center sec-title-animation animation-style1">
                <div class="section-title__tagline-box">
                    <div class="section-title__tagline-shape"></div>
                    <span class="section-title__tagline">خدمات ما</span>
                </div>
                <h2 class="section-title__title title-animation">خدمات مركز مامايي مادرانه
                    <span> <img src="{{asset('main')}}/images/shapes/section-title-shape-1.png" alt=""></span></h2>
            </div>
            <div class="row">
                @foreach($this->services as $service)
                    <div class="col-xl-4 col-lg-4 col-md-6 wow fadeInUp animated mb-5"
                         data-wow-delay="100ms" style="visibility: visible; animation-delay: 100ms; animation-name: fadeInUp;">
                        <div class="blog-two__single">
                            <div class="blog-two__img">
                                <img src="{{$service->featuredImageUrl}}" alt="">
                                <div class="blog-two__date">

                                </div>
                            </div>
                            <div class="blog-two__content">
                                <div class="blog-two__meta-box">
                                    <ul class="blog-two__meta list-unstyled">
                                        @foreach($service->tags ?? [] as $tag)
                                            <li>
                                                <a href="{{route('tags.show',$tag->slug)}}"><span class="icon-tags"></span>{{$tag->name}}</a>
                                            </li>
                                        @endforeach


                                    </ul>
                                </div>
                                <h4 class="blog-two__title"><a href="{{route('services.show',$service->slug)}}">{{$service->title}}</a></h4>
                                <p class="blog-two__text line-clamp-4">
                                    {!! nl2br(e($service->short_description)) !!}
                                </p></div>
                        </div>
                    </div>

                @endforeach
                <!--Blog Two Single Start -->
                <!--Blog Two Single End -->
                <div class="row">
                    <div class="col-xl-12">
                        {{ $this->services->links('vendor/livewire.custom-pagination') }}

                    </div>
                </div>
            </div>

        </div>
    </section>
</div>
