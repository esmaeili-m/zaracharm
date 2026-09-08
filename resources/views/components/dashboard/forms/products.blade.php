<div class="row g-3">

    {{-- روش نمایش محصولات --}}
    <div class="col-md-6">

        <label class="form-label">
            روش نمایش محصولات
        </label>

        <select
            wire:model.live="formData.mode"
            class="form-select">

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

            <option value="manual">
                انتخاب دستی
            </option>

        </select>

    </div>


    {{-- تعداد نمایش --}}
    <div class="col-md-3">

        <label class="form-label">
            تعداد نمایش
        </label>

        <select
            wire:model="formData.limit"
            class="form-select">

            <option value="4">
                ۴ محصول
            </option>

            <option value="8">
                ۸ محصول
            </option>

            <option value="12">
                ۱۲ محصول
            </option>

            <option value="16">
                ۱۶ محصول
            </option>

        </select>

    </div>
    <div class="col-md-3">

        <label class="form-label">
            تعداد نمایش
        </label>

        <select
            wire:model="formData.pictureMode"
            class="form-select">

            <option value="background">
                تصاویر با بکگراند
            </option>
            <option value="transparent">
                بدون بکگراند
            </option>
        </select>

    </div>
    <div class="col-md-3">

        <label class="form-label">
            نوع نمایش
        </label>

        <select
            wire:model="formData.view"
            class="form-select">

            <option value="1">
                نوع 1
            </option>

            <option value="2">
                نوع 2
            </option>

            <option value="3">
                نوع 3
            </option>

        </select>

    </div>


    {{-- انتخاب دستی محصولات --}}
    @if(($formData['mode'] ?? null) === 'manual')

        <div class="col-md-12">

            <label class="form-label">
                انتخاب محصولات

            </label>

            <select
                multiple
                wire:model="formData.product_ids"
                class="form-select"
                style="height:220px">

                @foreach($products ?? [] as $product)

                    <option value="{{ $product->id }}">
                        {{ $product->title }}
                    </option>

                @endforeach

            </select>

            @error('formData.product_ids')
            <div class="text-danger mt-1">
                {{ $message }}
            </div>
            @enderror

        </div>

    @endif


</div>
