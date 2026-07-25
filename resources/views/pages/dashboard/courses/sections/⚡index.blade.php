<?php


use App\Models\Course;
use App\Models\CourseSection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.dashboard')] class extends Component
{

    public Course $course;

    public  $model;

    public array $info = [];

    public $selectItem;

    public $data;

    public $title = '';

    public $description = '';

    public $sort = 0;

    public $search;

    public function mount(Course $course, CourseSection $model): void
    {
        $this->course = $course;
        $this->model = $model;
        $this->info['header'] = 'مدیریت فصل ها — '.$course->title;
        $this->info['create'] = 'افزودن فصل';
        $this->info['delete'] = 'حذف فصل';
        $this->info['table']['headers'] = [
            '',
            '#',
            'عنوان',
            'عملیات',
        ];
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
        $query = $this->scopedQuery();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            });
        }

        $this->data = $query->orderBy('sort')->get();
    }
    #[\Livewire\Attributes\On('updateOrder')]
    public function updateOrder($ids)
    {
        foreach ($ids as $index => $id) {
           $this->model->where('course_id',$this->course->id)->where('id', $id)->update([
                'sort' => $index + 1
            ]);
        }
        $this->loadData();
    }
    public function get_data($id): void
    {
        $this->selectItem = $this->scopedQuery()->findOrFail($id);
        $this->title = $this->selectItem->title;
        $this->description = $this->selectItem->description;
        $this->sort = $this->selectItem->sort;
    }

    public function resetData($action = 'create'): void
    {
        if ($action === 'create') {
            $this->resetExcept('course', 'model', 'info', 'data');
            $this->sort = ($this->data?->max('sort') ?? 0) + 1;
        } else {
            $this->resetExcept(['course', 'model', 'info', 'data']);
            $this->dispatch('close-modal');
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['course_id'] = $this->course->id;

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




    protected function scopedQuery()
    {
        return $this->model->newQuery()->where('course_id', $this->course->id);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'عنوان درس الزامی است.',
            'title.string' => 'عنوان درس باید متن باشد.',
            'title.min' => 'عنوان درس باید حداقل ۳ کاراکتر باشد.',
            'title.max' => 'عنوان درس نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',



            'description.string' => 'توضیحات باید متن باشد.',


            'sort.required' => 'ترتیب نمایش الزامی است.',
            'sort.integer' => 'ترتیب نمایش باید عدد باشد.',
            'sort.min' => 'ترتیب نمایش نمی‌تواند کمتر از صفر باشد.',
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
            <a href="{{ route('courses.index') }}" class="btn btn-light btn-wave me-2">
                <i class="bx bx-undo align-middle"></i> بازگشت به دوره‌ها
            </a>
            <a href="{{ route('courses.lessons.trash', $course) }}" class="btn btn-warning-light btn-wave me-2">
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
                            <tbody id="simple-list"  wire:ignore.self>
                            @php($counter = 1)
                            @foreach($data ?? [] as $item)
                                <tr  wire:key="lesson-{{ $item->id }}" data-id="{{ $item->id }}">
                                    <td class="text-muted" style="cursor: grab; width: 36px;">
                                        <i class="ri-drag-move-2-line fs-16 lesson-drag-handle"></i>
                                    </td>
                                    <th scope="row">{{ $counter }}</th>
                                    <td>{{ $item->title }}</td>

                                    <td>
                                        <div class="hstack gap-2 flex-wrap">
                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{ $item->id }})" class="text-info fs-14 lh-1" title="ویرایش">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a href="{{ route('sections.lessons', $item) }}" class="text-primary fs-14 lh-1" title="مدیریت فصل"><i
                                                    class="ri-play-list-line"></i></a>

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
                                <label class="form-label">ترتیب</label>
                                <input wire:model.lazy="sort" type="number" min="0" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیح کوتاه</label>
                                <textarea wire:model.lazy="description" rows="2" class="form-control"></textarea>
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
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <h6>{{ $info['delete'] }}</h6>
                    <p class="text-muted mb-0">{{ $selectItem?->title }}</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button wire:click="delete" type="button" class="btn btn-danger">حذف</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
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
