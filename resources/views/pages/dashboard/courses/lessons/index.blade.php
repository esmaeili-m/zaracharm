<?php


use App\Models\CourseSection;
use App\Models\CourseLesson;
use App\Models\Media;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Enums\MediaType;

new #[Layout('layouts.dashboard')] class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;

    public CourseSection $section;

    public CourseLesson $model;

    public array $info = [];

    public $selectItem;

    public $data;

    public $title = '';

    public $slug = '';

    public $description = '';

    public $short_description = '';

    public $duration = 0;

    public $sort = 0;

    public $is_free = false;

    public $status = false;

    public $lesson_type = '';

    public $published_at = null;

    public $search;

    public $mediaLessonId = null;

    public $lessonMedia = [];

    public $mediaUpload;

    public $externalUrl = '';

    public $externalName = '';

    public $internalName = '';

    public $collection = 'main';

    public $internalCollection = 'main';

    public $externalType = 'link';

    public function mount(CourseSection $section, CourseLesson $model): void
    {
        $this->section = $section;
        $this->model = $model;
        $this->info['header'] = 'مدیریت درس‌ها — '.$section->title;
        $this->info['create'] = 'افزودن درس';
        $this->info['delete'] = 'حذف درس';
        $this->info['table']['headers'] = [
            '',
            '#',
            'عنوان',
            'مدت (دقیقه)',
            'فایل‌ها',
            'وضعیت انتشار',
            'عملیات',
        ];
        $this->loadData();
    }

    public function change_status($id): void
    {
        $item = $this->scopedQuery()->findOrFail($id);
        $payload = ['status' => ! $item->status];
        if ($payload['status'] && ! $item->published_at) {
            $payload['published_at'] = now();
        }
        $item->update($payload);
        $this->loadData();
    }

    public function delete(): void
    {
        if ($this->selectItem) {
            $this->scopedQuery()->findOrFail($this->selectItem->id)->delete();
            $this->loadData();
            $this->resetData('close');
        }
    }

    public function loadData(): void
    {
        $query = $this->scopedQuery()->withCount('media');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%')
                    ->orWhere('short_description', 'like', '%'.$this->search.'%');
            });
        }

        $this->data = $query->orderBy('sort')->get();
    }

    public function get_data($id): void
    {
        $this->selectItem = $this->scopedQuery()->findOrFail($id);
        $this->title = $this->selectItem->title;
        $this->slug = $this->selectItem->slug;
        $this->description = $this->selectItem->description;
        $this->short_description = $this->selectItem->short_description;
        $this->duration = $this->selectItem->duration;
        $this->sort = $this->selectItem->sort;
        $this->is_free = $this->selectItem->is_free;
        $this->status = $this->selectItem->status;
        $this->published_at = $this->selectItem->published_at;
    }

    public function resetData($action = 'create'): void
    {
        if ($action === 'create') {
            $this->resetExcept('section', 'model', 'info', 'data');
            $this->sort = ($this->data?->max('sort') ?? 0) + 1;
        } else {
            $this->resetExcept(['section', 'model', 'info', 'data']);
            $this->dispatch('close-modal');
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['course_section_id'] = $this->section->id;
        $data['user_id'] = auth()->id() ?? 1;

        if ($this->selectItem) {
            $this->selectItem->update($data);
        } else {
            $this->model->create($data);
        }
        $this->loadData();
        $this->resetData('close');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'درس با موفقیت ذخیره شد.');
    }

    public function updateSort(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            $this->scopedQuery()
                ->whereKey($id)
                ->update(['sort' => $index + 1]);
        }
        $this->loadData();
    }

    public function openMediaManager($lessonId): void
    {
        $this->mediaLessonId = $lessonId;
        $this->loadLessonMedia();
        $this->reset(['mediaUpload', 'externalUrl', 'externalName']);
        $this->collection = 'main';
    }

    public function loadLessonMedia(): void
    {
        if (! $this->mediaLessonId) {
            $this->lessonMedia = [];

            return;
        }

        $lesson = $this->scopedQuery()->with('media')->findOrFail($this->mediaLessonId);
        $this->lessonMedia = $lesson->media;
    }
    #[\Livewire\Attributes\On('updateOrder')]
    public function updateOrder($ids)
    {
        foreach ($ids as $index => $id) {
            CourseLesson::where('course_section_id',$this->section->id)->where('id', $id)->update([
                'sort' => $index + 1
            ]);
        }
        $this->loadData();
    }
    public function saveMediaUpload(): void
    {
        $this->validate([
            'mediaUpload' => 'required|file|max:512000',
            'collection' => 'required|string|max:50',
            'internalName' => 'required|string|max:150',
        ], [
            'mediaUpload.required' => 'انتخاب فایل الزامی است.',
            'mediaUpload.file' => 'فایل انتخاب شده معتبر نیست.',
            'mediaUpload.max' => 'حجم فایل نمی‌تواند بیشتر از ۵۰۰ مگابایت باشد.',

            'collection.required' => 'نوع فایل الزامی است.',
            'collection.string' => 'نوع فایل نامعتبر است.',
            'collection.max' => 'نوع فایل نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',

            'internalName.required' => 'عنوان فایل الزامی است.',
            'internalName.string' => 'عنوان فایل باید متن باشد.',
            'internalName.max' => 'عنوان فایل نمی‌تواند بیشتر از ۱۵۰ کاراکتر باشد.',
        ]);
        if ($this->collection === 'main') {

            $exists = Media::where('mediable_type', $this->model->getMorphClass())
                ->where('mediable_id', $this->mediaLessonId)
                ->where('collection', 'main')
                ->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'collection' => 'برای این درس فقط یک فایل اصلی (main) می‌توانید داشته باشید.',
                ]);
            }
        }
        if ($this->collection === 'demo') {

            $exists = Media::where('mediable_type', $this->model->getMorphClass())
                ->where('mediable_id', $this->mediaLessonId)
                ->where('collection', 'demo')
                ->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'collection' => 'برای این درس فقط یک دمو (demo) می‌توانید داشته باشید.',
                ]);
            }
        }
        $lesson = $this->scopedQuery()->findOrFail($this->mediaLessonId);
        $sort = ($lesson->media()->max('sort') ?? 0) + 1;
        $this->upload(
            $this->mediaUpload,
            $lesson,
            $this->collection,
            sort: $sort,
            name: $this->internalName,
        );

        $this->reset('mediaUpload','internalName');
        $this->loadLessonMedia();
        $this->loadData();
    }

    public function saveExternalMedia(): void
    {
        $this->externalUrl=str_replace(" ","",$this->externalUrl);
        $this->validate([
            'externalUrl' => 'required|url|max:2000',
            'collection' => 'required|string|max:50',
            'externalName' => 'required|string|max:255',
        ], [
            'externalUrl.required' => 'آدرس لینک الزامی است.',
            'externalUrl.url' => 'فرمت لینک وارد شده معتبر نیست.',
            'externalUrl.max' => 'آدرس لینک نمی‌تواند بیشتر از ۲۰۰۰ کاراکتر باشد.',

            'collection.required' => 'نوع فایل الزامی است.',
            'collection.string' => 'نوع فایل نامعتبر است.',
            'collection.max' => 'نوع فایل نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',

            'externalName.required' => 'عنوان فایل الزامی است.',
            'externalName.string' => 'عنوان فایل باید متن باشد.',
            'externalName.max' => 'عنوان فایل نمی‌تواند بیشتر از ۱۵۰ کاراکتر باشد.',
        ]);

        $lesson = $this->scopedQuery()->findOrFail($this->mediaLessonId);

        $sort = ($lesson->media()->max('sort') ?? 0) + 1;

        $this->attachExternal(
            $this->externalUrl,
            $lesson,
            $this->collection,
            sort: $sort,
            name: $this->externalName,
        );

        $this->reset([
            'externalUrl',
            'externalName',
        ]);

        $this->loadLessonMedia();

        $this->loadData();

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'لینک خارجی با موفقیت ثبت شد.'
        );
    }

    public function deleteLessonMedia($mediaId): void
    {
        $media = Media::query()
            ->whereKey($mediaId)
            ->where('mediable_type', $this->model->getMorphClass())
            ->whereIn('mediable_id', $this->scopedQuery()->select('id'))
            ->firstOrFail();

        $this->deleteMedia($media);
        $this->loadLessonMedia();
        $this->loadData();
    }

    protected function scopedQuery()
    {
        return $this->model->newQuery()->where('course_section_id', $this->section->id);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'slug' => [
                'required', 'string', 'min:3', 'max:255', 'alpha_dash',
                Rule::unique('course_lessons', 'slug')
                    ->where('course_section_id', $this->section->id)
                    ->ignore($this->selectItem?->id),
            ],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'duration' => ['required', 'integer', 'min:0'],
            'sort' => ['required', 'integer', 'min:0'],
            'is_free' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
            'published_at' => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'عنوان درس الزامی است.',
            'title.string' => 'عنوان درس باید متن باشد.',
            'title.min' => 'عنوان درس باید حداقل ۳ کاراکتر باشد.',
            'title.max' => 'عنوان درس نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',

            'slug.required' => 'اسلاگ الزامی است.',
            'slug.string' => 'اسلاگ باید متن باشد.',
            'slug.min' => 'اسلاگ باید حداقل ۳ کاراکتر باشد.',
            'slug.max' => 'اسلاگ نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'slug.alpha_dash' => 'اسلاگ فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',
            'slug.unique' => 'این اسلاگ در این دوره قبلاً ثبت شده است.',

            'description.string' => 'توضیحات باید متن باشد.',

            'short_description.string' => 'توضیح کوتاه باید متن باشد.',
            'short_description.max' => 'توضیح کوتاه نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',

            'duration.required' => 'مدت زمان درس الزامی است.',
            'duration.integer' => 'مدت زمان باید عدد باشد.',
            'duration.min' => 'مدت زمان نمی‌تواند کمتر از صفر باشد.',

            'sort.required' => 'ترتیب نمایش الزامی است.',
            'sort.integer' => 'ترتیب نمایش باید عدد باشد.',
            'sort.min' => 'ترتیب نمایش نمی‌تواند کمتر از صفر باشد.',

            'is_free.required' => 'وضعیت رایگان بودن درس الزامی است.',
            'is_free.boolean' => 'مقدار رایگان بودن نامعتبر است.',

            'status.required' => 'وضعیت انتشار الزامی است.',
            'status.boolean' => 'وضعیت انتشار نامعتبر است.',

            'published_at.string' => 'فرمت تاریخ انتشار نامعتبر است.',
        ];
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">{{ $info['header'] }}</h1>
        </div>
        <div class="btn-list">
            <a href="{{ route('sections.index') }}" class="btn btn-light btn-wave me-2">
                <i class="bx bx-undo align-middle"></i> بازگشت به دوره‌ها
            </a>
            <a href="{{ route('courses.lessons.trash', $section) }}" class="btn btn-warning-light btn-wave me-2">
                <i class="bx bx-trash align-middle"></i> سطل آشغال
            </a>
            <button wire:click="resetData()" data-bs-toggle="modal" href="#create" class="btn btn-success-light btn-wave">
                <i class="ri-add-line align-middle"></i> {{ $info['create'] }}
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">لیست درس‌ها</div>
                    <div class="header-element header-search d-md-block d-none my-auto">
                        <input wire:model.lazy="search" wire:keydown.enter="loadData" class="header-search-bar form-control" placeholder="جستجو در درس‌ها...">
                        <a class="header-search-icon border-0" href="javascript:void(0);">
                            <i wire:click="loadData()" class="bi bi-search"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap">
                            <thead>
                            <tr>
                                @foreach($info['table']['headers'] as $h)
                                    <th scope="col">{{ $h }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody id="simple-list" wire:ignore.self>
                            @php($counter = 1)
                            @foreach($data ?? [] as $item)
                                <tr wire:key="lesson-{{ $item->id }}" data-id="{{ $item->id }}">
                                    <td class="text-muted" style="cursor: grab; width: 36px;">
                                        <i class="ri-drag-move-2-line fs-16 lesson-drag-handle"></i>
                                    </td>
                                    <th scope="row">{{ $counter }}</th>
                                    <td>{{ $item->title }}</td>

                                    <td>{{ $item->duration }}</td>
                                    <td>
                                        <span class="badge bg-secondary-transparent">{{ $item->media_count }} فایل</span>
                                    </td>
                                    <td>

                                        <span style="cursor: pointer" wire:click="change_status({{ $item->id }})"
                                              class="badge bg-outline-{{ $item->status ? 'success' : 'danger' }} ms-1">
                                            {{ $item->status ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="hstack gap-2 flex-wrap">
                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{ $item->id }})" class="text-info fs-14 lh-1" title="ویرایش">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a data-bs-toggle="modal" href="#media" wire:click="openMediaManager({{ $item->id }})" class="text-primary fs-14 lh-1" title="مدیریت فایل‌ها">
                                                <i class="ri-folder-video-line"></i>
                                            </a>
                                            <a data-bs-toggle="modal" href="#delete" wire:click="get_data({{ $item->id }})" class="text-danger fs-14 lh-1" title="حذف">
                                                <i class="ri-delete-bin-5-line"></i>
                                            </a>
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

    {{-- Create / Edit modal --}}
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <form wire:submit="save">
                    <div class="modal-header">
                        <h6 class="modal-title">{{ $selectItem ? 'ویرایش درس' : $info['create'] }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">عنوان درس</label>
                                <input wire:model.lazy="title" type="text" class="form-control @error('title') is-invalid @enderror">
                                @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اسلاگ</label>
                                <input wire:model.lazy="slug" type="text" class="form-control @error('slug') is-invalid @enderror">
                                @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>


                            <div class="col-md-4">
                                <label class="form-label">مدت (دقیقه)</label>
                                <input wire:model.lazy="duration" type="number" min="0" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ترتیب</label>
                                <input wire:model.lazy="sort" type="number" min="0" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تاریخ انتشار (شمسی)</label>
                                <input wire:model.lazy="published_at" type="text" class="form-control" data-jdp placeholder="1404/01/01">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-3">
                                <div class="form-check">
                                    <input wire:model="is_free" class="form-check-input" type="checkbox" id="lesson-free">
                                    <label class="form-check-label" for="lesson-free">رایگان</label>
                                </div>
                                <div class="form-check">
                                    <input wire:model="status" class="form-check-input" type="checkbox" id="lesson-status">
                                    <label class="form-check-label" for="lesson-status">منتشر شود</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیح کوتاه</label>
                                <textarea wire:model.lazy="short_description" rows="2" class="form-control"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیحات کامل</label>
                                <div wire:ignore>
                                    <div id="editor"></div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">ذخیره</span>
                            <span wire:loading wire:target="save">در حال ذخیره...</span>
                        </button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Media manager modal --}}
    <div wire:ignore.self class="modal fade" id="media">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">مدیریت فایل‌های درس</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <h6 class="fw-semibold mb-3">آپلود فایل</h6>
                            <div class="mb-3">
                                <label class="form-label">مجموعه (collection)</label>
                                <select wire:model="collection" class="form-select">
                                    <option value="demo">دمو</option>
                                    <option value="main">فایل اصلی درس</option>
                                    <option value="attachment">پیوست</option>
                                </select>
                                @error('collection')<div class="text-danger small">{{ $message }}</div>@enderror

                            </div>
                            <div class="mb-3">

                                <div x-data="{ progress: 0 }"
                                     x-on:livewire-upload-start="progress = 0"
                                     x-on:livewire-upload-finish="progress = 100"
                                     x-on:livewire-upload-error="progress = 0"
                                     x-on:livewire-upload-progress="progress = $event.detail.progress">

                                    <input wire:model="mediaUpload" type="file" class="form-control mb-1">

                                    <div class="progress mt-2" x-show="progress > 0">
                                        <div class="progress-bar"
                                             role="progressbar"
                                             :style="'width: ' + progress + '%'">
                                            <span x-text="progress + '%'"></span>
                                        </div>
                                    </div>
                                </div>
                            @error('mediaUpload')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-2">
                                <label class="form-label">عنوان نمایشی</label>
                                <input wire:model.lazy="internalName" type="text" class="form-control mb-1">
                                @error('internalName')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <button type="button" wire:click="saveMediaUpload" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                آپلود فایل
                            </button>

                            <hr class="my-4">

                            <h6 class="fw-semibold mb-3">افزودن لینک خارجی</h6>
                            <div class="mb-3">
                                <label class="form-label">مجموعه (collection)</label>
                                <select wire:model="collection" class="form-select">
                                    <option value="demo">دمو</option>
                                    <option value="main">فایل اصلی درس</option>
                                    <option value="attachment">پیوست</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">آدرس URL</label>
                                <input wire:model.lazy="externalUrl" type="url" class="form-control mb-1" placeholder="https://...">
                                @error('externalUrl')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label">عنوان نمایشی</label>
                                <input wire:model.lazy="externalName" type="text" class="form-control mb-1">
                                @error('externalName')<div class="text-danger small">{{ $message }}</div>@enderror

                            </div>

                            <button type="button" wire:click="saveExternalMedia" class="btn btn-outline-primary btn-sm">
                                افزودن لینک
                            </button>
                        </div>
                        <div class="col-lg-7">
                            <h6 class="fw-semibold mb-3">فایل‌های ثبت‌شده</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>نوع</th>
                                        <th>نام</th>
                                        <th>مجموعه</th>
                                        <th>عملیات</th>
                                    </tr>
                                    </thead>
                                    <tbody   >
                                    @forelse($lessonMedia as $media)
                                        <tr    wire:key="media-{{ $media->id }}">
                                            <td>
                                                <i class="{{ $media->type?->icon() ?? 'ri-file-line' }}"></i>
                                                {{ $media->type?->label() }}
                                            </td>
                                            <td class="text-truncate" style="max-width: 180px;">
                                                @if($media->isExternal())
                                                    <a href="{{$media->external_url }}" target="_blank" rel="noopener">{{ $media->name }}</a>
                                                @else
                                                    <a href="{{ '/media/'.$media->file_path }}" target="_blank">{{ $media->name }}</a>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-light text-dark">{{ $media->collection }}</span></td>
                                            <td>
                                                <button type="button" wire:click="deleteLessonMedia({{ $media->id }})"
                                                        wire:confirm="این فایل حذف شود؟"
                                                        class="btn btn-sm btn-danger-light">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted text-center">فایلی ثبت نشده است.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered text-center modal-lg" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['delete'] .' ' .$selectItem?->title}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">

                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <svg class="flex-shrink-0 me-2 svg-danger" xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="1.5rem" viewBox="0 0 24 24" width="1.5><0rem" fill="1.5rem" fill="1.5rem"00" height="24" width="24"/></g><g><g><g><path d="M15.73,3H8.27L3,8.27v7.46L8.27,21h7.46L21,15.73V8.27L15.73,3z M19,14.9L14.9,19H9.1L5,14.9V9.1L9.1,5h5.8L19,9.1V14.9z"/><rect height="6" width="2" x="11" y="7"/><rect height="2" width="2"><g="11">
                                        <div>
                                            از حذف کردن این ایتم مطمین هستید ؟!
                                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="delete">
                        <button class="btn btn-primary" wire:click="delete()">
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

