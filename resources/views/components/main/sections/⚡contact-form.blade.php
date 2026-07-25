<?php

use Livewire\Component;

new class extends Component
{
    public $name = '';
    public $mobile = '';
    public $message = '';

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'min:10'],
        ];
    }
    protected function messages()
    {
        return [
            'name.required' => 'لطفاً نام و نام خانوادگی خود را وارد کنید.',

            'mobile.required' => 'لطفاً شماره تماس خود را وارد کنید.',
            'mobile.regex' => 'شماره تماس وارد شده معتبر نیست.',

            'message.required' => 'لطفاً متن پیام خود را وارد کنید.',
            'message.min' => 'متن پیام باید حداقل :min کاراکتر باشد.',
        ];
    }
    public function save()
    {
        $this->validate();
        $this->message = \Illuminate\Support\Str::of($this->message)
            ->stripTags()
            ->trim();
        \App\Models\contact::create([
            'name' => $this->name,
            'mobile' => $this->mobile,
            'message' => $this->message,
            'type' => 'contact',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $this->reset([
            'name',
            'mobile',
            'message',
        ]);

        $this->dispatch(
            'alert',
            type: 'success',
            title: 'موفق',
            text: 'پیام شما با موفقیت ثبت شد.'
        );
    }
};
?>

<div>
    <section class="contact-three mt-5">
        <div class="container">
            <div class="row">
                <div class="col-xl-6 col-lg-6">
                    <div class="contact-three__left">
                        <div class="contact-three__img">
                            <img src="{{asset('main')}}/images/resources/contact-three-img-1.png" alt="">
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-6">
                    <div class="contact-three__right">
                        <div class="section-title-two text-left sec-title-animation animation-style1">
                            <div class="section-title-two__tagline-box">
                                <div class="section-title-two__tagline-shape">
                                    <img src="{{asset('main')}}/images/shapes/section-title-two-shape-1.png" alt="">
                                </div>
                                <span class="section-title-two__tagline">ارسال درخواست</span>
                            </div>
                            <h2 class="section-title-two__title title-animation" style="perspective: 400px;"><div style="position: relative; display: inline-block; transform: translate(0px); opacity: 1;">ما</div> <div style="position: relative; display: inline-block; transform: translate(0px); opacity: 1;">اینجا</div> <div style="position: relative; display: inline-block; transform: translate(0px); opacity: 1;">هستیم</div> <div style="position: relative; display: inline-block; transform: translate(0px); opacity: 1;">تا</div> <div style="position: relative; display: inline-block; transform: translate(0px); opacity: 1;">به</div> <div style="position: relative; display: inline-block; transform: translate(0px); opacity: 1;">شما</div> <div style="position: relative; display: inline-block; transform: translate(0px); opacity: 1;">کمک</div> <div style="position: relative; display: inline-block; transform: translate(0px); opacity: 1;">کنیم</div> </h2>
                        </div>
                        <form wire:submit="save" class="contact-form-validated contact-three__form">



                            <div class="row">

                                <div class="col-xl-6 col-lg-6">
                                    <h4 class="contact-three__input-title">
                                        نام کامل *
                                    </h4>

                                    <div class="contact-three__input-box">
                                        <input
                                            type="text"
                                            wire:model.blur="name"
                                            placeholder="علی مرادی">

                                        @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-xl-6 col-lg-6">
                                    <h4 class="contact-three__input-title">
                                        شماره تماس *
                                    </h4>

                                    <div class="contact-three__input-box">
                                        <input
                                            type="text"
                                            wire:model.blur="mobile"
                                            placeholder="09123456789">

                                        @error('mobile')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-xl-12">
                                    <h4 class="contact-three__input-title">
                                        پیام *
                                    </h4>

                                    <div class="contact-three__input-box text-message-box">
                <textarea
                    wire:model.blur="message"
                    rows="6"
                    placeholder="پیام خود را بنویسید..."></textarea>

                                        @error('message')
                                        <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="contact-three__btn-box">
                                        <button
                                            type="submit"
                                            class="thm-btn-two contact-three__btn"
                                            wire:loading.attr="disabled">

                    <span wire:loading.remove>
                        ارسال
                    </span>

                                            <span wire:loading>
                        در حال ارسال...
                    </span>

                                            <i class="far fa-angle-double-left"></i>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </form>
                        <div class="result"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
