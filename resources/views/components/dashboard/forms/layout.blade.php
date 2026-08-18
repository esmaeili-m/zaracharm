<div class="row g-3">

    {{-- عنوان سکشن --}}
    <div class="col-md-12">
        <label class="form-label">
            عنوان نمایشی
        </label>

        <input
            type="text"
            wire:model.lazy="title"
            class="form-control @error('title') is-invalid @enderror"
            placeholder="مثلاً جدیدترین محصولات">

        @error('title')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
        @enderror
    </div>


    {{-- عرض دسکتاپ --}}
    <div class="col-md-4">
        <label class="form-label">
            عرض دسکتاپ
        </label>

        <select
            wire:model="layout.grid.lg"
            class="form-select">

            @for($i = 1; $i <= 12; $i++)
                <option value="{{ $i }}">
                    {{ $i }}/12
                </option>
            @endfor

        </select>
    </div>


    {{-- عرض تبلت --}}
    <div class="col-md-4">
        <label class="form-label">
            عرض تبلت
        </label>

        <select
            wire:model="layout.grid.md"
            class="form-select">

            @for($i = 1; $i <= 12; $i++)
                <option value="{{ $i }}">
                    {{ $i }}/12
                </option>
            @endfor

        </select>
    </div>


    {{-- عرض موبایل --}}
    <div class="col-md-4">
        <label class="form-label">
            عرض موبایل
        </label>

        <select
            wire:model="layout.grid.default"
            class="form-select">

            @for($i = 1; $i <= 12; $i++)
                <option value="{{ $i }}">
                    {{ $i }}/12
                </option>
            @endfor

        </select>
    </div>



    {{-- Padding بالا --}}
    <div class="col-md-6">

        <label class="form-label">
            فاصله بالا
        </label>

        <select
            wire:model="layout.spacing.padding_top"
            class="form-select">

            @foreach([0,2,4,6,8,12,16,20,24,32] as $item)

                <option value="{{ $item }}">
                    {{ $item }}
                </option>

            @endforeach

        </select>

    </div>


    {{-- Padding پایین --}}
    <div class="col-md-6">

        <label class="form-label">
            فاصله پایین
        </label>

        <select
            wire:model="layout.spacing.padding_bottom"
            class="form-select">

            @foreach([0,2,4,6,8,12,16,20,24,32] as $item)

                <option value="{{ $item }}">
                    {{ $item }}
                </option>

            @endforeach

        </select>

    </div>



    {{-- Container --}}
    <div class="col-md-6">

        <label class="form-label">
            نوع نمایش
        </label>

        <select
            wire:model="layout.container"
            class="form-select">

            <option value="boxed">
                محدود شده
            </option>

            <option value="full">
                تمام عرض
            </option>

        </select>

    </div>



    {{-- Visibility --}}
    <div class="col-md-6">

        <label class="form-label">
            نمایش
        </label>

        <div class="form-check mt-2">

            <input
                type="checkbox"
                class="form-check-input"
                wire:model="layout.visibility.mobile">

            <label class="form-check-label">
                نمایش در موبایل
            </label>

        </div>


        <div class="form-check">

            <input
                type="checkbox"
                class="form-check-input"
                wire:model="layout.visibility.desktop">

            <label class="form-check-label">
                نمایش در دسکتاپ
            </label>

        </div>

    </div>

</div>
