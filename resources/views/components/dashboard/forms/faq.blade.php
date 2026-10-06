<div class="row g-3">

    {{-- روش انتخاب سوالات --}}
    <div class="col-md-4">

        <label class="form-label">
            روش نمایش سوالات
        </label>

        <select
            wire:model.live="formData.mode"
            class="form-select @error('formData.mode') is-invalid @enderror">

            <option value="latest">
                بر اساس ترتیب سوالات متداول
            </option>

            <option value="category">
                سوالات یک دسته‌بندی
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
    @if(($formData['mode'] ?? null) !== 'manual')
        <div class="col-md-4">

            <label class="form-label">
                تعداد نمایش
            </label>

            <select
                wire:model="formData.limit"
                class="form-select">

                <option value="4">۴ سوال</option>
                <option value="6">۶ سوال</option>
                <option value="8">۸ سوال</option>
                <option value="10">۱۰ سوال</option>
                <option value="20">۲۰ سوال</option>

            </select>

        </div>
    @endif


    {{-- چیدمان --}}
    <div class="col-md-4">

        <label class="form-label">
            چیدمان
        </label>

        <select
            wire:model="formData.columns"
            class="form-select">

            <option value="1">یک ستونه</option>
            <option value="2">دو ستونه</option>

        </select>

    </div>


    {{-- توضیح زیر عنوان --}}
    <div class="col-md-12">

        <label class="form-label">
            توضیح زیر عنوان
        </label>

        <input
            type="text"
            wire:model="formData.description"
            class="form-control @error('formData.description') is-invalid @enderror"
            placeholder="مثلاً: پاسخ رایج‌ترین پرسش‌های شما">

        @error('formData.description')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror

    </div>


    {{-- دسته‌بندی --}}
    @if(($formData['mode'] ?? null) === 'category')
        <div class="col-md-6">

            <label class="form-label">
                دسته‌بندی
            </label>

            <select
                wire:model="formData.category_id"
                class="form-select @error('formData.category_id') is-invalid @enderror">

                <option value="">انتخاب دسته‌بندی</option>

                @foreach($categories ?? [] as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->title }}
                    </option>
                @endforeach

            </select>

            @error('formData.category_id')
            <div class="invalid-feedback d-block">
                {{ $message }}
            </div>
            @enderror

        </div>
    @endif


    {{-- انتخاب دستی --}}
    @if(($formData['mode'] ?? null) === 'manual')
        <div class="col-md-12">

            <label class="form-label">
                سوالات
            </label>

            <select
                multiple
                wire:model="formData.faq_ids"
                class="form-select @error('formData.faq_ids') is-invalid @enderror"
                style="height:240px">

                @foreach($faqs ?? [] as $faq)
                    <option value="{{ $faq->id }}">
                        {{ \Illuminate\Support\Str::limit($faq->question, 120) }}
                    </option>
                @endforeach

            </select>

            @error('formData.faq_ids')
            <div class="invalid-feedback d-block">
                {{ $message }}
            </div>
            @enderror

        </div>
    @endif


    {{-- تنظیمات نمایش --}}
    <div class="col-md-12">
        <div class="row g-3">

            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="faq-open-first" wire:model="formData.open_first">
                    <label class="form-check-label" for="faq-open-first">
                        باز بودن اولین سوال
                    </label>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="faq-show-search" wire:model="formData.show_search">
                    <label class="form-check-label" for="faq-show-search">
                        نمایش جستجو در سوالات
                    </label>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="faq-schema" wire:model="formData.schema">
                    <label class="form-check-label" for="faq-schema">
                        افزودن اسکیما FAQ برای سئو
                    </label>
                </div>
            </div>

        </div>
    </div>

</div>
