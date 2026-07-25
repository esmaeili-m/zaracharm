<?php

use Livewire\Component;
use \App\Models\Comment;
new class extends Component
{

    public $info=[];
    public $selectItem;
    public $data;
    public $category_id;
    public $body;

    public $answer ;

    public $question = '';


    public $search;
    public Comment $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Comment $model)
    {
        abort_if(!auth()->user()->can('comments.view'), 403);

        $this->model=$model;
        $this->info['header']='لیست کامنت ها';
        $this->info['create']='افزودن کامنت';
        $this->info['delete']='حذف کامنت';
        $this->info['personal']='کامنت';
        $this->info['table']['headers']=[
            '#',
            'کاربر',
            'بخش',
            'وضعیت',
            'عملیات',
        ];
        $this->loadData();
    }

    public function change_status($id)
    {
        abort_if(!auth()->user()->can('comments.edit'), 403);

        $item = $this->model->findOrFail($id);

        $item->update([
            'is_approved' => ! $item->is_approved
        ]);

        $this->loadData();

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: $item->fresh()->is_approved
                ? 'نظر با موفقیت تایید شد.'
                : 'تایید نظر لغو شد.'
        );
    }
    public function delete()
    {
        abort_if(!auth()->user()->can('comments.delete'), 403);

        if ($this->selectItem){
            $item = $this->model->findOrFail($this->selectItem->id);
            $item->delete();
            $this->loadData();
            $this->resetData('close');
        }

    }
    public function loadData()
    {
        $query = $this->model
            ->with([
                'ratings',
                'user',
                'replies.user'
            ])
            ->whereNull('parent_id')->where(function ($query) {
                $query->where('body', 'LIKE', '%' . $this->search . '%');
            });
        $this->data = $query->orderBy('is_approved')->get();
    }


    public function get_data($id)
    {
        $this->selectItem= $this->model->findOrFail($id);
        $this->body=$this->selectItem->body;
        if ($this->selectItem->replies->count() > 0){
            $this->answer=$this->selectItem->replies->last()->body;
        }
        $this->dispatch('editor-update');
    }
    public function resetData($action= 'create')
    {
        if ($action == 'create'){
            $this->resetExcept('model','info','data');
        }else{
            $this->resetExcept(['model','info','data']);
            $this->dispatch('close-modal');

        }
    }
    public function rules()
    {
        return [
            'answer' => ['required', 'string', 'min:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'answer.required' => 'وارد کردن پاسخ الزامی است.',
            'answer.string' => 'پاسخ باید متن باشد.',
            'answer.min' => 'پاسخ باید حداقل ۲ کاراکتر باشد.',
        ];
    }
    public function save()
    {
        abort_if(!auth()->user()->can('comments.create'), 403);

        $this->validate();
        $this->selectItem->update([
            'is_approved' => true,
        ]);
        $reply = $this->selectItem
            ->replies()
            ->where('user_id', auth()->id())
            ->first();
        $reply
            ? $reply->update(['body' => $this->answer])
            : $this->model->create([
            'commentable_type' => $this->selectItem->commentable_type,
            'commentable_id'   => $this->selectItem->commentable_id,
            'parent_id'        => $this->selectItem->id,
            'user_id'          => auth()->id(),
            'body'             => $this->answer,
            'is_approved'      => true,
        ]);

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'عملیات موفق',
            text: 'پاسخ با موفقیت ثبت شد.'
        );

        $this->reset('answer');

        $this->loadData();

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
                            @foreach($data ?? [] as $item)
                                <tr wire:key="{{$item->id}}">
                                    <th scope="row">
                                        {{$counter}}
                                    </th>
                                    <td>
                                        @if($item->user)
                                            {{$item->user->mobile}} {{$item->user->name ? ' - '. $item->user->name : ''}}
                                        @else
                                            {{$item->name}}
                                        @endif

                                    </td>
                                    <td>
                                        {{$item->commentable_label }}
                                    </td>
                                    <td>
                                     <span
                                         style="cursor: pointer"
                                         wire:click="change_status({{ $item->id }})"
                                         wire:loading.attr="disabled"
                                         class="badge bg-outline-{{ $item->is_approved ? 'success' : 'warning' }}">

                                            <span wire:target="change_status" wire:loading.remove>
                                                {{ $item->is_approved ? 'تایید شده' : 'در انتظار تایید' }}
                                            </span>

                                            <span wire:target="change_status" wire:loading>
                                                در حال تغییر...
                                            </span>

                                        </span>
                                    </td>
                                    <td>

                                        <div class="hstack gap-2 flex-wrap">
                                            <a data-bs-toggle="modal" href="#create" wire:click="get_data({{$item->id}})"  class="text-info fs-14 lh-1"><i
                                                    class="ri-edit-line"></i></a>

                                            <a  data-bs-toggle="modal" href="#delete" wire:click="get_data({{$item->id}})"  class="text-danger fs-14 lh-1"><i
                                                    class="ri-delete-bin-5-line"></i></a>
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
    <div wire:ignore.self class="modal fade" id="create">
        <div class="modal-dialog modal-dialog-centered text-center modal-xl" role="document">
            <div class="modal-content modal-content-demo">

                <form wire:submit="save()">

                    <div class="modal-header">
                        <h6 class="modal-title">
                            {{$info['create']}}
                        </h6>

                        <button aria-label="Close"
                                class="btn-close"
                                data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body text-start">

                        <div class="row">

                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                   کامنت
                                </label>

                                <textarea
                                    disabled
                                    wire:model.lazy="body"
                                    class="form-control @error('body') is-invalid @enderror"
                                    rows="3"
                                    placeholder="لطفا توضیح کوتاه {{$info['personal'] ?? ''}} را وارد کنید"></textarea>

                                @error('body')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>


                            <div class="col-xl-12 mt-3">
                                <label class="form-label">
                                    پاسخ کامنت
                                </label>

                                <textarea
                                    wire:model.lazy="answer"
                                    class="form-control @error('answer') is-invalid @enderror"
                                    rows="3"
                                    placeholder="لطفا توضیح کوتاه {{$info['personal'] ?? ''}} را وارد کنید"></textarea>

                                @error('answer')
                                <div class="invalid-feedback d-block">
                                    {{$message}}
                                </div>
                                @enderror
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <div wire:loading.remove wire:target="save">

                            <button
                                type="submit"
                                class="btn btn-primary">
                                ذخیره تغییرات
                            </button>

                            <button
                                class="btn btn-light"
                                data-bs-dismiss="modal"
                                type="button">
                                بستن
                            </button>

                        </div>

                        {{-- لودینگ --}}
                        <div wire:loading
                             wire:target="save"
                             class="spinner-grow text-info"
                             role="status">

                        <span class="visually-hidden">
                            در حال بارگیری...
                        </span>

                        </div>

                    </div>

                </form>

            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="delete">
        <div class="modal-dialog modal-dialog-centered text-center " role="document">
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

</div>
