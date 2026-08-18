<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\ProductVariantOptionValue;
use App\Models\Specification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            /*
            |--------------------------------------------------------------------------
            | Brands
            |--------------------------------------------------------------------------
            */

            $brands = [];

            $brandData = [
                [
                    'title' => 'Nike',
                    'country' => 'آمریکا',
                    'website' => 'https://www.nike.com',
                ],
                [
                    'title' => 'Adidas',
                    'country' => 'آلمان',
                    'website' => 'https://www.adidas.com',
                ],
                [
                    'title' => 'Puma',
                    'country' => 'آلمان',
                    'website' => 'https://www.puma.com',
                ],
                [
                    'title' => 'Zara',
                    'country' => 'اسپانیا',
                    'website' => 'https://www.zara.com',
                ],
                [
                    'title' => 'چرم مشهد',
                    'country' => 'ایران',
                    'website' => null,
                ],
                [
                    'title' => 'چرم درسا',
                    'country' => 'ایران',
                    'website' => null,
                ],
            ];

            foreach ($brandData as $index => $data) {
                $brands[$data['title']] = Brand::create([
                    'title' => $data['title'],
                    'slug' => $this->slug(),
                    'description' => "محصولات برند {$data['title']}",
                    'website' => $data['website'],
                    'country' => $data['country'],
                    'sort' => $index + 1,
                    'status' => true,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Options
            |--------------------------------------------------------------------------
            */

            $colorOption = Option::create([
                'title' => 'رنگ',
                'slug' => $this->slug(),
                'sort' => 1,
                'status' => true,
            ]);

            $sizeOption = Option::create([
                'title' => 'سایز',
                'slug' => $this->slug(),
                'sort' => 2,
                'status' => true,
            ]);

            $materialOption = Option::create([
                'title' => 'جنس',
                'slug' => $this->slug(),
                'sort' => 3,
                'status' => true,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Option Values
            |--------------------------------------------------------------------------
            */

            $colors = [];

            foreach ([
                         'مشکی',
                         'سفید',
                         'قهوه‌ای',
                         'عسلی',
                         'قرمز',
                         'آبی',
                         'سبز',
                     ] as $index => $title) {

                $colors[$title] = OptionValue::create([
                    'option_id' => $colorOption->id,
                    'title' => $title,
                    'slug' => $this->slug(),
                    'sort' => $index + 1,
                    'status' => true,
                ]);
            }


            $sizes = [];

            foreach ([
                         '36',
                         '37',
                         '38',
                         '39',
                         '40',
                         '41',
                         '42',
                         '43',
                         '44',
                         '45',
                     ] as $index => $title) {

                $sizes[$title] = OptionValue::create([
                    'option_id' => $sizeOption->id,
                    'title' => $title,
                    'slug' => $this->slug(),
                    'sort' => $index + 1,
                    'status' => true,
                ]);
            }


            $materials = [];

            foreach ([
                         'چرم طبیعی',
                         'چرم مصنوعی',
                         'پارچه',
                         'جیر',
                         'برزنت',
                     ] as $index => $title) {

                $materials[$title] = OptionValue::create([
                    'option_id' => $materialOption->id,
                    'title' => $title,
                    'slug' => $this->slug(),
                    'sort' => $index + 1,
                    'status' => true,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Specifications
            |--------------------------------------------------------------------------
            */

            $specifications = [];

            foreach ([
                         'کشور سازنده',
                         'جنس رویه',
                         'جنس زیره',
                         'مناسب برای',
                         'نوع بسته شدن',
                         'ارتفاع',
                         'عرض',
                         'طول',
                     ] as $index => $title) {

                $specifications[$title] = Specification::create([
                    'title' => $title,
                    'slug' => $this->slug(),
                    'type' => 1,
                    'is_filterable' => true,
                    'is_visible' => true,
                    'sort' => $index + 1,
                    'status' => true,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            $products = [

                // ---------------------------------------------------------
                // کفش
                // ---------------------------------------------------------

                [
                    'title' => 'کفش اسپرت نایک Air Max',
                    'brand' => 'Nike',
                    'category' => 'کفش اسپرت',
                    'sub_category' => 'کفش دویدن',
                    'price' => 4890000,
                    'compare_price' => 5500000,
                    'material' => 'پارچه',
                    'colors' => ['مشکی', 'سفید'],
                    'sizes' => ['40', '41', '42', '43', '44'],
                ],

                [
                    'title' => 'کفش اسپرت آدیداس Ultraboost',
                    'brand' => 'Adidas',
                    'category' => 'کفش اسپرت',
                    'sub_category' => 'کفش دویدن',
                    'price' => 6200000,
                    'compare_price' => 6900000,
                    'material' => 'پارچه',
                    'colors' => ['مشکی', 'سفید'],
                    'sizes' => ['40', '41', '42', '43'],
                ],

                [
                    'title' => 'کفش رسمی مردانه چرم',
                    'brand' => 'چرم مشهد',
                    'category' => 'کفش مردانه',
                    'sub_category' => 'کفش رسمی',
                    'price' => 3900000,
                    'compare_price' => 4500000,
                    'material' => 'چرم طبیعی',
                    'colors' => ['مشکی', 'قهوه‌ای'],
                    'sizes' => ['40', '41', '42', '43', '44'],
                ],

                [
                    'title' => 'کفش مجلسی زنانه چرم',
                    'brand' => 'چرم درسا',
                    'category' => 'کفش زنانه',
                    'sub_category' => 'کفش مجلسی',
                    'price' => 4200000,
                    'compare_price' => 4800000,
                    'material' => 'چرم طبیعی',
                    'colors' => ['مشکی', 'عسلی'],
                    'sizes' => ['36', '37', '38', '39', '40'],
                ],

                [
                    'title' => 'کفش روزمره زنانه زارا',
                    'brand' => 'Zara',
                    'category' => 'کفش زنانه',
                    'sub_category' => 'کفش روزمره',
                    'price' => 3100000,
                    'compare_price' => 3500000,
                    'material' => 'چرم مصنوعی',
                    'colors' => ['مشکی', 'سفید', 'قهوه‌ای'],
                    'sizes' => ['36', '37', '38', '39'],
                ],

                [
                    'title' => 'کفش فوتبال نایک Mercurial',
                    'brand' => 'Nike',
                    'category' => 'کفش ورزشی',
                    'sub_category' => 'کفش فوتبال',
                    'price' => 5700000,
                    'compare_price' => 6500000,
                    'material' => 'پارچه',
                    'colors' => ['قرمز', 'مشکی'],
                    'sizes' => ['40', '41', '42', '43', '44'],
                ],


                // ---------------------------------------------------------
                // کیف
                // ---------------------------------------------------------

                [
                    'title' => 'کیف دوشی زنانه چرم طبیعی',
                    'brand' => 'چرم مشهد',
                    'category' => 'کیف زنانه',
                    'sub_category' => 'کیف دوشی',
                    'price' => 3500000,
                    'compare_price' => 4100000,
                    'material' => 'چرم طبیعی',
                    'colors' => ['مشکی', 'قهوه‌ای', 'عسلی'],
                    'sizes' => [],
                ],

                [
                    'title' => 'کیف دستی زنانه مجلسی',
                    'brand' => 'Zara',
                    'category' => 'کیف زنانه',
                    'sub_category' => 'کیف مجلسی',
                    'price' => 2800000,
                    'compare_price' => 3200000,
                    'material' => 'چرم مصنوعی',
                    'colors' => ['مشکی', 'قرمز'],
                    'sizes' => [],
                ],

                [
                    'title' => 'کیف اداری مردانه چرم',
                    'brand' => 'چرم درسا',
                    'category' => 'کیف مردانه',
                    'sub_category' => 'کیف اداری',
                    'price' => 4900000,
                    'compare_price' => 5500000,
                    'material' => 'چرم طبیعی',
                    'colors' => ['مشکی', 'قهوه‌ای'],
                    'sizes' => [],
                ],

                [
                    'title' => 'کیف دوشی مردانه روزمره',
                    'brand' => 'چرم مشهد',
                    'category' => 'کیف مردانه',
                    'sub_category' => 'کیف دوشی',
                    'price' => 2700000,
                    'compare_price' => 3100000,
                    'material' => 'چرم طبیعی',
                    'colors' => ['مشکی', 'قهوه‌ای'],
                    'sizes' => [],
                ],

                [
                    'title' => 'کوله پشتی دانشجویی آدیداس',
                    'brand' => 'Adidas',
                    'category' => 'کوله پشتی',
                    'sub_category' => 'کوله پشتی دانشجویی',
                    'price' => 2900000,
                    'compare_price' => 3400000,
                    'material' => 'برزنت',
                    'colors' => ['مشکی', 'آبی'],
                    'sizes' => [],
                ],

                [
                    'title' => 'کیف کمری اسپرت پوما',
                    'brand' => 'Puma',
                    'category' => 'کیف سفر',
                    'sub_category' => 'کیف کمری',
                    'price' => 1500000,
                    'compare_price' => 1800000,
                    'material' => 'پارچه',
                    'colors' => ['مشکی', 'سبز'],
                    'sizes' => [],
                ],
            ];


            /*
            |--------------------------------------------------------------------------
            | Create Products
            |--------------------------------------------------------------------------
            */

            foreach ($products as $index => $data) {

                $product = Product::create([
                    'brand_id' => $brands[$data['brand']]->id,

                    'title' => $data['title'],

                    'slug' => $this->slug(),

                    'short_description' =>
                        "خرید {$data['title']} با بهترین کیفیت و طراحی مناسب.",

                    'description' =>
                        "این محصول از محصولات باکیفیت فروشگاه است و با استفاده از {$data['material']} تولید شده است.",

                    'type' => 1,

                    'status' => true,

                    'sort' => $index + 1,

                ]);


                /*
                |--------------------------------------------------------------------------
                | Categories
                |--------------------------------------------------------------------------
                */

                $category = Category::where('title', $data['category'])
                    ->whereNull('parent_id')
                    ->first();

                // چون دسته اصلی نیست، از کل دسته‌ها پیدا می‌کنیم
                $childCategory = Category::where('title', $data['category'])
                    ->first();

                $subCategory = Category::where('title', $data['sub_category'])
                    ->first();

                if ($childCategory) {
                    $product->categories()->attach($childCategory->id);
                }

                if ($subCategory) {
                    $product->categories()->attach($subCategory->id);
                }


                /*
                |--------------------------------------------------------------------------
                | Product Options
                |--------------------------------------------------------------------------
                */

                $productOptions = [];

                // رنگ
                if (!empty($data['colors'])) {

                    $productOptions[] = [
                        'product_id' => $product->id,
                        'option_id' => $colorOption->id,
                        'sort' => 1,
                        'is_required' => true,
                        'status' => true,
                    ];
                }

                // سایز
                if (!empty($data['sizes'])) {

                    $productOptions[] = [
                        'product_id' => $product->id,
                        'option_id' => $sizeOption->id,
                        'sort' => 2,
                        'is_required' => true,
                        'status' => true,
                    ];
                }

                // جنس
                $productOptions[] = [
                    'product_id' => $product->id,
                    'option_id' => $materialOption->id,
                    'sort' => 3,
                    'is_required' => true,
                    'status' => true,
                ];

                foreach ($productOptions as $option) {
                    ProductOption::create($option);
                }


                /*
                |--------------------------------------------------------------------------
                | Specifications
                |--------------------------------------------------------------------------
                */

                $this->createSpecification(
                    $product,
                    $specifications['کشور سازنده'],
                    $data['brand'] === 'Nike' || $data['brand'] === 'Adidas' || $data['brand'] === 'Puma'
                        ? 'خارجی'
                        : 'ایران'
                );

                $this->createSpecification(
                    $product,
                    $specifications['جنس رویه'],
                    $data['material']
                );

                $this->createSpecification(
                    $product,
                    $specifications['مناسب برای'],
                    str_contains($data['title'], 'زنانه')
                        ? 'بانوان'
                        : 'آقایان'
                );


                /*
                |--------------------------------------------------------------------------
                | Variants
                |--------------------------------------------------------------------------
                */

                $this->createVariants(
                    $product,
                    $data,
                    $colors,
                    $sizes,
                    $materials
                );
            }
        });
    }


    private function createVariants(
        Product $product,
        array $data,
        array $colors,
        array $sizes,
        array $materials
    ): void {

        $counter = 0;

        /*
        |--------------------------------------------------------------------------
        | Shoes
        |--------------------------------------------------------------------------
        */

        if (!empty($data['sizes'])) {

            foreach ($data['colors'] as $color) {

                foreach ($data['sizes'] as $size) {

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,

                        'sku' => strtoupper(
                            'SKU-' . Str::random(10)
                        ),

                        'barcode' => null,

                        'price' => $data['price'],

                        'compare_price' => $data['compare_price'],

                        'cost_price' => (int) ($data['price'] * 0.7),

                        'is_default' => $counter === 0,

                        'weight' => 800,

                        'sort' => $counter++,

                        'status' => true,
                    ]);


                    // رنگ
                    ProductVariantOptionValue::create([
                        'product_variant_id' => $variant->id,
                        'option_value_id' => $colors[$color]->id,
                        'status' => true,
                    ]);

                    // سایز
                    ProductVariantOptionValue::create([
                        'product_variant_id' => $variant->id,
                        'option_value_id' => $sizes[$size]->id,
                        'status' => true,
                    ]);

                    // جنس
                    ProductVariantOptionValue::create([
                        'product_variant_id' => $variant->id,
                        'option_value_id' => $materials[$data['material']]->id,
                        'status' => true,
                    ]);
                }
            }

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Bags
        |--------------------------------------------------------------------------
        */

        foreach ($data['colors'] as $color) {

            $variant = ProductVariant::create([
                'product_id' => $product->id,

                'sku' => strtoupper(
                    'SKU-' . Str::random(10)
                ),

                'barcode' => null,

                'price' => $data['price'],

                'compare_price' => $data['compare_price'],

                'cost_price' => (int) ($data['price'] * 0.7),

                'is_default' => $counter === 0,

                'weight' => 600,

                'sort' => $counter++,

                'status' => true,
            ]);


            // رنگ
            ProductVariantOptionValue::create([
                'product_variant_id' => $variant->id,
                'option_value_id' => $colors[$color]->id,
                'status' => true,
            ]);

            // جنس
            ProductVariantOptionValue::create([
                'product_variant_id' => $variant->id,
                'option_value_id' => $materials[$data['material']]->id,
                'status' => true,
            ]);
        }
    }


    private function createSpecification(
        Product $product,
        Specification $specification,
        string $value
    ): void {

        ProductSpecification::create([
            'product_id' => $product->id,

            'specification_id' => $specification->id,

            'text_value' => $value,

            'number_value' => null,

            'decimal_value' => null,

            'boolean_value' => null,

            'date_value' => null,

            'status' => true,
        ]);
    }


    private function slug(): string
    {
        return Str::lower(Str::random(16));
    }
}
