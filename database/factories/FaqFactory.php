<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaqFactory extends Factory
{
    public function definition(): array
    {
        $faqs = [
            [
                'question' => 'آیا پس از خرید دوره دسترسی دائمی خواهم داشت؟',
                'answer' => 'بله، پس از خرید دوره دسترسی شما به صورت دائمی فعال خواهد بود.'
            ],
            [
                'question' => 'آیا گواهی پایان دوره ارائه می‌شود؟',
                'answer' => 'بله، در صورت تکمیل دوره گواهی پایان دوره صادر خواهد شد.'
            ],
            [
                'question' => 'دوره‌ها برای دانشجویان مامایی مناسب هستند؟',
                'answer' => 'بله، محتوای دوره‌ها برای دانشجویان و فارغ‌التحصیلان مامایی طراحی شده است.'
            ],
            [
                'question' => 'آیا امکان مشاهده مجدد ویدئوها وجود دارد؟',
                'answer' => 'بله، پس از خرید می‌توانید بارها ویدئوها را مشاهده کنید.'
            ],
            [
                'question' => 'در صورت بروز مشکل چگونه پشتیبانی دریافت کنم؟',
                'answer' => 'از طریق تیکت یا راه‌های ارتباطی سایت می‌توانید با پشتیبانی در تماس باشید.'
            ],
        ];

        $faq = fake()->randomElement($faqs);

        return [
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'category_id' => Category::query()->inRandomOrder()->value('id'),
            'status' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
