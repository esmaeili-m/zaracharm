<div class="row g-3">

    <div class="col-md-4 mb-3">

        <label class="form-label">
            کمپین
        </label>
        <select
            wire:model="formData.campaign_id"
            class="form-select @error('formData.campaign_id') is-invalid @enderror">


            <option value="">
                انتخاب کنید
            </option>


            @foreach($campaigns ?? [] as $campaign)

                <option value="{{ $campaign->id }}">
                    {{ $campaign->title }}
                </option>

            @endforeach


        </select>


        @error('formData.campaign_id')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror

    </div>

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
    {{-- تنظیمات نمایش کمپین --}}
    <div class="col-md-12">


            <div class="card-header bg-transparent">
                <h6 class="mb-2">
                    تنظیمات نمایش
                </h6>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    {{-- نمایش عنوان --}}
                    <div class="col-md-6">

                        <div class="form-check form-switch">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="show_title"
                                wire:model="formData.show_title">

                            <label
                                class="form-check-label"
                                for="show_title">

                                نمایش نام کمپین

                            </label>

                        </div>

                        <div class="form-text">
                            نام کمپین در ابتدای سکشن نمایش داده شود.
                        </div>

                    </div>


                    {{-- نمایش توضیحات --}}
                    <div class="col-md-6">

                        <div class="form-check form-switch">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="show_description"
                                wire:model="formData.show_description">

                            <label
                                class="form-check-label"
                                for="show_description">

                                نمایش توضیح کوتاه

                            </label>

                        </div>

                        <div class="form-text">
                            توضیح کوتاه کمپین زیر عنوان نمایش داده شود.
                        </div>

                    </div>


                    {{-- نمایش تصویر --}}
                    <div class="col-md-6">

                        <div class="form-check form-switch">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="show_image"
                                wire:model="formData.show_image">

                            <label
                                class="form-check-label"
                                for="show_image">

                                نمایش تصویر کمپین

                            </label>

                        </div>

                        <div class="form-text">
                            تصویر یا بنر تعریف‌شده برای کمپین نمایش داده شود.
                        </div>

                    </div>


                    {{-- نمایش زمان --}}
                    <div class="col-md-6">

                        <div class="form-check form-switch">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="show_timer"
                                wire:model="formData.show_timer">

                            <label
                                class="form-check-label"
                                for="show_timer">

                                نمایش زمان باقی‌مانده

                            </label>

                        </div>

                        <div class="form-text">
                            زمان باقی‌مانده تا پایان کمپین نمایش داده شود.
                        </div>

                    </div>

                </div>

            </div>


    </div>
</div>
