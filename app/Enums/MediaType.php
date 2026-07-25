<?php

namespace App\Enums;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case SPREADSHEET = 'spreadsheet';
    case PRESENTATION = 'presentation';
    case ARCHIVE = 'archive';
    case TEXT = 'text';
    case OTHER = 'other';

    public function icon(): string
    {
        return match ($this) {
            self::IMAGE => 'ri-image-line',
            self::VIDEO => 'ri-video-line',
            self::AUDIO => 'ri-music-line',
            self::DOCUMENT => 'ri-file-text-line',
            self::SPREADSHEET => 'ri-file-excel-line',
            self::PRESENTATION => 'ri-slideshow-line',
            self::ARCHIVE => 'ri-archive-line',
            self::TEXT => 'ri-file-text-line',
            self::OTHER => 'ri-file-line',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'تصویر',
            self::VIDEO => 'ویدیو',
            self::AUDIO => 'صوت',
            self::DOCUMENT => 'سند',
            self::SPREADSHEET => 'اکسل',
            self::PRESENTATION => 'ارائه',
            self::ARCHIVE => 'آرشیو',
            self::TEXT => 'متن',
            self::OTHER => 'سایر',
        };
    }
}
