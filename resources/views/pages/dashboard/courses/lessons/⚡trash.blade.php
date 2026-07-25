<?php

use App\Models\Course;
use App\Models\CourseLesson;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.dashboard')] class extends Component
{
    public Course $course;

    public CourseLesson $model;

    public array $info = [];

    public $selectItem;

    public $data;

    public function mount(Course $course, CourseLesson $model): void
    {
        $this->course = $course;
        $this->model = $model;
        $this->info['header'] = 'درس‌های حذف‌شده — '.$course->title;
        $this->info['table']['headers'] = ['#', 'عنوان', 'نوع', 'عملیات'];
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->data = $this->model->newQuery()
            ->where('course_id', $this->course->id)
            ->onlyTrashed()
            ->orderBy('sort')
            ->get();
    }

    public function get_data($id): void
    {
        $this->selectItem = $this->model->newQuery()
            ->where('course_id', $this->course->id)
            ->onlyTrashed()
            ->findOrFail($id);
    }

    public function delete(): void
    {
        if ($this->selectItem) {
            $this->selectItem->forceDelete();
            $this->loadData();
            $this->resetData();
        }
    }

    public function restore(): void
    {
        if ($this->selectItem) {
            $this->selectItem->restore();
            $this->loadData();
            $this->resetData();
        }
    }

    public function resetData(): void
    {
        $this->resetExcept(['course', 'model', 'info', 'data']);
        $this->dispatch('close-modal');
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">{{ $info['header'] }}</h1>
        </div>
        <div class="btn-list">
            <a href="{{ route('courses.lessons', $course) }}" class="btn btn-warning-light btn-wave">
                <i class="bx bx-undo align-middle"></i> بازگشت به درس‌ها
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap">
                            <thead>
                            <tr>
                                @foreach($info['table']['headers'] as $h)
                                    <th>{{ $h }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @php($counter = 1)
                            @foreach($data as $item)
                                <tr wire:key="trash-lesson-{{ $item->id }}">
                                    <th>{{ $counter++ }}</th>
                                    <td>{{ $item->title }}</td>
                                    <td>{{ $item->lesson_type?->label() ?? $item->lesson_type }}</td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <a data-bs-toggle="modal" href="#restore" wire:click="get_data({{ $item->id }})" class="text-info fs-14">
                                                <i class="ri-arrow-go-back-line"></i>
                                            </a>
                                            <a data-bs-toggle="modal" href="#delete" wire:click="get_data({{ $item->id }})" class="text-danger fs-14">
                                                <i class="ri-delete-bin-5-line"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="restore">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <h6>بازگردانی درس</h6>
                    <p class="text-muted">{{ $selectItem?->title }}</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button wire:click="restore" type="button" class="btn btn-success">بازگردانی</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <h6>حذف دائمی درس</h6>
                    <p class="text-muted">{{ $selectItem?->title }}</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button wire:click="delete" type="button" class="btn btn-danger">حذف دائمی</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                </div>
            </div>
        </div>
    </div>
</div>
