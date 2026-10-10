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
    @include('dashboard.forms.partials.picture-mode')
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

    {{-- چیدمان: زیر هم یا اسلایدر یک‌خطی --}}
    <div class="col-md-3">
        <label class="form-label">چیدمان محصولات</label>
        <select wire:model="formData.layout" class="form-select @error('formData.layout') is-invalid @enderror">
            <option value="">پیش‌فرض طرح</option>
            <option value="grid">زیر هم (شبکه‌ای)</option>
            <option value="slider">اسلایدر در یک خط</option>
        </select>
        <div class="form-text">پیش‌فرض: نوع ۱ و ۲ زیر هم، نوع ۳ اسلایدری.</div>
        @error('formData.layout') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>


    {{-- منبع محصولات: همه یا یک برند --}}
    <div class="col-md-3">

        <label class="form-label">
            منبع محصولات
        </label>

        <select
            wire:model.live="formData.source"
            class="form-select @error('formData.source') is-invalid @enderror">

            <option value="all">
                همه محصولات
            </option>

            <option value="brand">
                محصولات یک برند
            </option>

        </select>

        @error('formData.source')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror

    </div>

    @if(($formData['source'] ?? 'all') === 'brand')

        <div class="col-md-3">

            <label class="form-label">
                برند
            </label>

            <select
                wire:model.live="formData.brand_id"
                class="form-select @error('formData.brand_id') is-invalid @enderror">

                <option value="">
                    انتخاب برند
                </option>

                @foreach($brands ?? [] as $brand)
                    <option value="{{ $brand->id }}">
                        {{ $brand->title }}
                    </option>
                @endforeach

            </select>

            @error('formData.brand_id')
            <div class="invalid-feedback d-block">
                {{ $message }}
            </div>
            @enderror

        </div>

    @endif


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

                @php
                    $manualProducts = collect($products ?? []);
                    if (($formData['source'] ?? 'all') === 'brand') {
                        $manualProducts = $manualProducts->where('brand_id', (int) ($formData['brand_id'] ?? 0));
                    }
                @endphp

                @foreach($manualProducts as $product)

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
