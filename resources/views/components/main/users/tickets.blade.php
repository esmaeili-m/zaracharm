<?php

use Livewire\Component;
use App\Models\Ticket;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
new class extends Component
{
    public ?int $selectedTicketId = null;
    public bool $showCreateModal = false;

    public string $newTicketTitle = '';

    public string $newTicketMessage = '';
    public string $message = '';
    public $ticket;
    public function openCreateModal(): void
    {
        $this->resetValidation();

        $this->reset([
            'newTicketTitle',
            'newTicketMessage',
        ]);

        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;

        $this->resetValidation();

        $this->reset([
            'newTicketTitle',
            'newTicketMessage',
        ]);
    }

    public function createTicket(): void
    {
        $this->validate([
            'newTicketTitle' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'newTicketMessage' => [
                'required',
                'string',
                'min:5',
                'max:10000',
            ],
        ]);

        $ticket = DB::transaction(function () {

            $ticket = Ticket::create([
                'user_id' => auth()->id(),
                'ticket_number' => 'TEMP',
                'title' => $this->newTicketTitle,
                'status' => 'open',
                'priority' => 'normal',
                'last_reply_at' => now(),
            ]);

            $ticket->update([
                'ticket_number' => 'TCK-' . now()->format('Ymd') . '-' .
                    str_pad($ticket->id, 6, '0', STR_PAD_LEFT),
            ]);

            $ticket->messages()->create([
                'user_id' => auth()->id(),
                'message' => $this->newTicketMessage,
            ]);

            return $ticket;
        });

        $this->selectedTicketId = $ticket->id;

        $this->showCreateModal = false;

        $this->reset([
            'newTicketTitle',
            'newTicketMessage',
        ]);
        $this->selectTicket($ticket->id);

        unset($this->tickets);
        unset($this->ticket);
    }

    public function mount(): void
    {
        $this->selectedTicketId = Ticket::query()
            ->where('user_id', auth()->id())
            ->latest('last_reply_at')
            ->value('id');
    }

    #[Computed]
    public function tickets()
    {
        return Ticket::query()
            ->where('user_id', auth()->id())
            ->with('latestMessage')
            ->latest('last_reply_at')
            ->get();
    }

    public function getTicket()
    {
        if (!$this->selectedTicketId) {
            return null;
        }

        $this->ticket= Ticket::query()
            ->where('user_id', auth()->id())
            ->with('messages.user')
            ->find($this->selectedTicketId);
    }

    public function selectTicket(int $ticketId): void
    {
        $this->ticket = Ticket::query()
            ->where('user_id', auth()->id())
            ->whereKey($ticketId)
            ->first();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'min:2', 'max:10000'],
        ]);

        $ticket = Ticket::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->selectedTicketId)
            ->firstOrFail();

        if ($ticket->status === 'closed') {
            return;
        }

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => $this->message,
        ]);

        $ticket->update([
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        $this->reset('message');
        unset($this->tickets);

    }

    public function closeTicket(): void
    {
        $ticket = Ticket::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->selectedTicketId)
            ->firstOrFail();

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        unset($this->ticket);
        unset($this->tickets);
    }
}
?>

