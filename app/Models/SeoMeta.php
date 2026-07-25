<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    protected $table= 'seo_meta';
    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'title',
        'description',
        'keywords',
        'canonical_url',

        'no_index',
        'no_follow',
        'no_archive',

        'og_title',
        'og_description',
        'og_image',
        'og_type',

        'twitter_title',
        'twitter_description',
        'twitter_image',
        'twitter_card',

        'schema',

        'priority',
        'changefreq',
    ];

    protected $casts = [
        'schema' => 'array',
        'no_index' => 'boolean',
        'no_follow' => 'boolean',
        'no_archive' => 'boolean',
    ];

    public function seoable()
    {
        return $this->morphTo();
    }

}
