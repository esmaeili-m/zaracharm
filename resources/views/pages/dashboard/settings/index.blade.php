<?php

use Livewire\Component;
use App\Models\Setting;
new class extends Component
{
    use \Livewire\WithFileUploads;
    use \App\Traits\FileUploadTrait;
    public $info=[];
    public $selectItem;
    public $data;
    public $site_name;
    public $phone;
    public $mobile;
    public $email;
    public $address;
    public $instagram;
    public $telegram;
    public $whatsapp;
    public $eita;
    public $bale;
    public $rubika;


    public $search;
    public Setting $model;

    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public $settings = [];

    public function mount()
    {
        abort_if(!auth()->user()->can('seo.view'), 403);

        $this->settings = Setting::whereNot('key','logo')->pluck('value', 'key')->toArray();
        $this->info['header']='لیست تنظیمات';
        $this->info['create']='افزودن تنظیمات';
        $this->info['delete']='حذف تنظیمات';
        $this->info['personal']='تنظیمات';

    }

    public function save()
    {
        abort_if(!auth()->user()->can('seo.create'), 403);

        $this->validate([
            'settings.site_name' => 'required|string|max:255',
            'settings.email'     => 'nullable|email',
            'settings.phone'     => 'nullable|string|max:50',
            'settings.about'     => 'nullable|string',
            'settings.logo'      => 'nullable|mimes:jpg,jpeg,png,webp,svg|max:2048',
        ], [
            'settings.site_name.required' => 'وارد کردن نام سایت الزامی است.',
            'settings.site_name.string'   => 'نام سایت باید به صورت متن وارد شود.',
            'settings.site_name.max'      => 'نام سایت نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',

            'settings.email.email'        => 'آدرس ایمیل وارد شده معتبر نیست.',

            'settings.phone.string'       => 'شماره تماس باید به صورت متن وارد شود.',
            'settings.phone.max'          => 'شماره تماس نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',

            'settings.logo.mimes'         => 'فرمت لوگو باید یکی از انواع JPG، JPEG، PNG، WEBP یا SVG باشد.',
            'settings.logo.max'           => 'حجم لوگو نباید بیشتر از ۲ مگابایت باشد.',
        ]);

        foreach ($this->settings as $key => $value) {
            if ($key === 'logo' && $value) {
                $item=Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => 'logo']
                );
                if ($value) {

                    $item->media()
                        ->where('collection', 'logo')
                        ->delete();

                    $this->upload(
                        $value,
                        $item,
                        'logo'
                    );
                }
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        session()->flash('success', 'تنظیمات ذخیره شد');
    }




};
?>

<div>
    <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title fw-medium fs-18 mb-2">
                {{$info['header']}}
            </h1>

        </div>
        <div class="btn-list">

        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">

                <div class="card-body">

                        <div class="card ">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">تنظیمات سایت</h5>
                            </div>

                            <div class="card-body">

                                @if (session()->has('success'))
                                    <div class="alert alert-success">
                                        {{ session('success') }}
                                    </div>
                                @endif

                                <div class="row g-3">

                                    <!-- عمومی -->
                                    <div class="col-md-6">
                                        <label class="form-label">نام سایت</label>
                                        <input type="text"
                                               class="form-control @error('settings.site_name') is-invalid @enderror"
                                               wire:model="settings.site_name">

                                        @error('settings.site_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">ساعت کاری</label>
                                        <input type="text"
                                               class="form-control @error('settings.work_hours') is-invalid @enderror"
                                               wire:model="settings.work_hours">

                                        @error('settings.work_hours')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">ایمیل</label>
                                        <input type="email"
                                               class="form-control @error('settings.email') is-invalid @enderror"
                                               wire:model="settings.email">

                                        @error('settings.email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- تماس -->
                                    <div class="col-md-6">
                                        <label class="form-label">تلفن</label>
                                        <input type="text"
                                               class="form-control"
                                               wire:model="settings.phone">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">موبایل</label>
                                        <input type="text"
                                               class="form-control"
                                               wire:model="settings.mobile">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">آدرس</label>
                                        <textarea class="form-control"
                                                  rows="3"
                                                  wire:model="settings.address"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">درباره ما</label>
                                        <textarea class="form-control"
                                                  rows="3"
                                                  wire:model="settings.about"></textarea>
                                    </div>

                                    <!-- شبکه‌های اجتماعی -->
                                    <div class="col-md-4">
                                        <label class="form-label">اینستاگرام</label>
                                        <input type="text" class="form-control" wire:model="settings.instagram">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">تلگرام</label>
                                        <input type="text" class="form-control" wire:model="settings.telegram">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">ایتا</label>
                                        <input type="text" class="form-control" wire:model="settings.eita">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">بله</label>
                                        <input type="text" class="form-control" wire:model="settings.bale">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">روبیکا</label>
                                        <input type="text" class="form-control" wire:model="settings.rubika">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">لوگو سایت</label>
                                        <input type="file"
                                               class="form-control @error('settings.logo') is-invalid @enderror"
                                               wire:model.defer="settings.logo">

                                        @error('settings.logo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mt-4 text-end">
                                    <button class="btn btn-primary"
                                            wire:click="save"
                                            wire:loading.attr="disabled">

                                        <span wire:loading.remove>ذخیره تغییرات</span>
                                        <span wire:loading>در حال ذخیره...</span>

                                    </button>
                                </div>

                            </div>
                        </div>

                </div>
            </div>
        </div>
    </div>


</div>
