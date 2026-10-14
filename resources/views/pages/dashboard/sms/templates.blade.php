<?php

use App\Models\SmsTemplate;
use App\Services\Sms\AudienceBuilder;
use App\Services\Sms\MelipayamakClient;
use App\Services\Sms\SmsAudit;
use App\Services\Sms\SmsProviderException;
use App\Services\Sms\TemplateBody;
use App\Services\Sms\TemplateManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $category = '';

    public ?int $selectedId = null;

    // فرم ایجاد/ویرایش
    public ?int $editId = null;
    public string $title = '';
    public string $body = '';
    public string $templateCategory = 'service';
    public string $description = '';
    public array $variables = [];

    // نگاشت کد BodyStatus → وضعیت
    public array $statusMap = [];

    #[Layout('layouts.dashboard')]
    public function mount(): void
    {
        abort_if(!auth()->user()->can('sms.view'), 403);
        $this->statusMap = array_map('strval', app(TemplateManager::class)->statusMap());
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'category'], true)) {
            $this->resetPage();
        }

        if ($property === 'body') {
            $this->syncVariableRows();
        }
    }

    #[Computed]
    public function data()
    {
        $term = trim($this->search);

        return SmsTemplate::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%")
                ->orWhere('provider_body_id', is_numeric($term) ? (int) $term : -1)))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->orderByRaw("case when status = 'approved' then 0 else 1 end")
            ->latest('id')
            ->paginate(20);
    }

    /** کدهای خام دیده‌شده از ملی پیامک برای تنظیم نگاشت */
    #[Computed]
    public function providerCodes()
    {
        return SmsTemplate::whereNotNull('provider_status')
            ->selectRaw('provider_status, count(*) as c, min(title) as sample')
            ->groupBy('provider_status')
            ->orderBy('provider_status')
            ->get();
    }

    /** وضعیت خام الگوی کد ورود (OTP) که در عمل ارسال می‌شود؛ راهنمای کد «تأییدشده» */
    #[Computed]
    public function otpTemplate(): ?SmsTemplate
    {
        $bodyId = (int) config('sms.body_id');

        return $bodyId ? SmsTemplate::where('provider_body_id', $bodyId)->first() : null;
    }

    #[Computed]
    public function selected(): ?SmsTemplate
    {
        return $this->selectedId ? SmsTemplate::with('creator:id,first_name,last_name')->find($this->selectedId) : null;
    }

    public function show(int $id): void
    {
        $this->selectedId = $id;
        unset($this->selected);
    }

    public function sync(): void
    {
        abort_if(!auth()->user()->can('sms.templates'), 403);

        try {
            $stats = app(TemplateManager::class)->sync();
            SmsAudit::log('template.sync', null, $stats);
            $this->dispatch('alert', type: 'success', title: 'همگام‌سازی شد',
                text: "{$stats['total']} الگو در ملی پیامک؛ {$stats['created']} الگوی جدید" . ($stats['missing'] ? "، {$stats['missing']} الگو در پنل یافت نشد" : ''));
        } catch (SmsProviderException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $e->getMessage());
        }

        unset($this->data, $this->providerCodes);
    }

    public function saveStatusMap(): void
    {
        abort_if(!auth()->user()->can('sms.templates'), 403);

        app(TemplateManager::class)->saveStatusMap($this->statusMap);
        $this->statusMap = array_map('strval', app(TemplateManager::class)->statusMap());
        SmsAudit::log('template.status_map', null, ['map' => $this->statusMap]);

        unset($this->data);
        $this->dispatch('alert', type: 'success', title: 'ذخیره شد', text: 'نگاشت وضعیت روی همه الگوها اعمال شد.');
    }

    public function create(): void
    {
        abort_if(!auth()->user()->can('sms.templates'), 403);
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        abort_if(!auth()->user()->can('sms.templates'), 403);

        $template = SmsTemplate::findOrFail($id);
        $this->resetValidation();
        $this->editId = $template->id;
        $this->title = $template->title;
        $this->body = $template->body;
        $this->templateCategory = $template->category;
        $this->description = (string) $template->description;
        $this->variables = $template->variableDefinitions();
    }

    /** متن الگو فقط برای پیش‌نویس، خطادار یا ردشده قابل تغییر است */
    public function bodyEditable(): bool
    {
        if (!$this->editId) {
            return true;
        }

        $template = SmsTemplate::find($this->editId);

        return $template && in_array($template->status, ['draft', 'failed', 'rejected'], true);
    }

    public function insertVariable(): void
    {
        $this->body = rtrim($this->body) . ' {' . TemplateBody::count($this->body) . '}';
        $this->syncVariableRows();
    }

    protected function syncVariableRows(): void
    {
        $count = min(TemplateBody::count($this->body), 20);
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[$i] = $this->variables[$i] ?? ['label' => 'متغیر ' . ($i + 1), 'source' => 'fixed'];
        }

        $this->variables = $rows;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:1000',
            'templateCategory' => 'required|in:' . implode(',', array_keys(SmsTemplate::CATEGORIES)),
            'description' => 'nullable|string|max:1000',
            'variables' => 'array|max:20',
            'variables.*.label' => 'required|string|max:50',
            'variables.*.source' => 'required|in:' . implode(',', array_keys(AudienceBuilder::SOURCES)),
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'نام الگو را وارد کنید.',
            'title.max' => 'نام الگو حداکثر ۱۰۰ نویسه است.',
            'body.required' => 'متن پیامک را وارد کنید.',
            'body.max' => 'متن پیامک حداکثر ۱۰۰۰ نویسه است.',
            'variables.*.label.required' => 'عنوان متغیر را وارد کنید.',
            'variables.*.source.in' => 'منبع متغیر نامعتبر است.',
        ];
    }

    public function save(): void
    {
        abort_if(!auth()->user()->can('sms.templates'), 403);

        $this->syncVariableRows();
        $this->validate();

        if ($errors = TemplateBody::errors($this->body)) {
            $this->addError('body', implode(' ', $errors));
            return;
        }

        $template = $this->editId ? SmsTemplate::findOrFail($this->editId) : new SmsTemplate(['status' => 'draft', 'created_by' => auth()->id()]);
        $bodyEditable = $this->bodyEditable();

        $template->fill([
            'title' => trim($this->title),
            'category' => $this->templateCategory,
            'description' => trim($this->description) ?: null,
            'variables' => array_values($this->variables),
        ]);

        if ($bodyEditable) {
            $template->body = trim($this->body);
        }

        $template->save();
        SmsAudit::log($this->editId ? 'template.update' : 'template.create', $template, ['title' => $template->title]);

        $this->resetForm();
        unset($this->data);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'ذخیره شد', text: $template->provider_body_id
            ? 'تغییرات داخلی الگو ذخیره شد.'
            : 'الگو به‌صورت پیش‌نویس ذخیره شد؛ برای استفاده باید برای ثبت ارسال و تأیید شود.');
    }

    /** ارسال به ملی پیامک برای ثبت/بررسی (پیش‌نویس، خطادار یا ردشده) */
    public function submit(int $id): void
    {
        abort_if(!auth()->user()->can('sms.templates'), 403);

        $template = SmsTemplate::findOrFail($id);

        if (!in_array($template->status, ['draft', 'failed', 'rejected'], true)) {
            return;
        }

        try {
            $result = app(TemplateManager::class)->submit($template);
            SmsAudit::log('template.submit', $template, ['ok' => $result['ok'], 'body_id' => $template->fresh()->provider_body_id]);
            $this->dispatch('alert', type: $result['ok'] ? 'success' : 'error', title: $result['ok'] ? 'ارسال شد' : 'ثبت نشد', text: $result['message']);
        } catch (SmsProviderException $e) {
            $template->update(['last_error' => mb_substr($e->getMessage(), 0, 255)]);
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $e->getMessage());
        }

        unset($this->data, $this->selected, $this->providerCodes);
    }

    /** حذف از سامانه داخلی (الگوی ملی پیامک حذف نمی‌شود؛ همگام‌سازی بعدی دوباره آن را می‌آورد) */
    public function delete(int $id): void
    {
        abort_if(!auth()->user()->can('sms.templates'), 403);

        $template = SmsTemplate::findOrFail($id);

        if ($template->provider_body_id && $template->status !== 'missing') {
            $this->dispatch('alert', type: 'warning', title: 'حذف ممکن نیست', text: 'این الگو در ملی پیامک ثبت است و از پنل ملی پیامک مدیریت می‌شود.');
            return;
        }

        SmsAudit::log('template.delete', $template, ['title' => $template->title]);
        $template->delete();
        unset($this->data);
        $this->dispatch('alert', type: 'success', title: 'حذف شد', text: 'الگو از سامانه حذف شد.');
    }

    public function resetForm(): void
    {
        $this->reset(['editId', 'title', 'body', 'description', 'variables']);
        $this->templateCategory = 'service';
        $this->resetValidation();
    }

    public function isConfigured(): bool
    {
        return app(MelipayamakClient::class)->isConfigured();
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">مدیریت الگوها</h1>
            <div class="text-muted small">الگوهای خط خدماتی اشتراکی ملی پیامک — فقط الگوی تأییدشده قابل ارسال است</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('sms.templates')
                <button type="button" class="btn btn-light btn-wave" wire:click="sync" wire:loading.attr="disabled" wire:target="sync" @disabled(!$this->isConfigured())>
                    <span wire:loading.remove wire:target="sync"><i class="ri-refresh-line align-middle"></i></span>
                    <span wire:loading wire:target="sync" class="spinner-border spinner-border-sm"></span>
                    دریافت و بررسی وضعیت از ملی پیامک
                </button>
                <button type="button" class="btn btn-primary btn-wave" data-bs-toggle="modal" data-bs-target="#smsTemplateModal" wire:click="create">
                    <i class="ri-add-line align-middle"></i> الگوی جدید
                </button>
            @endcan
        </div>
    </div>

    @include('pages.dashboard.sms.partials.nav', ['active' => 'sms.templates'])

    @if(!$this->isConfigured())
        <div class="alert alert-danger">اطلاعات اتصال ملی پیامک (<code dir="ltr">PAYAMAK_USERNAME</code> و <code dir="ltr">PAYAMAK_PASSWORD</code>) در فایل .env تنظیم نشده است.</div>
    @endif

    {{-- نگاشت کد وضعیت --}}
    @can('sms.templates')
        @if($this->providerCodes->isNotEmpty())
            <div class="card custom-card" x-data="{ open: @js(empty($statusMap)) }">
                <div class="card-header justify-content-between" role="button" @click="open = !open">
                    <div class="card-title"><i class="ri-git-merge-line me-1"></i> نگاشت کد وضعیت الگو (BodyStatus)</div>
                    <span class="small text-muted">
                        @if(empty($statusMap))<span class="text-danger">تنظیم نشده — هیچ الگویی قابل ارسال نیست</span>@else{{ count($statusMap) }} کد تنظیم شده@endif
                        <i class="ri-arrow-down-s-line" :class="open && 'ri-arrow-up-s-line'"></i>
                    </span>
                </div>
                <div class="card-body" x-show="open" x-collapse>
                    <p class="small text-muted mb-2">
                        ملی پیامک وضعیت الگو را با یک کد عددی برمی‌گرداند و معنای این کدها را در مستندات منتشر نکرده است.
                        برای هر کد، وضعیت متناظر را با مقایسه الگوها با «وب‌سرویس خدماتی» در پنل ملی پیامک تعیین کنید. کد تنظیم‌نشده «نامشخص» و غیرقابل ارسال است.
                    </p>
                    @if($this->otpTemplate)
                        <div class="alert alert-light small py-2">
                            <i class="ri-lightbulb-line text-warning"></i>
                            الگوی کد ورود سایت (کد {{ $this->otpTemplate->provider_body_id }}) که هم‌اکنون با موفقیت ارسال می‌شود، کد وضعیت
                            <strong>{{ $this->otpTemplate->provider_status }}</strong> دارد؛ این کد به احتمال زیاد معادل «تأییدشده» است.
                        </div>
                    @endif
                    <div class="row g-2">
                        @foreach($this->providerCodes as $code)
                            <div class="col-md-4" wire:key="code-{{ $code->provider_status }}">
                                <div class="border rounded p-2">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>کد <strong>{{ $code->provider_status }}</strong></span>
                                        <span class="text-muted">{{ $code->c }} الگو (مثلاً «{{ \Illuminate\Support\Str::limit($code->sample, 20) }}»)</span>
                                    </div>
                                    <select class="form-select form-select-sm" wire:model="statusMap.{{ $code->provider_status }}">
                                        <option value="">نامشخص</option>
                                        <option value="approved">تأییدشده</option>
                                        <option value="pending">در انتظار بررسی</option>
                                        <option value="rejected">ردشده</option>
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-primary mt-3" wire:click="saveStatusMap" wire:confirm="نگاشت وضعیت ذخیره و روی همه الگوها اعمال شود؟">ذخیره نگاشت</button>
                </div>
            </div>
        @endif
    @endcan

    <div class="card custom-card">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6">
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" placeholder="جستجو در نام، متن یا کد الگو">
                </div>
                <div class="col-md-3">
                    <select wire:model.live="status" class="form-select form-select-sm">
                        <option value="">همه وضعیت‌ها</option>
                        @foreach(SmsTemplate::STATUSES as $key => $s)
                            <option value="{{ $key }}">{{ $s['title'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="category" class="form-select form-select-sm">
                        <option value="">همه نوع‌ها</option>
                        @foreach(SmsTemplate::CATEGORIES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card custom-card">
        <div class="card-body position-relative">
            <div wire:loading.flex wire:target="search,status,category,gotoPage,nextPage,previousPage" class="position-absolute top-0 start-0 w-100 h-100 bg-body bg-opacity-50 align-items-center justify-content-center" style="z-index: 2">
                <span class="spinner-border text-primary"></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>کد الگو</th><th>نام</th><th style="min-width: 280px">متن</th><th>نوع</th><th>وضعیت</th><th>آخرین بررسی</th><th></th></tr></thead>
                    <tbody>
                    @forelse($this->data as $template)
                        @php $badge = $template->statusBadge(); @endphp
                        <tr wire:key="t-{{ $template->id }}">
                            <td dir="ltr" class="text-end">
                                @if($template->provider_body_id)
                                    <span x-data class="font-monospace" role="button" title="کپی کد" @click="navigator.clipboard.writeText('{{ $template->provider_body_id }}'); $dispatch('copied')">{{ $template->provider_body_id }} <i class="ri-file-copy-line text-muted"></i></span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="fw-medium">{{ $template->title }}</td>
                            <td class="small text-wrap">{{ \Illuminate\Support\Str::limit($template->body, 120) }}
                                @if($template->placeholderCount())<div class="fs-11 text-muted">{{ $template->placeholderCount() }} متغیر</div>@endif
                            </td>
                            <td><span class="badge {{ $template->category === 'marketing' ? 'bg-purple-transparent' : 'bg-light text-dark' }}">{{ SmsTemplate::CATEGORIES[$template->category] ?? $template->category }}</span></td>
                            <td>
                                <span class="badge bg-{{ $badge['color'] }}-transparent">{{ $badge['title'] }}</span>
                                @if($template->provider_status !== null)<div class="fs-11 text-muted">کد خام: {{ $template->provider_status }}</div>@endif
                                @if($template->last_error)<div class="fs-11 text-danger text-wrap" style="max-width: 200px">{{ $template->last_error }}</div>@endif
                            </td>
                            <td class="small text-muted">{{ $template->last_checked_at ? verta($template->last_checked_at)->formatDifference() : '—' }}</td>
                            <td>
                                <div class="hstack gap-2 fs-15">
                                    <a href="#smsTemplateDetail" data-bs-toggle="modal" wire:click="show({{ $template->id }})" class="text-info" title="جزئیات"><i class="ri-eye-line"></i></a>
                                    @can('sms.templates')
                                        <a href="#smsTemplateModal" data-bs-toggle="modal" wire:click="edit({{ $template->id }})" class="text-primary" title="ویرایش"><i class="ri-edit-line"></i></a>
                                        @if(in_array($template->status, ['draft', 'failed', 'rejected'], true))
                                            <a href="javascript:void(0)" class="text-success" title="ارسال برای ثبت در ملی پیامک"
                                               wire:click="submit({{ $template->id }})" wire:loading.class="opacity-50" wire:target="submit({{ $template->id }})"
                                               wire:confirm="الگوی «{{ $template->title }}» برای {{ $template->provider_body_id ? 'بررسی دوباره' : 'ثبت' }} به ملی پیامک ارسال شود؟"><i class="ri-upload-cloud-2-line"></i></a>
                                        @endif
                                        @if(!$template->provider_body_id || $template->status === 'missing')
                                            <a href="javascript:void(0)" class="text-danger" title="حذف" wire:click="delete({{ $template->id }})" wire:confirm="الگوی «{{ $template->title }}» از سامانه حذف شود؟"><i class="ri-delete-bin-6-line"></i></a>
                                        @endif
                                    @endcan
                                    @can('sms.send')
                                        @if($template->isApproved())
                                            <a href="{{ route('sms.send', ['template' => $template->id]) }}" class="text-success" title="ارسال با این الگو"><i class="ri-send-plane-2-line"></i></a>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <i class="ri-file-list-3-line fs-32 d-block mb-2"></i>
                            الگویی یافت نشد. با «دریافت و بررسی وضعیت از ملی پیامک» الگوهای پنل را بیاورید یا الگوی جدید بسازید.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $this->data->links() }}
        </div>
    </div>

    {{-- فرم ایجاد/ویرایش --}}
    <div wire:ignore.self class="modal fade" id="smsTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" wire:submit="save">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $editId ? 'ویرایش الگو' : 'الگوی جدید' }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @php $bodyEditable = $this->bodyEditable(); @endphp
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">نام داخلی الگو <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title" maxlength="100">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">نوع پیامک</label>
                            <select class="form-select" wire:model="templateCategory">
                                @foreach(SmsTemplate::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($templateCategory === 'marketing')
                            <div class="col-12">
                                <div class="alert alert-warning small mb-0">پیامک تبلیغاتی فقط به مخاطبانی ارسال می‌شود که رضایت ثبت‌شده دارند و انصراف نداده‌اند. ارسال تبلیغات از خط خدماتی اشتراکی ممکن است طبق قوانین ملی پیامک مجاز نباشد؛ پیش از استفاده بررسی کنید.</div>
                            </div>
                        @endif
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-end mb-1">
                                <label class="form-label mb-0">متن پیامک <span class="text-danger">*</span></label>
                                @if($bodyEditable)
                                    <button type="button" class="btn btn-sm btn-light" wire:click="insertVariable"><i class="ri-add-line"></i> افزودن متغیر</button>
                                @endif
                            </div>
                            <textarea rows="5" class="form-control @error('body') is-invalid @enderror" wire:model.live.debounce.500ms="body" @disabled(!$bodyEditable) maxlength="1000"></textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                متغیرها مطابق قالب ملی پیامک با اندیس از صفر نوشته می‌شوند: <code dir="ltr">{0}</code>، <code dir="ltr">{1}</code>، ...
                                مثال: «سلام {0} عزیز، سفارش {1} ثبت شد.» —
                                {{ mb_strlen($body) }} نویسه، حدود {{ \App\Services\Sms\TemplateBody::parts($body ?: ' ') }} پیامک
                            </div>
                            @if(!$bodyEditable)
                                <div class="form-text text-warning">متن الگوی ثبت‌شده فقط در وضعیت ردشده قابل تغییر است (تغییر نیاز به تأیید دوباره دارد).</div>
                            @endif
                        </div>

                        @if($variables)
                            <div class="col-12">
                                <label class="form-label">متغیرها و منبع پیش‌فرض مقدار</label>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr><th style="width: 70px">متغیر</th><th>عنوان</th><th>منبع پیش‌فرض</th></tr></thead>
                                        <tbody>
                                        @foreach($variables as $i => $variable)
                                            <tr wire:key="var-{{ $i }}">
                                                <td><code dir="ltr">{{ '{' . $i . '}' }}</code></td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm @error('variables.' . $i . '.label') is-invalid @enderror" wire:model="variables.{{ $i }}.label" placeholder="مثلاً نام مشتری">
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm" wire:model="variables.{{ $i }}.source">
                                                        @foreach(AudienceBuilder::SOURCES as $key => $label)
                                                            <option value="{{ $key }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="col-12">
                            <label class="form-label">توضیحات داخلی</label>
                            <textarea rows="2" class="form-control" wire:model="description" maxlength="1000"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"></span> ذخیره
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- جزئیات --}}
    <div wire:ignore.self class="modal fade" id="smsTemplateDetail" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                @php $t = $this->selected; @endphp
                <div class="modal-header">
                    <h6 class="modal-title">{{ $t?->title ?? 'جزئیات الگو' }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($t)
                        <div class="bg-light rounded p-3 mb-3 position-relative" x-data="{ copied: false }">
                            <div style="white-space: pre-wrap">{{ $t->body }}</div>
                            <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2"
                                    @click="navigator.clipboard.writeText(@js($t->body)); copied = true; setTimeout(() => copied = false, 1500)">
                                <i class="ri-file-copy-line"></i> <span x-text="copied ? 'کپی شد' : 'کپی متن'"></span>
                            </button>
                        </div>
                        <dl class="row small mb-0">
                            <dt class="col-sm-4">کد الگو در ملی پیامک</dt>
                            <dd class="col-sm-8" dir="ltr" x-data="{ copied: false }">
                                @if($t->provider_body_id)
                                    <span class="font-monospace">{{ $t->provider_body_id }}</span>
                                    <a href="javascript:void(0)" @click="navigator.clipboard.writeText('{{ $t->provider_body_id }}'); copied = true; setTimeout(() => copied = false, 1500)"><i class="ri-file-copy-line"></i></a>
                                    <span x-show="copied" class="text-success">✓</span>
                                @else — ثبت نشده @endif
                            </dd>
                            <dt class="col-sm-4">وضعیت</dt>
                            <dd class="col-sm-8"><span class="badge bg-{{ $t->statusBadge()['color'] }}-transparent">{{ $t->statusBadge()['title'] }}</span>
                                @if($t->provider_status !== null)<span class="text-muted">(کد خام {{ $t->provider_status }})</span>@endif</dd>
                            @if($t->provider_description)<dt class="col-sm-4">توضیح ملی پیامک</dt><dd class="col-sm-8">{{ $t->provider_description }}</dd>@endif
                            @if($t->last_error)<dt class="col-sm-4">آخرین خطا</dt><dd class="col-sm-8 text-danger">{{ $t->last_error }}</dd>@endif
                            <dt class="col-sm-4">نوع</dt><dd class="col-sm-8">{{ SmsTemplate::CATEGORIES[$t->category] ?? $t->category }}</dd>
                            @if($t->provider_created_at)<dt class="col-sm-4">تاریخ ثبت در ملی پیامک</dt><dd class="col-sm-8" dir="ltr">{{ $t->provider_created_at }}</dd>@endif
                            <dt class="col-sm-4">ارسال برای ثبت</dt><dd class="col-sm-8">{{ $t->submitted_at ? verta($t->submitted_at)->format('Y/m/d H:i') : '—' }}</dd>
                            <dt class="col-sm-4">آخرین بررسی وضعیت</dt><dd class="col-sm-8">{{ $t->last_checked_at ? verta($t->last_checked_at)->format('Y/m/d H:i') : '—' }}</dd>
                            @if($t->creator)<dt class="col-sm-4">ایجادکننده</dt><dd class="col-sm-8">{{ trim($t->creator->first_name . ' ' . $t->creator->last_name) }}</dd>@endif
                            @if($t->description)<dt class="col-sm-4">توضیحات داخلی</dt><dd class="col-sm-8">{{ $t->description }}</dd>@endif
                        </dl>
                        @if($t->variableDefinitions())
                            <h6 class="fw-semibold mt-3">متغیرها</h6>
                            <ul class="list-unstyled small mb-0">
                                @foreach($t->variableDefinitions() as $i => $v)
                                    <li><code dir="ltr">{{ '{' . $i . '}' }}</code> {{ $v['label'] }} — <span class="text-muted">{{ AudienceBuilder::SOURCES[$v['source']] ?? $v['source'] }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div x-data="{ show: false }" @copied.window="show = true; setTimeout(() => show = false, 1500)" x-show="show" x-transition
         class="position-fixed bottom-0 start-0 m-3 badge bg-success p-2" style="z-index: 1080; display: none">کپی شد</div>
</div>
