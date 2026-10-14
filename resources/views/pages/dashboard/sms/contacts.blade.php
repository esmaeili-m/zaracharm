<?php

use App\Models\SmsContact;
use App\Models\SmsContactList;
use App\Models\SmsSuppression;
use App\Models\User;
use App\Services\Sms\AudienceBuilder;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsAudit;
use App\Services\Sms\SpreadsheetReader;
use Illuminate\Support\Facades\DB;
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

    #[Url(except: 'contacts')]
    public string $tab = 'contacts';

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'list', except: null)]
    public ?int $filterList = null;

    public array $checked = [];
    public ?int $targetList = null;

    // فرم مخاطب
    public ?int $contactId = null;
    public string $name = '';
    public string $mobile = '';
    public string $note = '';
    public bool $consent = false;
    public array $contactLists = [];

    // افزودن متنی
    public string $bulkText = '';
    public ?int $bulkList = null;
    public bool $bulkConsent = false;

    // لیست
    public ?int $listId = null;
    public string $listTitle = '';
    public string $listDescription = '';

    // مسدودی
    public string $suppressMobile = '';
    public string $suppressScope = 'all';
    public string $suppressReason = '';
    public string $suppressSearch = '';

    // واردکردن فایل
    public $file;
    public ?string $importToken = null;
    public string $importName = '';
    public array $importHeaders = [];
    public ?int $importMobile = null;
    public string $importNameCol = '';
    public string $importNoteCol = '';
    public ?int $importList = null;
    public bool $importConsent = false;
    public bool $importUpdate = false;

    #[Layout('layouts.dashboard')]
    public function mount(): void
    {
        abort_if(!auth()->user()->can('sms.contacts'), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'filterList', 'tab', 'suppressSearch'], true)) {
            $this->resetPage();
            $this->checked = [];
        }
    }

    // ───────────── مخاطبان ─────────────

    protected function contactsQuery()
    {
        $term = trim($this->search);
        $mobile = PhoneNumber::normalize($term);

        return SmsContact::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', '%' . ($mobile ?? $term) . '%')))
            ->when($this->filterList, fn ($q) => $q->whereHas('lists', fn ($l) => $l->whereKey($this->filterList)));
    }

    #[Computed]
    public function contacts()
    {
        return $this->contactsQuery()->with('lists:id,title', 'user:id,first_name,last_name')->latest('id')->paginate(25);
    }

    #[Computed]
    public function lists()
    {
        return SmsContactList::withCount('contacts')->orderBy('title')->get();
    }

    public function newContact(): void
    {
        $this->reset(['contactId', 'name', 'mobile', 'note', 'consent']);
        $this->contactLists = $this->filterList ? [(string) $this->filterList] : [];
        $this->resetValidation();
    }

    public function editContact(int $id): void
    {
        $contact = SmsContact::with('lists:id')->findOrFail($id);
        $this->contactId = $contact->id;
        $this->name = (string) $contact->name;
        $this->mobile = $contact->mobile;
        $this->note = (string) $contact->note;
        $this->consent = $contact->marketing_consent;
        $this->contactLists = $contact->lists->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->resetValidation();
    }

    public function saveContact(): void
    {
        $this->validate([
            'name' => 'nullable|string|max:100',
            'mobile' => 'required|string|max:20',
            'note' => 'nullable|string|max:255',
            'contactLists' => 'array',
            'contactLists.*' => 'exists:sms_contact_lists,id',
        ], ['mobile.required' => 'شماره موبایل را وارد کنید.']);

        $normalized = PhoneNumber::normalize($this->mobile);

        if (!$normalized) {
            $this->addError('mobile', 'شماره موبایل ایران معتبر نیست (مثل 09121234567).');
            return;
        }

        if (SmsContact::where('mobile', $normalized)->when($this->contactId, fn ($q) => $q->whereKeyNot($this->contactId))->exists()) {
            $this->addError('mobile', 'مخاطبی با این شماره وجود دارد.');
            return;
        }

        $contact = SmsContact::updateOrCreate(['id' => $this->contactId], [
            'name' => trim($this->name) ?: null,
            'mobile' => $normalized,
            'note' => trim($this->note) ?: null,
            'marketing_consent' => $this->consent,
            // اتصال اختیاری به حساب مشتری با همین شماره
            'user_id' => User::where('mobile', $normalized)->value('id'),
        ]);
        $contact->lists()->sync(array_map('intval', $this->contactLists));

        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'ذخیره شد', text: 'مخاطب ذخیره شد.');
        unset($this->contacts, $this->lists);
    }

    public function deleteContact(int $id): void
    {
        $contact = SmsContact::findOrFail($id);
        SmsAudit::log('contacts.delete', $contact, ['mobile' => PhoneNumber::mask($contact->mobile)]);
        $contact->delete();
        $this->checked = array_values(array_diff($this->checked, [(string) $id]));
        unset($this->contacts, $this->lists);
    }

    public function toggleAll(): void
    {
        $ids = $this->contacts->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->checked = count(array_intersect($ids, $this->checked)) === count($ids) ? [] : $ids;
    }

    /** عملیات گروهی روی مخاطبان علامت‌خورده: add | remove | move | delete */
    public function bulkAction(string $action): void
    {
        $ids = array_map('intval', $this->checked);

        if (!$ids) {
            return;
        }

        if (in_array($action, ['add', 'move'], true) && !SmsContactList::whereKey($this->targetList)->exists()) {
            $this->dispatch('alert', type: 'warning', title: 'لیست مقصد', text: 'لیست مقصد را انتخاب کنید.');
            return;
        }

        DB::transaction(function () use ($action, $ids) {
            $now = now();
            $attach = fn (int $listId) => DB::table('sms_contact_list_members')->insertOrIgnore(array_map(fn ($id) => [
                'sms_contact_list_id' => $listId, 'sms_contact_id' => $id, 'created_at' => $now, 'updated_at' => $now,
            ], $ids));
            $detach = fn (int $listId) => DB::table('sms_contact_list_members')
                ->where('sms_contact_list_id', $listId)->whereIn('sms_contact_id', $ids)->delete();

            switch ($action) {
                case 'add':
                    $attach($this->targetList);
                    break;
                case 'remove':
                    if ($this->filterList) {
                        $detach($this->filterList);
                    }
                    break;
                case 'move':
                    if ($this->filterList && $this->filterList !== $this->targetList) {
                        $attach($this->targetList);
                        $detach($this->filterList);
                    }
                    break;
                case 'delete':
                    SmsContact::whereIn('id', $ids)->delete();
                    SmsAudit::log('contacts.delete', null, ['count' => count($ids)]);
                    break;
            }
        });

        $this->checked = [];
        unset($this->contacts, $this->lists);
        $this->dispatch('alert', type: 'success', title: 'انجام شد', text: 'عملیات روی ' . count($ids) . ' مخاطب انجام شد.');
    }

    // ───────────── افزودن متنی ─────────────

    /**
     * هر خط: شماره یا «شماره، نام»
     *
     * @return array{rows: array<string, ?string>, invalid: list<string>, duplicates: int, existing: int}
     */
    #[Computed]
    public function bulkPreview(): array
    {
        $rows = [];
        $invalid = [];
        $duplicates = 0;

        foreach (preg_split('/\r\n|\r|\n/', $this->bulkText) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', preg_split('/[,،;\t]/u', $line, 2));
            $mobile = PhoneNumber::normalize($parts[0]);

            if (!$mobile) {
                $invalid[] = trim($line);
            } elseif (array_key_exists($mobile, $rows)) {
                $duplicates++;
            } else {
                $rows[$mobile] = ($parts[1] ?? '') !== '' ? mb_substr($parts[1], 0, 100) : null;
            }
        }

        $existing = $rows ? SmsContact::whereIn('mobile', array_keys($rows))->count() : 0;

        return compact('rows', 'invalid', 'duplicates', 'existing');
    }

    public function saveBulk(): void
    {
        $preview = $this->bulkPreview;

        if (!$preview['rows']) {
            $this->addError('bulkText', 'هیچ شماره معتبری وارد نشده است.');
            return;
        }

        $created = $this->upsertContacts(
            collect($preview['rows'])->map(fn ($name, $mobile) => ['mobile' => $mobile, 'name' => $name, 'note' => null])->values()->all(),
            $this->bulkList, $this->bulkConsent, false
        );

        $this->reset(['bulkText', 'bulkConsent']);
        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'ذخیره شد', text: "{$created['created']} مخاطب جدید؛ {$created['existing']} شماره از قبل وجود داشت.");
        unset($this->contacts, $this->lists, $this->bulkPreview);
    }

    /**
     * @param list<array{mobile: string, name: ?string, note: ?string}> $rows شماره‌های استاندارد و یکتا
     * @return array{created: int, existing: int, updated: int}
     */
    protected function upsertContacts(array $rows, ?int $listId, bool $consent, bool $update): array
    {
        $stats = ['created' => 0, 'existing' => 0, 'updated' => 0];

        DB::transaction(function () use ($rows, $listId, $consent, $update, &$stats) {
            foreach (array_chunk($rows, 500) as $chunk) {
                $mobiles = array_column($chunk, 'mobile');
                $existing = SmsContact::whereIn('mobile', $mobiles)->get()->keyBy('mobile');
                $users = User::whereIn('mobile', $mobiles)->pluck('id', 'mobile');
                $ids = [];

                foreach ($chunk as $row) {
                    if ($contact = $existing->get($row['mobile'])) {
                        $stats['existing']++;
                        if ($update) {
                            $contact->update(array_filter([
                                'name' => $row['name'], 'note' => $row['note'],
                                'marketing_consent' => $consent ?: null,
                            ], fn ($v) => $v !== null && $v !== ''));
                            $stats['updated']++;
                        }
                    } else {
                        $contact = SmsContact::create([
                            'mobile' => $row['mobile'], 'name' => $row['name'], 'note' => $row['note'],
                            'marketing_consent' => $consent, 'user_id' => $users[$row['mobile']] ?? null,
                        ]);
                        $stats['created']++;
                    }
                    $ids[] = $contact->id;
                }

                if ($listId && SmsContactList::whereKey($listId)->exists()) {
                    SmsContactList::find($listId)->contacts()->syncWithoutDetaching($ids);
                }
            }
        });

        return $stats;
    }

    // ───────────── لیست‌ها ─────────────

    public function editList(?int $id = null): void
    {
        $list = $id ? SmsContactList::findOrFail($id) : null;
        $this->listId = $list?->id;
        $this->listTitle = (string) $list?->title;
        $this->listDescription = (string) $list?->description;
        $this->resetValidation();
    }

    public function saveList(): void
    {
        $this->validate([
            'listTitle' => 'required|string|max:100',
            'listDescription' => 'nullable|string|max:255',
        ], ['listTitle.required' => 'نام لیست را وارد کنید.']);

        SmsContactList::updateOrCreate(['id' => $this->listId], ['title' => trim($this->listTitle), 'description' => trim($this->listDescription) ?: null]);

        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'ذخیره شد', text: 'لیست ذخیره شد.');
        unset($this->lists);
    }

    /** حذف لیست (مخاطبان حذف نمی‌شوند) */
    public function deleteList(int $id): void
    {
        SmsContactList::findOrFail($id)->delete();
        if ($this->filterList === $id) {
            $this->filterList = null;
        }
        unset($this->lists, $this->contacts);
    }

    // ───────────── مسدودی / انصراف ─────────────

    #[Computed]
    public function suppressions()
    {
        $term = trim($this->suppressSearch);

        return SmsSuppression::with('creator:id,first_name,last_name')
            ->when($term !== '', fn ($q) => $q->where('mobile', 'like', '%' . (PhoneNumber::normalize($term) ?? $term) . '%')->orWhere('reason', 'like', "%{$term}%"))
            ->latest('id')->paginate(25);
    }

    public function addSuppression(): void
    {
        $this->validate([
            'suppressMobile' => 'required|string',
            'suppressScope' => 'required|in:all,marketing',
            'suppressReason' => 'nullable|string|max:255',
        ], ['suppressMobile.required' => 'شماره را وارد کنید.']);

        $parsed = PhoneNumber::parseList($this->suppressMobile);

        if (!$parsed['valid']) {
            $this->addError('suppressMobile', 'شماره معتبری وارد نشده است.');
            return;
        }

        foreach ($parsed['valid'] as $mobile) {
            SmsSuppression::updateOrCreate(['mobile' => $mobile], [
                'scope' => $this->suppressScope, 'reason' => trim($this->suppressReason) ?: null, 'created_by' => auth()->id(),
            ]);
        }

        SmsAudit::log('suppression.add', null, ['count' => count($parsed['valid']), 'scope' => $this->suppressScope]);
        $this->reset(['suppressMobile', 'suppressReason']);
        unset($this->suppressions);
        $this->dispatch('alert', type: 'success', title: 'ثبت شد', text: count($parsed['valid']) . ' شماره به فهرست مسدود اضافه شد.'
            . ($parsed['invalid'] ? ' (' . count($parsed['invalid']) . ' مورد نامعتبر نادیده گرفته شد)' : ''));
    }

    public function removeSuppression(int $id): void
    {
        $item = SmsSuppression::findOrFail($id);
        SmsAudit::log('suppression.remove', null, ['mobile' => PhoneNumber::mask($item->mobile)]);
        $item->delete();
        unset($this->suppressions);
    }

    // ───────────── واردکردن فایل ─────────────

    public function updatedFile(): void
    {
        $this->validate(['file' => 'required|file|max:5120|mimes:csv,txt,xlsx'], [
            'file.mimes' => 'فقط فایل CSV یا Excel (xlsx) مجاز است.',
            'file.max' => 'حجم فایل حداکثر ۵ مگابایت است.',
        ]);

        try {
            $data = SpreadsheetReader::read($this->file->getRealPath(), $this->file->getClientOriginalExtension(), (int) config('sms.max_recipients', 20000));
        } catch (\RuntimeException $e) {
            $this->addError('file', $e->getMessage());
            return;
        }

        $this->importToken = AudienceBuilder::storeImport(['headers' => $data['headers'], 'rows' => $data['rows']]);
        $this->importName = $this->file->getClientOriginalName();
        $this->importHeaders = $data['headers'];
        $this->importMobile = 0;
        $this->importNameCol = '';
        $this->importNoteCol = '';

        foreach ($data['headers'] as $i => $header) {
            if (preg_match('/(موبایل|همراه|شماره|تلفن|mobile|phone|tel)/iu', $header)) {
                $this->importMobile = $i;
                break;
            }
        }
        foreach ($data['headers'] as $i => $header) {
            if ($i !== $this->importMobile && preg_match('/(نام|name)/iu', $header)) {
                $this->importNameCol = (string) $i;
                break;
            }
        }

        $this->file = null;
        unset($this->importAnalysis);
    }

    /** تحلیل فایل پیش از ثبت: معتبر، نامعتبر (با شماره سطر)، تکراری، موجود */
    #[Computed]
    public function importAnalysis(): ?array
    {
        if (!$this->importToken || $this->importMobile === null) {
            return null;
        }

        try {
            $data = AudienceBuilder::importData($this->importToken);
        } catch (\RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }

        $valid = [];
        $errors = [];
        $duplicates = 0;

        foreach ($data['rows'] as $i => $row) {
            $raw = $row[$this->importMobile] ?? '';
            $mobile = PhoneNumber::normalize($raw);

            if (!$mobile) {
                $errors[] = ['row' => $i + 2, 'value' => $raw, 'error' => $raw === '' ? 'شماره خالی' : 'شماره نامعتبر'];
            } elseif (isset($valid[$mobile])) {
                $duplicates++;
            } else {
                $valid[$mobile] = [
                    'mobile' => $mobile,
                    'name' => $this->importNameCol !== '' ? (mb_substr(trim($row[(int) $this->importNameCol] ?? ''), 0, 100) ?: null) : null,
                    'note' => $this->importNoteCol !== '' ? (mb_substr(trim($row[(int) $this->importNoteCol] ?? ''), 0, 255) ?: null) : null,
                ];
            }
        }

        $existing = 0;
        foreach (array_chunk(array_keys($valid), 1000) as $chunk) {
            $existing += SmsContact::whereIn('mobile', $chunk)->count();
        }

        return [
            'total' => count($data['rows']),
            'valid' => count($valid),
            'duplicates' => $duplicates,
            'existing' => $existing,
            'errors' => $errors,
            'preview' => array_slice(array_values($valid), 0, 8),
        ];
    }

    public function import(): void
    {
        $analysis = $this->importAnalysis;

        if (!$analysis || isset($analysis['error']) || !$analysis['valid']) {
            $this->dispatch('alert', type: 'error', title: 'خطا', text: $analysis['error'] ?? 'شماره معتبری در فایل نیست.');
            return;
        }

        // ساخت دوباره ردیف‌ها از داده سرور
        $data = AudienceBuilder::importData($this->importToken);
        $rows = [];
        foreach ($data['rows'] as $row) {
            if ($mobile = PhoneNumber::normalize($row[$this->importMobile] ?? '')) {
                $rows[$mobile] ??= [
                    'mobile' => $mobile,
                    'name' => $this->importNameCol !== '' ? (mb_substr(trim($row[(int) $this->importNameCol] ?? ''), 0, 100) ?: null) : null,
                    'note' => $this->importNoteCol !== '' ? (mb_substr(trim($row[(int) $this->importNoteCol] ?? ''), 0, 255) ?: null) : null,
                ];
            }
        }

        $stats = $this->upsertContacts(array_values($rows), $this->importList, $this->importConsent, $this->importUpdate);
        SmsAudit::log('contacts.import', null, $stats + ['file' => $this->importName, 'invalid' => count($analysis['errors']), 'list_id' => $this->importList]);

        $this->reset(['importToken', 'importName', 'importHeaders', 'importMobile', 'importNameCol', 'importNoteCol', 'importConsent', 'importUpdate']);
        unset($this->importAnalysis, $this->lists, $this->contacts);
        $this->dispatch('alert', type: 'success', title: 'واردکردن انجام شد',
            text: "{$stats['created']} مخاطب جدید، {$stats['existing']} موجود" . ($stats['updated'] ? " ({$stats['updated']} به‌روزرسانی)" : '') . '.');
    }

    // ───────────── خروجی ─────────────

    public function export()
    {
        abort_if(!auth()->user()->can('sms.export'), 403);

        $query = $this->contactsQuery()->with('lists:id,title');
        SmsAudit::log('contacts.export', null, ['count' => (clone $query)->count(), 'list_id' => $this->filterList, 'search' => $this->search]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM برای نمایش درست فارسی در Excel
            fputcsv($out, ['موبایل', 'نام', 'توضیحات', 'رضایت تبلیغاتی', 'لیست‌ها', 'تاریخ ایجاد']);
            $query->orderBy('id')->chunk(1000, function ($contacts) use ($out) {
                foreach ($contacts as $c) {
                    fputcsv($out, [$c->mobile, $c->name, $c->note, $c->marketing_consent ? 'بله' : 'خیر',
                        $c->lists->pluck('title')->implode(' | '), verta($c->created_at)->format('Y/m/d')]);
                }
            });
            fclose($out);
        }, 'sms-contacts-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-1">مخاطبان و لیست‌ها</h1>
            <div class="text-muted small">مخاطبان دستی (جدا از حساب مشتریان)، لیست‌ها، واردکردن فایل و فهرست مسدود</div>
        </div>
    </div>

    @include('pages.dashboard.sms.partials.nav', ['active' => 'sms.contacts'])

    <ul class="nav nav-tabs mb-3">
        @foreach(['contacts' => ['مخاطبان', 'ri-user-3-line'], 'lists' => ['لیست‌ها', 'ri-list-unordered'], 'import' => ['واردکردن فایل', 'ri-file-upload-line'], 'suppressions' => ['مسدود و انصراف', 'ri-forbid-line']] as $key => [$label, $icon])
            <li class="nav-item"><a href="javascript:void(0)" class="nav-link {{ $tab === $key ? 'active' : '' }}" wire:click="$set('tab', '{{ $key }}')"><i class="{{ $icon }} me-1"></i>{{ $label }}</a></li>
        @endforeach
    </ul>

    {{-- ───── مخاطبان ───── --}}
    @if($tab === 'contacts')
        <div class="card custom-card">
            <div class="card-body">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4"><input type="text" class="form-control form-control-sm" wire:model.live.debounce.400ms="search" placeholder="جستجوی نام یا شماره"></div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" wire:model.live="filterList">
                            <option value="">همه مخاطبان</option>
                            @foreach($this->lists as $list)<option value="{{ $list->id }}">{{ $list->title }} ({{ $list->contacts_count }})</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-5 d-flex gap-2 justify-content-md-end flex-wrap">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal" wire:click="newContact"><i class="ri-add-line"></i> مخاطب جدید</button>
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#bulkModal" wire:click="$set('bulkList', {{ $filterList ?? 'null' }})"><i class="ri-list-check"></i> افزودن چند شماره</button>
                        @can('sms.export')
                            <button type="button" class="btn btn-sm btn-light" wire:click="export"><i class="ri-download-2-line"></i> خروجی CSV</button>
                        @endcan
                    </div>
                </div>

                @if($checked)
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3 p-2 bg-light rounded">
                        <span class="small">{{ count($checked) }} مورد انتخاب شده</span>
                        <select class="form-select form-select-sm w-auto" wire:model="targetList">
                            <option value="">لیست مقصد...</option>
                            @foreach($this->lists as $list)<option value="{{ $list->id }}">{{ $list->title }}</option>@endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-light" wire:click="bulkAction('add')">افزودن به لیست</button>
                        @if($filterList)
                            <button type="button" class="btn btn-sm btn-light" wire:click="bulkAction('move')">انتقال به لیست</button>
                            <button type="button" class="btn btn-sm btn-light" wire:click="bulkAction('remove')" wire:confirm="از این لیست حذف شوند؟ (مخاطب حذف نمی‌شود)">حذف از این لیست</button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="bulkAction('delete')" wire:confirm="{{ count($checked) }} مخاطب حذف شوند؟">حذف مخاطبان</button>
                    </div>
                @endif

                <div class="table-responsive mt-3">
                    <table class="table table-hover align-middle text-nowrap">
                        <thead><tr>
                            <th style="width: 36px"><input type="checkbox" class="form-check-input" wire:click="toggleAll" @checked($this->contacts->count() && count(array_intersect($this->contacts->pluck('id')->map(fn ($i) => (string) $i)->all(), $checked)) === $this->contacts->count())></th>
                            <th>نام</th><th>موبایل</th><th>لیست‌ها</th><th>رضایت تبلیغاتی</th><th>مشتری سایت</th><th>توضیحات</th><th></th>
                        </tr></thead>
                        <tbody>
                        @forelse($this->contacts as $contact)
                            <tr wire:key="ct-{{ $contact->id }}">
                                <td><input type="checkbox" class="form-check-input" wire:model.live="checked" value="{{ $contact->id }}"></td>
                                <td>{{ $contact->name ?: '—' }}</td>
                                <td dir="ltr" class="text-end">{{ $contact->mobile }}</td>
                                <td>@foreach($contact->lists as $l)<span class="badge bg-light text-dark me-1">{{ $l->title }}</span>@endforeach</td>
                                <td>@if($contact->marketing_consent)<i class="ri-checkbox-circle-fill text-success"></i>@else<i class="ri-close-circle-line text-muted"></i>@endif</td>
                                <td class="small">{{ $contact->user ? trim($contact->user->first_name . ' ' . $contact->user->last_name) ?: 'بله' : '—' }}</td>
                                <td class="small text-muted text-truncate" style="max-width: 180px">{{ $contact->note }}</td>
                                <td>
                                    <div class="hstack gap-2">
                                        <a href="#contactModal" data-bs-toggle="modal" wire:click="editContact({{ $contact->id }})" class="text-primary"><i class="ri-edit-line"></i></a>
                                        <a href="javascript:void(0)" class="text-danger" wire:click="deleteContact({{ $contact->id }})" wire:confirm="مخاطب {{ $contact->mobile }} حذف شود؟"><i class="ri-delete-bin-6-line"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-5"><i class="ri-contacts-book-2-line fs-32 d-block mb-2"></i>مخاطبی یافت نشد.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $this->contacts->links() }}
            </div>
        </div>
    @endif

    {{-- ───── لیست‌ها ───── --}}
    @if($tab === 'lists')
        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">لیست‌های مخاطبان</div>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#listModal" wire:click="editList"><i class="ri-add-line"></i> لیست جدید</button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @forelse($this->lists as $list)
                        <div class="col-md-4" wire:key="l-{{ $list->id }}">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between">
                                    <div class="fw-medium">{{ $list->title }}</div>
                                    <div class="hstack gap-2">
                                        <a href="#listModal" data-bs-toggle="modal" wire:click="editList({{ $list->id }})" class="text-primary"><i class="ri-edit-line"></i></a>
                                        <a href="javascript:void(0)" class="text-danger" wire:click="deleteList({{ $list->id }})" wire:confirm="لیست «{{ $list->title }}» حذف شود؟ مخاطبان حذف نمی‌شوند."><i class="ri-delete-bin-6-line"></i></a>
                                    </div>
                                </div>
                                @if($list->description)<div class="small text-muted">{{ $list->description }}</div>@endif
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="badge bg-primary-transparent">{{ number_format($list->contacts_count) }} مخاطب</span>
                                    <a href="javascript:void(0)" class="small" wire:click="$set('filterList', {{ $list->id }}); $set('tab', 'contacts')">مشاهده مخاطبان</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted py-5"><i class="ri-list-unordered fs-32 d-block mb-2"></i>لیستی ساخته نشده است؛ مثلاً «مشتریان ویژه» بسازید.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- ───── واردکردن فایل ───── --}}
    @if($tab === 'import')
        <div class="card custom-card">
            <div class="card-header"><div class="card-title">واردکردن مخاطبان از Excel یا CSV</div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">فایل (csv یا xlsx، حداکثر ۵ مگابایت؛ سطر اول عنوان ستون‌ها)</label>
                        <input type="file" class="form-control @error('file') is-invalid @enderror" wire:model="file" accept=".csv,.xlsx,.txt">
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div wire:loading wire:target="file" class="small text-muted mt-1"><span class="spinner-border spinner-border-sm"></span> در حال خواندن فایل...</div>
                    </div>
                    @if($importToken)
                        <div class="col-md-6 small align-self-end"><i class="ri-file-line"></i> {{ $importName }}</div>
                        <div class="col-md-3">
                            <label class="form-label">ستون موبایل</label>
                            <select class="form-select form-select-sm" wire:model.live="importMobile">
                                @foreach($importHeaders as $i => $h)<option value="{{ $i }}">{{ $h }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ستون نام</label>
                            <select class="form-select form-select-sm" wire:model.live="importNameCol">
                                <option value="">—</option>
                                @foreach($importHeaders as $i => $h)<option value="{{ $i }}">{{ $h }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ستون توضیحات</label>
                            <select class="form-select form-select-sm" wire:model.live="importNoteCol">
                                <option value="">—</option>
                                @foreach($importHeaders as $i => $h)<option value="{{ $i }}">{{ $h }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">افزودن به لیست</label>
                            <select class="form-select form-select-sm" wire:model="importList">
                                <option value="">بدون لیست</option>
                                @foreach($this->lists as $list)<option value="{{ $list->id }}">{{ $list->title }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-4">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="impUpd" wire:model="importUpdate"><label class="form-check-label small" for="impUpd">نام/توضیحات مخاطبان موجود به‌روزرسانی شود</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="impCons" wire:model="importConsent"><label class="form-check-label small" for="impCons">این مخاطبان رضایت دریافت پیامک تبلیغاتی داده‌اند</label></div>
                        </div>
                    @endif
                </div>

                @if($importToken && ($a = $this->importAnalysis))
                    @if(isset($a['error']))
                        <div class="alert alert-danger mt-3">{{ $a['error'] }}</div>
                    @else
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="badge bg-light text-dark">کل سطرها: {{ number_format($a['total']) }}</span>
                            <span class="badge bg-success-transparent">معتبر و یکتا: {{ number_format($a['valid']) }}</span>
                            <span class="badge bg-info-transparent">از قبل موجود: {{ number_format($a['existing']) }}</span>
                            <span class="badge bg-warning-transparent">تکراری در فایل: {{ number_format($a['duplicates']) }}</span>
                            <span class="badge bg-danger-transparent">خطادار: {{ number_format(count($a['errors'])) }}</span>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <h6 class="small fw-semibold">پیش‌نمایش مخاطبان معتبر</h6>
                                <table class="table table-sm small">
                                    <thead><tr><th>موبایل</th><th>نام</th><th>توضیحات</th></tr></thead>
                                    <tbody>@foreach($a['preview'] as $row)<tr><td dir="ltr" class="text-end">{{ $row['mobile'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['note'] }}</td></tr>@endforeach</tbody>
                                </table>
                            </div>
                            @if($a['errors'])
                                <div class="col-md-6">
                                    <h6 class="small fw-semibold text-danger">سطرهای خطادار (ثبت نمی‌شوند؛ برای اصلاح، فایل را ویرایش و دوباره بارگذاری کنید)</h6>
                                    <div style="max-height: 240px; overflow: auto">
                                        <table class="table table-sm small">
                                            <thead><tr><th>سطر</th><th>مقدار</th><th>خطا</th></tr></thead>
                                            <tbody>@foreach(array_slice($a['errors'], 0, 200) as $e)<tr><td>{{ $e['row'] }}</td><td dir="ltr" class="text-end">{{ $e['value'] }}</td><td>{{ $e['error'] }}</td></tr>@endforeach</tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <button type="button" class="btn btn-primary" wire:click="import" wire:loading.attr="disabled" wire:target="import" @disabled(!$a['valid'])
                                wire:confirm="{{ number_format($a['valid']) }} شماره معتبر ثبت شود؟">
                            <span wire:loading wire:target="import" class="spinner-border spinner-border-sm"></span> ثبت نهایی {{ number_format($a['valid']) }} مخاطب
                        </button>
                    @endif
                @endif
            </div>
        </div>
    @endif

    {{-- ───── مسدود و انصراف ───── --}}
    @if($tab === 'suppressions')
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">افزودن به فهرست مسدود</div></div>
                    <div class="card-body">
                        <form wire:submit="addSuppression">
                            <label class="form-label">شماره(ها)</label>
                            <textarea rows="3" dir="ltr" class="form-control @error('suppressMobile') is-invalid @enderror" wire:model="suppressMobile" placeholder="هر شماره در یک خط"></textarea>
                            @error('suppressMobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <label class="form-label mt-2">دامنه</label>
                            <select class="form-select" wire:model="suppressScope">
                                @foreach(SmsSuppression::SCOPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                            <label class="form-label mt-2">دلیل</label>
                            <input type="text" class="form-control" wire:model="suppressReason" maxlength="255" placeholder="مثلاً درخواست لغو اشتراک">
                            <button class="btn btn-primary w-100 mt-3" type="submit">ثبت</button>
                        </form>
                        <p class="small text-muted mt-3 mb-0">«همه پیامک‌ها»: هیچ پیامکی از این بخش ارسال نمی‌شود. «فقط تبلیغاتی»: پیامک خدماتی (مثل اطلاع‌رسانی سفارش) ارسال می‌شود.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card custom-card">
                    <div class="card-body">
                        <input type="text" class="form-control form-control-sm mb-3" wire:model.live.debounce.400ms="suppressSearch" placeholder="جستجوی شماره یا دلیل">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle text-nowrap">
                                <thead><tr><th>موبایل</th><th>دامنه</th><th>دلیل</th><th>ثبت‌کننده</th><th>تاریخ</th><th></th></tr></thead>
                                <tbody>
                                @forelse($this->suppressions as $item)
                                    <tr wire:key="s-{{ $item->id }}">
                                        <td dir="ltr" class="text-end">{{ $item->mobile }}</td>
                                        <td><span class="badge {{ $item->scope === 'all' ? 'bg-danger-transparent' : 'bg-warning-transparent' }}">{{ SmsSuppression::SCOPES[$item->scope] ?? $item->scope }}</span></td>
                                        <td class="small text-wrap">{{ $item->reason }}</td>
                                        <td class="small">{{ $item->creator ? trim($item->creator->first_name . ' ' . $item->creator->last_name) : '—' }}</td>
                                        <td class="small text-muted">{{ verta($item->created_at)->format('Y/m/d') }}</td>
                                        <td><a href="javascript:void(0)" class="text-danger" wire:click="removeSuppression({{ $item->id }})" wire:confirm="شماره از فهرست مسدود خارج شود؟"><i class="ri-delete-bin-6-line"></i></a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">فهرست مسدود خالی است.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        {{ $this->suppressions->links() }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- مودال مخاطب --}}
    <div wire:ignore.self class="modal fade" id="contactModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" wire:submit="saveContact">
                <div class="modal-header"><h6 class="modal-title">{{ $contactId ? 'ویرایش مخاطب' : 'مخاطب جدید' }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">شماره موبایل <span class="text-danger">*</span></label>
                        <input type="text" dir="ltr" class="form-control @error('mobile') is-invalid @enderror" wire:model="mobile" placeholder="09121234567">
                        @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2"><label class="form-label">نام</label><input type="text" class="form-control" wire:model="name" maxlength="100"></div>
                    <div class="mb-2"><label class="form-label">توضیحات</label><input type="text" class="form-control" wire:model="note" maxlength="255"></div>
                    <div class="mb-2">
                        <label class="form-label">لیست‌ها</label>
                        <div class="d-flex flex-wrap gap-3">
                            @forelse($this->lists as $list)
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="cl-{{ $list->id }}" value="{{ $list->id }}" wire:model="contactLists"><label class="form-check-label" for="cl-{{ $list->id }}">{{ $list->title }}</label></div>
                            @empty
                                <span class="small text-muted">لیستی وجود ندارد.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="consent" wire:model="consent"><label class="form-check-label" for="consent">رضایت دریافت پیامک تبلیغاتی دارد</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button><button type="submit" class="btn btn-primary">ذخیره</button></div>
            </form>
        </div>
    </div>

    {{-- مودال افزودن چند شماره --}}
    <div wire:ignore.self class="modal fade" id="bulkModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" wire:submit="saveBulk">
                <div class="modal-header"><h6 class="modal-title">افزودن چند شماره</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">هر خط یک شماره؛ برای نام: «شماره، نام»</label>
                    <textarea rows="8" class="form-control font-monospace @error('bulkText') is-invalid @enderror" wire:model.live.debounce.600ms="bulkText" placeholder="09121234567، علی رضایی&#10;09351234567"></textarea>
                    @error('bulkText')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if(trim($bulkText) !== '')
                        @php $p = $this->bulkPreview; @endphp
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="badge bg-success-transparent">معتبر و یکتا: {{ count($p['rows']) }}</span>
                            <span class="badge bg-info-transparent">از قبل موجود: {{ $p['existing'] }}</span>
                            <span class="badge bg-warning-transparent">تکراری: {{ $p['duplicates'] }}</span>
                            <span class="badge bg-danger-transparent">نامعتبر: {{ count($p['invalid']) }}</span>
                        </div>
                        @if($p['invalid'])<div class="small text-danger mt-2">نامعتبر (ذخیره نمی‌شوند): {{ implode('، ', array_slice($p['invalid'], 0, 10)) }}</div>@endif
                    @endif
                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">افزودن به لیست</label>
                            <select class="form-select" wire:model="bulkList">
                                <option value="">بدون لیست</option>
                                @foreach($this->lists as $list)<option value="{{ $list->id }}">{{ $list->title }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="bulkConsent" wire:model="bulkConsent"><label class="form-check-label small" for="bulkConsent">رضایت دریافت پیامک تبلیغاتی دارند</label></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button><button type="submit" class="btn btn-primary">ذخیره شماره‌های معتبر</button></div>
            </form>
        </div>
    </div>

    {{-- مودال لیست --}}
    <div wire:ignore.self class="modal fade" id="listModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" wire:submit="saveList">
                <div class="modal-header"><h6 class="modal-title">{{ $listId ? 'ویرایش لیست' : 'لیست جدید' }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">نام لیست <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('listTitle') is-invalid @enderror" wire:model="listTitle" maxlength="100" placeholder="مثلاً مشتریان ویژه">
                    @error('listTitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <label class="form-label mt-2">توضیحات</label>
                    <input type="text" class="form-control" wire:model="listDescription" maxlength="255">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button><button type="submit" class="btn btn-primary">ذخیره</button></div>
            </form>
        </div>
    </div>
</div>
