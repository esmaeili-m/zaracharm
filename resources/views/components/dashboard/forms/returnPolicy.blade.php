@php($maxItems = \App\Support\Sections\ReturnPolicy::MAX_ITEMS)

<div class="row g-3">

    {{-- توضیح --}}
    <div class="col-md-9">
        <label class="form-label">
            توضیح زیر عنوان
        </label>
        <textarea
            wire:model="formData.description"
            rows="2"
            class="form-control @error('formData.description') is-invalid @enderror"
            placeholder="توضیح کوتاه درباره شرایط مرجوعی"></textarea>
        @error('formData.description')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    {{-- مهلت مرجوعی --}}
    <div class="col-md-3">
        <label class="form-label">
            مهلت مرجوعی (روز)
        </label>
        <input
            type="number"
            min="0"
            max="365"
            wire:model="formData.return_days"
            class="form-control @error('formData.return_days') is-invalid @enderror">
        <div class="form-text">۰ = نمایش داده نشود</div>
        @error('formData.return_days')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>


    {{-- شرایط پذیرش / کالاهای غیرقابل مرجوع --}}
    @foreach(['conditions' => 'شرایط پذیرش مرجوعی', 'exclusions' => 'کالاهای غیرقابل مرجوع'] as $listKey => $listLabel)
        <div class="col-md-6" wire:key="return-list-{{ $listKey }}">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label mb-0">{{ $listLabel }}</label>
                @if(count($formData[$listKey] ?? []) < $maxItems)
                    <button type="button" class="btn btn-sm btn-success-light" wire:click="addListItem('{{ $listKey }}')">
                        <i class="ri-add-line align-middle"></i> افزودن
                    </button>
                @endif
            </div>

            @forelse($formData[$listKey] ?? [] as $index => $value)
                <div class="input-group mb-2" wire:key="return-{{ $listKey }}-{{ $index }}">
                    <input
                        type="text"
                        wire:model="formData.{{ $listKey }}.{{ $index }}"
                        class="form-control @error('formData.' . $listKey . '.' . $index) is-invalid @enderror"
                        placeholder="متن مورد">
                    <button type="button" class="btn btn-danger-light" wire:click="removeListItem('{{ $listKey }}', {{ $index }})" title="حذف">
                        <i class="ri-delete-bin-5-line"></i>
                    </button>
                </div>
                @error('formData.' . $listKey . '.' . $index)
                <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
                @enderror
            @empty
                <div class="text-muted small border rounded p-3 text-center">موردی ثبت نشده است.</div>
            @endforelse

            @error('formData.' . $listKey)
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    @endforeach


    {{-- مراحل ثبت مرجوعی --}}
    <div class="col-md-12">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label mb-0">مراحل ثبت مرجوعی</label>
            @if(count($formData['steps'] ?? []) < $maxItems)
                <button type="button" class="btn btn-sm btn-success-light" wire:click="addListItem('steps')">
                    <i class="ri-add-line align-middle"></i> افزودن مرحله
                </button>
            @endif
        </div>

        @forelse($formData['steps'] ?? [] as $index => $step)
            <div class="row g-2 align-items-start mb-2" wire:key="return-step-{{ $index }}">
                <div class="col-auto pt-2">
                    <span class="badge bg-primary-transparent">{{ $loop->iteration }}</span>
                </div>
                <div class="col-md-3">
                    <input
                        type="text"
                        wire:model="formData.steps.{{ $index }}.title"
                        class="form-control @error('formData.steps.' . $index . '.title') is-invalid @enderror"
                        placeholder="عنوان مرحله">
                    @error('formData.steps.' . $index . '.title')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col">
                    <input
                        type="text"
                        wire:model="formData.steps.{{ $index }}.text"
                        class="form-control @error('formData.steps.' . $index . '.text') is-invalid @enderror"
                        placeholder="توضیح مرحله">
                    @error('formData.steps.' . $index . '.text')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-danger-light" wire:click="removeListItem('steps', {{ $index }})" title="حذف">
                        <i class="ri-delete-bin-5-line"></i>
                    </button>
                </div>
            </div>
        @empty
            <div class="text-muted small border rounded p-3 text-center">مرحله‌ای ثبت نشده است.</div>
        @endforelse

        @error('formData.steps')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>


    {{-- یادداشت --}}
    <div class="col-md-12">
        <label class="form-label">
            یادداشت مهم (اختیاری)
        </label>
        <textarea
            wire:model="formData.note"
            rows="2"
            class="form-control @error('formData.note') is-invalid @enderror"
            placeholder="مثلاً: هزینه ارسال کالای مرجوعی در صورت عدم ایراد بر عهده مشتری است."></textarea>
        @error('formData.note')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="return-show-cta" wire:model="formData.show_cta">
            <label class="form-check-label" for="return-show-cta">
                نمایش دکمه «ثبت درخواست مرجوعی از سفارش‌های من»
            </label>
        </div>
    </div>

</div>
