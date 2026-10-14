@php
    $bannerMedia = $selectItem?->media ?? collect();
    $desktopOf = fn ($uid) => $bannerMedia->first(fn ($m) => $m->collection === \App\Support\Sections\Banners::DESKTOP && $m->purpose === $uid);
    $mobileOf = fn ($uid) => $bannerMedia->first(fn ($m) => $m->collection === \App\Support\Sections\Banners::MOBILE && $m->purpose === $uid);
    $bannerItems = array_values($formData['items'] ?? []);
    $isTemp = fn ($f) => $f instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
@endphp

<div class="row g-3">

    {{-- ================= نمایش ================= --}}
    <div class="col-12"><h6 class="fw-semibold mb-0">نحوه نمایش</h6></div>

    <div class="col-md-3">
        <label class="form-label">چیدمان</label>
        <select wire:model.live="formData.layout" class="form-select @error('formData.layout') is-invalid @enderror">
            <option value="grid">شبکه‌ای (کنار هم)</option>
            <option value="slider">اسلایدی (یکی‌یکی، قابل کشیدن)</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">تعداد ستون در دسکتاپ</label>
        <select wire:model="formData.columns_desktop" class="form-select">
            @foreach([1, 2, 3, 4] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">تعداد ستون در موبایل</label>
        <select wire:model="formData.columns_mobile" class="form-select">
            <option value="1">۱</option>
            <option value="2">۲</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">نسبت ابعاد</label>
        <select wire:model="formData.ratio" class="form-select @error('formData.ratio') is-invalid @enderror">
            @foreach(\App\Support\Sections\Banners::RATIOS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        @error('formData.ratio')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">فاصله بین بنرها</label>
        <select wire:model="formData.gap" class="form-select">
            <option value="sm">کم</option>
            <option value="md">متوسط</option>
            <option value="lg">زیاد</option>
        </select>
    </div>
    <div class="col-md-9 d-flex flex-wrap align-items-end gap-4">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="bn_rounded" wire:model="formData.rounded"><label class="form-check-label" for="bn_rounded">گوشه‌های گرد</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="bn_zoom" wire:model="formData.hover_zoom"><label class="form-check-label" for="bn_zoom">بزرگ‌نمایی هنگام هاور</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="bn_text" wire:model="formData.show_text"><label class="form-check-label" for="bn_text">نمایش عنوان روی بنر</label></div>
        @if(($formData['layout'] ?? 'grid') === 'slider')
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="bn_auto" wire:model="formData.autoplay"><label class="form-check-label" for="bn_auto">پخش خودکار</label></div>
        @endif
    </div>
    <div class="col-12 form-text mt-0">
        در حالت اسلایدی، «تعداد ستون» یعنی تعداد بنرهای قابل مشاهده هم‌زمان. اندازه پیشنهادی: بنر تمام‌عرض ۱۹۲۰×۶۰۰، بنر دوتایی ۱۲۰۰×۶۰۰ پیکسل؛ برای موبایل تصویر جدا (مثلاً ۸۰۰×۸۰۰) بگذارید.
    </div>

    {{-- ================= بنرها ================= --}}
    <div class="col-12 mt-4 d-flex justify-content-between align-items-center">
        <h6 class="fw-semibold mb-0">بنرها ({{ count($bannerItems) }} از {{ \App\Support\Sections\Banners::MAX_ITEMS }})</h6>
        @if(count($bannerItems) < \App\Support\Sections\Banners::MAX_ITEMS)
            <button type="button" class="btn btn-sm btn-primary-light" wire:click="addBannerItem"><i class="ri-add-line"></i> افزودن بنر</button>
        @endif
    </div>
    @error('formData.items')<div class="col-12 text-danger small">{{ $message }}</div>@enderror

    @foreach($bannerItems as $i => $banner)
        @php
            $uid = $banner['uid'] ?? '';
            $desktop = $desktopOf($uid);
            $mobile = $mobileOf($uid);
            $pendingDesktop = $formData['images']['desktop_' . $uid] ?? null;
            $pendingMobile = $formData['images']['mobile_' . $uid] ?? null;
        @endphp
        <div class="col-12" wire:key="banner-item-{{ $uid }}">
            <div class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-medium">بنر {{ $i + 1 }}</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-light" wire:click="moveBannerItem({{ $i }}, -1)" @disabled($i === 0) title="جابه‌جایی به قبل"><i class="ri-arrow-up-line"></i></button>
                        <button type="button" class="btn btn-light" wire:click="moveBannerItem({{ $i }}, 1)" @disabled($i === count($bannerItems) - 1) title="جابه‌جایی به بعد"><i class="ri-arrow-down-line"></i></button>
                        @if(count($bannerItems) > 1)
                            <button type="button" class="btn btn-light text-danger" wire:click="removeBannerItem({{ $i }})" wire:confirm="این بنر حذف شود؟ (پس از ذخیره اعمال می‌شود)" title="حذف"><i class="ri-delete-bin-6-line"></i></button>
                        @endif
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">تصویر دسکتاپ @if(!$desktop)<span class="text-danger">*</span>@endif</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="formData.images.desktop_{{ $uid }}"
                               class="form-control @error('formData.images.desktop_' . $uid) is-invalid @enderror">
                        <div wire:loading wire:target="formData.images.desktop_{{ $uid }}" class="small text-muted mt-1">در حال بارگذاری...</div>
                        @error('formData.images.desktop_' . $uid)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if($isTemp($pendingDesktop))
                            <img src="{{ $pendingDesktop->temporaryUrl() }}" class="img-thumbnail mt-2" style="max-height: 110px" alt="">
                        @elseif($desktop)
                            <img src="{{ url('/storage/' . $desktop->file_path) }}" class="img-thumbnail mt-2" style="max-height: 110px" alt="">
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">تصویر موبایل (اختیاری)</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="formData.images.mobile_{{ $uid }}"
                               class="form-control @error('formData.images.mobile_' . $uid) is-invalid @enderror">
                        <div wire:loading wire:target="formData.images.mobile_{{ $uid }}" class="small text-muted mt-1">در حال بارگذاری...</div>
                        @error('formData.images.mobile_' . $uid)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if($isTemp($pendingMobile))
                            <img src="{{ $pendingMobile->temporaryUrl() }}" class="img-thumbnail mt-2" style="max-height: 110px" alt="">
                        @elseif($mobile)
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <img src="{{ url('/storage/' . $mobile->file_path) }}" class="img-thumbnail" style="max-height: 110px" alt="">
                                <button type="button" class="btn btn-sm btn-danger-light" wire:click="removeBannerMobileImage('{{ $uid }}')" wire:confirm="تصویر موبایل حذف شود؟">حذف</button>
                            </div>
                        @else
                            <div class="form-text">اگر خالی باشد، تصویر دسکتاپ در موبایل هم نمایش داده می‌شود.</div>
                        @endif
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">عنوان روی بنر</label>
                        <input type="text" wire:model="formData.items.{{ $i }}.title" maxlength="80" class="form-control @error('formData.items.' . $i . '.title') is-invalid @enderror" placeholder="مثلاً: حراج پاییزه">
                        @error('formData.items.' . $i . '.title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">زیرعنوان</label>
                        <input type="text" wire:model="formData.items.{{ $i }}.subtitle" maxlength="150" class="form-control @error('formData.items.' . $i . '.subtitle') is-invalid @enderror" placeholder="مثلاً: تا ۴۰٪ تخفیف کیف‌های چرمی">
                        @error('formData.items.' . $i . '.subtitle')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">لینک</label>
                        <input type="text" dir="ltr" wire:model="formData.items.{{ $i }}.link" class="form-control @error('formData.items.' . $i . '.link') is-invalid @enderror" placeholder="/categories/bags">
                        @error('formData.items.' . $i . '.link')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">متن جایگزین تصویر (alt، برای سئو)</label>
                        <input type="text" wire:model="formData.items.{{ $i }}.alt" maxlength="150" class="form-control @error('formData.items.' . $i . '.alt') is-invalid @enderror">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="bn_tab_{{ $uid }}" wire:model="formData.items.{{ $i }}.new_tab">
                            <label class="form-check-label small" for="bn_tab_{{ $uid }}">تب جدید</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
