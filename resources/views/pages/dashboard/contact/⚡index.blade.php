<?php
use Livewire\Component;
use App\Models\Contact;

new class extends Component
{
    public $info = [];
    public $selectItem;
    public $data;
    public $search;

    public Contact $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Contact $model)
    {
        abort_if(!auth()->user()->can('messages.view'), 403);
        $this->model = $model;

        $this->info['header'] = 'پیام‌های کاربران';
        $this->info['delete'] = 'حذف پیام';
        $this->info['create'] = 'حذف پیام';

        $this->info['table']['headers'] = [
            '#',
            'نام',
            'موبایل',
            'وضعیت',
            'عملیات',
        ];

        $this->loadData();
    }

    public function loadData()
    {
        $this->data = $this->model
            ->where(function ($q) {
                $q->where('name', 'LIKE', "%{$this->search}%")
                    ->orWhere('email', 'LIKE', "%{$this->search}%")
                    ->orWhere('mobile', 'LIKE', "%{$this->search}%")
                    ->orWhere('subject', 'LIKE', "%{$this->search}%");
            })
            ->latest()
            ->get();
    }

    public function get_data($id)
    {
        $this->selectItem = $this->model->findOrFail($id);
        if ($this->selectItem->status === 'new') {
            $this->selectItem->update([
                'status' => 'in_progress',
            ]);

            $this->selectItem->refresh();
        }
    }

    public function delete()
    {
        abort_if(!auth()->user()->can('messages.delete'), 403);

        if ($this->selectItem) {
            $this->selectItem->delete();
            $this->selectItem = null;

            $this->loadData();

            $this->dispatch(
                'alert',
                type: 'success',
                title: 'حذف شد',
                text: 'پیام با موفقیت حذف شد.'
            );
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
                                @foreach($info['table']['headers'] as $h)
                                    <th>{{ $h }}</th>
                                @endforeach
                            </tr>
                            </thead>

                            <tbody>

                            @php($counter = 1)

                            @foreach($data ?? [] as $item)

                                <tr wire:key="contact-{{ $item->id }}">

                                    <th>{{ $counter }}</th>

                                    {{-- نام --}}
                                    <td>{{ $item->name }}</td>

                                    {{-- ایمیل / موبایل --}}
                                    <td>
                                        <div class="">{{ $item->mobile ?? '-' }}</div>
                                    </td>



                                    {{-- وضعیت --}}
                                    <td>
                    <span class="badge bg-{{
                        $item->status == 'new' ? 'warning' :
                        ($item->status == 'in_progress' ? 'info' :
                        ($item->status == 'answered' ? 'success' : 'dark'))
                    }}">
                        {{ $item->status }}
                    </span>
                                    </td>

                                    {{-- عملیات --}}
                                    <td>
                                        <div class="hstack gap-2">

                                            {{-- مشاهده --}}
                                            <a href="#"
                                               data-bs-toggle="modal"
                                               data-bs-target="#show"
                                               wire:click="get_data({{ $item->id }})"
                                               class="text-info">
                                                <i class="ri-eye-line"></i>
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
    <div wire:ignore.self class="modal fade" id="show">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content border-0 shadow">

                {{-- HEADER --}}
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="mb-0">جزئیات پیام</h5>
                        <small class="text-muted">
                            {{ $selectItem?->created_at?->format('Y/m/d H:i') }}
                        </small>
                    </div>

                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                {{-- BODY --}}
                <div class="modal-body">

                    @if($selectItem)

                        {{-- INFO GRID --}}
                        <div class="row g-3 mb-3">

                            <div class="col-md-6">
                                <div class="p-2 border rounded">
                                    <small class="text-muted">نام</small>
                                    <div class="fw-bold">{{ $selectItem->name }}</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-2 border rounded">
                                    <small class="text-muted">ایمیل</small>
                                    <div>{{ $selectItem->email ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-2 border rounded">
                                    <small class="text-muted">موبایل</small>
                                    <div>{{ $selectItem->mobile ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-2 border rounded">
                                    <small class="text-muted">نوع درخواست</small>
                                    <div>
                                    <span class="badge bg-secondary">
                                        {{ $selectItem->type }}
                                    </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-2 border rounded">
                                    <small class="text-muted">آی‌پی کاربر</small>
                                    <div class="fw-bold font-monospace">
                                        {{ $selectItem->ip ?? '-' }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-2 border rounded">
                                    <small class="text-muted">مرورگر (User Agent)</small>
                                    <div class="fw-bold text-break">
                                        {{ $selectItem->user_agent ?? '-' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="p-2 border rounded">
                                    <small class="text-muted">موضوع</small>
                                    <div class="fw-semibold">
                                        {{ $selectItem->subject ?? '-' }}
                                    </div>
                                </div>
                            </div>

                        </div>

                        {{-- MESSAGE --}}
                        <div class="mb-3">
                            <label class="text-muted mb-1">پیام کاربر</label>

                            <div class="p-3 border rounded bg-light">
                                {!! nl2br(e($selectItem->message)) !!}
                            </div>
                        </div>

                        {{-- ADMIN REPLY --}}
                        <div class="mb-3">
                            <label class="text-muted mb-1">پاسخ ادمین</label>

                            <div class="p-3 border rounded bg-white">
                                @if($selectItem->admin_reply)
                                    {!! nl2br(e($selectItem->admin_reply)) !!}
                                @else
                                    <span class="text-muted">هنوز پاسخی ثبت نشده</span>
                                @endif
                            </div>
                        </div>

                        {{-- STATUS --}}
                        <div class="mb-2">
                            <label class="text-muted mb-1">وضعیت</label>

                            <div>
                            <span class="badge bg-{{
                                $selectItem->status == 'new' ? 'warning' :
                                ($selectItem->status == 'in_progress' ? 'info' :
                                ($selectItem->status == 'answered' ? 'success' : 'dark'))
                            }}">
                                {{ $selectItem->status }}
                            </span>
                            </div>
                        </div>

                    @endif

                </div>

                {{-- FOOTER --}}
                <div class="modal-footer justify-content-between">

                    <button wire:click="delete"
                            class="btn btn-outline-danger"
                            data-bs-dismiss="modal">
                        حذف پیام
                    </button>

                    <div class="d-flex gap-2">

                        <button class="btn btn-light" data-bs-dismiss="modal">
                            بستن
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>
</div>
