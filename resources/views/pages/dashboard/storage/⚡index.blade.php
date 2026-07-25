<?php

use Livewire\Component;
use \Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
new class extends Component
{
    public $files;
    public $info;
    public $data;
    public $viewUrl;
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('settings.dashboard'), 403);
        $this->info['header']='لیست فایل ها';
        $this->info['create']='افزودن فایل';
        $this->info['delete']='حذف فایل';
        $this->info['personal']='فایل ها';
        $this->info['table']['headers']=[
            '#',
            'نام',
            'لینک',
            'عملیات',
        ];
        $this->loadData();
    }
    public function loadData()
    {
        try {
            $handler = new \GuzzleHttp\Handler\CurlHandler();
            $stack = \GuzzleHttp\HandlerStack::create($handler);
            $httpClient = new \GuzzleHttp\Client(['handler' => $stack]);

            $s3 = new S3Client([
                'version'     => 'latest',
                'region'      => env('AWS_DEFAULT_REGION'),
                'endpoint'    => env('AWS_ENDPOINT'),
                'credentials' => new Credentials(
                    env('AWS_ACCESS_KEY_ID'),
                    env('AWS_SECRET_ACCESS_KEY')
                ),
                'http_handler' => new \Aws\Handler\GuzzleV6\GuzzleHandler($httpClient),
            ]);

            $result = $s3->listObjectsV2([
                'Bucket' => env('AWS_BUCKET'),
            ]);

            $files = $result['Contents'] ?? [];

            $fileDetails = collect($files)->map(function ($file) use ($s3) {
                $key = $file['Key'];
                return [
                    'name'         => basename($key),
                    'path'         => $key,
                    'size'         => $file['Size'] ?? 0,
                    'lastModified' => isset($file['LastModified'])
                        ? strtotime($file['LastModified'])
                        : 0,
                    'url'          => $s3->getObjectUrl(env('AWS_BUCKET'), $key),
                ];
            })->sortByDesc('lastModified')->values();
            $this->data = $fileDetails;

        } catch (\Exception $e) {
            report($e);
            $this->data = collect();
        }
    }
    public function generatePresignedUrl(string $file): void
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

            $this->viewUrl = $url;

        } catch (\Throwable $e) {
            $this->viewUrl = null;
            report($e);
            session()->flash('error', 'Could not generate video URL.');
        }
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">
                {{$info['header']}}
            </h1>
            @if($viewUrl)
                <a href="{{ $viewUrl }}" target="_blank" class="btn btn-info-light btn-wave me-0">
                    <i class="ri-eye-line align-middle">
                    </i>
                    مشاهده فایل
                </a>
                <a >

                </a>
            @endif
        </div>
        <div class="btn-list">

            @can('settings.dashboard')
                <button wire:click="resetData()" data-bs-effect="effect-flip-horizontal" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave me-0">
                    <i class="ri-add-line align-middle">
                    </i>
                    {{$info['create']}}
                </button>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">
                        {{$info['header']}}
                    </div>
                    <div class="header-element header-search d-md-block d-none my-auto">
                        <div class="autoComplete_wrapper" role="combobox" aria-owns="autoComplete_list_1" aria-haspopup="true" aria-expanded="false"><input wire:model.lazy="search" autocapitalize="none" autocomplete="off" class="header-search-bar form-control" id="header-search" placeholder="جستجو برای نتایج..." spellcheck="false" type="text" aria-controls="autoComplete_list_1" aria-autocomplete="both"><ul id="autoComplete_list_1" role="listbox" hidden=""></ul></div>
                        <a class="header-search-icon border-0" href="javascript:void(0);">
                            <i wire:click="loadData()" class="bi bi-search">
                            </i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap">
                            <thead>
                            <tr>
                                @foreach($info['table']['headers'] ?? [] as $h)
                                    <th scope="col">
                                        {{$h}}
                                    </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @php($counter=1)
                            @foreach($data ?? [] as $key => $item)
                                <tr wire:key="{{$counter}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>
                                        {{ \Illuminate\Support\Str::limit($item['name'], 40) }}
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-primary-light" onclick="navigator.clipboard.writeText('https://madaranee.ir/{{ $item['path'] }}'); alert('آدرس کپی شد');"><i class="fa fa-copy"></i> کپی آدرس</button>
                                    </td>


                                    <td>

                                        <div class="hstack gap-2 flex-wrap">

                                            @can('settings.dashboard')

                                                <a  wire:click="generatePresignedUrl('{{$item['path']}}')"  class="text-info fs-14 lh-1"><i
                                                        class="ri-eye-line"></i></a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @php($counter++)
                                    @endforeach

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@script
function copyToClipboard(text) {
try {
await navigator.clipboard.writeText(text);
alert('آدرس کپی شد.');
} catch (err) {
console.error(err);
alert('خطا در کپی کردن آدرس.');
}
}
@endscript
