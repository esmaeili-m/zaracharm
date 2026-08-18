<div class="row g-3">
    <div class="col-md-2 mb-2">

        <label class="form-label">
          lat
        </label>

        {{-- اینجا کامپوننت انتخاب تصویر خودت --}}
        <input
            type="text"
            wire:model="formData.lat"
            class="form-control"
        >

        @error('formData.lat')
        <div class="text-danger mt-1">
            {{ $message }}
        </div>
        @enderror

    </div>
    <div class="col-md-2 mb-2">

        <label class="form-label">
            lang
        </label>

        {{-- اینجا کامپوننت انتخاب تصویر خودت --}}
        <input
            type="text"
            wire:model="formData.long"
            class="form-control"
        >

        @error('formData.lang')
        <div class="text-danger mt-1">
            {{ $message }}
        </div>
        @enderror

    </div>
</div>
