<div class="row">

    {{-- TITLE --}}
    <div class="col-md-12 mb-3">
        <label class="form-label">عنوان</label>

        <input
            type="text"
            wire:model.lazy="formData.title"
            class="form-control @error('formData.title') is-invalid @enderror"
            placeholder="عنوان ">

        @error('formData.title')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">
            تصویر
        </label>

        <input
            type="file"
            wire:model="media.image_1"
            class="form-control">

        @error('media.image_1')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

</div>
