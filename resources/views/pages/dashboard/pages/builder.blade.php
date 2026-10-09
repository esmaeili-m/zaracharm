<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Page;
use App\Models\PageRow;
use App\Models\RowSection;
use App\Models\Section;
use App\Support\Sections\Catalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * صفحه‌ساز بصری (Drag & Drop)
 *  - کشیدن سکشن از پالت به ردیف‌ها یا «ردیف جدید»
 *  - جابه‌جایی ردیف‌ها و سکشن‌ها (داخل ردیف و بین ردیف‌ها)
 *  - تنظیم عرض برای دسکتاپ / تبلت / موبایل، نمایش/مخفی، کپی، حذف
 *  - فرم محتوای هر سکشن همان فرم صفحه قدیمی است (page-section در حالت editor)
 *
 * ترتیب‌ها همیشه در سرور بازنویسی می‌شوند؛ جابه‌جایی DOM توسط SortableJS بلافاصله برگردانده
 * می‌شود و Livewire صفحه را با ترتیب جدید رندر می‌کند (تداخل Sortable و morph پیش نمی‌آید).
 */
new class extends Component
{
    use \App\Traits\FileUploadTrait;

    public Page $page;
    public string $device = 'lg'; // lg = دسکتاپ | md = تبلت | default = موبایل
    public string $paletteSearch = '';

    // فرم تنظیمات ردیف
    public ?int $rowId = null;
    public array $rowForm = [];

    public const DEVICES = [
        'lg' => ['title' => 'دسکتاپ', 'icon' => 'ri-computer-line', 'width' => '100%'],
        'md' => ['title' => 'تبلت', 'icon' => 'ri-tablet-line', 'width' => '820px'],
        'default' => ['title' => 'موبایل', 'icon' => 'ri-smartphone-line', 'width' => '400px'],
    ];

    public const WIDTHS = [12 => 'کامل', 9 => '۳/۴', 8 => '۲/۳', 6 => '۱/۲', 4 => '۱/۳', 3 => '۱/۴'];

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount(Page $page)
    {
        abort_if(!auth()->user()->can('sections.view'), 403);
        $this->page = $page;
    }

    // ------------------------------------------------------------------ data

    #[Computed]
    public function rows()
    {
        return $this->page->rows()
            ->with(['sections' => fn ($q) => $q->orderBy('sort')->orderBy('id'), 'sections.section', 'sections.media'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function palette()
    {
        $term = trim($this->paletteSearch);

        return Section::orderBy('id')->get()
            ->map(function ($section) {
                $meta = Catalog::meta($section->key);

                return (object) [
                    'id' => $section->id,
                    'key' => $section->key,
                    'name' => $section->name,
                    'icon' => $meta['icon'],
                    'description' => $meta['description'],
                    'group' => $meta['group'],
                ];
            })
            ->when($term !== '', fn ($c) => $c->filter(fn ($s) => str_contains($s->name, $term) || str_contains($s->description, $term)))
            ->groupBy('group')
            ->sortBy(fn ($items, $group) => array_search($group, array_keys(Catalog::GROUPS)));
    }

    #[On('builder-section-saved')]
    public function refreshCanvas(): void
    {
        unset($this->rows);
    }

    public function setDevice(string $device): void
    {
        $this->device = array_key_exists($device, self::DEVICES) ? $device : 'lg';
    }

    /** عرض نمایشی سکشن در دستگاه فعلی (مانند Tailwind: دستگاه بزرگ‌تر از کوچک‌تر ارث می‌برد) */
    public function span(RowSection $section, ?string $device = null): int
    {
        $grid = (array) data_get($section->layout, 'grid', []);
        $value = match ($device ?? $this->device) {
            'lg' => $grid['lg'] ?? $grid['md'] ?? $grid['default'] ?? 12,
            'md' => $grid['md'] ?? $grid['default'] ?? 12,
            default => $grid['default'] ?? 12,
        };

        return max(1, min(12, (int) $value));
    }

    public function previewUrl(): string
    {
        return $this->page->slug === 'home' ? route('home') : route('page.show', $this->page->slug);
    }

    // ------------------------------------------------------------------ rows

    public function addRow(?int $afterRowId = null): void
    {
        abort_if(!auth()->user()->can('sections.create'), 403);

        $this->createRow($afterRowId);
    }

    protected function createRow(?int $afterRowId = null): PageRow
    {
        return DB::transaction(function () use ($afterRowId) {
            $row = PageRow::create([
                'page_id' => $this->page->id,
                'title' => 'ردیف ' . ($this->page->rows()->count() + 1),
                'sort' => 0,
                'gap' => 4,
                'padding_top' => 0,
                'padding_bottom' => 0,
                'container' => 'boxed',
                'status' => true,
            ]);

            $ids = $this->page->rows()->orderBy('sort')->orderBy('id')->pluck('id')->reject(fn ($id) => $id === $row->id)->values()->all();
            $position = $afterRowId ? array_search($afterRowId, $ids, true) : false;
            array_splice($ids, $position === false ? count($ids) : $position + 1, 0, [$row->id]);
            $this->writeRowOrder($ids);

            unset($this->rows);

            return $row;
        });
    }

    public function reorderRows(array $ids): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        $valid = $this->page->rows()->pluck('id')->all();
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($id) => in_array($id, $valid, true)));

        $this->writeRowOrder(array_values(array_unique(array_merge($ids, $valid))));
        unset($this->rows);
    }

    public function editRow(int $id): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        $row = $this->findRow($id);
        $this->rowId = $row->id;
        $this->rowForm = [
            'title' => $row->title,
            'container' => $row->container ?: 'boxed',
            'gap' => (int) $row->gap,
            'padding_top' => (int) $row->padding_top,
            'padding_bottom' => (int) $row->padding_bottom,
        ];
        $this->resetValidation();
        $this->dispatch('pb-open-modal', id: 'pb-row-settings');
    }

    public function saveRow(): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        $data = $this->validate([
            'rowForm.title' => ['required', 'string', 'max:255'],
            'rowForm.container' => ['required', 'in:boxed,full'],
            'rowForm.gap' => ['required', 'integer', 'in:0,1,2,4,6,8,10'],
            'rowForm.padding_top' => ['required', 'integer', 'between:0,20'],
            'rowForm.padding_bottom' => ['required', 'integer', 'between:0,20'],
        ], [
            'rowForm.title.required' => 'عنوان ردیف الزامی است.',
            'rowForm.title.max' => 'عنوان ردیف نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'rowForm.container.in' => 'نوع عرض ردیف نامعتبر است.',
            'rowForm.gap.in' => 'فاصله بین سکشن‌ها نامعتبر است.',
            'rowForm.padding_top.between' => 'فاصله بالا باید بین ۰ تا ۲۰ باشد.',
            'rowForm.padding_bottom.between' => 'فاصله پایین باید بین ۰ تا ۲۰ باشد.',
        ])['rowForm'];

        $this->findRow($this->rowId)->update($data);
        unset($this->rows);

        $this->dispatch('close-modal');
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'تنظیمات ردیف ذخیره شد.');
    }

    public function toggleRow(int $id): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        $row = $this->findRow($id);
        $row->update(['status' => !$row->status]);
        unset($this->rows);
    }

    public function deleteRow(int $id): void
    {
        abort_if(!auth()->user()->can('sections.delete'), 403);

        DB::transaction(function () use ($id) {
            $row = $this->findRow($id);
            $row->sections()->with('media')->get()->each(fn ($section) => $this->destroySection($section));
            $row->delete();
            $this->writeRowOrder($this->page->rows()->orderBy('sort')->orderBy('id')->pluck('id')->all());
        });

        unset($this->rows);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'ردیف و سکشن‌هایش حذف شد.');
    }

    // ------------------------------------------------------------------ sections

    /**
     * افزودن سکشن از پالت؛ rowId خالی = ساخت ردیف جدید در انتهای صفحه
     */
    public function addSection(int $sectionTypeId, ?int $rowId = null, ?int $index = null): void
    {
        abort_if(!auth()->user()->can('sections.create'), 403);

        $type = Section::findOrFail($sectionTypeId);

        $item = DB::transaction(function () use ($type, $rowId, $index) {
            $row = $rowId ? $this->findRow($rowId) : $this->createRow($this->rows->last()?->id);
            $width = Catalog::meta($type->key)['width'];

            $item = RowSection::create([
                'page_row_id' => $row->id,
                'section_id' => $type->id,
                'title' => $type->name,
                'sort' => 0,
                'status' => true,
                'layout' => [
                    'grid' => ['default' => 12, 'md' => $width, 'lg' => $width],
                    'spacing' => ['padding_top' => 0, 'padding_bottom' => 0],
                    'container' => 'boxed',
                    'visibility' => ['mobile' => true, 'desktop' => true],
                ],
            ]);

            $this->placeSection($item, $row->id, $index);

            return $item;
        });

        unset($this->rows);

        // مثل قبل: فرم محتوای سکشن جدید بلافاصله باز می‌شود
        $this->dispatch('builder-edit-section', id: $item->id);
    }

    public function moveSection(int $id, ?int $toRowId, int $index): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        DB::transaction(function () use ($id, $toRowId, $index) {
            $section = $this->findSection($id);
            $fromRowId = $section->page_row_id;
            $target = $toRowId ? $this->findRow($toRowId) : $this->createRow($this->rows->last()?->id);

            $section->update(['page_row_id' => $target->id]);
            $this->placeSection($section, $target->id, $index);

            if ($fromRowId !== $target->id) {
                $this->writeSectionOrder($fromRowId, RowSection::where('page_row_id', $fromRowId)->orderBy('sort')->orderBy('id')->pluck('id')->all());
            }
        });

        unset($this->rows);
    }

    public function setWidth(int $id, int $span, ?string $device = null): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        $device ??= $this->device;
        abort_unless(array_key_exists($device, self::DEVICES), 422);

        $section = $this->findSection($id);
        $layout = (array) ($section->layout ?? []);
        data_set($layout, "grid.{$device}", max(1, min(12, $span)));
        $section->update(['layout' => $layout]);

        unset($this->rows);
    }

    public function toggleVisibility(int $id, string $target): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);
        abort_unless(in_array($target, ['mobile', 'desktop'], true), 422);

        $section = $this->findSection($id);
        $layout = (array) ($section->layout ?? []);
        data_set($layout, "visibility.{$target}", !data_get($layout, "visibility.{$target}", true));
        $section->update(['layout' => $layout]);

        unset($this->rows);
    }

    public function toggleSection(int $id): void
    {
        abort_if(!auth()->user()->can('sections.edit'), 403);

        $section = $this->findSection($id);
        $section->update(['status' => !$section->status]);
        unset($this->rows);
    }

    public function duplicateSection(int $id): void
    {
        abort_if(!auth()->user()->can('sections.create'), 403);

        DB::transaction(function () use ($id) {
            $source = $this->findSection($id)->load('media');
            $copy = $source->replicate();
            $copy->title = Str::limit($source->title, 230, '') . ' (کپی)';
            $copy->save();

            // فایل‌ها هم کپی می‌شوند تا حذف یکی، تصویر دیگری را از بین نبرد
            foreach ($source->media as $media) {
                $newMedia = $media->replicate(['uuid']);
                $newMedia->uuid = (string) Str::uuid();
                $newMedia->mediable_id = $copy->id;

                if (!$media->external_url && $media->file_path && Storage::disk($media->disk ?: 'public')->exists($media->file_path)) {
                    $newPath = trim(dirname($media->file_path), '/.') . '/' . Str::random(40) . '.' . pathinfo($media->file_path, PATHINFO_EXTENSION);
                    Storage::disk($media->disk ?: 'public')->copy($media->file_path, ltrim($newPath, '/'));
                    $newMedia->file_path = ltrim($newPath, '/');
                }

                $newMedia->save();
            }

            $ids = RowSection::where('page_row_id', $source->page_row_id)->orderBy('sort')->orderBy('id')->pluck('id')->reject(fn ($i) => $i === $copy->id)->values()->all();
            array_splice($ids, array_search($source->id, $ids, true) + 1, 0, [$copy->id]);
            $this->writeSectionOrder($source->page_row_id, $ids);
        });

        unset($this->rows);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'از سکشن یک کپی ساخته شد.');
    }

    public function deleteSection(int $id): void
    {
        abort_if(!auth()->user()->can('sections.delete'), 403);

        DB::transaction(function () use ($id) {
            $section = $this->findSection($id)->load('media');
            $rowId = $section->page_row_id;
            $this->destroySection($section);
            $this->writeSectionOrder($rowId, RowSection::where('page_row_id', $rowId)->orderBy('sort')->orderBy('id')->pluck('id')->all());
        });

        unset($this->rows);
        $this->dispatch('alert', type: 'success', title: 'عملیات موفق', text: 'سکشن حذف شد.');
    }

    // ------------------------------------------------------------------ helpers

    protected function findRow(?int $id): PageRow
    {
        return PageRow::where('page_id', $this->page->id)->findOrFail($id);
    }

    protected function findSection(int $id): RowSection
    {
        return RowSection::whereHas('row', fn ($q) => $q->where('page_id', $this->page->id))->findOrFail($id);
    }

    /** قرار دادن سکشن در اندیس مشخص از ردیف و بازنویسی ترتیب */
    protected function placeSection(RowSection $section, int $rowId, ?int $index): void
    {
        $ids = RowSection::where('page_row_id', $rowId)->orderBy('sort')->orderBy('id')->pluck('id')->reject(fn ($i) => $i === $section->id)->values()->all();
        $index = $index === null ? count($ids) : max(0, min($index, count($ids)));
        array_splice($ids, $index, 0, [$section->id]);
        $this->writeSectionOrder($rowId, $ids);
    }

    protected function writeSectionOrder(int $rowId, array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            RowSection::where('page_row_id', $rowId)->whereKey($id)->update(['sort' => $i + 1]);
        }
    }

    protected function writeRowOrder(array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            PageRow::where('page_id', $this->page->id)->whereKey($id)->update(['sort' => $i + 1]);
        }
    }

    protected function destroySection(RowSection $section): void
    {
        $section->media->each(fn ($media) => $this->deleteMedia($media));
        $section->delete();
    }
};
?>

