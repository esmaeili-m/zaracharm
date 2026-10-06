{{-- انتخاب حالت نمایش تصاویر سکشن (formData.pictureMode) --}}
<div class="{{ $col ?? 'col-md-3' }}">

    <label class="form-label">
        حالت نمایش تصاویر
    </label>

    <select
        wire:model="formData.pictureMode"
        class="form-select @error('formData.pictureMode') is-invalid @enderror">

        @foreach(\App\Enums\PictureMode::options() as $value => $label)
            <option value="{{ $value }}">
                {{ $label }}
            </option>
        @endforeach

    </select>

    @error('formData.pictureMode')
    <div class="invalid-feedback d-block">
        {{ $message }}
    </div>
    @enderror

</div>
