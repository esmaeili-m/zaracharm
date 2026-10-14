<?php

use App\Models\SmsBatch;
use App\Models\SmsContact;
use App\Models\SmsContactList;
use App\Models\SmsTemplate;
use App\Services\Sms\AudienceBuilder;
use App\Services\Sms\CustomerFilter;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SpreadsheetReader;
use App\Services\Sms\TemplateBody;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;
    protected string $paginationTheme = 'bootstrap';

    public int $step = 1;

    // ۱) مخاطبان
    public string $source = 'single';
    public string $singleMobile = '';
    public string $singleName = '';
    public string $numbers = '';
    public array $filters = CustomerFilter::DEFAULTS;
    public string $selection = 'selected'; // selected | all (همه نتایج فیلتر به‌جز excluded)
    public array $selectedIds = [];
    public array $excludedIds = [];
    public ?int $listId = null;
    public $importFile;
    public ?string $importToken = null;
    public string $importName = '';
    public array $importHeaders = [];
    public array $importPreview = [];
    public int $importCount = 0;
    public bool $importTruncated = false;
    public ?int $mobileColumn = null;
    public string $nameColumn = '';
    public string $saveListTitle = '';

    // ۲) الگو
    #[Url(as: 'template', except: null)]
    public ?int $templateId = null;

    // ۳) متغیرها
    public array $vars = [];

    // ۴) بررسی
    public ?array $plan = null;
    public bool $confirmed = false;
    public string $uuid = '';

    // ۵) نتیجه
    public ?int $batchId = null;

    #[Layout('layouts.dashboard')]
    public function mount(): void
    {
        abort_if(!auth()->user()->can('sms.send'), 403);

        $this->uuid = (string) Str::uuid();

        if ($this->templateId && !SmsTemplate::approved()->whereKey($this->templateId)->exists()) {
            $this->templateId = null;
        }

        if ($this->templateId) {
            $this->initVars();
        }
    }

    public function canBulk(): bool
    {
        return auth()->user()->can('sms.bulk');
    }

    public function updated($property): void
    {
        // هر تغییر در مخاطبان/الگو/متغیرها پیش‌نمایش قبلی را باطل می‌کند
        if (!str_starts_with($property, 'confirmed') && !str_starts_with($property, 'saveListTitle') && $property !== 'step') {
            $this->plan = null;
            $this->confirmed = false;
        }

        if (str_starts_with($property, 'filters')) {
            $this->resetPage('cpage');
            // با تغییر فیلتر، انتخاب «همه نتایج» دوباره محاسبه می‌شود؛ استثناها حفظ می‌شوند
        }

        if ($property === 'source' && $this->source !== 'single' && !$this->canBulk()) {
            $this->source = 'single';
        }
    }

    // ───────────── مشتریان سایت ─────────────

    #[Computed]
    public function customers()
    {
        return CustomerFilter::query($this->filters)
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.mobile', 'users.status', 'users.created_at')
            ->selectSub('select count(*) from orders where orders.user_id = users.id and orders.payment_status = \'paid\'', 'orders_count')
            ->latest('users.id')
            ->paginate(15, pageName: 'cpage');
    }

    #[Computed]
    public function matchingCount(): int
    {
        return CustomerFilter::query($this->filters)->count();
    }

    public function selectedCount(): int
    {
        return $this->selection === 'all'
            ? max(0, $this->matchingCount - count($this->excludedIds))
            : count($this->selectedIds);
    }

    public function toggleCustomer(int $id): void
    {
        $list = $this->selection === 'all' ? 'excludedIds' : 'selectedIds';
        $this->{$list} = in_array($id, $this->{$list}, true)
            ? array_values(array_diff($this->{$list}, [$id]))
            : [...$this->{$list}, $id];
        $this->plan = null;
    }

    public function selectPage(): void
    {
        $ids = $this->customers->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($this->selection === 'all') {
            $this->excludedIds = array_values(array_diff($this->excludedIds, $ids));
        } else {
            $this->selectedIds = array_values(array_unique([...$this->selectedIds, ...$ids]));
        }
        $this->plan = null;
    }

    public function selectAllMatching(): void
    {
        $this->selection = 'all';
        $this->excludedIds = [];
        $this->selectedIds = [];
        $this->plan = null;
    }

    public function clearSelection(): void
    {
        $this->selection = 'selected';
        $this->excludedIds = [];
        $this->selectedIds = [];
        $this->plan = null;
    }

    public function resetFilters(): void
    {
        $this->filters = CustomerFilter::DEFAULTS;
        $this->resetPage('cpage');
        unset($this->customers, $this->matchingCount);
    }

    /** ذخیره مخاطبان انتخاب‌شده (مشتریان یا شماره‌های دستی/فایل) به‌عنوان لیست */
    public function saveAsList(): void
    {
        abort_if(!auth()->user()->can('sms.contacts'), 403);

        $this->validate(['saveListTitle' => 'required|string|max:100'], ['saveListTitle.required' => 'نام لیست را وارد کنید.']);

        $added = 0;
        $list = SmsContactList::create(['title' => trim($this->saveListTitle), 'description' => 'ساخته‌شده از صفحه ارسال (' . (SmsBatch::SOURCES[$this->source] ?? '') . ')']);

        $attach = function (array $rows) use ($list, &$added) {
            $ids = [];
            foreach ($rows as $row) {
                if (!$mobile = PhoneNumber::normalize($row['mobile'] ?? null)) {
                    continue;
                }
                $contact = SmsContact::firstOrCreate(['mobile' => $mobile], ['name' => $row['name'] ?? null, 'user_id' => $row['user_id'] ?? null]);
                $ids[] = $contact->id;
            }
            $list->contacts()->syncWithoutDetaching($ids);
            $added += count($ids);
        };

        if ($this->source === 'customers') {
            $query = CustomerFilter::query($this->filters);
            $this->selection === 'all'
                ? $query->whereNotIn('users.id', $this->excludedIds ?: [0])
                : $query->whereIn('users.id', $this->selectedIds ?: [0]);

            $query->select('users.id', 'users.first_name', 'users.last_name', 'users.mobile')
                ->chunkById(500, fn ($users) => $attach($users->map(fn ($u) => [
                    'mobile' => $u->mobile, 'name' => trim($u->first_name . ' ' . $u->last_name) ?: null, 'user_id' => $u->id,
                ])->all()), 'users.id', 'id');
        } elseif ($this->source === 'manual') {
            foreach (array_chunk(PhoneNumber::parseList($this->numbers)['valid'], 500) as $chunk) {
                $attach(array_map(fn ($m) => ['mobile' => $m], $chunk));
            }
        } elseif ($this->source === 'import') {
            $data = AudienceBuilder::importData((string) $this->importToken);
            foreach (array_chunk($data['rows'], 500) as $chunk) {
                $attach(array_map(fn ($r) => [
                    'mobile' => $r[(int) $this->mobileColumn] ?? null,
                    'name' => $this->nameColumn !== '' ? ($r[(int) $this->nameColumn] ?? null) : null,
                ], $chunk));
            }
        }

        $this->saveListTitle = '';
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'لیست ساخته شد', text: "لیست «{$list->title}» با {$added} مخاطب ذخیره شد.");
    }

    // ───────────── فایل ─────────────

    public function updatedImportFile(): void
    {
        $this->validate(['importFile' => 'required|file|max:5120|mimes:csv,txt,xlsx'], [
            'importFile.mimes' => 'فقط فایل CSV یا Excel (xlsx) مجاز است.',
            'importFile.max' => 'حجم فایل حداکثر ۵ مگابایت است.',
        ]);

        try {
            $data = SpreadsheetReader::read($this->importFile->getRealPath(), $this->importFile->getClientOriginalExtension(), (int) config('sms.max_recipients', 20000));
        } catch (\RuntimeException $e) {
            $this->addError('importFile', $e->getMessage());
            return;
        }

        $this->importToken = AudienceBuilder::storeImport(['headers' => $data['headers'], 'rows' => $data['rows']]);
        $this->importName = $this->importFile->getClientOriginalName();
        $this->importHeaders = $data['headers'];
        $this->importPreview = array_slice($data['rows'], 0, 8);
        $this->importCount = count($data['rows']);
        $this->importTruncated = $data['truncated'];
        $this->mobileColumn = $this->guessColumn('/(موبایل|همراه|شماره|تلفن|mobile|phone|number|tel)/iu') ?? 0;
        $guessName = $this->guessColumn('/(نام|name)/iu');
        $this->nameColumn = $guessName !== null && $guessName !== $this->mobileColumn ? (string) $guessName : '';
        $this->importFile = null;
        $this->plan = null;
    }

    protected function guessColumn(string $pattern): ?int
    {
        foreach ($this->importHeaders as $i => $header) {
            if (preg_match($pattern, $header)) {
                return $i;
            }
        }

        return null;
    }

    /** بررسی ستون شماره فایل پیش از ادامه */
    #[Computed]
    public function importStats(): ?array
    {
        if (!$this->importToken || $this->mobileColumn === null) {
            return null;
        }

        try {
            $rows = AudienceBuilder::importData($this->importToken)['rows'];
        } catch (\RuntimeException) {
            return null;
        }

        $valid = $invalid = $duplicates = 0;
        $seen = [];
        $invalidSamples = [];
        foreach ($rows as $index => $row) {
            $mobile = PhoneNumber::normalize($row[$this->mobileColumn] ?? '');
            if (!$mobile) {
                $invalid++;
                if (count($invalidSamples) < 10) {
                    $invalidSamples[] = ['row' => $index + 2, 'value' => $row[$this->mobileColumn] ?? ''];
                }
            } elseif (isset($seen[$mobile])) {
                $duplicates++;
            } else {
                $seen[$mobile] = true;
                $valid++;
            }
        }

        return compact('valid', 'invalid', 'duplicates', 'invalidSamples');
    }

    #[Computed]
    public function manualStats(): array
    {
        return PhoneNumber::parseList($this->numbers);
    }

    #[Computed]
    public function lists()
    {
        return SmsContactList::withCount('contacts')->orderBy('title')->get();
    }

    // ───────────── الگو و متغیرها ─────────────

    #[Computed]
    public function templates()
    {
        return SmsTemplate::approved()->orderBy('title')->get();
    }

    #[Computed]
    public function template(): ?SmsTemplate
    {
        return $this->templateId ? SmsTemplate::approved()->find($this->templateId) : null;
    }

    public function chooseTemplate(int $id): void
    {
        // فقط الگوی تأییدشده قابل انتخاب است (بررسی سمت سرور)
        if (!SmsTemplate::approved()->whereKey($id)->exists()) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'این الگو تأییدشده نیست.');
            return;
        }

        $this->templateId = $id;
        unset($this->template);
        $this->initVars();
        $this->plan = null;
    }

    protected function initVars(): void
    {
        $template = SmsTemplate::approved()->find($this->templateId);
        $this->vars = [];

        foreach ($template?->variableDefinitions() ?? [] as $i => $definition) {
            $source = $definition['source'];
            // ستون فایل فقط برای ورود از فایل معنی دارد
            if ($source === 'column' && $this->source !== 'import') {
                $source = 'fixed';
            }
            $this->vars[$i] = ['source' => $source, 'value' => '', 'column' => ''];
        }
    }

    // ───────────── مراحل ─────────────

    public function audience(): array
    {
        return match ($this->source) {
            'single' => ['source' => 'single', 'mobile' => $this->singleMobile, 'name' => $this->singleName],
            'manual' => ['source' => 'manual', 'numbers' => $this->numbers],
            'customers' => ['source' => 'customers', 'filters' => $this->filters, 'selection' => $this->selection,
                'selected_ids' => $this->selectedIds, 'excluded_ids' => $this->excludedIds],
            'list' => ['source' => 'list', 'list_id' => $this->listId],
            'import' => ['source' => 'import', 'import_token' => $this->importToken, 'mobile_column' => $this->mobileColumn, 'name_column' => $this->nameColumn],
            default => [],
        };
    }

    protected function audienceError(): ?string
    {
        if ($this->source !== 'single' && !$this->canBulk()) {
            return 'دسترسی ارسال گروهی ندارید.';
        }

        return match ($this->source) {
            'single' => PhoneNumber::normalize($this->singleMobile) ? null : 'شماره موبایل معتبر وارد کنید (مثل 09121234567).',
            'manual' => $this->manualStats['valid'] ? null : 'حداقل یک شماره معتبر وارد کنید.',
            'customers' => $this->selectedCount() ? null : 'حداقل یک مشتری انتخاب کنید.',
            'list' => $this->listId && SmsContactList::whereKey($this->listId)->exists() ? null : 'یک لیست انتخاب کنید.',
            'import' => $this->importToken && $this->mobileColumn !== null ? null : 'فایل مخاطبان را بارگذاری و ستون شماره را مشخص کنید.',
            default => 'منبع مخاطب نامعتبر است.',
        };
    }

    public function goTo(int $step): void
    {
        if ($step >= 2 && ($error = $this->audienceError())) {
            $this->dispatch('alert', type: 'warning', title: 'مخاطبان', text: $error);
            $this->step = 1;
            return;
        }

        if ($step >= 3 && !$this->template) {
            $this->dispatch('alert', type: 'warning', title: 'الگو', text: 'یک الگوی تأییدشده انتخاب کنید.');
            $this->step = 2;
            return;
        }

        if ($step === 3 && !$this->vars && $this->template?->placeholderCount()) {
            $this->initVars();
        }

        if ($step === 3 && !$this->template->placeholderCount()) {
            $step = $this->step === 4 ? 2 : 4; // الگو متغیر ندارد
        }

        if ($step === 4) {
            if (!$this->buildPlan()) {
                return;
            }
        }

        $this->step = max(1, min(4, $step));
    }

    protected function buildPlan(): bool
    {
        $this->resetErrorBag();

        foreach ($this->vars as $i => $var) {
            if (($var['source'] ?? 'fixed') === 'fixed' && ($error = TemplateBody::valueError($var['value'] ?? ''))) {
                $this->addError("vars.$i.value", $error);
            }
            if (($var['source'] ?? '') === 'column' && ($this->source !== 'import' || ($var['column'] ?? '') === '')) {
                $this->addError("vars.$i.column", 'ستون فایل را انتخاب کنید.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->step = 3;
            return false;
        }

        try {
            $plan = app(SmsSender::class)->prepare($this->template, $this->audience(), $this->vars);
        } catch (\RuntimeException $e) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $e->getMessage());
            return false;
        }

        // فهرست کامل گیرندگان در state کامپوننت نگه داشته نمی‌شود؛ هنگام ارسال دوباره در سرور ساخته می‌شود
        unset($plan['ready']);
        $plan['count'] = $plan['input'] - array_sum($plan['skipped']);
        $this->plan = $plan;
        $this->confirmed = false;

        return true;
    }

    public function send(): void
    {
        abort_if(!auth()->user()->can('sms.send'), 403);
        abort_if($this->source !== 'single' && !auth()->user()->can('sms.bulk'), 403);

        if ($this->batchId) {
            return; // این فرم قبلاً ارسال شده است
        }

        if (!$this->plan || !$this->confirmed) {
            $this->dispatch('alert', type: 'warning', title: 'تأیید نهایی', text: 'خلاصه ارسال را بررسی و تأیید کنید.');
            return;
        }

        if (!$this->template) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: 'الگو دیگر تأییدشده نیست.');
            $this->step = 2;
            return;
        }

        $key = 'sms-send:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->dispatch('alert', type: 'error', title: 'محدودیت', text: 'تعداد درخواست ارسال زیاد است؛ کمی بعد تلاش کنید.');
            return;
        }
        RateLimiter::hit($key, 60);

        try {
            $batch = app(SmsSender::class)->dispatch($this->template, $this->audience(), $this->vars, $this->uuid, auth()->id(), $this->sourceMeta());
        } catch (\RuntimeException $e) {
            $this->dispatch('alert', type: 'error', title: 'ارسال انجام نشد', text: $e->getMessage());
            return;
        }

        $this->batchId = $batch->id;
        $this->step = 5;
    }

    protected function sourceMeta(): array
    {
        return match ($this->source) {
            'customers' => ['filters' => CustomerFilter::describe($this->filters), 'selection' => $this->selection,
                'selected' => count($this->selectedIds), 'excluded' => count($this->excludedIds)],
            'list' => ['list_id' => $this->listId, 'list' => SmsContactList::find($this->listId)?->title],
            'import' => ['file' => $this->importName, 'rows' => $this->importCount],
            'manual' => ['lines' => count($this->manualStats['valid']) + count($this->manualStats['invalid'])],
            default => [],
        };
    }

    #[Computed]
    public function batch(): ?SmsBatch
    {
        return $this->batchId ? SmsBatch::find($this->batchId) : null;
    }

    public function cancelBatch(): void
    {
        abort_if(!auth()->user()->can('sms.bulk'), 403);

        if ($this->batch) {
            $count = app(SmsSender::class)->cancel($this->batch);
            unset($this->batch);
            $this->dispatch('alert', type: 'success', title: 'لغو شد', text: "{$count} پیامک در صف لغو شد.");
        }
    }

    public function startOver(): void
    {
        $this->reset(['step', 'singleMobile', 'singleName', 'numbers', 'selectedIds', 'excludedIds', 'listId', 'plan', 'confirmed', 'batchId', 'vars']);
        $this->selection = 'selected';
        $this->uuid = (string) Str::uuid();
        $this->initVars();
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">ارسال پیامک</h1>
            <div class="text-muted small">ارسال با الگوی تأییدشده ملی پیامک به یک شماره، چند شماره، مشتریان سایت، لیست یا فایل</div>
        </div>
    </div>

    @include('pages.dashboard.sms.partials.nav', ['active' => 'sms.send'])

    {{-- مراحل --}}
    @php
        $steps = [1 => 'انتخاب مخاطبان', 2 => 'انتخاب الگو', 3 => 'متغیرها', 4 => 'بررسی و تأیید', 5 => 'نتیجه'];
    @endphp
    <div class="card custom-card">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @foreach($steps as $n => $label)
                    <button type="button" class="btn btn-sm {{ $step === $n ? 'btn-primary' : ($step > $n ? 'btn-success-light' : 'btn-light') }}"
                            @if($n < 5 && $step < 5) wire:click="goTo({{ $n }})" @else disabled @endif>
                        <span class="badge {{ $step === $n ? 'bg-white text-primary' : 'bg-secondary-transparent' }} rounded-pill me-1">{{ $n }}</span>{{ $label }}
                    </button>
                    @if($n < 5)<i class="ri-arrow-left-s-line text-muted d-none d-md-inline"></i>@endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- ───── مرحله ۱: مخاطبان ───── --}}
    @if($step === 1)
        <div class="card custom-card">
            <div class="card-header"><div class="card-title">روش ارسال</div></div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    @foreach([
                        'single' => ['ri-user-line', 'یک شماره', 'ارسال تکی'],
                        'manual' => ['ri-list-check', 'چند شماره دستی', 'هر شماره در یک خط'],
                        'customers' => ['ri-group-line', 'مشتریان سایت', 'با فیلتر پیشرفته'],
                        'list' => ['ri-contacts-book-2-line', 'لیست ذخیره‌شده', 'لیست‌های مخاطبان'],
                        'import' => ['ri-file-excel-2-line', 'فایل Excel / CSV', 'واردکردن از فایل'],
                    ] as $key => [$icon, $title, $hint])
                        @php $locked = $key !== 'single' && !$this->canBulk(); @endphp
                        <div class="col-6 col-md">
                            <label class="border rounded p-3 d-block h-100 {{ $source === $key ? 'border-primary bg-primary-transparent' : '' }} {{ $locked ? 'opacity-50' : '' }}" role="button">
                                <input type="radio" class="d-none" wire:model.live="source" value="{{ $key }}" @disabled($locked)>
                                <i class="{{ $icon }} fs-20 d-block mb-1"></i>
                                <div class="fw-medium">{{ $title }}</div>
                                <div class="fs-11 text-muted">{{ $locked ? 'نیازمند دسترسی ارسال گروهی' : $hint }}</div>
                            </label>
                        </div>
                    @endforeach
                </div>

                @if($source === 'single')
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">شماره موبایل <span class="text-danger">*</span></label>
                            <input type="text" dir="ltr" class="form-control" wire:model.live.debounce.400ms="singleMobile" placeholder="09121234567">
                            @if($singleMobile !== '')
                                @if($normalized = PhoneNumber::normalize($singleMobile))
                                    <div class="form-text text-success">شماره استاندارد: <span dir="ltr">{{ $normalized }}</span></div>
                                @else
                                    <div class="form-text text-danger">شماره موبایل ایران معتبر نیست.</div>
                                @endif
                            @endif
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">نام گیرنده (اختیاری)</label>
                            <input type="text" class="form-control" wire:model.live.debounce.400ms="singleName" maxlength="100">
                            <div class="form-text">اگر خالی باشد از مشتری یا مخاطب ثبت‌شده با همین شماره خوانده می‌شود.</div>
                        </div>
                    </div>
                @elseif($source === 'manual')
                    <label class="form-label">شماره‌ها (هر شماره در یک خط، یا جداشده با کاما)</label>
                    <textarea rows="8" dir="ltr" class="form-control font-monospace" wire:model.live.debounce.600ms="numbers" placeholder="09121234567&#10;+989351234567&#10;۰۹۱۲۷۶۵۴۳۲۱"></textarea>
                    @php $m = $this->manualStats; @endphp
                    <div class="d-flex flex-wrap gap-2 mt-2 small">
                        <span class="badge bg-success-transparent">معتبر و یکتا: {{ count($m['valid']) }}</span>
                        <span class="badge bg-danger-transparent">نامعتبر: {{ count($m['invalid']) }}</span>
                        <span class="badge bg-warning-transparent">تکراری: {{ count($m['duplicates']) }}</span>
                    </div>
                    @if($m['invalid'])
                        <div class="small text-danger mt-2">نامعتبر: <span dir="ltr">{{ implode('، ', array_slice($m['invalid'], 0, 15)) }}</span>@if(count($m['invalid']) > 15) و {{ count($m['invalid']) - 15 }} مورد دیگر@endif</div>
                    @endif
                @elseif($source === 'customers')
                    @include('pages.dashboard.sms.partials.customer-picker')
                @elseif($source === 'list')
                    @if($this->lists->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="ri-contacts-book-2-line fs-32 d-block"></i>
                            لیستی ساخته نشده است. @can('sms.contacts')<a href="{{ route('sms.contacts') }}">ساخت لیست مخاطبان</a>@endcan
                        </div>
                    @else
                        <div class="row g-2">
                            @foreach($this->lists as $list)
                                <div class="col-md-4" wire:key="list-{{ $list->id }}">
                                    <label class="border rounded p-3 d-flex align-items-center gap-2 {{ $listId === $list->id ? 'border-primary bg-primary-transparent' : '' }}" role="button">
                                        <input type="radio" class="form-check-input mt-0" wire:model.live="listId" value="{{ $list->id }}">
                                        <div>
                                            <div class="fw-medium">{{ $list->title }}</div>
                                            <div class="fs-11 text-muted">{{ number_format($list->contacts_count) }} مخاطب</div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @elseif($source === 'import')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">فایل مخاطبان (CSV یا xlsx، حداکثر ۵ مگابایت)</label>
                            <input type="file" class="form-control @error('importFile') is-invalid @enderror" wire:model="importFile" accept=".csv,.xlsx,.txt">
                            @error('importFile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div wire:loading wire:target="importFile" class="small text-muted mt-1"><span class="spinner-border spinner-border-sm"></span> در حال خواندن فایل...</div>
                            <div class="form-text">سطر اول باید عنوان ستون‌ها باشد. ستون‌های اضافه (مثل کد پیگیری) را می‌توانید در مرحله متغیرها استفاده کنید.</div>
                        </div>
                        @if($importToken)
                            <div class="col-md-3">
                                <label class="form-label">ستون شماره موبایل</label>
                                <select class="form-select" wire:model.live="mobileColumn">
                                    @foreach($importHeaders as $i => $h)<option value="{{ $i }}">{{ $h }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ستون نام (اختیاری)</label>
                                <select class="form-select" wire:model.live="nameColumn">
                                    <option value="">—</option>
                                    @foreach($importHeaders as $i => $h)<option value="{{ $i }}">{{ $h }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    @if($importToken)
                        @php $st = $this->importStats; @endphp
                        <div class="mt-3 small">
                            <i class="ri-file-line"></i> {{ $importName }} — {{ number_format($importCount) }} سطر
                            @if($importTruncated)<span class="text-danger">(فقط {{ number_format(config('sms.max_recipients')) }} سطر اول خوانده شد)</span>@endif
                        </div>
                        @if($st)
                            <div class="d-flex flex-wrap gap-2 mt-2 small">
                                <span class="badge bg-success-transparent">معتبر و یکتا: {{ number_format($st['valid']) }}</span>
                                <span class="badge bg-danger-transparent">نامعتبر/خالی: {{ number_format($st['invalid']) }}</span>
                                <span class="badge bg-warning-transparent">تکراری: {{ number_format($st['duplicates']) }}</span>
                            </div>
                            @if($st['invalidSamples'])
                                <div class="small text-danger mt-2">
                                    نمونه سطرهای نامعتبر (برای اصلاح، فایل را ویرایش و دوباره بارگذاری کنید):
                                    @foreach($st['invalidSamples'] as $bad) <span class="badge bg-light text-danger">سطر {{ $bad['row'] }}: <span dir="ltr">{{ $bad['value'] ?: '(خالی)' }}</span></span>@endforeach
                                </div>
                            @endif
                        @endif
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered small mb-0">
                                <thead><tr>@foreach($importHeaders as $i => $h)<th class="{{ (string) $i === (string) $mobileColumn ? 'table-primary' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
                                <tbody>
                                @foreach($importPreview as $row)
                                    <tr>@foreach($row as $i => $cell)<td class="{{ (string) $i === (string) $mobileColumn && !PhoneNumber::isValid($cell) ? 'table-danger' : '' }}">{{ $cell }}</td>@endforeach</tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
                    <div>
                        @can('sms.contacts')
                            @if(in_array($source, ['customers', 'manual', 'import'], true))
                                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#saveListModal"><i class="ri-save-line"></i> ذخیره به‌عنوان لیست</button>
                            @endif
                        @endcan
                    </div>
                    <button type="button" class="btn btn-primary" wire:click="goTo(2)">مرحله بعد: انتخاب الگو <i class="ri-arrow-left-line align-middle"></i></button>
                </div>
            </div>
        </div>
    @endif

    {{-- ───── مرحله ۲: الگو ───── --}}
    @if($step === 2)
        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">انتخاب الگوی تأییدشده</div>
                <a href="{{ route('sms.templates') }}" class="small">مدیریت الگوها</a>
            </div>
            <div class="card-body">
                @if($this->templates->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="ri-file-forbid-line fs-32 d-block mb-2"></i>
                        هیچ الگوی تأییدشده‌ای وجود ندارد. ابتدا در صفحه الگوها، الگوها را از ملی پیامک دریافت و نگاشت وضعیت را تنظیم کنید.
                    </div>
                @else
                    <div class="row g-2">
                        @foreach($this->templates as $t)
                            <div class="col-md-6" wire:key="tpl-{{ $t->id }}">
                                <div class="border rounded p-3 h-100 {{ $templateId === $t->id ? 'border-primary bg-primary-transparent' : '' }}" role="button" wire:click="chooseTemplate({{ $t->id }})">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-medium">{{ $t->title }}</span>
                                        <span>
                                            @if($t->category === 'marketing')<span class="badge bg-purple-transparent">تبلیغاتی</span>@endif
                                            <span class="badge bg-light text-dark" dir="ltr">#{{ $t->provider_body_id }}</span>
                                        </span>
                                    </div>
                                    <div class="small text-muted" style="white-space: pre-wrap">{{ $t->body }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($this->template?->category === 'marketing')
                        <div class="alert alert-warning small mt-3 mb-0">این الگو تبلیغاتی است: فقط به گیرندگانی ارسال می‌شود که رضایت دریافت پیامک تبلیغاتی دارند (تنظیمات اعلان مشتری یا رضایت ثبت‌شده مخاطب) و انصراف نداده‌اند.</div>
                    @endif
                @endif
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-light" wire:click="goTo(1)"><i class="ri-arrow-right-line align-middle"></i> قبلی</button>
                    <button type="button" class="btn btn-primary" wire:click="goTo(3)" @disabled(!$this->template)>مرحله بعد <i class="ri-arrow-left-line align-middle"></i></button>
                </div>
            </div>
        </div>
    @endif

    {{-- ───── مرحله ۳: متغیرها ───── --}}
    @if($step === 3 && $this->template)
        @php $definitions = $this->template->variableDefinitions(); @endphp
        <div class="card custom-card">
            <div class="card-header"><div class="card-title">مقدار متغیرهای الگو</div></div>
            <div class="card-body">
                <div class="bg-light rounded p-3 mb-3 small" style="white-space: pre-wrap">{{ $this->template->body }}</div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th style="width: 70px">متغیر</th><th>منبع مقدار</th><th>مقدار ثابت / جایگزین برای مقدار خالی</th></tr></thead>
                        <tbody>
                        @foreach($definitions as $i => $definition)
                            <tr wire:key="v-{{ $i }}">
                                <td><code dir="ltr">{{ '{' . $i . '}' }}</code><div class="fs-11 text-muted">{{ $definition['label'] }}</div></td>
                                <td style="min-width: 220px">
                                    <select class="form-select form-select-sm" wire:model.live="vars.{{ $i }}.source">
                                        @foreach(AudienceBuilder::SOURCES as $key => $label)
                                            @continue($key === 'column' && $source !== 'import')
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @if(($vars[$i]['source'] ?? '') === 'column')
                                        <select class="form-select form-select-sm mt-1 @error('vars.' . $i . '.column') is-invalid @enderror" wire:model.live="vars.{{ $i }}.column">
                                            <option value="">انتخاب ستون...</option>
                                            @foreach($importHeaders as $ci => $h)<option value="{{ $ci }}">{{ $h }}</option>@endforeach
                                        </select>
                                        @error('vars.' . $i . '.column')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    @endif
                                </td>
                                <td style="min-width: 220px">
                                    <input type="text" class="form-control form-control-sm @error('vars.' . $i . '.value') is-invalid @enderror" wire:model.live.debounce.400ms="vars.{{ $i }}.value" maxlength="{{ TemplateBody::MAX_VALUE_LENGTH }}"
                                           placeholder="{{ ($vars[$i]['source'] ?? 'fixed') === 'fixed' ? 'مقدار برای همه گیرندگان' : 'اختیاری: اگر برای گیرنده‌ای مقدار نبود' }}">
                                    @error('vars.' . $i . '.value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted">
                    مقادیر هر گیرنده در سرور محاسبه می‌شود. گیرنده‌ای که مقدار متغیرش خالی یا نامعتبر باشد (و مقدار جایگزین نداشته باشد) ارسال نمی‌شود و در مرحله بعد گزارش می‌شود.
                    مقدار متغیر نباید لینک داشته باشد (ملی پیامک رد می‌کند).
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-light" wire:click="goTo(2)"><i class="ri-arrow-right-line align-middle"></i> قبلی</button>
                    <button type="button" class="btn btn-primary" wire:click="goTo(4)" wire:loading.attr="disabled" wire:target="goTo">
                        <span wire:loading wire:target="goTo" class="spinner-border spinner-border-sm"></span> بررسی گیرندگان و پیش‌نمایش <i class="ri-arrow-left-line align-middle"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ───── مرحله ۴: بررسی و تأیید ───── --}}
    @if($step === 4 && $plan && $this->template)
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">خلاصه ارسال</div></div>
                    <div class="card-body">
                        <div class="row g-2 text-center mb-3">
                            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="fs-20 fw-semibold text-primary">{{ number_format($plan['count']) }}</div><div class="fs-11 text-muted">گیرنده نهایی</div></div></div>
                            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="fs-20 fw-semibold">{{ number_format($plan['input']) }}</div><div class="fs-11 text-muted">ورودی اولیه</div></div></div>
                            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="fs-20 fw-semibold text-danger">{{ number_format(array_sum($plan['skipped'])) }}</div><div class="fs-11 text-muted">حذف‌شده</div></div></div>
                            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="fs-20 fw-semibold">{{ number_format($plan['count'] * $plan['parts']) }}</div><div class="fs-11 text-muted">پیامک احتمالی ({{ $plan['parts'] }} بخشی)</div></div></div>
                        </div>

                        @if(array_sum($plan['skipped']))
                            <h6 class="fw-semibold small">دلایل حذف از فهرست ارسال</h6>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach($plan['skipped'] as $reason => $count)
                                    @if($count)<span class="badge bg-danger-transparent">{{ AudienceBuilder::SKIP_REASONS[$reason] ?? $reason }}: {{ number_format($count) }}</span>@endif
                                @endforeach
                            </div>
                            <details class="mb-3">
                                <summary class="small text-primary" role="button">مشاهده موارد حذف‌شده ({{ count($plan['issues']) }}{{ array_sum($plan['skipped']) > count($plan['issues']) ? ' مورد اول' : '' }})</summary>
                                <div class="table-responsive mt-2" style="max-height: 280px">
                                    <table class="table table-sm small mb-0">
                                        <thead><tr><th>شماره / گیرنده</th><th>دلیل</th><th>جزئیات</th></tr></thead>
                                        <tbody>
                                        @foreach($plan['issues'] as $issue)
                                            <tr><td dir="ltr" class="text-end">{{ $issue['value'] }}</td><td>{{ AudienceBuilder::SKIP_REASONS[$issue['reason']] ?? $issue['reason'] }}</td><td class="text-muted">{{ $issue['detail'] }}</td></tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if(($plan['skipped']['missing_vars'] ?? 0) > 0)
                                    <div class="small text-muted mt-2">برای ارسال به این گیرندگان، در مرحله «متغیرها» مقدار جایگزین تعیین کنید یا اطلاعات مشتری/فایل را اصلاح کنید.</div>
                                @endif
                            </details>
                        @endif

                        <h6 class="fw-semibold small">پیش‌نمایش پیام برای چند گیرنده</h6>
                        @forelse($plan['samples'] as $sample)
                            <div class="border rounded p-2 mb-2">
                                <div class="fs-11 text-muted mb-1"><span dir="ltr">{{ $sample['mobile'] }}</span>@if($sample['name']) — {{ $sample['name'] }}@endif</div>
                                <div class="small" style="white-space: pre-wrap">{{ $sample['text'] }}</div>
                            </div>
                        @empty
                            <div class="alert alert-danger small">هیچ گیرنده قابل ارسالی باقی نمانده است.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">تأیید نهایی</div></div>
                    <div class="card-body">
                        <dl class="small mb-3">
                            <dt>الگو</dt><dd>{{ $this->template->title }} <span dir="ltr" class="text-muted">#{{ $this->template->provider_body_id }}</span></dd>
                            <dt>منبع مخاطبان</dt><dd>{{ SmsBatch::SOURCES[$source] ?? $source }}</dd>
                            <dt>تعداد گیرندگان</dt><dd>{{ number_format($plan['count']) }}</dd>
                            <dt>هزینه</dt>
                            <dd>
                                @if($plan['estimated_cost'] !== null)
                                    حدود {{ number_format($plan['estimated_cost']) }} ریال (تخمینی بر اساس SMS_ESTIMATED_PRICE)
                                @else
                                    <span class="text-muted">تخمین معتبر در دسترس نیست؛ هزینه واقعی در پنل ملی پیامک محاسبه می‌شود.</span>
                                @endif
                            </dd>
                        </dl>
                        @if(config('sms.sending') !== 'live')
                            <div class="alert alert-warning small">حالت شبیه‌سازی فعال است؛ پیامکی واقعاً ارسال نمی‌شود و فقط گزارش ثبت می‌شود.</div>
                        @endif
                        @if($plan['count'] > 0)
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="smsConfirm" wire:model.live="confirmed">
                                <label class="form-check-label small" for="smsConfirm">
                                    خلاصه بالا را بررسی کردم و ارسال {{ number_format($plan['count']) }} پیامک را تأیید می‌کنم.
                                </label>
                            </div>
                            <button type="button" class="btn btn-success w-100" wire:click="send" wire:loading.attr="disabled" wire:target="send" @disabled(!$confirmed)
                                    @if($plan['count'] > 1) wire:confirm="ارسال {{ number_format($plan['count']) }} پیامک شروع شود؟" @endif>
                                <span wire:loading wire:target="send" class="spinner-border spinner-border-sm"></span>
                                <i class="ri-send-plane-2-line align-middle" wire:loading.remove wire:target="send"></i> شروع ارسال
                            </button>
                        @endif
                        <button type="button" class="btn btn-light w-100 mt-2" wire:click="goTo(3)">بازگشت و اصلاح</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ───── مرحله ۵: نتیجه ───── --}}
    @if($step === 5 && $this->batch)
        @php
            $b = $this->batch;
            $counts = $b->counts();
            $done = $b->total - ($counts['queued'] ?? 0) - ($counts['sending'] ?? 0);
            $percent = $b->total ? round($done / $b->total * 100) : 100;
            $running = in_array($b->status, ['queued', 'sending'], true);
        @endphp
        <div class="card custom-card" @if($running) wire:poll.3s @endif>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">عملیات ارسال #{{ $b->id }}</h5>
                        <span class="badge bg-{{ $b->statusBadge()['color'] }}-transparent">{{ $b->statusBadge()['title'] }}</span>
                        @if($b->is_fake)<span class="badge bg-warning-transparent">شبیه‌سازی</span>@endif
                    </div>
                    <div class="d-flex gap-2">
                        @if($b->isCancellable() && $b->source !== 'single')
                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="cancelBatch" wire:confirm="ارسال پیامک‌های باقی‌مانده در صف لغو شود؟">لغو باقی‌مانده</button>
                        @endif
                        <a href="{{ route('sms.reports', ['batch' => $b->id]) }}" class="btn btn-light btn-sm"><i class="ri-file-chart-line"></i> گزارش کامل</a>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="startOver"><i class="ri-add-line"></i> ارسال جدید</button>
                    </div>
                </div>

                <div class="progress mb-2" style="height: 10px">
                    <div class="progress-bar {{ $running ? 'progress-bar-striped progress-bar-animated' : '' }}" style="width: {{ $percent }}%"></div>
                </div>
                <div class="small text-muted mb-3">{{ number_format($done) }} از {{ number_format($b->total) }} پردازش شد ({{ $percent }}٪)</div>

                @if($b->last_error)
                    <div class="alert alert-danger small">ارسال متوقف شد: {{ $b->last_error }} — پس از رفع مشکل، از صفحه گزارش «ادامه / تلاش مجدد» را بزنید.</div>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    @foreach(\App\Models\SmsMessage::STATUSES as $key => $st)
                        @if($counts[$key] ?? 0)
                            <span class="badge bg-{{ $st['color'] }}-transparent fs-12">{{ $st['title'] }}: {{ number_format($counts[$key]) }}</span>
                        @endif
                    @endforeach
                    @if($b->skipped)<span class="badge bg-light text-dark fs-12">حذف‌شده پیش از ارسال: {{ number_format($b->skipped) }}</span>@endif
                </div>
                <p class="small text-muted mt-3 mb-0">«پذیرفته‌شده توسط سرویس» یعنی ملی پیامک درخواست را پذیرفته است؛ وضعیت تحویل به گوشی به‌صورت دوره‌ای از ملی پیامک استعلام و در گزارش به‌روز می‌شود.</p>
            </div>
        </div>
    @endif

    {{-- ذخیره به‌عنوان لیست --}}
    <div wire:ignore.self class="modal fade" id="saveListModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" wire:submit="saveAsList">
                <div class="modal-header"><h6 class="modal-title">ذخیره مخاطبان به‌عنوان لیست</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">نام لیست</label>
                    <input type="text" class="form-control @error('saveListTitle') is-invalid @enderror" wire:model="saveListTitle" placeholder="مثلاً مشتریان ویژه" maxlength="100">
                    @error('saveListTitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">شماره‌های معتبر انتخاب‌شده در «مخاطبان» ذخیره می‌شوند (مخاطب تکراری ساخته نمی‌شود).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveAsList"><span wire:loading wire:target="saveAsList" class="spinner-border spinner-border-sm"></span> ذخیره</button>
                </div>
            </form>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('dashboard/libs/datepicker/jalalidatepicker.min.js') }}"></script>
        <script>jalaliDatepicker.startWatch();</script>
    @endpush
</div>
