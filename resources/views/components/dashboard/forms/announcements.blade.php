<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">نوع نمایش</label>
        <select wire:model.live="formData.style" class="form-select @error('formData.style') is-invalid @enderror">
            @foreach(\App\Support\Sections\Announcements::STYLES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        @error('formData.style')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">حداکثر تعداد</label>
        <input type="number" min="1" max="20" wire:model="formData.limit" class="form-control @error('formData.limit') is-invalid @enderror">
        @error('formData.limit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">فقط این نوع‌ها (خالی = همه)</label>
        <div class="d-flex flex-wrap gap-3 mt-1">
            @foreach(\App\Models\Announcement::TYPES as $key => $meta)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ann_type_{{ $key }}" value="{{ $key }}" wire:model="formData.types">
                    <label class="form-check-label" for="ann_type_{{ $key }}">{{ $meta['title'] }}</label>
                </div>
            @endforeach
        </div>
        @error('formData.types.*')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    @if(($formData['style'] ?? 'list') !== 'bar')
        <div class="col-md-8">
            <label class="form-label">توضیح زیر عنوان سکشن</label>
            <input type="text" wire:model="formData.description" maxlength="200" class="form-control @error('formData.description') is-invalid @enderror" placeholder="مثلاً: آخرین اخبار و اطلاع‌رسانی‌های فروشگاه">
            @error('formData.description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    @endif

    <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="ann_date" wire:model="formData.show_date"><label class="form-check-label" for="ann_date">نمایش تاریخ</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="ann_dismiss" wire:model="formData.dismissible"><label class="form-check-label" for="ann_dismiss">امکان بستن توسط کاربر</label></div>
    </div>

    <div class="col-12">
        <div class="alert alert-light small mb-0">
            <i class="ri-information-line"></i>
            محتوای اطلاعیه‌ها از بخش
            @can('announcements.view')<a href="{{ route('announcements.index') }}" target="_blank">اطلاعیه‌های سایت</a>@else «اطلاعیه‌های سایت» @endcan
            مدیریت می‌شود؛ فقط اطلاعیه‌های فعال و در بازه زمانی نمایش داده می‌شوند (سنجاق‌شده‌ها اول). اگر اطلاعیه‌ای نباشد، سکشن نمایش داده نمی‌شود.
        </div>
    </div>
</div>
