@push('styles')
    <style>
        .gallery-block{
            margin-bottom: 70px;
        }

        .section-title{
            margin-bottom: 25px;
        }

        /* Masonry */
        .grid{
            width: 100%;
            margin: 0 -7.5px;
        }

        /* responsive columns */
        .grid-sizer,
        .grid-item{
            width: 25%;
            padding: 0 7.5px;
        }

        @media(max-width: 1200px){
            .grid-sizer, .grid-item{ width: 33.333%; }
        }

        @media(max-width: 992px){
            .grid-sizer, .grid-item{ width: 50%; }
        }

        @media(max-width: 576px){
            .grid-sizer, .grid-item{ width: 100%; }
        }

        .grid-item{
            margin-bottom: 15px;
            float: left;
        }

        /* image */
        .grid-item img{
            width: 100%;
            display: block;
            border-radius: 10px;
        }

        /* hover */
        .img-popup{
            position: relative;
            display: block;
            overflow: hidden;
        }

        .gallery-overlay{
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.4);

            display:flex;
            align-items:center;
            justify-content:center;

            opacity:0;
            transition:.3s;
        }

        .img-popup:hover .gallery-overlay{
            opacity:1;
        }
    </style>
@endpush


<section class="gallery-page" style="margin-top: 200px;margin-bottom: 200px">

    <div class="container">

        @foreach(\App\Models\Gallery::with('media')->get() as $gallery)

            <div class="gallery-block">

                {{-- TITLE --}}
                <div class="section-title text-center mb-4">

                    <span class="section-title__tagline">گالریا</span>

                    <h2 class="section-title__title">
                        {{ $gallery->name }}
                    </h2>

                </div>

                {{-- GRID مخصوص همین گالری --}}
                <div class="grid">

                    <div class="grid-sizer"></div>

                    @foreach($gallery->media as $media)

                        <div class="grid-item">

                            @if(str_starts_with($media->mime_type, 'image/'))

                                <a class="img-popup"
                                   href="{{ url('/media/'.$media->file_path) }}">

                                    <img src="{{ url('/media/'.$media->file_path) }}">

                                    <span class="gallery-overlay">
                                        <i class="icon-plus"></i>
                                    </span>

                                </a>

                            @else

                                <div class="video-box">
                                    <i class="ri-video-line"></i>
                                </div>

                            @endif

                        </div>

                    @endforeach

                </div>

            </div>

        @endforeach

    </div>

</section>

@push('scripts')
    <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>

    <script>
        function initMasonry() {

            document.querySelectorAll('.grid').forEach((grid) => {

                new Masonry(grid, {
                    itemSelector: '.grid-item',
                    columnWidth: '.grid-sizer',
                    percentPosition: true
                });

            });

        }

        window.addEventListener('load', initMasonry);
    </script>
@endpush
