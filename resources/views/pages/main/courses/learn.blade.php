<?php

use Livewire\Component;
use App\Models\Course;
use \Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
new class extends Component
{
    public $course;
    public $urlFile;
    public $selectedFile = null;

    public function openFile($fileId)
    {
        $this->selectedFile = \App\Models\Media::with('mediable')->findOrFail($fileId);
        $url = $this->selectedFile->external_url
            ?: Storage::url($this->selectedFile->file_path);
        if($this->selectedFile->external_url){
            $standard_url=str_replace("https://madaranee.ir/","",$url);
            if ($this->selectedFile->mediable->is_free){
                $this->urlFile=$standard_url;

            }else{
                $this->urlFile=$this->generatePresignedUrl($standard_url);

            }
        }else{
            $this->urlFile= $this->selectedFile->external_url ?: Storage::disk('public')->url($this->selectedFile->file_path);
        }
        $type = $this->selectedFile->type->value;
        if ($type === 'video') {
            $this->dispatch('video-changed', url: $this->urlFile);
        }
    }

    public function generatePresignedUrl(string $file)
    {
        try {
            $handler = new \GuzzleHttp\Handler\CurlHandler();
            $stack = \GuzzleHttp\HandlerStack::create($handler);
            $httpClient = new \GuzzleHttp\Client(['handler' => $stack]);

            $s3 = new \Aws\S3\S3Client([
                'version'      => 'latest',
                'region'       => env('AWS_DEFAULT_REGION'),
                'endpoint'     => env('AWS_ENDPOINT'),
                'credentials'  => new \Aws\Credentials\Credentials(
                    env('AWS_ACCESS_KEY_ID'),
                    env('AWS_SECRET_ACCESS_KEY')
                ),
                'http_handler' => new \Aws\Handler\GuzzleV6\GuzzleHandler($httpClient),
            ]);

            // چک وجود فایل بدون Storage facade
            $exists = $s3->doesObjectExist(env('AWS_BUCKET'), $file);

            if (!$exists) {
                $this->viewUrl = null;
                session()->flash('error', 'File not found.');
                return;
            }

            // ساخت presigned URL
            $cmd = $s3->getCommand('GetObject', [
                'Bucket' => env('AWS_BUCKET'),
                'Key'    => $file,
            ]);

            $request = $s3->createPresignedRequest($cmd, '+1 hour');
            $url = (string) $request->getUri();
            $url = str_replace(
                'https://startwebone.storage.c2.liara.site',
                'https://cdn.madaranee.ir',
                $url
            );

            return $url;

        } catch (\Throwable $e) {


            report($e);
            session()->flash('error', 'Could not generate video URL.');
        }
    }

    public function mount($course)
    {

        $this->course = Course::where('slug', $course)
            ->with(['sections.lessons.media', 'media'])
            ->first();
        $hasAccess = auth()->user()
            ->courses()
            ->where('course_id',$this->course?->id)
            ->exists();

        abort_unless($hasAccess, 403);
    }

    public function getFileUrl($file): string
    {

        return $file->external_url ?: Storage::disk('public')->url($file->file_path);
    }
};
?>

