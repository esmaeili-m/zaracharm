<?php

use Livewire\Component;
use  App\Models\User;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;

    public $attachment;
    public $tickets;
    public $selectedTicket = null;
    public $messages = [];
    public $body = '';
    public $activeTab = 'new';

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->dispatch('scroll');
    }

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('tickets.view'), 403);

        $this->load_data();
    }
    public function load_data(){
        $this->tickets = \App\Models\Ticket::with(['user', 'lastMessage'])
            ->withCount([
                'messages as unread_count' => function ($q) {
                    $q->where('is_admin', false)
                        ->where('is_read', false);
                }
            ])
            ->orderByDesc('last_reply_at')
            ->get();
    }
    public function selectTicket($ticketId)
    {
        abort_if(!auth()->user()->can('tickets.view'), 403);

        $this->selectedTicket = \App\Models\Ticket::with(['user', 'messages.user'])
            ->find($ticketId);

        $this->messages = $this->selectedTicket
            ->messages()
            ->with('user')
            ->orderBy('id')
            ->get()
            ->toArray();
        $this->dispatch('background');

    }

    protected array $rules = [
        'body' => 'required|min:3',
    ];

    public function clsoe()
    {
        $this->selectedTicket->update([
            'is_read'=> 1,
            'status' => 'answered'
        ]);
    }
    public function sendTicketMessage()
    {
        abort_if(!auth()->user()->can('tickets.create'), 403);

        $validated = $this->validate([
            'body' => 'required|min:3',
        ]);

        $message =auth()->user()->messages()->create([
            'message' => $this->body,
            'is_admin' => 1,
            'ticket_id' => $this->selectedTicket->id
        ]);

        $this->selectedTicket->update([
            'is_read'=> 1,
            'status' => 'answered'
        ]);

        if ($this->attachment) {

            // حذف فایل قبلی این collection (اگر آپدیت/جایگزینی مدنظرته)
            $message->media()
                ->where('collection', 'chat_attachment')
                ->delete();

            // آپلود فایل جدید
            $this->upload(
                $this->attachment,
                $message,
                'chat_attachment'
            );
        }

        $this->body = ''; // clear input

        $this->dispatch('message-sent'); // برای آپدیت لیست چت
    }

    public function change_status($status)
    {
        abort_if(!auth()->user()->can('tickets.edit'), 403);

        $this->selectedTicket->update([
            'status' => $status,
            'is_read'=> 1,
        ]);
        $this->load_data();
    }




};
?>