@push('scripts')
    <script src="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.js')}}"></script>
    <script>
        jalaliDatepicker.startWatch();
    </script>
    <script>
        const toolbarOptions = [
            [{ header: [1, 2, 3, 4, 5, 6, false] }],
            [{ font: [] }],
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ script: 'sub' }, { script: 'super' }],
            [{ indent: '-1' }, { indent: '+1' }],
            [{ direction: 'rtl' }],
            [{ size: ['small', false, 'large', 'huge'] }],
            [{ color: [] }, { background: [] }],
            [{ align: [] }],
            ['image', 'video'],
            ['clean']
        ];

        async function uploadToLivewire(file) {
            return new Promise((resolve, reject) => {

            @this.upload(
                'editorImage',
                file,

                async (uploadedFilename) => {

                    try {
                        const url = await @this.call('saveEditorImage');
                        resolve(url);
                    } catch (e) {
                        reject(e);
                    }

                },

                (error) => {
                    reject(error);
                },

                (event) => {
                    console.log(event.detail.progress + '%');
                }
            );

            });
        }

        function imageHandler() {

            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');

            input.click();

            input.onchange = async () => {

                const file = input.files[0];

                if (!file) return;

                const range = quill.getSelection(true);

                const loadingText = 'در حال آپلود...';

                quill.insertText(range.index, loadingText, {
                    italic: true,
                    color: '#999'
                });

                quill.disable();

                try {

                    const url = await uploadToLivewire(file);

                    quill.enable();

                    quill.deleteText(range.index, loadingText.length);

                    quill.insertEmbed(range.index, 'image', url);

                    quill.setSelection(range.index + 1);

                } catch (e) {

                    console.error(e);

                    quill.enable();

                    quill.deleteText(range.index, loadingText.length);

                    alert('خطا در آپلود تصویر');

                }

            };
        }

        const quill = new Quill('#editor', {

            theme: 'snow',

            modules: {
                toolbar: {
                    container: toolbarOptions,
                    handlers: {
                        image: imageHandler
                    }
                }
            }

        });

        quill.on('text-change', function () {

        @this.set('description', quill.root.innerHTML);

        });

        Livewire.on('editor-update', () => {

            const html = @this.get('description');

            if (quill.root.innerHTML !== html) {
                quill.root.innerHTML = html;
            }

        });

    </script>
    <script src="{{asset('dashboard')}}/libs/sortablejs/Sortable.min.js"></script>
    <!-- Internal Sortable JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const simple = document.getElementById('simple-list');

            new Sortable(simple, {
                animation: 150,

                onEnd: function () {

                    const ids = Array.from(simple.children)
                        .map(item => item.dataset.id);

                    Livewire.dispatch('updateOrder', {
                        ids: ids
                    });
                }
            });

        });
    </script>
@endpush
@push('styles')
    <link rel="stylesheet" href="{{asset('dashboard/libs/datepicker/jalalidatepicker.min.css')}}">
    <link href="{{asset('dashboard')}}/libs/quill/quill.snow.css" rel="stylesheet"/>
    <link href="{{asset('dashboard')}}/libs/quill/quill.bubble.css" rel="stylesheet"/>
@endpush
