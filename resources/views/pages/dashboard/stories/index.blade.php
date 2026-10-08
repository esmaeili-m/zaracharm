<?php

use Livewire\Component;
use \App\Models\Story;
use \App\Models\StoryItem;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $user;
    public $avatar;
    public $status = 1;
    public $search;
    public Story $model;

    // ---- آیتم‌های استوری ----
    public array $items = [];        // آیتم‌های ذخیره‌شده (قابل ویرایش)
    public array $newItems = [];     // عنوان/توضیح/نوع آیتم‌های جدید (هم‌اندیس با $newFiles)
    public array $newFiles = [];     // فایل آیتم‌های جدید؛ جدا نگه داشته می‌شود چون Livewire فایل داخل آرایه تودرتو را بازیابی نمی‌کند
    public array $removedItems = []; // شناسه آیتم‌هایی که حذف می‌شوند
    public $pendingFiles = [];       // ورودی چندفایلی

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Story $model)
    {
        abort_if(!auth()->user()->can('stories.view'), 403);
        $this->model=$model;
        $this->info['header']='لیست استوری ها';
        $this->info['create']='افزودن استوری';
        $this->info['delete']='حذف استوری';
        $this->info['personal']='استوری';
        $this->info['table']['headers']=[
            '#',
            'نام',
            'آیتم‌ها',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();

    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('stories.edit'), 403);

        $item = $this->model->findOrFail($id);
        $item->update(['status' => !$item->status]);
        $this->loadData();
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $this->info['personal']." موفقیت آپدیت شد."
        );
    }
    public function delete()
    {
        abort_if(!auth()->user()->can('stories.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: $this->info['personal']." موفقیت حذف شد."
            );

            $this->resetData('close');
        }

    }
    public function loadData()
    {
        $query = $this->model->withCount([
            'items',
            'items as video_items_count' => fn ($q) => $q->where('type', 'video'),
        ])->where(function ($query) {
            $query->where('user', 'LIKE', '%' . $this->search . '%');
        });

        $this->data = $query->orderBy('sort')->get();
    }
    public function get_data($id)
    {
        $this->resetErrorBag();
        $this->selectItem= $this->model->with('items.file')->findOrFail($id);
        $this->user=$this->selectItem->user;
        $this->status=(int) $this->selectItem->status;
        $this->avatar = null;
        $this->newItems = [];
        $this->newFiles = [];
        $this->removedItems = [];
        $this->pendingFiles = [];
        $this->items = $this->selectItem->items->map(fn (StoryItem $item) => [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'description' => $item->description,
            'duration' => $item->duration,
            'link' => $item->link,
            'url' => $item->file_url,
        ])->all();
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
            $this->resetErrorBag();
        }else{
            $this->resetExcept(['selectItem','model','info','data']);
            $this->dispatch('close-modal');
        }
        $this->dispatch('editor-update');

    }

    /**
     * فایل‌های انتخاب‌شده به فهرست آیتم‌های جدید اضافه می‌شوند (نوع از روی فایل تشخیص داده می‌شود)
     */
    public function updatedPendingFiles(): void
    {
        $this->validate([
            'pendingFiles' => ['array', 'max:20'],
            'pendingFiles.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov', 'max:40960'],
        ], [
            'pendingFiles.max' => 'در هر بار حداکثر ۲۰ فایل قابل انتخاب است.',
            'pendingFiles.*.mimes' => 'فقط تصویر (jpg, png, webp, gif) یا ویدیو (mp4, webm, mov) مجاز است.',
            'pendingFiles.*.max' => 'حجم هر فایل حداکثر ۴۰ مگابایت است.',
        ]);

        foreach ((array) $this->pendingFiles as $file) {
            $this->newFiles[] = $file;
            $this->newItems[] = [
                'type' => str_starts_with((string) $file->getMimeType(), 'video') ? 'video' : 'image',
                'title' => null,
                'description' => null,
                'duration' => 7000,
                'link' => null,
            ];
        }

        $this->pendingFiles = [];
    }

    public function removeItem(int $index): void
    {
        if (isset($this->items[$index])) {
            $this->removedItems[] = $this->items[$index]['id'];
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    /**
     * فایل آیتم جدید؛ اگر Livewire آن را به‌صورت رشته (livewire-file:...) برگرداند، دوباره به فایل تبدیل می‌شود
     */
    public function newFileAt(int $index): ?\Livewire\Features\SupportFileUploads\TemporaryUploadedFile
    {
        $file = $this->newFiles[$index] ?? null;

        if (is_string($file) && \Livewire\Features\SupportFileUploads\TemporaryUploadedFile::canUnserialize($file)) {
            $file = \Livewire\Features\SupportFileUploads\TemporaryUploadedFile::unserializeFromLivewireRequest($file);
        }

        return $file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && $file->exists() ? $file : null;
    }

    public function removeNewItem(int $index): void
    {
        unset($this->newItems[$index], $this->newFiles[$index]);
        $this->newItems = array_values($this->newItems);
        $this->newFiles = array_values($this->newFiles);
    }

    public function moveItem(string $list, int $index, string $direction): void
    {
        if (!in_array($list, ['items', 'newItems'], true)) {
            return;
        }

        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if (isset($this->{$list}[$index], $this->{$list}[$swap])) {
            [$this->{$list}[$index], $this->{$list}[$swap]] = [$this->{$list}[$swap], $this->{$list}[$index]];

            // فایل‌ها هم‌اندیس با newItems جابه‌جا می‌شوند
            if ($list === 'newItems' && isset($this->newFiles[$index], $this->newFiles[$swap])) {
                [$this->newFiles[$index], $this->newFiles[$swap]] = [$this->newFiles[$swap], $this->newFiles[$index]];
            }
        }
    }

    protected function rules(): array
    {
        $itemRules = fn (string $prefix) => [
            "{$prefix}.*.title" => ['nullable', 'string', 'max:150'],
            "{$prefix}.*.description" => ['nullable', 'string', 'max:1000'],
            "{$prefix}.*.duration" => ['required', 'integer', 'min:1000', 'max:60000'],
            "{$prefix}.*.link" => ['nullable', 'url', 'max:500'],
        ];

        return [
            'user' => [
                'required',
                'string',
                'max:100',
            ],

            'avatar' => [
                $this->selectItem ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'status' => ['required', 'boolean'],

            'newFiles.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov', 'max:40960'],
        ] + $itemRules('items') + $itemRules('newItems');
    }

    protected function messages(): array
    {
        return [
            'user.required' => 'وارد کردن نام استوری الزامی است.',
            'user.max' => 'نام استوری نمی‌تواند بیشتر از ۱۰۰ کاراکتر باشد.',

            'avatar.required' => 'تصویر آواتار الزامی است.',
            'avatar.image' => 'آواتار باید تصویر باشد.',
            'avatar.max' => 'حجم آواتار حداکثر ۵ مگابایت است.',

            'newFiles.*.required' => 'فایل آیتم الزامی است.',
            'newFiles.*.mimes' => 'فقط تصویر یا ویدیو مجاز است.',
            'newFiles.*.max' => 'حجم هر فایل حداکثر ۴۰ مگابایت است.',

            '*.*.title.max' => 'عنوان حداکثر ۱۵۰ کاراکتر است.',
            '*.*.description.max' => 'توضیحات حداکثر ۱۰۰۰ کاراکتر است.',
            '*.*.duration.required' => 'مدت نمایش الزامی است.',
            '*.*.duration.min' => 'مدت نمایش حداقل ۱ ثانیه است.',
            '*.*.duration.max' => 'مدت نمایش حداکثر ۶۰ ثانیه است.',
            '*.*.link.url' => 'لینک وارد شده معتبر نیست.',
            '*.*.link.max' => 'لینک نمی‌تواند بیشتر از ۵۰۰ کاراکتر باشد.',
        ];
    }

    public function save(){
        abort_if(!auth()->user()->can($this->selectItem ? 'stories.edit' : 'stories.create'), 403);

        // فایل‌های برگشتی به‌صورت رشته دوباره به فایل تبدیل می‌شوند (فایل منقضی‌شده => خطای «الزامی»)
        $this->newFiles = array_map(fn ($index) => $this->newFileAt($index), array_keys($this->newFiles));

        $this->validate();

        if (count($this->items) + count($this->newItems) === 0) {
            $this->addError('newItems', 'حداقل یک تصویر یا ویدیو برای استوری اضافه کنید.');
            return;
        }

        $clean = fn ($value) => filled($value) ? trim((string) $value) : null;
        $editing = (bool) $this->selectItem;

        DB::transaction(function () use ($clean) {
            $data = [
                'user' => trim($this->user),
                'status' => (bool) $this->status,
            ];

            $item = $this->selectItem
                ? tap($this->selectItem)->update($data)
                : $this->model->create($data + ['sort' => (int) $this->model->max('sort') + 1]);

            if ($this->avatar) {
                $item->media()->where('collection', 'avatar')->get()->each(fn ($m) => $this->deleteMedia($m));
                $this->upload($this->avatar, $item, 'avatar');
            }

            // حذف آیتم‌ها و فایل‌هایشان
            StoryItem::where('story_id', $item->id)->whereIn('id', $this->removedItems)->get()->each(function (StoryItem $storyItem) {
                $storyItem->media->each(fn ($m) => $this->deleteMedia($m));
                $storyItem->delete();
            });

            $sort = 1;

            foreach ($this->items as $row) {
                StoryItem::where('story_id', $item->id)->whereKey($row['id'])->update([
                    'title' => $clean($row['title'] ?? null),
                    'description' => $clean($row['description'] ?? null),
                    'duration' => (int) $row['duration'],
                    'link' => $clean($row['link'] ?? null),
                    'sort' => $sort++,
                ]);
            }

            foreach ($this->newItems as $index => $row) {
                $file = $this->newFileAt($index);

                if (!$file) {
                    continue;
                }

                $storyItem = StoryItem::create([
                    'story_id' => $item->id,
                    'type' => $row['type'],
                    'title' => $clean($row['title'] ?? null),
                    'description' => $clean($row['description'] ?? null),
                    'duration' => (int) $row['duration'],
                    'link' => $clean($row['link'] ?? null),
                    'sort' => $sort++,
                ]);

                // تصاویر به‌صورت خودکار فشرده می‌شوند (FileUploadTrait)
                $this->upload($file, $storyItem, 'story_media');
            }
        });

        // رفرش دیتا
        $this->loadData();

        // ریست فرم
        $this->resetData('close');
        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $editing
                ? $this->info['personal'] . ' با موفقیت ویرایش شد.'
                : $this->info['personal'] . ' جدید با موفقیت ایجاد شد.',
        );
    }

    #[\Livewire\Attributes\On('updateOrder')]
    public function updateOrder($ids)
    {
        abort_if(!auth()->user()->can('stories.edit'), 403);

        foreach ($ids as $index => $id) {
            $this->model->where('id', $id)->update([
                'sort' => $index + 1
            ]);
        }
        $this->loadData();
    }

};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">
                {{$info['header']}}
            </h1>

        </div>
        <div class="btn-list">
            @can('stories.view')

                <a href="{{route('stories.trash')}}" class="btn btn-warning-light btn-wave me-2">
                    <i class="bx bx-trash align-middle">
                    </i>
                    سطل آشغال
                </a>
            @endcan
            @can('stories.create')
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
                            <tbody  id="simple-list">
                            @forelse($data ?? [] as $item)
                                <tr data-id="{{ $item->id }}" wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{ $loop->iteration }}
                                    </th>
                                    <td>
                                        {{$item->user}}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">{{ $item->items_count - $item->video_items_count }} تصویر</span>
                                        <span class="badge bg-light text-dark">{{ $item->video_items_count }} ویدیو</span>
                                    </td>
                                    <td>
                                        @can('stories.edit')

                                            <span style="cursor: pointer" wire:click="change_status({{$item->id}})"
                                                  wire:loading.attr="disabled"
                                                  class="badge bg-outline-{{$item->status == 1 ? 'success' : 'danger'}}">
                                            <span wire:target="change_status" wire:loading.remove>{{$item->status == 1 ? 'فعال' : 'غیرفعال'}}</span>
                                            <span wire:target="change_status" wire:loading>در حال تغییر...</span>
                                     </span>
                                        @endcan
                                    </td>
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            @can('stories.edit')

                                                <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                        class="ri-edit-line"></i></a>
                                            @endcan
                                            @can('stories.delete')

                                                <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                        class="ri-delete-bin-5-line"></i></a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($info['table']['headers'] ?? []) }}" class="text-center py-5 text-muted">
                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                        <strong>موردی برای نمایش وجود ندارد.</strong>
                                        <div class="small mt-1">
                                            پس از ایجاد اولین آیتم، اطلاعات در این بخش نمایش داده خواهد شد.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered text-center modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $selectItem ? 'ویرایش استوری' : $info['create'] }}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <form wire:submit.prevent="save" id="save">

                        <div class="row g-3">

                            {{-- نام استوری (زیر دایره آواتار) --}}
                            <div class="col-xl-5">
                                <label class="form-label">نام استوری</label>
                                <input wire:model.lazy="user" type="text"
                                       class="form-control @error('user') is-invalid @enderror"
                                       placeholder="نامی که زیر دایره استوری نمایش داده می‌شود">
                                @error('user') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- تصویر آواتار --}}
                            <div class="col-xl-5">
                                <label class="form-label">تصویر آواتار @if($selectItem)<span class="text-muted small">(برای تغییر انتخاب کنید)</span>@endif</label>
                                <input wire:model="avatar" class="form-control @error('avatar') is-invalid @enderror" type="file" accept="image/*">
                                <div wire:loading wire:target="avatar" class="small text-muted mt-1">در حال بارگذاری...</div>
                                @error('avatar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            {{-- وضعیت --}}
                            <div class="col-xl-2">
                                <label class="form-label">وضعیت</label>
                                <select wire:model="status" class="form-select @error('status') is-invalid @enderror">
                                    <option value="1">فعال</option>
                                    <option value="0">غیرفعال</option>
                                </select>
                            </div>

                            {{-- افزودن تصویر / ویدیو --}}
                            <div class="col-12">
                                <div class="border rounded p-3 bg-light">
                                    <label class="form-label fw-semibold mb-1">افزودن تصویر یا ویدیو به استوری</label>
                                    <div class="small text-muted mb-2">می‌توانید چند فایل را با هم انتخاب کنید. تصاویر هنگام ذخیره به‌صورت خودکار فشرده می‌شوند. حداکثر حجم هر فایل ۴۰ مگابایت.</div>
                                    <div
                                        x-data="{ progress: 0 }"
                                        x-on:livewire-upload-start="progress = 0"
                                        x-on:livewire-upload-finish="progress = 0"
                                        x-on:livewire-upload-error="progress = 0"
                                        x-on:livewire-upload-progress="progress = $event.detail.progress">
                                        <input wire:model="pendingFiles" type="file" multiple
                                               accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
                                               class="form-control @error('pendingFiles') is-invalid @enderror @error('pendingFiles.*') is-invalid @enderror">
                                        <div class="progress mt-2" x-show="progress > 0">
                                            <div class="progress-bar" role="progressbar" :style="'width: ' + progress + '%'"><span x-text="progress + '%'"></span></div>
                                        </div>
                                    </div>
                                    @error('pendingFiles') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    @error('pendingFiles.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    @error('newItems') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            {{-- آیتم‌ها --}}
                            @php
                                $rows = collect($items)->map(fn ($r, $i) => ['list' => 'items', 'index' => $i, 'row' => $r])
                                    ->concat(collect($newItems)->map(fn ($r, $i) => ['list' => 'newItems', 'index' => $i, 'row' => $r]));
                            @endphp
                            @foreach($rows as $entry)
                                @php
                                    $list = $entry['list'];
                                    $i = $entry['index'];
                                    $row = $entry['row'];
                                    $isNew = $list === 'newItems';
                                    $file = $isNew ? $this->newFileAt($i) : null;
                                    $hasFile = $file !== null;
                                    $preview = $isNew
                                        ? ($hasFile && $file->isPreviewable() ? $file->temporaryUrl() : null)
                                        : $row['url'];
                                    $count = $isNew ? count($newItems) : count($items);
                                @endphp
                                <div class="col-12" wire:key="story-{{ $list }}-{{ $isNew ? 'n' . $i . '-' . ($hasFile ? $file->getFilename() : '') : $row['id'] }}">
                                    <div class="border rounded p-3 d-flex gap-3 flex-wrap flex-md-nowrap">
                                        <div class="flex-shrink-0 text-center" style="width: 120px">
                                            <div class="rounded overflow-hidden bg-dark d-flex align-items-center justify-content-center" style="width: 120px; height: 200px">
                                                @if($preview && $row['type'] === 'video')
                                                    <video src="{{ $preview }}" class="w-100 h-100" style="object-fit: cover" muted playsinline preload="metadata"></video>
                                                @elseif($preview)
                                                    <img src="{{ $preview }}" class="w-100 h-100" style="object-fit: cover" alt="">
                                                @else
                                                    <i class="ri-{{ $row['type'] === 'video' ? 'film' : 'image' }}-line text-white fs-1"></i>
                                                @endif
                                            </div>
                                            <span class="badge bg-{{ $row['type'] === 'video' ? 'danger' : 'primary' }}-transparent mt-2">{{ $row['type'] === 'video' ? 'ویدیو' : 'تصویر' }}</span>
                                            @if($isNew)<span class="badge bg-success-transparent mt-2">جدید</span>@endif
                                        </div>

                                        <div class="flex-grow-1">
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">عنوان <span class="text-muted">(اختیاری)</span></label>
                                                    <input type="text" wire:model="{{ $list }}.{{ $i }}.title" class="form-control form-control-sm @error($list . '.' . $i . '.title') is-invalid @enderror">
                                                    @error($list . '.' . $i . '.title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">لینک <span class="text-muted">(اختیاری)</span></label>
                                                    <input type="url" dir="ltr" wire:model="{{ $list }}.{{ $i }}.link" class="form-control form-control-sm @error($list . '.' . $i . '.link') is-invalid @enderror" placeholder="https://">
                                                    @error($list . '.' . $i . '.link') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-md-9">
                                                    <label class="form-label small mb-1">توضیحات <span class="text-muted">(اختیاری)</span></label>
                                                    <textarea rows="2" wire:model="{{ $list }}.{{ $i }}.description" class="form-control form-control-sm @error($list . '.' . $i . '.description') is-invalid @enderror"></textarea>
                                                    @error($list . '.' . $i . '.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">مدت نمایش</label>
                                                    @if($row['type'] === 'video')
                                                        <div class="form-control form-control-sm bg-light text-muted">طول ویدیو</div>
                                                    @else
                                                        <select wire:model="{{ $list }}.{{ $i }}.duration" class="form-select form-select-sm">
                                                            @foreach([3000 => '۳ ثانیه', 5000 => '۵ ثانیه', 7000 => '۷ ثانیه', 10000 => '۱۰ ثانیه', 15000 => '۱۵ ثانیه'] as $ms => $label)
                                                                <option value="{{ $ms }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($isNew)
                                                @error('newFiles.' . $i) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                            @endif
                                        </div>

                                        <div class="d-flex flex-md-column gap-1 flex-shrink-0">
                                            <button type="button" class="btn btn-sm btn-light" wire:click="moveItem('{{ $list }}', {{ $i }}, 'up')" @disabled($i === 0) title="بالا"><i class="ri-arrow-up-line"></i></button>
                                            <button type="button" class="btn btn-sm btn-light" wire:click="moveItem('{{ $list }}', {{ $i }}, 'down')" @disabled($i === $count - 1) title="پایین"><i class="ri-arrow-down-line"></i></button>
                                            <button type="button" class="btn btn-sm btn-danger-light"
                                                    wire:click="{{ $isNew ? 'removeNewItem' : 'removeItem' }}({{ $i }})" title="حذف"><i class="ri-delete-bin-5-line"></i></button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if($rows->isEmpty())
                                <div class="col-12 text-center text-muted small py-3">هنوز تصویر یا ویدیویی اضافه نشده است.</div>
                            @endif

                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="save">
                        <button class="btn btn-info"
                                form="save"
                                wire:loading.attr="disabled"
                                wire:target="avatar,pendingFiles,save"
                                type="submit">
                            ذخیره تغییرات
                        </button>
                        <button class="btn btn-light" data-bs-dismiss="modal" type="button">
                            بستن
                        </button>
                    </div>

                    <!-- اسپینر لودینگ Livewire -->
                    <div wire:loading wire:target="save" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال بارگیری...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered text-center " role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['delete'] .' ' .$selectItem?->user}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="ri-error-warning-line fs-4 me-2"></i>
                        <div>
                            از حذف کردن این ایتم مطمین هستید ؟!
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="delete">
                        <button class="btn btn-info" wire:click="delete()">
                            حذف
                        </button>
                        <button class="btn btn-light" data-bs-dismiss="modal">
                            بستن
                        </button>
                    </div>

                    <!-- اسپینر لودینگ Livewire -->
                    <div wire:loading wire:target="delete" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال حذف...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
