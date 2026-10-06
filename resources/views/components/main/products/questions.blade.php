<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\ProductQuestion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

new class extends Component
{
    #[Locked]
    public int $productId;

    public string $questionBody = '';
    public bool $isAnonymous = false;

    public ?int $answeringId = null;
    public string $answerBody = '';

    public int $perPage = 5;

    public function mount(int $productId)
    {
        $this->productId = $productId;
    }

    #[Computed]
    public function questions()
    {
        return ProductQuestion::active()
            ->where('product_id', $this->productId)
            ->with(['user', 'activeAnswers.user'])
            ->latest()
            ->paginate($this->perPage, ['*'], 'questions_page', 1);
    }

    public function loadMore(): void
    {
        $this->perPage += 5;
    }

    public function submitQuestion(): void
    {
        if (! Auth::check()) {
            $this->dispatch('alert', type: 'error', message: 'برای ثبت پرسش ابتدا وارد شوید.');
            return;
        }

        $this->validate(
            [
                'questionBody' => ['required', 'string', 'min:10', 'max:1000'],
                'isAnonymous' => ['boolean'],
            ],
            [
                'questionBody.required' => 'لطفاً متن پرسش خود را وارد کنید.',
                'questionBody.string' => 'متن پرسش واردشده معتبر نیست.',
                'questionBody.min' => 'متن پرسش باید حداقل ۱۰ کاراکتر باشد.',
                'questionBody.max' => 'متن پرسش نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',
                'isAnonymous.boolean' => 'مقدار ارسال ناشناس معتبر نیست.',
            ]
        );

        // جلوگیری از ارسال پشت سر هم
        $key = 'product-question:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('questionBody', 'تعداد پرسش‌های شما زیاد است، لطفاً چند دقیقه دیگر تلاش کنید.');
            return;
        }

        $body = trim($this->questionBody);

        $duplicate = ProductQuestion::where('product_id', $this->productId)
            ->where('user_id', Auth::id())
            ->where('body', $body)
            ->exists();

        if ($duplicate) {
            $this->addError('questionBody', 'این پرسش قبلاً توسط شما ثبت شده است.');
            return;
        }

        ProductQuestion::create([
            'product_id' => $this->productId,
            'user_id' => Auth::id(),
            'body' => $body,
            'is_anonymous' => $this->isAnonymous,
            'status' => false,
        ]);

        RateLimiter::hit($key, 600);

        $this->reset(['questionBody', 'isAnonymous']);

        $this->dispatch(
            'alert',
            type: 'success',
            message: 'پرسش شما ثبت شد و پس از تأیید نمایش داده خواهد شد.'
        );
    }

    public function openAnswer(int $questionId): void
    {
        if (! Auth::check()) {
            $this->dispatch('alert', type: 'error', message: 'برای ثبت پاسخ ابتدا وارد شوید.');
            return;
        }

        $this->resetErrorBag();
        $this->answerBody = '';
        $this->answeringId = $questionId;
    }

    public function cancelAnswer(): void
    {
        $this->resetErrorBag();
        $this->reset(['answeringId', 'answerBody']);
    }

    public function submitAnswer(): void
    {
        if (! Auth::check()) {
            $this->dispatch('alert', type: 'error', message: 'برای ثبت پاسخ ابتدا وارد شوید.');
            return;
        }

        $this->validate(
            [
                'answerBody' => ['required', 'string', 'min:2', 'max:2000'],
            ],
            [
                'answerBody.required' => 'لطفاً متن پاسخ را وارد کنید.',
                'answerBody.string' => 'متن پاسخ واردشده معتبر نیست.',
                'answerBody.min' => 'متن پاسخ باید حداقل ۲ کاراکتر باشد.',
                'answerBody.max' => 'متن پاسخ نمی‌تواند بیشتر از ۲۰۰۰ کاراکتر باشد.',
            ]
        );

        $key = 'product-answer:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('answerBody', 'تعداد پاسخ‌های شما زیاد است، لطفاً چند دقیقه دیگر تلاش کنید.');
            return;
        }

        // فقط به پرسش تاییدشده‌ی همین محصول می‌توان پاسخ داد
        $question = ProductQuestion::active()
            ->where('product_id', $this->productId)
            ->findOrFail($this->answeringId);

        // پاسخ مدیر/کارشناس مستقیماً منتشر می‌شود
        $isOfficial = Auth::user()->can('comments.edit');

        $question->answers()->create([
            'user_id' => Auth::id(),
            'body' => trim($this->answerBody),
            'is_official' => $isOfficial,
            'status' => $isOfficial,
        ]);

        RateLimiter::hit($key, 600);

        $this->reset(['answeringId', 'answerBody']);
        unset($this->questions);

        $this->dispatch(
            'alert',
            type: 'success',
            message: $isOfficial
                ? 'پاسخ شما با موفقیت منتشر شد.'
                : 'پاسخ شما ثبت شد و پس از تأیید نمایش داده خواهد شد.'
        );
    }
};
?>