<div>
    <div
        class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6"
        dir="rtl"
    >

        {{-- لیست تیکت‌ها --}}
        <aside
            class="bg-white dark:bg-gray-900 rounded-3xl
               border border-gray-100 dark:border-white/5
               overflow-hidden"
        >

            {{-- Header --}}
            <div class="p-5 border-b border-gray-100 dark:border-white/5">

                <div class="flex items-center justify-between">

                    <div>
                        <h2 class="text-base font-black text-gray-900 dark:text-white">
                            تیکت‌های من
                        </h2>

                        <p class="text-[11px] text-gray-400 mt-1">
                            درخواست‌ها و گفتگوهای شما
                        </p>
                    </div>

                    <button
                        wire:click="openCreateModal"
                        type="button"
                        class="w-10 h-10 rounded-2xl
                           bg-primary-500 text-white
                           flex items-center justify-center
                           shadow-lg shadow-primary-500/20
                           hover:bg-primary-600 transition"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                    </button>

                </div>

            </div>


            {{-- Tickets --}}
            <div class="p-3 space-y-2 max-h-[650px] overflow-y-auto">

                @forelse($this->tickets as $item)

                    <button
                        type="button"
                        wire:click="selectTicket({{ $item->id }})"
                        wire:key="ticket-{{ $item->id }}"
                        class="w-full text-right p-4 rounded-2xl transition-all
                    {{ $ticket?->id === $item->id
                        ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20'
                        : 'hover:bg-gray-100 dark:hover:bg-white/5 text-gray-700 dark:text-gray-300'
                    }}"
                    >

                        <div class="flex items-start gap-3">

                            {{-- Icon --}}
                            <div
                                class="w-10 h-10 shrink-0 rounded-xl
                            flex items-center justify-center
                            {{ $ticket?->id === $item->id
                                ? 'bg-white/15'
                                : 'bg-gray-100 dark:bg-white/5'
                            }}"
                            >

                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 10h8m-8 4h5m6-1a8 8 0 11-15.5-2.5L3 6l4.5.5A8 8 0 0119 13z"
                                    />
                                </svg>

                            </div>


                            {{-- Content --}}
                            <div class="min-w-0 flex-1">

                                <div class="flex items-center justify-between gap-2">

                                <span class="text-[12px] font-black truncate">
                                    {{ $item->title }}
                                </span>

                                    @if($item->status === 'open')

                                        <span
                                            class="shrink-0 w-2 h-2 rounded-full
                                        {{ $ticket?->id === $item->id
                                            ? 'bg-white'
                                            : 'bg-primary-500'
                                        }}"
                                        ></span>

                                    @endif

                                </div>


                                <p
                                    class="text-[10px] mt-1 truncate
                                {{ $ticket?->id === $item->id
                                    ? 'text-white/70'
                                    : 'text-gray-400'
                                }}"
                                >
                                    {{ $item->latestMessage?->message ?? 'بدون پیام' }}
                                </p>


                                <div
                                    class="flex items-center justify-between mt-2
                                text-[9px]
                                {{ $ticket?->id === $item->id
                                    ? 'text-white/60'
                                    : 'text-gray-400'
                                }}"
                                >

                                <span>
                                    #{{ $item->ticket_number }}
                                </span>

                                    <span>
                                    {{ $item->last_reply_at?->diffForHumans() }}
                                </span>

                                </div>

                            </div>

                        </div>

                    </button>

                @empty

                    <div class="py-16 text-center">

                        <div
                            class="w-14 h-14 mx-auto rounded-2xl
                               bg-gray-100 dark:bg-white/5
                               flex items-center justify-center"
                        >
                            <svg
                                class="w-6 h-6 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 10h8m-8 4h5m6-1a8 8 0 11-15.5-2.5L3 6l4.5.5A8 8 0 0119 13z"
                                />
                            </svg>
                        </div>

                        <p class="text-xs font-bold text-gray-500 mt-4">
                            هنوز تیکتی ندارید
                        </p>

                    </div>

                @endforelse

            </div>

        </aside>


        {{-- Chat --}}
        <section
            class="bg-white dark:bg-gray-900 rounded-3xl
               border border-gray-100 dark:border-white/5
               overflow-hidden min-h-[700px]
               flex flex-col"
        >

            @if($ticket ??0)

                {{-- Chat Header --}}
                <header
                    class="p-5 border-b border-gray-100 dark:border-white/5"
                >

                    <div class="flex items-center justify-between">

                        <div class="flex items-center gap-3">

                            <div
                                class="w-11 h-11 rounded-2xl
                                   bg-primary-500/10
                                   text-primary-500
                                   flex items-center justify-center"
                            >

                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 10h8m-8 4h5m6-1a8 8 0 11-15.5-2.5L3 6l4.5.5A8 8 0 0119 13z"
                                    />
                                </svg>

                            </div>

                            <div>

                                <h1 class="text-sm font-black text-gray-900 dark:text-white">
                                    {{ $ticket->title }}
                                </h1>

                                <div class="flex items-center gap-2 mt-1">

                                <span class="text-[10px] text-gray-400">
                                    #{{ $ticket->ticket_number }}
                                </span>

                                    <span class="text-gray-300 dark:text-gray-700">
                                    •
                                </span>

                                    <span
                                        class="text-[10px] font-bold
                                    {{ $ticket->status === 'closed'
                                        ? 'text-red-500'
                                        : ($ticket->status === 'answered'
                                            ? 'text-green-500'
                                            : 'text-primary-500')
                                    }}"
                                    >
                                    @if($ticket->status === 'open')
                                            در انتظار پاسخ
                                        @elseif($ticket->status === 'answered')
                                            پاسخ داده شده
                                        @else
                                            بسته شده
                                        @endif
                                </span>

                                </div>

                            </div>

                        </div>


                        {{-- Close --}}
                        @if($ticket->status !== 'closed')

                            <button
                                type="button"
                                class="px-4 py-2 rounded-xl
                                   text-[11px] font-black
                                   text-red-500
                                   bg-red-500/10
                                   hover:bg-red-500/15
                                   transition"
                            >
                                بستن تیکت
                            </button>

                        @endif

                    </div>

                </header>


                {{-- Messages --}}
                <div
                    class="flex-1 p-6 space-y-5 overflow-y-auto"
                    style="max-height: 560px"
                >

                    @foreach($ticket->messages as $message)

                        @php
                            $isMine = $message->user_id === auth()->id();
                        @endphp

                        <div
                            wire:key="message-{{ $message->id }}"
                            class="flex {{ $isMine ? 'justify-start' : 'justify-end' }}"
                        >

                            <div
                                class="max-w-[75%]
                            {{ $isMine
                                ? 'items-start'
                                : 'items-end'
                            }} flex flex-col"
                            >

                                <div
                                    class="px-4 py-3 rounded-2xl text-[12px] leading-7
                                {{ $isMine
                                    ? 'bg-primary-500 text-white rounded-br-md'
                                    : 'bg-gray-100 dark:bg-white/5 text-gray-700 dark:text-gray-300 rounded-bl-md'
                                }}"
                                >
                                    {{ $message->message }}
                                </div>

                                <span class="text-[9px] text-gray-400 mt-1 px-1">
                                {{ $message->created_at->format('H:i') }}
                                -
                                {{ $message->created_at->diffForHumans() }}
                            </span>

                            </div>

                        </div>

                    @endforeach

                </div>


                {{-- Message Box --}}
                @if($ticket->status !== 'closed')

                    <footer
                        class="p-5 border-t border-gray-100 dark:border-white/5"
                    >

                        <form
                            wire:submit="sendMessage"
                            class="flex items-end gap-3"
                        >

                        <textarea
                            wire:model="message"
                            rows="2"
                            placeholder="پیام خود را بنویسید..."
                            class="flex-1 resize-none
                                   rounded-2xl
                                   border border-gray-200
                                   dark:border-white/10
                                   bg-gray-50
                                   dark:bg-white/5
                                   px-4 py-3
                                   text-sm
                                   text-gray-800
                                   dark:text-white
                                   placeholder:text-gray-400
                                   focus:border-primary-500
                                   focus:ring-2
                                   focus:ring-primary-500/20
                                   outline-none transition"
                        ></textarea>


                            <button
                                type="submit"
                                class="w-12 h-12 shrink-0
                                   rounded-2xl
                                   bg-primary-500
                                   text-white
                                   flex items-center justify-center
                                   shadow-lg shadow-primary-500/20
                                   hover:bg-primary-600
                                   transition"
                            >

                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"
                                    />
                                </svg>

                            </button>

                        </form>

                    </footer>

                @else

                    <div class="p-5">

                        <div
                            class="rounded-2xl
                               bg-gray-100
                               dark:bg-white/5
                               text-gray-500
                               dark:text-gray-400
                               text-center
                               py-4
                               text-xs
                               font-bold"
                        >
                            این تیکت بسته شده است.
                        </div>

                    </div>

                @endif

            @else

                {{-- No Ticket --}}
                <div
                    class="flex-1 flex items-center justify-center"
                >

                    <div class="text-center">

                        <div
                            class="w-20 h-20 mx-auto
                               rounded-3xl
                               bg-primary-500/10
                               text-primary-500
                               flex items-center justify-center"
                        >

                            <svg
                                class="w-9 h-9"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 10h8m-8 4h5m6-1a8 8 0 11-15.5-2.5L3 6l4.5.5A8 8 0 0119 13z"
                                />
                            </svg>

                        </div>

                        <h3 class="mt-5 text-sm font-black text-gray-800 dark:text-white">
                            تیکتی انتخاب نشده است
                        </h3>

                        <p class="text-[11px] text-gray-400 mt-2">
                            برای مشاهده گفتگو، یک تیکت را انتخاب کنید.
                        </p>

                    </div>

                </div>

            @endif

        </section>

    </div>
    @if($showCreateModal)

        <div
            class="fixed inset-0 z-[100]
               flex items-center justify-center
               p-4"
            dir="rtl"
        >

            {{-- Backdrop --}}
            <div
                wire:click="closeCreateModal"
                class="absolute inset-0
                   bg-black/50
                   backdrop-blur-sm"
            ></div>


            {{-- Modal --}}
            <div
                wire:click.stop
                class="relative w-full max-w-lg
                   bg-white dark:bg-gray-900
                   rounded-3xl
                   border border-gray-100
                   dark:border-white/5
                   shadow-2xl
                   overflow-hidden"
            >

                {{-- Header --}}
                <div
                    class="flex items-center justify-between
                       p-6
                       border-b border-gray-100
                       dark:border-white/5"
                >

                    <div>

                        <h2
                            class="text-base font-black
                               text-gray-900 dark:text-white"
                        >
                            ایجاد تیکت جدید
                        </h2>

                        <p
                            class="text-[11px]
                               text-gray-400
                               mt-1"
                        >
                            درخواست یا مشکل خود را برای پشتیبانی ارسال کنید.
                        </p>

                    </div>


                    {{-- Close --}}
                    <button
                        type="button"
                        wire:click="closeCreateModal"
                        class="w-9 h-9
                           rounded-xl
                           flex items-center justify-center
                           text-gray-400
                           hover:bg-gray-100
                           dark:hover:bg-white/5
                           hover:text-gray-600
                           dark:hover:text-gray-200
                           transition"
                    >

                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>

                    </button>

                </div>


                {{-- Body --}}
                <div class="p-6 space-y-5">

                    {{-- Title --}}
                    <div>

                        <label
                            class="block
                               text-[11px]
                               font-black
                               text-gray-700
                               dark:text-gray-300
                               mb-2"
                        >
                            عنوان تیکت
                        </label>

                        <input
                            type="text"
                            wire:model="newTicketTitle"
                            placeholder="مثلاً مشکل در پرداخت"
                            class="w-full
                               h-12
                               rounded-2xl
                               border
                               border-gray-200
                               dark:border-white/10
                               bg-gray-50
                               dark:bg-white/5
                               px-4
                               text-sm
                               text-gray-800
                               dark:text-white
                               placeholder:text-gray-400
                               outline-none
                               focus:border-primary-500
                               focus:ring-2
                               focus:ring-primary-500/20
                               transition"
                        >

                        @error('newTicketTitle')
                        <p class="text-[10px] text-red-500 mt-2">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    {{-- Message --}}
                    <div>

                        <label
                            class="block
                               text-[11px]
                               font-black
                               text-gray-700
                               dark:text-gray-300
                               mb-2"
                        >
                            توضیحات
                        </label>

                        <textarea
                            wire:model="newTicketMessage"
                            rows="6"
                            placeholder="مشکل یا درخواست خود را با جزئیات توضیح دهید..."
                            class="w-full
                               resize-none
                               rounded-2xl
                               border
                               border-gray-200
                               dark:border-white/10
                               bg-gray-50
                               dark:bg-white/5
                               px-4 py-3
                               text-sm
                               leading-7
                               text-gray-800
                               dark:text-white
                               placeholder:text-gray-400
                               outline-none
                               focus:border-primary-500
                               focus:ring-2
                               focus:ring-primary-500/20
                               transition"
                        ></textarea>

                        @error('newTicketMessage')
                        <p class="text-[10px] text-red-500 mt-2">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>

                </div>


                {{-- Footer --}}
                <div
                    class="flex items-center gap-3
                       p-6
                       pt-0"
                >

                    <button
                        type="button"
                        wire:click="closeCreateModal"
                        class="flex-1
                           h-12
                           rounded-2xl
                           bg-gray-100
                           dark:bg-white/5
                           text-gray-600
                           dark:text-gray-300
                           text-xs
                           font-black
                           hover:bg-gray-200
                           dark:hover:bg-white/10
                           transition"
                    >
                        انصراف
                    </button>


                    <button
                        type="button"
                        wire:click="createTicket"
                        wire:loading.attr="disabled"
                        class="flex-1
                           h-12
                           rounded-2xl
                           bg-primary-500
                           text-white
                           text-xs
                           font-black
                           shadow-lg
                           shadow-primary-500/20
                           hover:bg-primary-600
                           disabled:opacity-50
                           disabled:cursor-not-allowed
                           transition"
                    >

                    <span wire:loading.remove wire:target="createTicket">
                        ایجاد تیکت
                    </span>

                        <span
                            wire:loading
                            wire:target="createTicket"
                        >
                        در حال ارسال...
                    </span>

                    </button>

                </div>

            </div>

        </div>

    @endif
</div>
