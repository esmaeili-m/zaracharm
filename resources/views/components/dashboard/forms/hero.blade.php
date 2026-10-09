@php
    $heroMedia = $selectItem?->media?->firstWhere('collection', 'hero_media');
    $heroPoster = $selectItem?->media?->firstWhere('collection', 'hero_poster');
    $heroStyle = $formData['style'] ?? 'split';
    $isMediaVideo = fn ($m) => $m && ($m->type === 'video' || str_starts_with((string) $m->mime_type, 'video/'));
@endphp

<div class="row g-3">

    {{-- ================= چیدمان ================= --}}
    <div class="col-12"><h6 class="fw-semibold mb-0">چیدمان</h6></div>

    <div class="col-md-4">
        <label class="form-label">سبک نمایش</label>
        <select wire:model.live="formData.style" class="form-select @error('formData.style') is-invalid @enderror">
            <option value="split">دوستونه (متن + قاب تصویر/ویدیو)</option>
            <option value="overlay">تمام‌عرض (متن روی تصویر/ویدیو)</option>
        </select>
        @error('formData.style') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    @if($heroStyle === 'split')
        <div class="col-md-4">
            <label class="form-label">جای تصویر در دسکتاپ</label>
            <select wire:model="formData.media_position" class="form-select @error('formData.media_position') is-invalid @enderror">
                <option value="end">سمت چپ (متن سمت راست)</option>
                <option value="start">سمت راست (متن سمت چپ)</option>
            </select>
        </div>
    @else
        <div class="col-md-4">
            <label class="form-label">تیرگی روی تصویر: {{ (int) ($formData['overlay_opacity'] ?? 45) }}٪</label>
            <input type="range" min="0" max="90" step="5" wire:model.live="formData.overlay_opacity" class="form-range">
            <div class="form-text">برای خوانایی متن روی تصاویر روشن بیشتر کنید.</div>
        </div>
    @endif

    <div class="col-md-4 d-flex align-items-center">
        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" id="hero_animate" wire:model="formData.animate">
            <label class="form-check-label" for="hero_animate">انیمیشن‌ها فعال باشد</label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ $heroStyle === 'overlay' ? 'ارتفاع سکشن' : 'ارتفاع قاب تصویر' }} در موبایل (پیکسل)</label>
        <input type="number" min="240" max="1000" step="10" wire:model="formData.height_mobile" class="form-control @error('formData.height_mobile') is-invalid @enderror" placeholder="{{ $heroStyle === 'overlay' ? 560 : 320 }}">
        @error('formData.height_mobile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">{{ $heroStyle === 'overlay' ? 'ارتفاع سکشن' : 'ارتفاع قاب تصویر' }} در دسکتاپ (پیکسل)</label>
        <input type="number" min="320" max="1200" step="10" wire:model="formData.height_desktop" class="form-control @error('formData.height_desktop') is-invalid @enderror" placeholder="{{ $heroStyle === 'overlay' ? 680 : 520 }}">
        @error('formData.height_desktop') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    {{-- ================= متن ================= --}}
    <div class="col-12 mt-4"><h6 class="fw-semibold mb-0">متن</h6></div>

    <div class="col-md-6">
        <label class="form-label">متن کوچک بالای عنوان</label>
        <input type="text" wire:model="formData.eyebrow" class="form-control @error('formData.eyebrow') is-invalid @enderror" placeholder="مثلاً: کالکشن جدید پاییز ۱۴۰۵">
        @error('formData.eyebrow') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">بخش برجسته عنوان (رنگی و متحرک)</label>
        <input type="text" wire:model="formData.highlight" class="form-control @error('formData.highlight') is-invalid @enderror" placeholder="مثلاً: چرم طبیعی">
        @error('formData.highlight') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">اگر داخل عنوان باشد همان‌جا رنگی می‌شود، وگرنه خط دوم عنوان است.</div>
    </div>
    <div class="col-12">
        <label class="form-label">عنوان اصلی</label>
        <input type="text" wire:model="formData.heading" class="form-control @error('formData.heading') is-invalid @enderror" placeholder="مثلاً: اصالت چرم طبیعی در هر جزئیات">
        @error('formData.heading') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label">توضیح</label>
        <textarea rows="3" wire:model="formData.description" class="form-control @error('formData.description') is-invalid @enderror" placeholder="یک یا دو جمله درباره مزیت اصلی فروشگاه..."></textarea>
        @error('formData.description') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    {{-- ================= دکمه‌ها ================= --}}
    <div class="col-12 mt-4"><h6 class="fw-semibold mb-0">دکمه‌ها</h6></div>

    <div class="col-md-3">
        <label class="form-label">متن دکمه اصلی</label>
        <input type="text" wire:model="formData.primary_text" class="form-control @error('formData.primary_text') is-invalid @enderror" placeholder="خرید کنید">
        @error('formData.primary_text') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">لینک دکمه اصلی</label>
        <input type="text" dir="ltr" wire:model="formData.primary_link" class="form-control @error('formData.primary_link') is-invalid @enderror" placeholder="/categories/bags">
        @error('formData.primary_link') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">متن دکمه دوم</label>
        <input type="text" wire:model="formData.secondary_text" class="form-control @error('formData.secondary_text') is-invalid @enderror" placeholder="درباره ما">
        @error('formData.secondary_text') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">لینک دکمه دوم</label>
        <input type="text" dir="ltr" wire:model="formData.secondary_link" class="form-control @error('formData.secondary_link') is-invalid @enderror" placeholder="/about-us">
        @error('formData.secondary_link') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    {{-- ================= آمار ================= --}}
    <div class="col-12 mt-4">
        <h6 class="fw-semibold mb-0">آمار (اختیاری، با شمارش متحرک)</h6>
        <div class="form-text">عدد را با پیشوند/پسوند بنویسید، مثلاً «+۱۲۰۰» یا «۹۸٪».</div>
    </div>
    @foreach([1, 2, 3] as $i)
        <div class="col-md-2 col-6">
            <label class="form-label">مقدار {{ $i }}</label>
            <input type="text" wire:model="formData.stat{{ $i }}_value" class="form-control @error('formData.stat' . $i . '_value') is-invalid @enderror" placeholder="{{ ['+۱۲۰۰', '۹۸٪', '۲۴/۷'][$i - 1] }}">
        </div>
        <div class="col-md-2 col-6">
            <label class="form-label">عنوان {{ $i }}</label>
            <input type="text" wire:model="formData.stat{{ $i }}_label" class="form-control @error('formData.stat' . $i . '_label') is-invalid @enderror" placeholder="{{ ['مشتری راضی', 'رضایت', 'پشتیبانی'][$i - 1] }}">
        </div>
    @endforeach
    @foreach([1, 2, 3] as $i)
        @error('formData.stat' . $i . '_value') <div class="col-12 text-danger small">{{ $message }}</div> @enderror
        @error('formData.stat' . $i . '_label') <div class="col-12 text-danger small">{{ $message }}</div> @enderror
    @endforeach

    @if($heroStyle === 'split')
        <div class="col-md-6">
            <label class="form-label">کارت شناور: عنوان</label>
            <input type="text" wire:model="formData.badge_title" class="form-control @error('formData.badge_title') is-invalid @enderror" placeholder="ضمانت اصالت کالا">
            @error('formData.badge_title') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">کارت شناور: توضیح</label>
            <input type="text" wire:model="formData.badge_text" class="form-control @error('formData.badge_text') is-invalid @enderror" placeholder="۷ روز ضمانت بازگشت">
            @error('formData.badge_text') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
    @endif

    {{-- ================= تصویر / ویدیو ================= --}}
    <div class="col-12 mt-4">
        <h6 class="fw-semibold mb-0">تصویر یا ویدیو</h6>
        <div class="form-text">تصویر: jpg/png/webp حداکثر ۵ مگابایت. ویدیو: mp4/webm حداکثر ۳۰ مگابایت (بی‌صدا و حلقه‌ای پخش می‌شود؛ زیر ۱۰ مگابایت پیشنهاد می‌شود).</div>
    </div>

    <div class="col-md-6">
        <label class="form-label">تصویر یا ویدیوی اصلی</label>
        <input type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" wire:model="formData.images.hero_media"
               class="form-control @error('formData.images.hero_media') is-invalid @enderror">
        <div wire:loading wire:target="formData.images.hero_media" class="small text-muted mt-1">در حال بارگذاری... (ویدیو ممکن است کمی طول بکشد)</div>
        @error('formData.images.hero_media') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

        @php($pendingMedia = $formData['images']['hero_media'] ?? null)
        @if($pendingMedia instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && str_starts_with((string) $pendingMedia->getMimeType(), 'image/'))
            <img src="{{ $pendingMedia->temporaryUrl() }}" class="img-thumbnail mt-2" style="max-height: 140px" alt="">
        @elseif($heroMedia)
            <div class="d-flex align-items-center gap-2 mt-2">
                @if($isMediaVideo($heroMedia))
                    <video src="{{ url('/storage/' . $heroMedia->file_path) }}" class="rounded border" style="max-height: 120px; max-width: 200px" muted controls preload="metadata"></video>
                @else
                    <img src="{{ url('/storage/' . $heroMedia->file_path) }}" class="img-thumbnail" style="max-height: 120px" alt="">
                @endif
                <button type="button" wire:click="removeHeroMedia('hero_media')" wire:confirm="فایل فعلی حذف شود؟" class="btn btn-sm btn-danger-light">حذف</button>
            </div>
        @endif
    </div>

    <div class="col-md-6">
        <label class="form-label">تصویر پوستر ویدیو / جایگزین موبایل (اختیاری)</label>
        <input type="file" accept="image/jpeg,image/png,image/webp" wire:model="formData.images.hero_poster"
               class="form-control @error('formData.images.hero_poster') is-invalid @enderror">
        <div wire:loading wire:target="formData.images.hero_poster" class="small text-muted mt-1">در حال بارگذاری...</div>
        @error('formData.images.hero_poster') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">تا لود شدن ویدیو نمایش داده می‌شود.</div>

        @php($pendingPoster = $formData['images']['hero_poster'] ?? null)
        @if($pendingPoster instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
            <img src="{{ $pendingPoster->temporaryUrl() }}" class="img-thumbnail mt-2" style="max-height: 140px" alt="">
        @elseif($heroPoster)
            <div class="d-flex align-items-center gap-2 mt-2">
                <img src="{{ url('/storage/' . $heroPoster->file_path) }}" class="img-thumbnail" style="max-height: 120px" alt="">
                <button type="button" wire:click="removeHeroMedia('hero_poster')" wire:confirm="پوستر حذف شود؟" class="btn btn-sm btn-danger-light">حذف</button>
            </div>
        @endif
    </div>

    <div class="col-md-8">
        <label class="form-label">یا لینک مستقیم ویدیو (mp4، اختیاری)</label>
        <input type="url" dir="ltr" wire:model="formData.video_url" class="form-control @error('formData.video_url') is-invalid @enderror" placeholder="https://cdn.example.com/hero.mp4">
        @error('formData.video_url') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">برای ویدیوهای حجیم یا هاست اشتراکی؛ اگر پر شود به جای فایل آپلودی پخش می‌شود.</div>
    </div>

    <div class="col-md-4 d-flex align-items-center">
        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" id="hero_video_mobile" wire:model="formData.video_on_mobile">
            <label class="form-check-label" for="hero_video_mobile">پخش ویدیو در موبایل</label>
        </div>
    </div>
    <div class="col-12 form-text mt-0">اگر خاموش باشد و پوستر داشته باشید، در موبایل فقط پوستر نمایش داده می‌شود (صرفه‌جویی در اینترنت کاربر).</div>
</div>
