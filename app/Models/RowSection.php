<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RowSection extends Model
{
    protected $guarded=[];
    protected $casts =[
      'data' => 'json',
      'layout' => 'json'
    ];
    public function classes(): string
    {
        return collect([
            $this->gridClasses(),
            $this->spacingClasses(),
        ])
            ->filter()
            ->implode(' ');
    }
    protected function spacingClasses(): string
    {
        $classes = [];

        $map = [
            'padding_top'    => 'pt',
            'padding_bottom' => 'pb',
            'padding_left'   => 'pl',
            'padding_right'  => 'pr',

            'margin_top'     => 'mt',
            'margin_bottom'  => 'mb',
            'margin_left'    => 'ml',
            'margin_right'   => 'mr',
        ];

        foreach ($map as $key => $prefix) {

            $value = data_get($this->layout, "spacing.$key");

            if ($value === null) {
                continue;
            }

            $classes[] = "{$prefix}-{$value}";
        }

        return implode(' ', $classes);
    }
    public function gridClasses(): string
    {
        $classes = [];

        foreach (['default', 'sm', 'md', 'lg', 'xl', '2xl'] as $breakpoint) {

            $value = data_get($this->layout, "grid.$breakpoint");

            if (!$value) {
                continue;
            }

            $classes[] = $breakpoint === 'default'
                ? "col-span-$value"
                : "$breakpoint:col-span-$value";
        }

        return implode(' ', $classes);
    }
    public function section()
    {
        return $this->belongsTo(Section::class);
    }
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function getMediaByCollection(string $collection)
    {
        return $this->media()
            ->where('collection', $collection)
            ->first();
    }
}
