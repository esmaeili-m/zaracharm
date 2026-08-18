<div class="row g-3">
    <div class="col-md-6">

        <label class="form-label">
            روش نمایش مقالات
        </label>

        <select
            wire:model.live="formData.mode"
            class="form-select">

            <option value="latest">
                جدیدترین مقالات
            </option>


            <option value="views">
                پربازدیدترین مقالات
            </option>

            <option value="random">
                پیشنهاد لحظه‌ای
            </option>

            <option value="manual">
                انتخاب دستی
            </option>

        </select>

    </div>

    <div class="col-md-3">

        <label class="form-label">
            تعداد نمایش
        </label>

        <select
            wire:model="formData.limit"
            class="form-select">

            <option value="4">
                ۴ مقاله
            </option>

            <option value="8">
                ۸ مقاله
            </option>

            <option value="12">
                ۱۲ مقاله
            </option>

            <option value="16">
                ۱۶ مقاله
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


    {{-- انتخاب دستی مقالات --}}
    @if(($formData['mode'] ?? null) === 'manual')

        <div class="col-md-12">

            <label class="form-label">
                انتخاب مقالات

            </label>

            <select
                multiple
                wire:model="formData.article_ids"
                class="form-select"
                style="height:220px">

                @foreach($articles ?? [] as $article)

                    <option value="{{ $article->id }}">
                        {{ $article->title }}
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
