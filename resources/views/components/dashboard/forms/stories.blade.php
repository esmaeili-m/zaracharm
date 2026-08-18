<div class="row g-3">
    <div class="col-md-6">

        <label class="form-label">
            روش نمایش استوری
        </label>

        <select
            wire:model.live="formData.mode"
            class="form-select">

            <option value="latest">
                جدیدترین استوری
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
    <div class="col-md-6">

        <label class="form-label">
            تعداد نمایش
        </label>

        <select
            wire:model="formData.limit"
            class="form-select">

            <option value="4">
                ۴ استوری
            </option>

            <option value="8">
                ۸ استوری
            </option>

            <option value="12">
                ۱۲ استوری
            </option>

            <option value="16">
                ۱۶ استوری
            </option>

        </select>

    </div>

    {{-- انتخاب دستی استوریات --}}
    @if(($formData['mode'] ?? null) === 'manual')

        <div class="col-md-12">

            <label class="form-label">
                انتخاب استوری

            </label>

            <select
                multiple
                wire:model="formData.story_ids"
                class="form-select"
                style="height:220px">

                @foreach($stories ?? [] as $story)

                    <option value="{{ $story->id }}">
                        {{ $story->user }}
                    </option>

                @endforeach

            </select>

            @error('formData.story_ids')
            <div class="text-danger mt-1">
                {{ $message }}
            </div>
            @enderror

        </div>

    @endif
</div>