<div>
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />

    <style>
        .cvp-wrapper {
            border-radius: 14px;
            overflow: hidden;
            background: #000;
            box-shadow: 0 4px 32px rgba(0,0,0,0.35);
            width: 100%;
            height: 500px;
        }
        #cvp-video,
        .cvp-wrapper .video-js {
            width: 100% !important;
            height: 100% !important;
        }
        .cvp-wrapper .vjs-big-play-button {
            background: rgba(99,102,241,0.88) !important;
            border: none !important;
            border-radius: 50% !important;
            width: 68px !important;
            height: 68px !important;
            line-height: 68px !important;
            margin-top: -34px !important;
            margin-left: -34px !important;
            transition: transform 0.2s ease, background 0.2s ease !important;
        }
        .cvp-wrapper .vjs-big-play-button:hover {
            background: #6366f1 !important;
            transform: scale(1.1) !important;
        }
        .cvp-wrapper .vjs-big-play-button .vjs-icon-placeholder::before {
            font-size: 26px !important;
            line-height: 68px !important;
        }
        .cvp-wrapper .vjs-control-bar {
            background: linear-gradient(transparent, rgba(8,8,16,0.96)) !important;
            height: 70px !important;
            padding: 0 16px 12px !important;
            align-items: flex-end !important;
        }
        .cvp-wrapper .vjs-progress-control {
            position: absolute !important;
            top: -4px !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            height: 20px !important;
        }
        .cvp-wrapper .vjs-progress-holder {
            height: 3px !important;
            background: rgba(255,255,255,0.18) !important;
            border-radius: 3px !important;
            transition: height 0.2s !important;
            margin: 0 !important;
        }
        .cvp-wrapper .vjs-control-bar:hover .vjs-progress-holder {
            height: 5px !important;
        }
        .cvp-wrapper .vjs-play-progress {
            background: #6366f1 !important;
            border-radius: 3px !important;
        }
        .cvp-wrapper .vjs-play-progress::before {
            color: #6366f1 !important;
            font-size: 11px !important;
            top: -5px !important;
        }
        .cvp-wrapper .vjs-load-progress div {
            background: rgba(99,102,241,0.22) !important;
        }
        .cvp-wrapper .vjs-control-bar button,
        .cvp-wrapper .vjs-control-bar .vjs-time-control {
            color: rgba(255,255,255,0.6) !important;
            transition: color 0.15s !important;
        }
        .cvp-wrapper .vjs-control-bar button:hover {
            color: #fff !important;
        }
        .cvp-wrapper .vjs-control-bar .vjs-icon-placeholder::before {
            font-size: 26px !important;
            line-height: 58px !important;
        }
        .cvp-wrapper .vjs-control-bar .vjs-button {
            width: 48px !important;
            min-width: 48px !important;
        }
        .cvp-wrapper .vjs-time-control {
            font-size: 14px !important;
            line-height: 58px !important;
            padding: 0 6px !important;
        }
        .cvp-wrapper .vjs-playback-rate {
            width: 48px !important;
            min-width: 48px !important;
        }
        .cvp-wrapper .vjs-playback-rate .vjs-playback-rate-value {
            font-size: 14px !important;
            line-height: 58px !important;
            color: rgba(255,255,255,0.6) !important;
        }
        .cvp-wrapper .vjs-playback-rate:hover .vjs-playback-rate-value {
            color: #fff !important;
        }
        .cvp-wrapper .vjs-menu-content {
            background: #0f0f18 !important;
            border: 1px solid #2a2a45 !important;
            border-radius: 8px !important;
        }
        .cvp-wrapper .vjs-menu-item {
            font-size: 13px !important;
            color: rgba(255,255,255,0.6) !important;
            padding: 8px 16px !important;
        }
        .cvp-wrapper .vjs-menu-item:hover,
        .cvp-wrapper .vjs-menu-item.vjs-selected {
            background: #1e1e40 !important;
            color: #6366f1 !important;
        }
        .cvp-wrapper .vjs-volume-panel {
            display: flex !important;
            align-items: center !important;
        }
        .cvp-wrapper .vjs-volume-panel.vjs-volume-panel-horizontal {
            width: 130px !important;
            transition: none !important;
        }
        .cvp-wrapper .vjs-volume-panel.vjs-hover,
        .cvp-wrapper .vjs-volume-panel:active,
        .cvp-wrapper .vjs-volume-panel:focus {
            width: 130px !important;
            transition: none !important;
        }
        .cvp-wrapper .vjs-volume-vertical {
            display: none !important;
        }
        .cvp-wrapper .vjs-volume-control.vjs-volume-horizontal {
            display: flex !important;
            align-items: center !important;
            width: 70px !important;
            opacity: 1 !important;
            visibility: visible !important;
            transition: none !important;
        }
        .cvp-wrapper .vjs-volume-bar.vjs-slider-horizontal {
            width: 70px !important;
            height: 4px !important;
            background: rgba(255,255,255,0.18) !important;
            border-radius: 3px !important;
            margin: 0 6px !important;
        }
        .cvp-wrapper .vjs-volume-level {
            background: #6366f1 !important;
            border-radius: 3px !important;
        }
        .cvp-wrapper .vjs-volume-level::before {
            font-size: 11px !important;
            color: #6366f1 !important;
            top: -4px !important;
            right: -6px !important;
        }

        /* placeholder */
        .cvp-placeholder {
            aspect-ratio: 16/9;
            background: #0f0f18;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            border: 1px dashed #2a2a45;
        }
        .cvp-placeholder p {
            color: #44448a;
            font-size: 14px;
            margin: 0;
        }

        /* فایل viewer عمومی */
        .cvp-file-viewer {
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #1e1e30;
            background: #0f0f18;
        }
        .cvp-file-viewer iframe {
            display: block;
            width: 100%;
            height: 700px;
            border: none;
        }
        .cvp-file-viewer img {
            display: block;
            width: 100%;
            height: auto;
        }

        /* کارت دانلود برای فایل‌های غیر قابل نمایش */
        .cvp-download-card {
            border-radius: 14px;
            background: #0f0f18;
            border: 1px solid #1e1e30;
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            text-align: center;
        }
        .cvp-download-card .cvp-file-icon {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        .cvp-download-card h5 {
            color: #ccccee;
            font-size: 16px;
            margin: 0;
            font-weight: 500;
        }
        .cvp-download-card p {
            color: #44448a;
            font-size: 13px;
            margin: 0;
        }
        .cvp-download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #6366f1;
            color: #fff !important;
            border: none;
            border-radius: 10px;
            padding: 10px 24px;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.2s;
            margin-top: 4px;
        }
        .cvp-download-btn:hover {
            background: #4f46e5;
            color: #fff !important;
        }

        /* موبایل */
        @media (max-width: 768px) {
            .cvp-wrapper {
                height: 220px !important;
            }
            .cvp-wrapper .vjs-control-bar {
                height: 52px !important;
                padding: 0 4px 8px !important;
            }
            .cvp-wrapper .vjs-control-bar .vjs-icon-placeholder::before {
                font-size: 18px !important;
                line-height: 44px !important;
            }
            .cvp-wrapper .vjs-control-bar .vjs-button {
                width: 32px !important;
                min-width: 32px !important;
            }
            .cvp-wrapper .vjs-time-control {
                font-size: 11px !important;
                line-height: 44px !important;
                padding: 0 2px !important;
            }
            .cvp-wrapper .vjs-volume-panel {
                display: none !important;
            }
            .cvp-wrapper .vjs-playback-rate {
                width: 36px !important;
                min-width: 36px !important;
            }
            .cvp-wrapper .vjs-playback-rate .vjs-playback-rate-value {
                font-size: 11px !important;
                line-height: 44px !important;
            }
            .cvp-file-viewer iframe {
                height: 400px;
            }
        }
    </style>

    <section class="course-details">
        <div class="row mx-5 mt-5">

            <div class="col-12">
                <h3 class="course-details__curriculam-title">{{ $course->title }}</h3>
            </div>

            {{-- سایدبار --}}
            <div class="col-xl-3 col-lg-4">
                <div class="course-details__tab-inner">
                    <div class="accordion" id="accordionPanelsStayOpenExample">
                        @foreach($course->sections as $key => $section)
                            @php
                                $lessonsCount = $section->lessons->count();
                                $totalMinutes = $section->lessons->sum('duration');
                            @endphp
                            <div class="accordion-item mb-3 overflow-hidden" style="border-radius:12px" wire:ignore>
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#panelsStay{{ $key }}-collapseOne">
                                        <div>
                                            <div>{{ $section->title }}</div>
                                            <small class="text-muted d-block mt-2">
                                                <i class="fas fa-play-circle"></i> {{ $lessonsCount }} جلسه
                                                &nbsp;|&nbsp;
                                                <i class="far fa-clock"></i> {{ $totalMinutes }} دقیقه
                                            </small>
                                        </div>
                                    </button>
                                </h2>
                                <div id="panelsStay{{ $key }}-collapseOne" class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        @foreach($section->lessons as $lesson)
                                            <div class="lesson-item border-bottom py-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="fas fa-play-circle text-primary me-1"></i>
                                                        {{ $lesson->title }}
                                                    </div>
                                                    <div class="text-muted small">
                                                        <i class="far fa-clock me-1"></i>
                                                        {{ $lesson->duration }}
                                                    </div>
                                                </div>
                                                @if($lesson->media->count())
                                                    <div class="mt-2 pe-4">
                                                        @foreach($lesson->media as $attachment)
                                                            <div class="small text-muted" style="cursor:pointer"
                                                                 wire:click="openFile({{ $attachment->id }})">
                                                                <i class="fas fa-paperclip me-1"></i>
                                                                {{ $attachment->name }}
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ناحیه اصلی --}}
            <div class="col-xl-9 col-lg-8">

                {{-- ویدیو wrapper --}}
                <div class="cvp-wrapper" id="cvp-wrapper" style="display:none"></div>

                @if($selectedFile && $selectedFile->type->value !== 'video')
                    @php
                        $fileUrl  = $this->urlFile;
                        $ext      = strtolower($selectedFile->extension ?? pathinfo($selectedFile->file_path ?? '', PATHINFO_EXTENSION));
                        $name     = $selectedFile->name ?? basename($selectedFile->file_path ?? 'فایل');
                        $mime     = $selectedFile->mime_type ?? '';

                        // تعیین نوع نمایش
                        $showInline  = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp3', 'wav', 'ogg']);
                        $isImage     = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                        $isPdf       = $ext === 'pdf';
                        $isAudio     = in_array($ext, ['mp3', 'wav', 'ogg','m4a']);
                        // آیکون و رنگ برای کارت دانلود
                        $iconMap = [
                            'pdf'  => ['icon' => '📄', 'bg' => '#2a1a1a', 'label' => 'PDF'],
                            'doc'  => ['icon' => '📝', 'bg' => '#1a1a2a', 'label' => 'Word'],
                            'docx' => ['icon' => '📝', 'bg' => '#1a1a2a', 'label' => 'Word'],
                            'xls'  => ['icon' => '📊', 'bg' => '#1a2a1a', 'label' => 'Excel'],
                            'xlsx' => ['icon' => '📊', 'bg' => '#1a2a1a', 'label' => 'Excel'],
                            'ppt'  => ['icon' => '📑', 'bg' => '#2a1a10', 'label' => 'PowerPoint'],
                            'pptx' => ['icon' => '📑', 'bg' => '#2a1a10', 'label' => 'PowerPoint'],
                            'zip'  => ['icon' => '🗜️', 'bg' => '#1e1e1e', 'label' => 'ZIP'],
                            'rar'  => ['icon' => '🗜️', 'bg' => '#1e1e1e', 'label' => 'RAR'],
                            'mp3'  => ['icon' => '🎵', 'bg' => '#1a1a2a', 'label' => 'Audio'],
                            'wav'  => ['icon' => '🎵', 'bg' => '#1a1a2a', 'label' => 'Audio'],
                            'txt'  => ['icon' => '📃', 'bg' => '#1e1e1e', 'label' => 'Text'],
                            'csv'  => ['icon' => '📋', 'bg' => '#1a2a1a', 'label' => 'CSV'],
                        ];
                        $iconInfo = $iconMap[$ext] ?? ['icon' => '📎', 'bg' => '#1e1e2a', 'label' => strtoupper($ext)];
                    @endphp

                    @if($isImage)
                        <div class="cvp-file-viewer">
                            <img src="{{ $fileUrl }}" alt="{{ $name }}">
                        </div>

                    @elseif($isPdf)
                        <div class="cvp-file-viewer">
                            <iframe src="{{ $fileUrl }}"></iframe>
                        </div>

                    @elseif($isAudio)

                        <div class="cvp-download-card" wire:key="audio-{{ $selectedFile->id }}">
                            <div class="cvp-file-icon" style="background:{{ $iconInfo['bg'] }}">
                                {{ $iconInfo['icon'] }}
                            </div>
                            <h5>{{ $name }}</h5>
                            <audio controls style="width:100%;margin-top:8px;accent-color:#6366f1">
                                <source src="{{ $fileUrl }}" >
                            </audio>
                        </div>

                    @else
                        {{-- فایل‌های دانلودی: Excel، Word، PPT، ZIP و ... --}}
                        <div class="cvp-download-card">
                            <div class="cvp-file-icon" style="background:{{ $iconInfo['bg'] }}">
                                {{ $iconInfo['icon'] }}
                            </div>
                            <h5>{{ $name }}</h5>
                            <p>{{ $iconInfo['label'] }} · {{ number_format($selectedFile->size / 1024, 0) }} KB</p>
                            <a href="{{ $fileUrl }}" download="{{ $name }}" class="cvp-download-btn">
                                <i class="fas fa-download"></i>
                                دانلود فایل
                            </a>
                        </div>
                    @endif
                @endif

                {{-- placeholder --}}
                @if(!$selectedFile)
                    <div class="cvp-placeholder">
                        <div style="width:64px;height:64px;background:#1a1a30;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#44448a" stroke-width="1.5">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                        <p>یک درس را از منوی کنار انتخاب کنید</p>
                    </div>
                @endif

            </div>
        </div>
    </section>

    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script>
        var cvpPlayer = null;

        window.addEventListener('video-changed', function (e) {
            var wrapper = document.getElementById('cvp-wrapper');
            wrapper.style.display = 'block';
            if (cvpPlayer) {
                cvpPlayer.dispose();
                cvpPlayer = null;
            }

            wrapper.innerHTML = '<video id="cvp-video" class="video-js vjs-big-play-centered" preload="auto" controlsList="nodownload" disablePictureInPicture oncontextmenu="return false;"></video>';

            cvpPlayer = videojs('cvp-video', {
                controls: true,
                fluid: false,
                fill: true,
                playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
                controlBar: {
                    pictureInPictureToggle: false,
                    downloadButton: false,
                    playbackRateMenuButton: true,
                    volumePanel: { inline: true }
                },
                sources: [{ type: 'video/mp4', src: e.detail.url }]
            });

            cvpPlayer.ready(function () {
                cvpPlayer.play();
            });
        });

        // وقتی فایل غیر ویدیو انتخاب میشه wrapper مخفی بشه
        document.addEventListener('livewire:update', function () {
            var wrapper = document.getElementById('cvp-wrapper');
            if (wrapper && wrapper.style.display !== 'none') {
                // اگه ویدیو انتخاب نشده wrapper رو مخفی کن
                @if(!$selectedFile || $selectedFile->type->value !== 'video')
                    wrapper.style.display = 'none';
                if (cvpPlayer) { cvpPlayer.dispose(); cvpPlayer = null; }
                @endif
            }
        });

        document.addEventListener('contextmenu', e => e.preventDefault());
        // document.addEventListener('keydown', e => {
        //     if (e.key === 'F12' ||
        //         (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase())) ||
        //         (e.ctrlKey && e.key.toUpperCase() === 'U')) {
        //         e.preventDefault();
        //     }
        // });
    </script>
</div>
