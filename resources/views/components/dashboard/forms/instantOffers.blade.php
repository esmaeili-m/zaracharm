<div class="row g-3">

    {{-- روش نمایش --}}
    <div class="col-md-6">

        <label class="form-label">
            روش نمایش محصولات
        </label>

        <select
            wire:model.live="formData.mode"
            class="form-select">

            <option value="random">
                انتخاب تصادفی
            </option>

            <option value="latest">
                جدیدترین محصولات
            </option>

            <option value="sales">
                پرفروش‌ترین
            </option>

            <option value="views">
                پربازدیدترین
            </option>

            <option value="manual">
                انتخاب دستی
            </option>

        </select>

    </div>

    {{-- تعداد نمایش --}}
    <div class="col-md-6">

        <label class="form-label">
            تعداد نمایش
        </label>

        <select
            wire:model="formData.limit"
            class="form-select">

            @for($i = 4; $i <= 20; $i += 2)
                <option value="{{ $i }}">
                    {{ $i }} محصول
                </option>
            @endfor

        </select>

    </div>

    {{-- انتخاب دستی محصولات --}}
    @if(($formData['mode'] ?? null) === 'manual')

        <div class="col-md-12">

            <label class="form-label">
                محصولات
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

            <small class="text-muted">
                در حالت انتخاب دستی فقط محصولات انتخاب‌شده نمایش داده می‌شوند.
            </small>

        </div>

    @endif

</div>
