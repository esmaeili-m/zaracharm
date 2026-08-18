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
