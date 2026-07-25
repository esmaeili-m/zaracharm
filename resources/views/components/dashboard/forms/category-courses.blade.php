<div class="row">

    <div class="col-md-6 mb-3">
        <label class="form-label">
            برچسب بالا
        </label>

        <input
            type="text"
            wire:model.lazy="formData.tagline"
            class="form-control"
            placeholder="دسته بندی">
    </div>

    <div class="col-md-12 mb-3">
        <label class="form-label">
            عنوان اصلی
        </label>

        <textarea
            rows="3"
            wire:model.lazy="formData.title"
            class="form-control"
            placeholder="دوره های رایگان برنامه نویسی تحت ویندوز">
        </textarea>
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">
            تصویر سمت راست
        </label>

        <input
            type="file"
            wire:model="media.image_1"
            class="form-control">
    </div>

</div>
