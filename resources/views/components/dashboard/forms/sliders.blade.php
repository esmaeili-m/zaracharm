<div class="row g-3">


    {{-- انتخاب اسلایدر --}}
    <div class="col-md-12 mb-3">

        <label class="form-label">
            اسلایدر
        </label>
        <select
            wire:model="formData.slider_id"
            class="form-select @error('formData.slider_id') is-invalid @enderror">


            <option value="">
                انتخاب کنید
            </option>


            @foreach($sliders ?? [] as $slider)

                <option value="{{ $slider->id }}">
                    {{ $slider->title }}
                </option>

            @endforeach


        </select>


        @error('formData.slider_id')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror

    </div>



    {{-- ارتفاع --}}
    <div class="col-md-12">
        <label class="form-label">نوع ارتفاع</label>
        <select wire:model.live="formData.height_mode" class="form-select @error('formData.height_mode') is-invalid @enderror">
            <option value="fixed">ثابت (همه اسلایدها دقیقاً همین ارتفاع؛ تصویر برش می‌خورد)</option>
            <option value="max">حداکثر (تصویر کوتاه‌تر اندازه خودش، بلندتر برش می‌خورد)</option>
        </select>
        @error('formData.height_mode') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">ارتفاع در موبایل (پیکسل)</label>
        <input type="number" min="120" max="800" step="10" wire:model="formData.height_mobile"
               class="form-control @error('formData.height_mobile') is-invalid @enderror" placeholder="200">
        @error('formData.height_mobile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">پیشنهادی: ۱۸۰ تا ۲۴۰</div>
    </div>

    <div class="col-md-6">
        <label class="form-label">ارتفاع در دسکتاپ (پیکسل)</label>
        <input type="number" min="150" max="1000" step="10" wire:model="formData.height_desktop"
               class="form-control @error('formData.height_desktop') is-invalid @enderror" placeholder="480">
        @error('formData.height_desktop') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">پیشنهادی: ۴۰۰ تا ۵۲۰ — تبلت میانگین این دو مقدار است.</div>
    </div>

    {{-- Autoplay --}}
    <div class="col-md-6">

        <div class="form-check mt-2">

            <input
                type="checkbox"
                class="form-check-input"
                wire:model="formData.autoplay"
                id="autoplay">


            <label
                class="form-check-label"
                for="autoplay">

                حرکت خودکار اسلایدها

            </label>

        </div>

    </div>



    {{-- Loop --}}
    <div class="col-md-6">

        <div class="form-check mt-2">

            <input
                type="checkbox"
                class="form-check-input"
                wire:model="formData.loop"
                id="loop">


            <label
                class="form-check-label"
                for="loop">

                تکرار اسلایدها

            </label>

        </div>

    </div>



    {{-- Speed --}}
    <div class="col-md-6 mt-3">

        <label class="form-label">
            سرعت تغییر اسلاید
        </label>


        <select
            wire:model="formData.speed"
            class="form-select">

            <option value="3000">
                ۳ ثانیه
            </option>

            <option value="5000">
                ۵ ثانیه
            </option>

            <option value="7000">
                ۷ ثانیه
            </option>

        </select>

    </div>


</div>