<div class="w-full space-y-12" dir="rtl">

    {{-- =================================================
        QUESTION FORM
    ================================================== --}}
    <section class="relative overflow-hidden bg-white/40 dark:bg-zinc-900/40 backdrop-blur-md border-2 border-gray-200 dark:border-white/10 rounded-[3rem] p-8 lg:p-12 shadow-lg shadow-gray-200/50 dark:shadow-none">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-12">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <span class="w-2 h-8 bg-brown-600 rounded-full"></span>
                    <h3 class="text-[20px] font-black text-zinc-900 dark:text-white uppercase">پرسش خود را بنویسید</h3>
                </div>
                <p class="text-[13px] font-bold text-zinc-400 mr-5">سوالات شما توسط کارشناسان و خریداران پاسخ داده می‌شود.</p>
            </div>
            <div class="hidden md:flex w-16 h-16 items-center justify-center rounded-2xl bg-brown-600/10 text-brown-600 border border-brown-600/20">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
            </div>
        </div>

        @auth
            <form wire:submit="submitQuestion" class="space-y-6">
                <div class="relative group space-y-2">
                    <textarea
                        wire:model="questionBody"
                        rows="4"
                        class="w-full bg-white/60 dark:bg-black/40 border-2 border-gray-200 dark:border-white/5 rounded-[2.5rem] px-8 py-7 text-[15px] font-bold text-zinc-900 dark:text-white outline-none focus:border-brown-600 focus:ring-4 focus:ring-brown-600/10 transition-all placeholder:text-zinc-400 resize-none"
                        placeholder="پرسش خود را اینجا مطرح کنید..."></textarea>

                    @error('questionBody')
                    <p class="text-xs font-bold text-red-500 mr-4">
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" wire:model="isAnonymous" class="w-5 h-5 rounded-lg border-2 border-gray-200 dark:border-white/10 text-brown-600 focus:ring-brown-600 bg-transparent">
                        <span class="text-[12px] font-black text-zinc-500 dark:text-zinc-400">ارسال پرسش به صورت ناشناس</span>
                    </label>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submitQuestion"
                        class="relative overflow-hidden group w-full md:w-auto px-12 py-5 bg-brown-600 text-white rounded-[2rem] font-black text-[14px] shadow-[0_20px_40px_rgba(120,72,45,0.25)] hover:bg-brown-700 dark:hover:bg-brown-500 transition-all duration-500 active:scale-95 flex items-center justify-center gap-3 disabled:opacity-50">

                        <div class="absolute inset-0 bg-gradient-to-tr from-brown-400/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                        <span class="relative z-10 tracking-wide" wire:loading.remove wire:target="submitQuestion">ثبت پرسش</span>
                        <span class="relative z-10 tracking-wide" wire:loading wire:target="submitQuestion">در حال ثبت پرسش...</span>

                        <div class="relative z-10 w-6 h-6 rounded-xl bg-white/20 flex items-center justify-center group-hover:rotate-12 group-hover:scale-110 transition-all duration-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </button>
                </div>
            </form>
        @else
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 p-6 rounded-[2rem] bg-brown-600/5 border border-brown-600/20">
                <p class="text-[13px] font-bold text-zinc-600 dark:text-zinc-300">
                    برای ثبت پرسش و پاسخ ابتدا وارد حساب کاربری خود شوید.
                </p>
                <a href="{{ route('login') }}" class="w-full md:w-auto text-center px-10 py-4 bg-brown-600 text-white rounded-[2rem] font-black text-[13px] hover:bg-brown-700 dark:hover:bg-brown-500 transition-all duration-500 active:scale-95">
                    ورود / ثبت‌نام
                </a>
            </div>
        @endauth
    </section>

    {{-- =================================================
        QUESTIONS LIST
    ================================================== --}}
    <div class="grid grid-cols-1 gap-10">

        @forelse($this->questions as $question)
            @php($answered = $question->activeAnswers->isNotEmpty())

            <div wire:key="question-{{ $question->id }}" class="relative group p-1 lg:p-2 bg-transparent">
                <div class="flex flex-col md:flex-row gap-8">
                    <div class="flex-shrink-0 flex md:flex-col items-center gap-4">
                        @if($answered)
                            <div class="w-14 h-14 bg-zinc-900 dark:bg-white text-white dark:text-black rounded-2xl flex items-center justify-center text-[20px] font-black shadow-xl">
                                Q
                            </div>
                            <div class="w-1 h-16 bg-brown-600/20 rounded-full hidden md:block"></div>
                        @else
                            <div class="w-14 h-14 bg-gray-100 dark:bg-zinc-800 text-zinc-400 rounded-2xl flex items-center justify-center text-[20px] font-black">
                                ?
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 space-y-6 min-w-0">
                        <div class="space-y-3">
                            <h4 class="text-[18px] font-black text-zinc-900 dark:text-white leading-8 break-words">{{ $question->body }}</h4>
                            <div class="flex flex-wrap items-center gap-4 text-[11px] font-bold text-zinc-400">
                                <span class="flex items-center gap-1.5"><i class="far fa-user"></i> {{ $question->author_name }}</span>
                                <span class="w-1.5 h-1.5 bg-zinc-200 dark:bg-white/10 rounded-full"></span>
                                <span class="flex items-center gap-1.5"><i class="far fa-calendar-alt"></i> {{ verta($question->created_at)->format('d F Y') }}</span>

                                @auth
                                    @if($answeringId !== $question->id)
                                        <span class="w-1.5 h-1.5 bg-zinc-200 dark:bg-white/10 rounded-full"></span>
                                        <button type="button" wire:click="openAnswer({{ $question->id }})" class="text-brown-600 font-black hover:underline">
                                            پاسخ دهید
                                        </button>
                                    @endif
                                @endauth
                            </div>
                        </div>

                        @unless($answered)
                            <div class="flex items-center gap-3">
                                <span class="text-[10px] font-black text-brown-600 bg-brown-600/10 px-3 py-1.5 rounded-lg border border-brown-600/20">در انتظار پاسخ</span>
                            </div>
                        @endunless

                        @foreach($question->activeAnswers as $answer)
                            <div wire:key="answer-{{ $answer->id }}" class="relative bg-white/40 dark:bg-zinc-900/60 backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-[2.5rem] p-8 lg:p-10 shadow-lg transition-all duration-500 hover:shadow-2xl">
                                <div class="flex flex-wrap items-center gap-3 mb-5">
                                    @if($answer->is_official)
                                        <div class="px-4 py-1.5 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-[11px] font-black rounded-lg uppercase">
                                            پاسخ کارشناس
                                        </div>
                                    @else
                                        <div class="px-4 py-1.5 bg-brown-600/10 text-brown-600 border border-brown-600/20 text-[11px] font-black rounded-lg">
                                            {{ $answer->author_name }}
                                        </div>
                                    @endif
                                    <span class="text-[11px] font-bold text-zinc-400">{{ verta($answer->created_at)->format('d F Y') }}</span>
                                </div>
                                <p class="text-[14px] font-bold text-zinc-600 dark:text-zinc-300 leading-8 break-words">
                                    {{ $answer->body }}
                                </p>
                            </div>
                        @endforeach

                        {{-- Answer form --}}
                        @auth
                            @if($answeringId === $question->id)
                                <form wire:submit="submitAnswer" class="space-y-4 bg-white/40 dark:bg-zinc-900/40 border border-gray-200 dark:border-white/10 rounded-[2.5rem] p-6">
                                    <textarea
                                        wire:model="answerBody"
                                        rows="3"
                                        class="w-full bg-white/80 dark:bg-black/40 border border-zinc-200 dark:border-white/5 rounded-[2rem] px-6 py-5 text-[14px] font-bold text-zinc-900 dark:text-white outline-none focus:ring-4 focus:ring-brown-600/10 focus:border-brown-600 transition-all shadow-sm resize-none"
                                        placeholder="پاسخ خود را بنویسید..."></textarea>

                                    @error('answerBody')
                                    <p class="text-xs font-bold text-red-500 mr-2">
                                        {{ $message }}
                                    </p>
                                    @enderror

                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" wire:click="cancelAnswer" class="px-8 py-3 rounded-2xl text-[13px] font-black text-zinc-500 hover:bg-gray-100 dark:hover:bg-white/5 transition-all">
                                            انصراف
                                        </button>
                                        <button
                                            type="submit"
                                            wire:loading.attr="disabled"
                                            wire:target="submitAnswer"
                                            class="px-8 py-3 bg-brown-600 text-white rounded-2xl text-[13px] font-black hover:bg-brown-700 dark:hover:bg-brown-500 transition-all active:scale-95 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="submitAnswer">ثبت پاسخ</span>
                                            <span wire:loading wire:target="submitAnswer">در حال ثبت...</span>
                                        </button>
                                    </div>
                                </form>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <p class="text-[14px] font-bold text-zinc-400">
                    هنوز پرسشی برای این محصول ثبت نشده است
                </p>
            </div>
        @endforelse
    </div>

    @if($this->questions->hasMorePages())
        <div class="flex justify-center pt-10">
            <button
                type="button"
                wire:click="loadMore"
                wire:loading.attr="disabled"
                wire:target="loadMore"
                class="group relative px-14 py-5 bg-brown-600 rounded-[2rem] overflow-hidden transition-all duration-500 shadow-[0_20px_40px_rgba(120,72,45,0.25)] hover:scale-[1.03] active:scale-95 disabled:opacity-50">

                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shine"></div>

                <div class="absolute inset-0 rounded-[2rem] border border-white/20 pointer-events-none"></div>

                <div class="relative flex items-center justify-center gap-3">
                    <svg class="w-5 h-5 text-white/80 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                    </svg>

                    <span class="text-[14px] font-black text-white uppercase tracking-wider" wire:loading.remove wire:target="loadMore">
                        مشاهده پرسش‌های بیشتر ({{ $this->questions->total() - $this->questions->count() }})
                    </span>
                    <span class="text-[14px] font-black text-white uppercase tracking-wider" wire:loading wire:target="loadMore">
                        در حال بارگذاری...
                    </span>
                </div>
            </button>
        </div>
    @endif
</div>
