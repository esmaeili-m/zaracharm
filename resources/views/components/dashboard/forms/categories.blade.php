<div class="row g-3">

    {{-- روش انتخاب --}}
    <div class="col-md-3">

        <label class="form-label">
            روش نمایش دسته‌بندی‌ها
        </label>
        <select
            wire:model.live="formData.mode"
            class="form-select">

            <option value="latest">
               اخرین  ها
            </option>
            <option value="manual">
                انتخاب دستی
            </option>

            <option value="random">
                انتخاب تصادفی
            </option>

            <option value="sales">
                بر اساس بیشترین فروش
            </option>

            <option value="views">
                بر اساس بیشترین بازدید
            </option>
            <option value="all">
                همه
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

    <div class="col-md-3">

        <label class="form-label">
            تعداد نمایش
        </label>

        <select
            wire:model="formData.limit"
            class="form-select">

            <option value="4">۴</option>
            <option value="6">۶</option>
            <option value="8">۸</option>
            <option value="10">۱۰</option>

        </select>

    </div>


    {{-- انتخاب دستی دسته بندی --}}
    @if(($formData['mode'] ?? null) === 'manual')

        <div class="col-md-12">

            <label class="form-label">
                دسته‌بندی‌ها
            </label>

            <select
                multiple
                wire:model="formData.category_ids"
                class="form-select"
                style="height:200px">

                @foreach($categories ?? [] as $category)

                    <option value="{{ $category->id }}">
                        {{ $category->title }}
                    </option>

                @endforeach

            </select>

        </div>

    @endif



    {{-- تعداد نمایش --}}


</div>
