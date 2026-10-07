<?php

namespace App\Marketplaces;

/**
 * قابلیت‌هایی که API رسمی هر مارکت‌پلیس پشتیبانی می‌کند.
 * پنل و هسته همگام‌سازی فقط عملیاتی را اجرا/نمایش می‌دهند که Provider اعلام کرده باشد.
 */
final class Capability
{
    public const CREATE_LISTING = 'listing.create';     // ایجاد محصول در مارکت‌پلیس
    public const UPDATE_CONTENT = 'listing.content';    // نام، توضیحات، تصاویر
    public const UPDATE_PRICE = 'listing.price';
    public const UPDATE_STOCK = 'listing.stock';
    public const UPDATE_STATUS = 'listing.status';      // فعال/غیرفعال کردن آگهی
    public const REMOTE_LISTINGS = 'listing.remote';    // دریافت فهرست محصولات مارکت‌پلیس (برای اتصال)
    public const PULL_ORDERS = 'orders.pull';
    public const WEBHOOK = 'orders.webhook';
    public const ORDER_ACTIONS = 'orders.actions';      // تغییر وضعیت سفارش در مارکت‌پلیس
    public const PRODUCT_FEED = 'feed';                 // مارکت‌پلیس اطلاعات را از API فروشگاه می‌خواند

    public const LABELS = [
        self::CREATE_LISTING => 'ایجاد محصول',
        self::UPDATE_CONTENT => 'نام/توضیحات/تصاویر',
        self::UPDATE_PRICE => 'قیمت',
        self::UPDATE_STOCK => 'موجودی',
        self::UPDATE_STATUS => 'فعال/غیرفعال',
        self::REMOTE_LISTINGS => 'دریافت فهرست محصولات',
        self::PULL_ORDERS => 'دریافت سفارش',
        self::WEBHOOK => 'وب‌هوک',
        self::ORDER_ACTIONS => 'مدیریت وضعیت سفارش',
        self::PRODUCT_FEED => 'خوراک محصولات (Pull)',
    ];

    // قابلیت‌هایی که «همگام‌سازی محصول» را ممکن می‌کنند
    public const LISTING_SYNC = [self::UPDATE_CONTENT, self::UPDATE_PRICE, self::UPDATE_STOCK, self::UPDATE_STATUS];
}
