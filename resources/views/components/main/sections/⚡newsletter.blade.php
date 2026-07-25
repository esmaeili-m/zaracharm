<?php

use Livewire\Component;

new class extends Component
{
    public $email;

    protected function rules()
    {
        return [
            'email' => ['required', 'email', 'unique:newsletters,email'],
        ];
    }

    public function subscribe()
    {
        $this->validate();

        \App\Models\Newsletter::create([
            'email' => $this->email,
            'subscribed_at' => now(),
        ]);

        $this->reset('email');

        session()->flash('success', 'عضویت شما با موفقیت ثبت شد.');
    }
    protected function messages()
    {
        return [
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً در خبرنامه عضو شده است.',
        ];
    }
};
?>

<div>
    <section class="newsletter-one mt-5">
        <div class="container">
            <div class="newsletter-one__inner">
                <div class="newsletter-one__bg-shape" ></div>
                <div class="newsletter-one__shape-1 float-bob-y">
                    <img src="{{asset('main')}}/images/shapes/newsletter-one-shape-1.png" alt="">
                </div>
                <div class="newsletter-one__shape-2 img-bounce">
                    <img src="{{asset('main')}}/images/shapes/newsletter-one-shape-2.png" alt="">
                </div>
                <div class="newsletter-one__img">
                    <img src="{{asset('main')}}/images/resources/123.png" alt="">
                </div>
                <h2 class="newsletter-one__title">عضویت در خبرنامه</h2>
                <p class="newsletter-one__text">برای دریافت جدیدترین دوره‌ها، مقالات و اخبار در خبرنامه عضو شوید.</p>
                <form wire:submit.prevent="subscribe" class="newsletter-one__contact-form">
                    <div class="newsletter-one__contact-input-box">
                        <input wire:model.defer="email" type="email" placeholder="ایمیل" name="email">
                        <button type="submit" class="thm-btn"><span class="far fa-angle-double-left"></span>عضویت</button>
                    </div>
                    @error('email')
                    <p class="text-white mt-2">{{$message}}</p>
                    @enderror
                </form>
            </div>
        </div>
    </section>
</div>
