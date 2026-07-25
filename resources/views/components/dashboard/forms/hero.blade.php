<div class="row">

    {{-- عنوان --}}
    <div class="col-md-6 mb-3">
        <label class="form-label">
            عنوان اصلی
        </label>

        <input
            type="text"
            wire:model.lazy="formData.title"
            class="form-control @error('formData.title') is-invalid @enderror"
            placeholder="مثال: آموزش برنامه نویسی از صفر تا حرفه‌ای">

        @error('formData.title')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    {{-- زیر عنوان --}}
    <div class="col-md-6 mb-3">
        <label class="form-label">
            زیر عنوان
        </label>

        <input
            type="text"
            wire:model.lazy="formData.subtitle"
            class="form-control @error('formData.subtitle') is-invalid @enderror"
            placeholder="مثال: با پشتیبانی و پروژه‌های واقعی">

        @error('formData.subtitle')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    {{-- توضیحات --}}
    <div class="col-md-12 mb-3">
        <label class="form-label">
            توضیحات
        </label>

        <textarea
            rows="5"
            wire:model.lazy="formData.description"
            class="form-control @error('formData.description') is-invalid @enderror"
            placeholder="توضیحات بخش هیرو">
        </textarea>

        @error('formData.description')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    {{-- تصویر دسکتاپ --}}
    <div class="col-md-6 mb-3">
        <label class="form-label">
           تصویر - دسکتاپ
        </label>

        <input
            type="file"
            wire:model="media.image_1"
            class="form-control @error('media.image_1') is-invalid @enderror">

        @error('media.image_1')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>    {{-- تصویر موبایل --}}
    <div class="col-md-6 mb-3">
        <label class="form-label">
            تصویر هیرو - موبایل
        </label>

        <input
            type="file"
            wire:model="media.image_2"
            class="form-control @error('media.image_2') is-invalid @enderror">

        @error('media.image_2')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

</div>
