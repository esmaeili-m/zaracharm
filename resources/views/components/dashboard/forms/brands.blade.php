<div class="row g-3">

    {{-- روش نمایش برندها --}}
    <div class="col-md-4">

        <label class="form-label">
            روش نمایش برندها
        </label>

        <select
            wire:model.live="formData.mode"
            class="form-select @error('formData.mode') is-invalid @enderror">

            <option value="popular">
                بیشترین تعداد محصول
            </option>

            <option value="latest">
                جدیدترین برندها
            </option>

            <option value="all">
                همه برندها
            </option>

            <option value="manual">
                انتخاب دستی
            </option>

        </select>

        @error('formData.mode')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror

    </div>


    {{-- تعداد نمایش --}}
    @if(!in_array(($formData['mode'] ?? null), ['manual', 'all'], true))
        <div class="col-md-4">

            <label class="form-label">
                تعداد نمایش
            </label>

            <select
                wire:model="formData.limit"
                class="form-select">

                <option value="3">۳ برند</option>
                <option value="6">۶ برند</option>
                <option value="9">۹ برند</option>
                <option value="12">۱۲ برند</option>

            </select>

        </div>
    @endif

    @include('dashboard.forms.partials.picture-mode', ['col' => 'col-md-4'])


    {{-- انتخاب دستی برندها --}}
    @if(($formData['mode'] ?? null) === 'manual')

        <div class="col-md-12">

            <label class="form-label">
                برندها
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

            @error('formData.brand_ids')
            <div class="invalid-feedback d-block">
                {{ $message }}
            </div>
            @enderror

        </div>

    @endif

</div>
