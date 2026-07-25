<?php

namespace App\Traits;
use App\Models\SeoMeta;
trait  HasSeoMeta
{
    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function getSeoPayload(): array
    {
        $seo = $this->seoMeta;

        return [
            'title' => $seo->title ?? $this->title ?? config('app.name'),
            'description' => $seo->description ?? '',
            'keywords' => $seo->keywords ?? '',

            'canonical' => $seo->canonical_url ?? url()->current(),

            'no_index' => (bool) ($seo->no_index ?? false),
            'no_follow' => (bool) ($seo->no_follow ?? false),

            'og_title' => $seo->og_title ?? null,
            'og_description' => $seo->og_description ?? null,
            'og_image' => $seo->og_image ?? null,
            'og_type' => $seo->og_type ?? 'website',

            'twitter_title' => $seo->twitter_title ?? null,
            'twitter_description' => $seo->twitter_description ?? null,
            'twitter_image' => $seo->twitter_image ?? null,
            'twitter_card' => $seo->twitter_card ?? 'summary_large_image',

            'schema' => $seo->schema ?? null,
        ];
    }
}
