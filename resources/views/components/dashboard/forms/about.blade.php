<div class="row g-3">
    <div class="col-md-3 mb-2">

        <label class="form-label">
            تصویر
        </label>

        {{-- اینجا کامپوننت انتخاب تصویر خودت --}}
        <input
            type="text"
            wire:model="formData.images.image_1"
            class="form-control"
        >

        @error('formData.images')
        <div class="text-danger mt-1">
            {{ $message }}
        </div>
        @enderror

    </div>
    <div class="col-md-2 mb-2">

        <label class="form-label">
            مشتری های فعال
        </label>

        {{-- اینجا کامپوننت انتخاب تصویر خودت --}}
        <input
            type="text"
            wire:model="formData.client"
            class="form-control"
        >

        @error('formData.client')
        <div class="text-danger mt-1">
            {{ $message }}
        </div>
        @enderror

    </div>
    <div class="col-md-2 mb-2">

        <label class="form-label">
        رضایت خدمات
        </label>

        {{-- اینجا کامپوننت انتخاب تصویر خودت --}}
        <input
            type="text"
            wire:model="formData.service"
            class="form-control"
        >

        @error('formData.service')
        <div class="text-danger mt-1">
            {{ $message }}
        </div>
        @enderror

    </div>
    <div class="col-md-2 mb-2">

        <label class="form-label">
            نمایندگی رسمی
        </label>

        {{-- اینجا کامپوننت انتخاب تصویر خودت --}}
        <input
            type="text"
            wire:model="formData.shop"
            class="form-control"
        >

        @error('formData.shop')
        <div class="text-danger mt-1">
            {{ $message }}
        </div>
        @enderror

    </div>
    <div class="col-md-2 mb-2">

        <label class="form-label">
            پشتیبانی آنلاین
        </label>

        {{-- اینجا کامپوننت انتخاب تصویر خودت --}}
        <input
            type="text"
            wire:model="formData.support"
            class="form-control"
        >

        @error('formData.support')
        <div class="text-danger mt-1">
            {{ $message }}
        </div>
        @enderror

    </div>



    <div class="col-md-12">

        <label class="form-label">
            توضیحات
        </label>

        <textarea
            wire:model="formData.description"
            class="form-control"
            rows="5"
            placeholder="توضیحات کوتاهی درباره مجموعه، فعالیت‌ها و خدمات..."
        ></textarea>

        @error('formData.description')
        <div class="text-danger mt-1">
            {{ $message }}
        </div>
        @enderror

    </div>
</div>
