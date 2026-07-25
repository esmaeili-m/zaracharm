<div class="row">

    {{-- TITLE --}}
    <div class="col-md-12 mb-3">
        <label class="form-label">دفتر مرکزی</label>

        <textarea
            rows="5"
            wire:model.lazy="formData.location"
            class="form-control @error('formData.location') is-invalid @enderror"
            placeholder="آدرس دفتر مرکزی">
        </textarea>

        @error('formData.location')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    {{-- TITLE --}}
    <div class="col-md-12 mb-3">
        <label class="form-label">شماره تماس</label>

        <textarea
            rows="5"
            wire:model.lazy="formData.mobile"
            class="form-control @error('formData.mobile') is-invalid @enderror"
            placeholder="شماره تماس">
        </textarea>

        @error('formData.mobile')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12 mb-3">
        <label class="form-label">ایمیل </label>

        <textarea
            rows="5"
            wire:model.lazy="formData.email"
            class="form-control @error('formData.email') is-invalid @enderror"
            placeholder="ایمیل">
        </textarea>

        @error('formData.email')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12 mb-3">
        <label class="form-label">ساعت کاری</label>

        <textarea
            rows="5"
            wire:model.lazy="formData.work_hours"
            class="form-control @error('formData.work_hours') is-invalid @enderror"
            placeholder="ساعت کاری">
        </textarea>

        @error('formData.work_hours')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>
