<?php

use Livewire\Component;
use \App\Models\Option;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public Option $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Option $model)
    {
        abort_if(!auth()->user()->can('categories.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست ویژگی های حذف شده';
        $this->info['list']='لیست ویژگی ها';
        $this->info['delete']='حذف ویژگی';
        $this->info['personal']='ویژگی';
        $this->info['restore']='بازگردانی ویژگی';
        $this->info['table']['headers']=[
            '#',
            'نام ویژگی',
            'عملیات',
        ];
        $this->loadData();
    }
    public function loadData()
    {
        $this->data = $this->model->onlyTrashed()->get();
    }
    public function get_data($id)
    {
        $this->selectItem= $this->model->withTrashed()->findOrFail($id);
    }
    public function delete()
    {
        abort_if(!auth()->user()->can('categories.delete'), 403);

        if ($this->selectItem){
            $this->selectItem->forceDelete();
            $this->loadData();
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: $this->info['personal']." باموفقیت حذف شد."
            );
            $this->resetData('close');
        }

    }

    public function restore()
    {
        abort_if(!auth()->user()->can('categories.edit'), 403);

        if ($this->selectItem){
            $item = $this->model->withTrashed()->findOrFail($this->selectItem->id);
            $item->restore();
            $this->loadData();
            $this->dispatch(
                'alert',
                type: 'success',
                title: 'عملیات موفق',
                text: $this->info['personal']."باموفقیت بازگردانی شد."
            );
            $this->resetData('close');
        }
    }
    public function resetData($action= 'create')
    {
        $this->resetExcept(['model','info','data']);
        $this->dispatch('close-modal');
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
            <a href="{{route('brands.index')}}" class="btn btn-warning-light btn-wave me-2">
                <i class="bx bx-undo align-middle">
                </i>
                بازگشت
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header pb-0 justify-content-between">
                    <div class="card-title">
                        {{$info['header']}}
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
                            @forelse($data ?? [] as $item)
                                <tr wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>
                                        {{$item->title}}
                                    </td>

                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            <a  data-bs-toggle="modal" href="#restore" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-restart-line"></i></a>
                                            <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                    class="ri-delete-bin-5-line"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                @php($counter++)
                            @empty
                                <tr>
                                    <td colspan="{{ count($info['table']['headers'] ?? []) }}" class="text-center py-5 text-muted">
                                        <i class="ri-delete-bin-line fs-1 d-block mb-2"></i>
                                        <strong>سطل زباله خالی است.</strong>
                                        <div class="small mt-1">
                                            هیچ مورد حذف‌شده‌ای برای نمایش وجود ندارد.
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
    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered text-center modal-lg" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['delete'] .' ' .$selectItem?->name}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
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
    <div wire:ignore.self class="modal fade" id="restore">
        <div class="modal-dialog modal-dialog-centered text-center modal-lg" role="document">
            <div class="modal-content modal-content-demo">
                <div class="modal-header">
                    <h6 class="modal-title">{{$info['restore'] .' ' .$selectItem?->name}}</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">

                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <svg class="flex-shrink-0 me-2 svg-danger" xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="1.5rem" viewBox="0 0 24 24" width="1.5><0rem" fill="1.5rem" fill="1.5rem"00" height="24" width="24"/></g><g><g><g><path d="M15.73,3H8.27L3,8.27v7.46L8.27,21h7.46L21,15.73V8.27L15.73,3z M19,14.9L14.9,19H9.1L5,14.9V9.1L9.1,5h5.8L19,9.1V14.9z"/><rect height="6" width="2" x="11" y="7"/><rect height="2" width="2"><g="11">
                                        <div>
                                            از بازگردانی این ایتم مطمین هستید ؟!
                                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div wire:loading.remove wire:target="restore">
                        <button class="btn btn-primary" wire:click="restore()">
                            بازگردانی
                        </button>
                        <button class="btn btn-light" data-bs-dismiss="modal">
                            بستن
                        </button>
                    </div>

                    <!-- اسپینر لودینگ Livewire -->
                    <div wire:loading wire:target="restore" class="spinner-grow text-info" role="status">
                        <span class="visually-hidden">در حال حذف...</span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

