<div class="row g-3">

    {{-- روش نمایش محصولات --}}
    <div class="col-md-4">

        <label class="form-label">
            روش نمایش محصولات
        </label>

        <select
            wire:model="formData.mode"
            class="form-select @error('formData.mode') is-invalid @enderror">

            <option value="latest">
                جدیدترین محصولات
            </option>

            <option value="sales">
                پرفروش‌ترین محصولات
            </option>

            <option value="views">
                پربازدیدترین محصولات
            </option>

            <option value="random">
                پیشنهاد لحظه‌ای
            </option>

        </select>

        @error('formData.mode')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror

    </div>


    {{-- تعداد نمایش --}}
    <div class="col-md-4">

        <label class="form-label">
            تعداد نمایش
        </label>

        <select
            wire:model="formData.limit"
            class="form-select">

            <option value="3">۳ محصول</option>
            <option value="6">۶ محصول</option>
            <option value="9">۹ محصول</option>
            <option value="12">۱۲ محصول</option>

        </select>

    </div>

    @include('dashboard.forms.partials.picture-mode', ['col' => 'col-md-4'])


    {{-- برندهای فیلتر بالای سکشن --}}
    <div class="col-md-12">

        <label class="form-label">
            برندهای فیلتر
        </label>

        <select
            multiple
            wire:model="formData.brand_ids"
            class="form-select @error('formData.brand_ids') is-invalid @enderror"
            style="height:220px">

            @foreach($brands ?? [] as $brand)
                <option value="{{ $brand->id }}">
                    {{ $brand->title }}
                </option>
            @endforeach

        </select>

        <div class="form-text">
            اگر برندی انتخاب نشود، همه برندهای فعالی که محصول دارند نمایش داده می‌شوند.
        </div>

        @error('formData.brand_ids')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror

    </div>

</div>
