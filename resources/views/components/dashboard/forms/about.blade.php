<div class="row">

    {{-- TITLE --}}
    <div class="col-md-12 mb-3">
        <label class="form-label">عنوان</label>

        <input
            type="text"
            wire:model.lazy="formData.title"
            class="form-control @error('formData.title') is-invalid @enderror"
            placeholder="عنوان سکشن">

        @error('formData.title')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    {{-- DESCRIPTION --}}
    <div class="col-md-12 mb-3">
        <label class="form-label">توضیحات</label>

        <textarea
            rows="5"
            wire:model.lazy="formData.description"
            class="form-control @error('formData.description') is-invalid @enderror"
            placeholder="توضیحات سکشن">
        </textarea>

        @error('formData.description')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">
            تصویر سمت راست
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
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">
            تصویر سمت چپ
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