<div>
    <!-- Page Header -->
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">
                چت کنید
            </h1>
            <div class="">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="javascript:void(0);">
                                صفحات
                            </a>
                        </li>
                        <li aria-current="page" class="breadcrumb-item active">
                            چت کنید
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="btn-list">
            <button class="btn btn-primary-light btn-wave me-2">
                <i class="bx bx-crown align-middle">
                </i>
                ارتقاء طرح
            </button>
            <button class="btn btn-secondary-light btn-wave me-0">
                <i class="ri-upload-cloud-line align-middle">
                </i>
                گزارش صادرات
            </button>
        </div>
    </div>
    <!-- Page Header Close -->
    <div class="main-chart-wrapper gap-2 mb-2 d-flex">
        <div class="chat-navbar rounded border bg-white p-3 d-flex flex-column justify-content-between" id="main-chat-navabar">
            <ul class="nav nav-tabs scaleX nav-justified mb-0 border-bottom-0" id="myTab1" role="tablist">


                <li  class="nav-item me-0" role="presentation">
                    <button wire:click="setTab('new')" aria-current="page" aria-selected="true"
                            class="nav-link chat-nav-link mb-3 d-flex align-items-center justify-content-center {{ $activeTab === 'new' ? 'active' : '' }}"
                            data-bs-toggle="tab"
                            data-bs-target="#new-chat-tab" type="button">
                        <svg class="icon icon-tabler icons-tabler-outline icon-tabler-messages" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0 0h24v24H0z" fill="none" stroke="none">
                            </path>
                            <path d="M21 14l-3 -3h-7a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1h9a1 1 0 0 1 1 1v10">
                            </path>
                            <path d="M14 15v2a1 1 0 0 1 -1 1h-7l-3 3v-10a1 1 0 0 1 1 -1h2">
                            </path>
                        </svg>
                    </button>
                </li>


                <li  class="nav-item me-0" role="presentation">
                    <button wire:click="setTab('replay')" aria-controls="replay-tab-pane" aria-selected="false"
                            class="nav-link chat-nav-link mb-3 d-flex align-items-center justify-content-center {{ $activeTab === 'replay' ? 'active' : '' }}"
                            data-bs-target="#replay-tab" data-bs-toggle="tab"  role="tab" type="button">
                        <i class="ri-reply-line fs-18"></i>
                    </button>
                </li>


                <li class="nav-item me-0" role="presentation">
                    <button aria-controls="call-tab-pane" aria-selected="false"
                            class="nav-link chat-nav-link mb-3 d-flex align-items-center justify-content-center {{ $activeTab === 'closed' ? 'active' : '' }}"
                            wire:click="setTab('closed')"
                            data-bs-target="#close-tab" data-bs-toggle="tab"  role="tab" type="button">
                        <i class="ri-lock-line fs-18"></i>
                    </button>
                </li>
                <li  class="nav-item me-0" role="presentation">
                    <button aria-controls="groups-tab-pane" aria-selected="false"
                            class="nav-link chat-nav-link mb-3 d-flex align-items-center justify-content-center {{ $activeTab === 'users' ? 'active' : '' }}"
                            wire:click="setTab('users')"
                            data-bs-target="#users-tab" data-bs-toggle="tab"  role="tab" type="button">
                        <svg class="icon icon-tabler icons-tabler-outline icon-tabler-users" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0 0h24v24H0z" fill="none" stroke="none">
                            </path>
                            <path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0">
                            </path>
                            <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2">
                            </path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75">
                            </path>
                            <path d="M21 21v-2a4 4 0 0 0 -3 -3.85">
                            </path>
                        </svg>
                    </button>
                </li>

            </ul>
        </div>

        <div class="chat-info border">
            <div class="tab-content" id="myTabContent">


                <div aria-labelledby="newChat-tab"
                     class="tab-pane   {{ $activeTab === 'new' ? 'show active' : '' }} border-0 chat-users-tab"
                     id="new-chat-tab" role="tabpanel" tabindex="0">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-semibold mb-0">
                            تیکت جدید
                        </h6>
                    </div>
                    <ul class="list-unstyled mb-0 mt-2 chat-users-tab" id="chat-msg-scroll">
                        <li class="pb-0">
                            <p class="text-muted fs-11 fw-medium mb-2 op-7">
                                چت فعال
                            </p>
                        </li>

                        @foreach($tickets->where('status', 'open') as $ticket)

                            @php
                                $user = $ticket->user;
                                $last = $ticket->lastMessage;
                            @endphp

                            <li wire:click="selectTicket({{ $ticket->id }})" class="checkforactive {{ $loop->first ? 'active' : '' }}">

                                <a href="javascript:void(0);"
                                   onclick="changeTheInfo(this,'{{ $user->name }}','{{ $ticket->id }}','آنلاین')">

                                    <div class="d-flex align-items-top">

                                        <div class="me-1 lh-1">
                    <span class="avatar avatar-md online me-2 avatar-rounded">
                        <img src="{{ $user->avatarUrl ?? asset('assets/images/faces/default.jpg') }}">
                    </span>
                                        </div>

                                        <div class="flex-fill">

                                            <p class="mb-0 fw-medium">
                                                {{ $user->name }}

                                                <span class="float-end text-muted fw-normal fs-11">
                            {{ optional($last?->created_at)->format('H:i') }}
                        </span>
                                            </p>

                                            <p class="fs-12 mb-0">

                        <span class="chat-msg text-truncate">
                            {{ $last?->message ?? 'هنوز پیامی ارسال نشده' }}
                        </span>

                                                @if($ticket->unread_count ?? 0)
                                                    <span class="badge bg-danger rounded-pill float-end">
                                {{ $ticket->unread_count }}
                            </span>
                                                @endif

                                            </p>

                                        </div>

                                    </div>

                                </a>
                            </li>

                        @endforeach
                    </ul>
                </div>



                <div  aria-labelledby="replay-tab" class="tab-pane {{ $activeTab === 'replay' ? 'show active' : '' }} border-0 chat-users-tab" id="replay-tab" role="tabpanel" tabindex="0">
                    <div class="p-3 border-bottom   d-flex justify-content-between align-items-center">
                        <h6 class="fw-semibold mb-0">
                            تیکت های پاسخ داده شده
                        </h6>
                    </div>
                    <ul class="list-unstyled mb-0 mt-2 chat-users-tab" id="groups-tab-pane-list">
                        <li class="pb-0">
                            <p class="text-muted fs-11 fw-medium mb-2 op-7">
                                تیکت های پاسخ داده شده
                            </p>
                        </li>
                        @foreach($tickets->where('status', 'answered') as $ticket)

                            @php
                                $user = $ticket->user;
                                $last = $ticket->lastMessage;
                            @endphp

                            <li wire:click="selectTicket({{ $ticket->id }})" class="checkforactive {{ $loop->first ? ' active' : '' }}">

                                <a href="javascript:void(0);"
                                   onclick="changeTheInfo(this,'{{ $user->name }}','{{ $ticket->id }}','آنلاین')">

                                    <div class="d-flex align-items-top">

                                        <div class="me-1 lh-1">
                    <span class="avatar avatar-md online me-2 avatar-rounded">
                        <img src="{{ $user->avatarUrl ?? asset('assets/images/faces/default.jpg') }}">
                    </span>
                                        </div>

                                        <div class="flex-fill">

                                            <p class="mb-0 fw-medium">
                                                {{ $user->name }}

                                                <span class="float-end text-muted fw-normal fs-11">
                            {{ optional($last?->created_at)->format('H:i') }}
                        </span>
                                            </p>

                                            <p class="fs-12 mb-0">

                        <span class="chat-msg text-truncate">
                            {{ $last?->message ?? 'هنوز پیامی ارسال نشده' }}
                        </span>

                                                @if($ticket->unread_count ?? 0)
                                                    <span class="badge bg-danger rounded-pill float-end">
                                {{ $ticket->unread_count }}
                            </span>
                                                @endif

                                            </p>

                                        </div>

                                    </div>

                                </a>
                            </li>

                        @endforeach
                    </ul>

                </div>
                <div  aria-labelledby="replay-tab" class="tab-pane {{ $activeTab === 'closed' ? 'show active' : '' }} border-0 chat-users-tab" id="close-tab" role="tabpanel" tabindex="0">
                    <div class="p-3 border-bottom d-flex     justify-content-between align-items-center">
                        <h6 class="fw-semibold mb-0">
                            تیکت ها بسته شده
                        </h6>
                    </div>
                    <ul class="list-unstyled mb-0 mt-2 chat-users-tab" id="contacts-tab-pane-list">
                        <li class="pb-0">
                            <p class="text-muted fs-11 fw-medium mb-2 op-7">
                                چت های بسته شده
                            </p>
                        </li>
                        @foreach($tickets->where('status', 'closed') as $ticket)

                            @php
                                $user = $ticket->user;
                                $last = $ticket->lastMessage;
                            @endphp

                            <li wire:click="selectTicket({{ $ticket->id }})" class="checkforactive {{ $loop->first ? 'show active' : '' }}">

                                <a href="javascript:void(0);"
                                   onclick="changeTheInfo(this,'{{ $user->name }}','{{ $ticket->id }}','آنلاین')">

                                    <div class="d-flex align-items-top">

                                        <div class="me-1 lh-1">
                    <span class="avatar avatar-md online me-2 avatar-rounded">
                        <img src="{{ $user->avatarUrl ?? asset('assets/images/faces/default.jpg') }}">
                    </span>
                                        </div>

                                        <div class="flex-fill">

                                            <p class="mb-0 fw-medium">
                                                {{ $user->name }}

                                                <span class="float-end text-muted fw-normal fs-11">
                            {{ optional($last?->created_at)->format('H:i') }}
                        </span>
                                            </p>

                                            <p class="fs-12 mb-0">

                        <span class="chat-msg text-truncate">
                            {{ $last?->message ?? 'هنوز پیامی ارسال نشده' }}
                        </span>

                                                @if($ticket->unread_count ?? 0)
                                                    <span class="badge bg-danger rounded-pill float-end">
                                {{ $ticket->unread_count }}
                            </span>
                                                @endif

                                            </p>

                                        </div>

                                    </div>

                                </a>
                            </li>

                        @endforeach
                    </ul>

                </div>
                <div aria-labelledby="replay-tab" class="tab-pane {{ $activeTab === 'users' ? 'active' : '' }} border-0 chat-users-tab" id="users-tab" role="tabpanel" tabindex="0">
                    <div class="p-3 border-bottom   d-flex justify-content-between align-items-center">
                        <h6 class="fw-semibold mb-0">
                            کاربران
                        </h6>
                    </div>
                    <ul class="list-unstyled mb-0 mt-2 chat-users-tab" id="main-chat-content">
                        <li class="pb-0">
                            <p class="text-muted fs-11 fw-medium mb-2 op-7">
                                همه کاربران
                            </p>
                        </li>
                        @foreach(User::active()->with('lastMessage')->get() as $user)
                            @php
                                $lastMessage = $user->messages->last();
                            @endphp

                            <li class="chat-inactive checkforactive">
                                <a href="javascript:void(0);"
                                   onclick="changeTheInfo(this,'{{ $user->name }}','{{ $user->id }}','offline')">

                                    <div class="d-flex align-items-top">

                                        <div class="me-1 lh-1">
                    <span class="avatar avatar-md offline me-2 avatar-rounded">
                        <img alt="img" src="{{ $user->avatarUrl ?? asset('/') }}"/>
                    </span>
                                        </div>

                                        <div class="flex-fill">

                                            <p class="mb-0 fw-medium">
                                                {{ $user->mobile }}

                                                <span class="float-end text-muted fw-normal fs-11">
                            {{ optional($lastMessage?->created_at)->format('H:i') ?? '' }}
                        </span>
                                            </p>

                                            <p class="fs-12 mb-0">
                        <span class="chat-msg text-truncate">
                            {{ $lastMessage?->message ?? 'پیامی وجود ندارد' }}
                        </span>

                                                <span class="chat-read-icon float-end align-middle">
                            <i class="ri-check-double-fill"></i>
                        </span>
                                            </p>

                                        </div>

                                    </div>
                                </a>
                            </li>
                        @endforeach

                    </ul>
                </div>

            </div>

        </div>
        <div class="main-chat-area border">
            <div class="offcanvas-body p-0">

                    @if($selectedTicket)

                        <div class="main-chat">
                            <div class="d-flex align-items-center border-bottom main-chat-head flex-wrap">
                                <div class="me-2 lh-1">
                              <span class="avatar avatar-md online avatar-rounded chatstatusperson">
                               <img alt="img" class="chatimageperson" src="{{$selectedTicket->user->avatarUrl}}"/>
                              </span>
                                </div>
                                <div class="flex-fill">
                                    <p class="mb-0 fw-medium fs-14 lh-1">
                                        <a aria-controls="offcanvasRight" class="chatnameperson responsive-userinfo-open" data-bs-target="#offcanvasRight" data-bs-toggle="offcanvas" href="javascript:void(0);">
                                            {{$selectedTicket->user->name}}
                                        </a>
                                    </p>
                                    <p class="text-muted mb-0 chatpersonstatus">
                                        آنلاین
                                    </p>
                                </div>
                                <div class="d-flex flex-wrap rightIcons">
                                    <!-- Open -->
                                    <button type="button"
                                            class="btn btn-icon btn-primary-light my-1 ms-2 rounded-pill"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-original-title="Open Tickets"
                                            wire:click="change_status('open')">

                                        <i class="ri-inbox-line"></i>
                                    </button>

                                    <!-- Answered -->
                                    <button type="button"
                                            class="btn btn-icon btn-success-light my-1 ms-2 rounded-pill"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-original-title="Answered Tickets"
                                            wire:click="change_status('answered')">

                                        <i class="ri-reply-line"></i>
                                    </button>

                                    <!-- Closed -->
                                    <button type="button"
                                            class="btn btn-icon btn-danger-light my-1 ms-2 rounded-pill"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-original-title="Closed Tickets"
                                            wire:click="change_status('closed')">

                                        <i class="ri-lock-line"></i>
                                    </button>
                                    <button aria-label="button" class="btn btn-icon btn-outline-light my-1 ms-2 rounded-pill responsive-userinfo-open" type="button">
                                        <i class="ti ti-user-circle" id="responsive-chat-close">
                                        </i>
                                    </button>
                                    <button aria-label="button" class="btn btn-icon btn-outline-light my-1 ms-2 rounded-pill responsive-chat-close" type="button">
                                        <i class="ri-close-line">
                                        </i>
                                    </button>
                                </div>
                            </div>
                            <div class="chat-content" id="main-chat-content">
                                <ul class="list-unstyled">

                                    @foreach($selectedTicket->messages ?? [] as $message)
                                        @if($message->is_admin)
                                            <li class="chat-item-start">
                                                <div class="chat-list-inner">
                                                    <div class="chat-user-profile">
                                                 <span class="avatar avatar-md online avatar-rounded chatstatusperson">
                                                  <img alt="img" class="chatimageperson"
                                                       src="{{$message->user->avatarUrl}}"/>
                                                 </span>
                                                    </div>
                                                    <div class="ms-3">
                                                <span class="chatting-user-info">
                                                          <span class="chatnameperson">
                                                           {{$message->user->name}}
                                                          </span>
                                                          <span class="msg-sent-time">
                                                          {{\Hekmatinasser\Verta\Verta::instance($message->created_at)->format('m/d H:i')}}
                                                          </span>
                                                         </span>
                                                        <div class="main-chat-msg">
                                                            <div>
                                                                <p class="mb-0">
                                                                    {{$message->message}}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                            @else
                                            <li class="chat-item-end">
                                                <div class="chat-list-inner">
                                                    <div class="me-3">
                                                         <span class="chatting-user-info">
                                                          <span class="msg-sent-time">
                                                           <span class="chat-read-mark align-middle d-inline-flex">
                                                            <i class="ri-check-double-line">
                                                            </i>
                                                           </span>
                                                          {{\Hekmatinasser\Verta\Verta::instance($message->created_at)->format('m/d H:i')}}
                                                          </span>
                                                              {{$message->user->name}}
                                                         </span>
                                                        <div class="main-chat-msg">
                                                            <div>
                                                                <p class="mb-0">
                                                                    {{$message->message}}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="chat-user-profile">
                                                         <span class="avatar avatar-md online avatar-rounded">
                                                          <img alt="img" src="{{$message->user->avatarUrl}}"/>
                                                         </span>
                                                    </div>
                                                </div>
                                            </li>

                                        @endif
                                    @endforeach

                            </div>
                            <div class="chat-footer">

                                <!-- attachment -->
                                <button type="button"
                                        class="btn btn-danger-light me-2 btn-icon"
                                        onclick="document.getElementById('fileInput').click()">
                                    <i class="ri-attachment-2"></i>
                                </button>

                                <input type="file"
                                       id="fileInput"
                                       class="d-none"
                                       wire:model="attachment">


                                <!-- input -->
                                <input type="text"
                                       class="form-control chat-message-space"
                                       placeholder="پیام خود را اینجا تایپ کنید..."
                                       wire:model.defer="body"
                                       wire:keydown.enter="sendTicketMessage">

                                <!-- send -->
                                <button type="button"
                                        class="btn btn-secondary ms-2 btn-icon"
                                        wire:click="sendTicketMessage">
                                    <i class="ri-send-plane-2-line" style="transform: rotate(180deg);"></i>
                                </button>

                            </div>
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            یک تیکت انتخاب کنید
                        </div>
                    @endif
            </div>

        </div>
    </div>
    @push('scripts')
        <script>

            document.addEventListener('livewire:navigated', () => {
                initSimpleBar('chat-msg-scroll');
                initSimpleBar('groups-tab-pane-list');
                initSimpleBar('contacts-tab-pane-list');
                initSimpleBar('main-chat-content');
            });
            function initSimpleBar(id) {
                const el = document.getElementById(id);
                if (!el) return;

                if (el.SimpleBar) return; // جلوگیری از دوباره init

                new SimpleBar(el, { autoHide: true });
            }
            document.addEventListener('livewire:init', () => {
                Livewire.on('scroll', (event) => {
                    initSimpleBar('chat-msg-scroll');
                    initSimpleBar('groups-tab-pane-list');
                    initSimpleBar('contacts-tab-pane-list');
                    initSimpleBar('main-chat-content');

                });
                Livewire.on('background', (event) => {
                    setTimeout(() => {

                        const el = document.getElementById('main-chat-content');

                        if (!el) return;

                        if (el.SimpleBar) {
                            el.SimpleBar.unMount();
                        }

                        new SimpleBar(el, { autoHide: true });

                    }, 80);

                });

            });
        </script>

    @endpush

</div>