<div class="pb-root" x-data="{ preview: false, previewDevice: 'lg' }">

    {{-- ================= Header ================= --}}
    <div class="my-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('pages.index') }}" class="btn btn-sm btn-light"><i class="ri-arrow-right-line"></i></a>
                <h1 class="page-title fw-semibold fs-18 mb-0">صفحه‌ساز: {{ $page->title }}</h1>
                <span class="badge bg-{{ $page->status ? 'success' : 'secondary' }}-transparent">{{ $page->status ? 'منتشر شده' : 'غیرفعال' }}</span>
            </div>
            <div class="text-muted small">سکشن‌ها را از سمت راست بکشید و داخل ردیف‌ها رها کنید. برای جابه‌جایی، دستگیره <i class="ri-draggable"></i> را بکشید.</div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="btn-group" role="group" aria-label="دستگاه">
                @foreach($this::DEVICES as $key => $d)
                    <button type="button" wire:click="setDevice('{{ $key }}')"
                            class="btn btn-sm {{ $device === $key ? 'btn-primary' : 'btn-outline-primary' }}" title="ویرایش عرض برای {{ $d['title'] }}">
                        <i class="{{ $d['icon'] }} me-1"></i>{{ $d['title'] }}
                    </button>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-info-light" @click="preview = true; $nextTick(() => $refs.frame.src = @js($this->previewUrl()) + '?_pb=' + Date.now())">
                <i class="ri-eye-line me-1"></i>پیش‌نمایش
            </button>
            <a href="{{ $this->previewUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-light" title="مشاهده صفحه در تب جدید">
                <i class="ri-external-link-line"></i>
            </a>
        </div>
    </div>

    <div class="pb-layout">

        {{-- ================= Palette ================= --}}
        @can('sections.create')
            <aside class="pb-palette card custom-card mb-0">
                <div class="card-header py-2">
                    <div class="card-title fs-14"><i class="ri-stack-line me-1"></i>سکشن‌ها</div>
                </div>
                <div class="card-body p-2">
                    <input type="search" wire:model.live.debounce.300ms="paletteSearch" class="form-control form-control-sm mb-2" placeholder="جستجوی سکشن...">

                    @forelse($this->palette as $group => $items)
                        <div class="pb-palette-group">{{ \App\Support\Sections\Catalog::GROUPS[$group] ?? $group }}</div>
                        <div class="pb-palette-list" data-pb-palette>
                            @foreach($items as $item)
                                <div class="pb-palette-item" data-type-id="{{ $item->id }}" wire:key="palette-{{ $item->id }}" title="{{ $item->description }}">
                                    <span class="pb-palette-icon"><i class="{{ $item->icon }}"></i></span>
                                    <span class="flex-fill min-w-0">
                                        <span class="d-block fw-semibold fs-12 text-truncate">{{ $item->name }}</span>
                                        <span class="d-block text-muted fs-11 text-truncate">{{ $item->description }}</span>
                                    </span>
                                    <button type="button" class="btn btn-sm btn-icon btn-light pb-no-drag" title="افزودن به انتهای صفحه"
                                            wire:click="addSection({{ $item->id }}, {{ $this->rows->last()?->id ?? 'null' }})">
                                        <i class="ri-add-line"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="text-muted small text-center py-3">سکشنی پیدا نشد.</div>
                    @endforelse
                </div>
            </aside>
        @endcan

        {{-- ================= Canvas ================= --}}
        <main class="pb-canvas-wrap">
            <div class="pb-canvas" style="max-width: {{ $this::DEVICES[$device]['width'] }}" data-device="{{ $device }}">

                <div class="pb-rows" data-pb-rows>
                    @foreach($this->rows as $row)
                        <section class="pb-row {{ $row->status ? '' : 'is-off' }}" data-row-id="{{ $row->id }}" wire:key="pb-row-{{ $row->id }}">
                            <header class="pb-row-head">
                                <span class="pb-row-handle" title="جابه‌جایی ردیف"><i class="ri-draggable"></i></span>
                                <span class="fw-semibold fs-13 text-truncate">{{ $row->title }}</span>
                                <span class="badge bg-light text-muted fw-normal">{{ $row->container === 'full' ? 'تمام‌عرض' : 'محدود' }}</span>
                                @unless($row->status)
                                    <span class="badge bg-warning-transparent">مخفی در سایت</span>
                                @endunless

                                <span class="ms-auto d-flex align-items-center gap-1 pb-no-drag">
                                    @can('sections.edit')
                                        <button type="button" class="btn btn-sm btn-icon btn-light" wire:click="toggleRow({{ $row->id }})" title="{{ $row->status ? 'مخفی کردن ردیف' : 'نمایش ردیف' }}">
                                            <i class="{{ $row->status ? 'ri-eye-line' : 'ri-eye-off-line' }}"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-icon btn-light" wire:click="editRow({{ $row->id }})" title="تنظیمات ردیف">
                                            <i class="ri-settings-3-line"></i>
                                        </button>
                                    @endcan
                                    @can('sections.create')
                                        <button type="button" class="btn btn-sm btn-icon btn-light" wire:click="addRow({{ $row->id }})" title="ردیف خالی بعد از این">
                                            <i class="ri-insert-row-bottom"></i>
                                        </button>
                                    @endcan
                                    @can('sections.delete')
                                        <button type="button" class="btn btn-sm btn-icon btn-danger-light" wire:click="deleteRow({{ $row->id }})"
                                                wire:confirm="ردیف «{{ $row->title }}» و همه سکشن‌هایش حذف شود؟" title="حذف ردیف">
                                            <i class="ri-delete-bin-6-line"></i>
                                        </button>
                                    @endcan
                                </span>
                            </header>

                            <div class="pb-grid" data-pb-sections data-row-id="{{ $row->id }}" style="gap: {{ max(4, (int) $row->gap * 3) }}px">
                                @foreach($row->sections as $section)
                                    @php
                                        $meta = \App\Support\Sections\Catalog::meta($section->section?->key);
                                        $span = $this->span($section);
                                        $visMobile = (bool) data_get($section->layout, 'visibility.mobile', true);
                                        $visDesktop = (bool) data_get($section->layout, 'visibility.desktop', true);
                                        $hiddenHere = $device === 'lg' ? !$visDesktop : !$visMobile;
                                        $needsSetup = empty($section->data);
                                    @endphp
                                    <article class="pb-block {{ $section->status ? '' : 'is-off' }} {{ $hiddenHere ? 'is-hidden-device' : '' }}"
                                             data-section-id="{{ $section->id }}" wire:key="pb-section-{{ $section->id }}"
                                             style="grid-column: span {{ $span }} / span {{ $span }}">
                                        <div class="pb-block-head">
                                            <span class="pb-block-handle" title="جابه‌جایی سکشن"><i class="ri-draggable"></i></span>
                                            <span class="pb-block-icon"><i class="{{ $meta['icon'] }}"></i></span>
                                            <span class="min-w-0 flex-fill">
                                                <span class="d-block fw-semibold fs-13 text-truncate">{{ $section->title ?: $section->section?->name }}</span>
                                                <span class="d-block text-muted fs-11 text-truncate">{{ $section->section?->name ?? 'سکشن حذف‌شده' }}</span>
                                            </span>
                                            <span class="pb-span-badge" title="عرض در {{ $this::DEVICES[$device]['title'] }}">{{ $span }}/12</span>
                                        </div>

                                        <div class="pb-block-flags">
                                            @if($needsSetup)
                                                <span class="badge bg-warning-transparent"><i class="ri-error-warning-line"></i> نیاز به تنظیم محتوا</span>
                                            @endif
                                            @unless($section->status)
                                                <span class="badge bg-secondary-transparent">مخفی</span>
                                            @endunless
                                            @unless($visMobile)
                                                <span class="badge bg-light text-muted"><i class="ri-smartphone-line"></i> در موبایل نمایش داده نمی‌شود</span>
                                            @endunless
                                            @unless($visDesktop)
                                                <span class="badge bg-light text-muted"><i class="ri-computer-line"></i> در دسکتاپ نمایش داده نمی‌شود</span>
                                            @endunless
                                        </div>

                                        <div class="pb-block-actions pb-no-drag">
                                            @can('sections.edit')
                                                <button type="button" class="btn btn-sm btn-primary" wire:click="$dispatch('builder-edit-section', { id: {{ $section->id }} })">
                                                    <i class="ri-edit-2-line me-1"></i>محتوا
                                                </button>

                                                <div class="dropdown">
                                                    <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" title="عرض در {{ $this::DEVICES[$device]['title'] }}">
                                                        <i class="ri-layout-column-line"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><h6 class="dropdown-header">عرض در {{ $this::DEVICES[$device]['title'] }}</h6></li>
                                                        @foreach($this::WIDTHS as $w => $label)
                                                            <li>
                                                                <button type="button" class="dropdown-item d-flex justify-content-between gap-3 {{ $span === $w ? 'active' : '' }}" wire:click="setWidth({{ $section->id }}, {{ $w }})">
                                                                    <span>{{ $label }}</span><span class="text-muted">{{ $w }}/12</span>
                                                                </button>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>

                                                <button type="button" class="btn btn-sm btn-icon btn-light" wire:click="toggleVisibility({{ $section->id }}, 'mobile')"
                                                        title="{{ $visMobile ? 'مخفی در موبایل و تبلت' : 'نمایش در موبایل و تبلت' }}">
                                                    <i class="{{ $visMobile ? 'ri-smartphone-line' : 'ri-smartphone-line text-danger opacity-50' }}"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-icon btn-light" wire:click="toggleVisibility({{ $section->id }}, 'desktop')"
                                                        title="{{ $visDesktop ? 'مخفی در دسکتاپ' : 'نمایش در دسکتاپ' }}">
                                                    <i class="{{ $visDesktop ? 'ri-computer-line' : 'ri-computer-line text-danger opacity-50' }}"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-icon btn-light" wire:click="toggleSection({{ $section->id }})"
                                                        title="{{ $section->status ? 'مخفی کردن سکشن' : 'نمایش سکشن' }}">
                                                    <i class="{{ $section->status ? 'ri-eye-line' : 'ri-eye-off-line' }}"></i>
                                                </button>
                                            @endcan
                                            @can('sections.create')
                                                <button type="button" class="btn btn-sm btn-icon btn-light" wire:click="duplicateSection({{ $section->id }})" title="کپی سکشن">
                                                    <i class="ri-file-copy-line"></i>
                                                </button>
                                            @endcan
                                            @can('sections.delete')
                                                <button type="button" class="btn btn-sm btn-icon btn-danger-light" wire:click="deleteSection({{ $section->id }})"
                                                        wire:confirm="سکشن «{{ $section->title }}» حذف شود؟" title="حذف سکشن">
                                                    <i class="ri-delete-bin-6-line"></i>
                                                </button>
                                            @endcan
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                {{-- رها کردن روی این ناحیه = ردیف جدید --}}
                @can('sections.create')
                    <div class="pb-newrow" data-pb-newrow>
                        <i class="ri-add-box-line fs-20"></i>
                        <span>
                            @if($this->rows->isEmpty())
                                صفحه خالی است؛ اولین سکشن را از پالت به اینجا بکشید
                            @else
                                برای ساخت ردیف جدید، سکشن را اینجا رها کنید
                            @endif
                        </span>
                        <button type="button" class="btn btn-sm btn-light pb-no-drag" wire:click="addRow({{ $this->rows->last()?->id ?? 'null' }})">
                            <i class="ri-insert-row-bottom me-1"></i>ردیف خالی
                        </button>
                    </div>
                @endcan
            </div>
        </main>
    </div>

    {{-- ================= فرم محتوای سکشن (همان فرم قبلی، در حالت editor) ================= --}}
    @if($editorRow = $this->rows->first())
        <livewire:pages::dashboard.pages.sections.page-section :row="$editorRow" mode="editor" wire:key="pb-section-editor" />
    @endif

    {{-- ================= تنظیمات ردیف ================= --}}
    <div wire:ignore.self class="modal fade" id="pb-row-settings">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form wire:submit="saveRow">
                    <div class="modal-header">
                        <h6 class="modal-title">تنظیمات ردیف</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">عنوان (فقط برای شما در صفحه‌ساز)</label>
                                <input type="text" wire:model="rowForm.title" class="form-control @error('rowForm.title') is-invalid @enderror">
                                @error('rowForm.title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">عرض ردیف</label>
                                <div class="btn-group w-100">
                                    <input type="radio" class="btn-check" wire:model="rowForm.container" value="boxed" id="pb-container-boxed">
                                    <label class="btn btn-outline-primary" for="pb-container-boxed"><i class="ri-layout-column-line me-1"></i>محدود (وسط‌چین)</label>
                                    <input type="radio" class="btn-check" wire:model="rowForm.container" value="full" id="pb-container-full">
                                    <label class="btn btn-outline-primary" for="pb-container-full"><i class="ri-fullscreen-line me-1"></i>تمام‌عرض</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">فاصله بین سکشن‌ها</label>
                                <select wire:model="rowForm.gap" class="form-select @error('rowForm.gap') is-invalid @enderror">
                                    @foreach([0 => 'بدون فاصله', 2 => 'کم', 4 => 'معمولی', 6 => 'زیاد', 8 => 'خیلی زیاد'] as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">فاصله بالا</label>
                                <select wire:model="rowForm.padding_top" class="form-select @error('rowForm.padding_top') is-invalid @enderror">
                                    @foreach([0, 2, 4, 6, 8, 12, 16, 20] as $v)
                                        <option value="{{ $v }}">{{ $v === 0 ? 'هیچ' : $v * 4 . ' پیکسل' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">فاصله پایین</label>
                                <select wire:model="rowForm.padding_bottom" class="form-select @error('rowForm.padding_bottom') is-invalid @enderror">
                                    @foreach([0, 2, 4, 6, 8, 12, 16, 20] as $v)
                                        <option value="{{ $v }}">{{ $v === 0 ? 'هیچ' : $v * 4 . ' پیکسل' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveRow">ذخیره</button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ================= پیش‌نمایش زنده ================= --}}
    <div class="pb-preview" x-show="preview" x-cloak x-transition.opacity @keydown.escape.window="preview = false">
        <div class="pb-preview-bar">
            <span class="fw-semibold"><i class="ri-eye-line me-1"></i>پیش‌نمایش: {{ $page->title }}</span>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn" :class="previewDevice === 'lg' ? 'btn-light' : 'btn-outline-light'" @click="previewDevice = 'lg'"><i class="ri-computer-line"></i></button>
                <button type="button" class="btn" :class="previewDevice === 'md' ? 'btn-light' : 'btn-outline-light'" @click="previewDevice = 'md'"><i class="ri-tablet-line"></i></button>
                <button type="button" class="btn" :class="previewDevice === 'sm' ? 'btn-light' : 'btn-outline-light'" @click="previewDevice = 'sm'"><i class="ri-smartphone-line"></i></button>
            </div>
            <span class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-light" @click="$refs.frame.contentWindow.location.reload()"><i class="ri-refresh-line"></i></button>
                <button type="button" class="btn btn-sm btn-light" @click="preview = false"><i class="ri-close-line"></i> بستن</button>
            </span>
        </div>
        <div class="pb-preview-stage">
            <iframe x-ref="frame" title="پیش‌نمایش صفحه" class="pb-preview-frame"
                    :style="{ width: previewDevice === 'lg' ? '100%' : (previewDevice === 'md' ? '820px' : '390px') }"></iframe>
        </div>
    </div>

    @assets
        <script src="{{ asset('dashboard/libs/sortablejs/Sortable.min.js') }}"></script>
        <style>
            [x-cloak] { display: none !important; }
            .pb-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 1rem; align-items: start; }
            @media (max-width: 1199px) { .pb-layout { grid-template-columns: 1fr; } }

            .pb-palette { position: sticky; top: 80px; max-height: calc(100vh - 100px); overflow: hidden; display: flex; flex-direction: column; }
            .pb-palette .card-body { overflow-y: auto; }
            @media (max-width: 1199px) { .pb-palette { position: static; max-height: none; } .pb-palette-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .4rem; } }
            .pb-palette-group { font-size: 11px; font-weight: 700; color: var(--text-muted, #8c9097); margin: .75rem .25rem .35rem; }
            .pb-palette-item { display: flex; align-items: center; gap: .55rem; padding: .5rem; margin-bottom: .35rem; border: 1px dashed rgba(var(--primary-rgb, 132, 90, 223), .35); border-radius: .6rem; background: var(--custom-white, #fff); cursor: grab; transition: border-color .15s, box-shadow .15s, transform .15s; user-select: none; }
            .pb-palette-item:hover { border-style: solid; border-color: rgb(var(--primary-rgb, 132, 90, 223)); box-shadow: 0 4px 14px rgba(0,0,0,.06); transform: translateY(-1px); }
            .pb-palette-icon, .pb-block-icon { flex: none; width: 34px; height: 34px; border-radius: .55rem; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; background: rgba(var(--primary-rgb, 132, 90, 223), .1); color: rgb(var(--primary-rgb, 132, 90, 223)); }

            .pb-canvas-wrap { min-width: 0; background: repeating-linear-gradient(45deg, transparent 0 10px, rgba(0,0,0,.015) 10px 20px); border-radius: 1rem; padding: 1rem; }
            .pb-canvas { margin: 0 auto; transition: max-width .3s ease; }
            .pb-rows { display: flex; flex-direction: column; gap: .85rem; }

            .pb-row { background: var(--custom-white, #fff); border: 1px solid var(--default-border, #e9edf6); border-radius: .9rem; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
            .pb-row.is-off { opacity: .55; }
            .pb-row-head { display: flex; align-items: center; gap: .5rem; padding: .5rem .65rem; border-bottom: 1px solid var(--default-border, #e9edf6); min-width: 0; }
            .pb-row-handle, .pb-block-handle { cursor: grab; color: var(--text-muted, #8c9097); font-size: 18px; line-height: 1; padding: 2px; border-radius: 6px; }
            .pb-row-handle:hover, .pb-block-handle:hover { background: rgba(0,0,0,.05); color: inherit; }

            .pb-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); padding: .75rem; min-height: 96px; position: relative; }
            .pb-grid:not(:has(> .pb-block))::after { content: 'سکشن را اینجا رها کنید'; grid-column: 1 / -1; display: flex; align-items: center; justify-content: center; min-height: 72px; border: 2px dashed var(--default-border, #dfe3ec); border-radius: .75rem; color: var(--text-muted, #8c9097); font-size: 12px; }

            .pb-block { min-width: 0; display: flex; flex-direction: column; gap: .5rem; padding: .65rem; border-radius: .75rem; border: 1px solid rgba(var(--primary-rgb, 132, 90, 223), .25); background: linear-gradient(180deg, rgba(var(--primary-rgb, 132, 90, 223), .045), transparent 70%), var(--custom-white, #fff); transition: box-shadow .15s, border-color .15s; }
            .pb-block:hover { border-color: rgb(var(--primary-rgb, 132, 90, 223)); box-shadow: 0 6px 20px rgba(0,0,0,.07); }
            .pb-block.is-off { opacity: .5; }
            .pb-block.is-hidden-device { background: repeating-linear-gradient(-45deg, transparent 0 8px, rgba(0,0,0,.035) 8px 16px), var(--custom-white, #fff); }
            .pb-block-head { display: flex; align-items: center; gap: .5rem; min-width: 0; }
            .pb-span-badge { flex: none; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: rgba(0,0,0,.05); }
            .pb-block-flags { display: flex; flex-wrap: wrap; gap: .3rem; }
            .pb-block-flags:empty { display: none; }
            .pb-block-actions { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: auto; }

            .pb-newrow { margin-top: .85rem; display: flex; align-items: center; justify-content: center; gap: .6rem; flex-wrap: wrap; min-height: 84px; padding: 1rem; border: 2px dashed rgba(var(--primary-rgb, 132, 90, 223), .35); border-radius: .9rem; color: rgb(var(--primary-rgb, 132, 90, 223)); font-size: 13px; background: rgba(var(--primary-rgb, 132, 90, 223), .03); }
            .pb-newrow > .pb-palette-item, .pb-newrow > .pb-block { display: none !important; }

            /* SortableJS */
            .pb-ghost { opacity: .35; outline: 2px dashed rgb(var(--primary-rgb, 132, 90, 223)); outline-offset: 2px; }
            .pb-grid .pb-palette-item.pb-ghost { grid-column: span 12; }
            .pb-chosen { box-shadow: 0 12px 30px rgba(0,0,0,.15) !important; }
            .pb-dragging .pb-grid { outline: 2px dashed rgba(var(--primary-rgb, 132, 90, 223), .25); outline-offset: -4px; border-radius: .8rem; }
            .pb-dragging .pb-newrow { background: rgba(var(--primary-rgb, 132, 90, 223), .08); }
            .pb-busy .pb-canvas { opacity: .7; pointer-events: none; transition: opacity .15s; }

            .pb-preview { position: fixed; inset: 0; z-index: 1060; background: rgba(15, 18, 30, .92); display: flex; flex-direction: column; }
            .pb-preview-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .65rem 1rem; color: #fff; }
            .pb-preview-stage { flex: 1; display: flex; justify-content: center; padding: 0 1rem 1rem; min-height: 0; }
            .pb-preview-frame { height: 100%; max-width: 100%; border: 0; border-radius: .75rem; background: #fff; transition: width .3s ease; }
        </style>
    @endassets

    @script
    <script>
        const root = $wire.$el;

        const ids = (container, attr) => [...container.children].filter((el) => el.dataset[attr]).map((el) => +el.dataset[attr]);
        // DOM را به حالت قبل برمی‌گردانیم؛ ترتیب نهایی را سرور رندر می‌کند
        const revert = (evt) => {
            if (evt.pullMode === 'clone' && evt.clone) {
                evt.clone.replaceWith(evt.item); // عنصر اصلی (با wire:key) به پالت برمی‌گردد
                return;
            }
            evt.item.remove();
            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
        };
        const busy = (on) => root.classList.toggle('pb-busy', on);
        const call = (method, ...args) => { busy(true); return $wire[method](...args).finally(() => busy(false)); };
        const dragState = { onStart: () => root.classList.add('pb-dragging'), onUnchoose: () => root.classList.remove('pb-dragging') };

        function initSortables() {
            if (typeof Sortable === 'undefined') return;

            root.querySelectorAll('[data-pb-palette]').forEach((el) => {
                if (el._pb) return;
                el._pb = Sortable.create(el, {
                    group: { name: 'pb-sections', pull: 'clone', put: false },
                    sort: false, animation: 150, filter: '.pb-no-drag', preventOnFilter: false,
                    ghostClass: 'pb-ghost', chosenClass: 'pb-chosen', ...dragState,
                });
            });

            root.querySelectorAll('[data-pb-sections]').forEach((el) => {
                if (el._pb) return;
                el._pb = Sortable.create(el, {
                    group: { name: 'pb-sections', pull: true, put: true },
                    handle: '.pb-block-handle', draggable: '.pb-block, .pb-palette-item', animation: 180,
                    ghostClass: 'pb-ghost', chosenClass: 'pb-chosen', ...dragState,
                    onAdd(evt) {
                        const rowId = +evt.to.dataset.rowId;
                        const typeId = evt.item.dataset.typeId;
                        const sectionId = evt.item.dataset.sectionId;
                        revert(evt);
                        if (typeId) call('addSection', +typeId, rowId, evt.newIndex);
                        else if (sectionId) call('moveSection', +sectionId, rowId, evt.newIndex);
                    },
                    onUpdate(evt) {
                        const sectionId = +evt.item.dataset.sectionId;
                        const rowId = +evt.to.dataset.rowId;
                        revert(evt);
                        call('moveSection', sectionId, rowId, evt.newIndex);
                    },
                });
            });

            root.querySelectorAll('[data-pb-newrow]').forEach((el) => {
                if (el._pb) return;
                el._pb = Sortable.create(el, {
                    group: { name: 'pb-sections', pull: false, put: true },
                    sort: false, ...dragState,
                    onAdd(evt) {
                        const typeId = evt.item.dataset.typeId;
                        const sectionId = evt.item.dataset.sectionId;
                        revert(evt);
                        if (typeId) call('addSection', +typeId, null, 0);
                        else if (sectionId) call('moveSection', +sectionId, null, 0);
                    },
                });
            });

            root.querySelectorAll('[data-pb-rows]').forEach((el) => {
                if (el._pb) return;
                el._pb = Sortable.create(el, {
                    group: 'pb-rows', handle: '.pb-row-handle', draggable: '.pb-row', animation: 200,
                    ghostClass: 'pb-ghost', chosenClass: 'pb-chosen',
                    onUpdate(evt) {
                        const order = ids(evt.to, 'rowId');
                        revert(evt);
                        call('reorderRows', order);
                    },
                });
            });
        }

        initSortables();
        // ردیف‌های جدیدی که Livewire اضافه می‌کند هم قابل کشیدن شوند
        new MutationObserver(() => initSortables()).observe(root, { childList: true, subtree: true });

        // باز کردن فرم محتوای سکشن (شنونده‌ی خود page-section فقط در اولین بارگذاری ثبت می‌شود)
        Livewire.on('open-section-settings', () => {
            setTimeout(() => {
                const el = document.getElementById('create');
                if (el && !el.classList.contains('show')) bootstrap.Modal.getOrCreateInstance(el).show();
            }, 450);
        });

        $wire.on('pb-open-modal', ({ id }) => {
            setTimeout(() => {
                const el = document.getElementById(id);
                if (el) bootstrap.Modal.getOrCreateInstance(el).show();
            }, 50);
        });
    </script>
    @endscript
</div>
