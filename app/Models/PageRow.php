<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageRow extends Model
{
    protected $guarded=[];

    public function sections()
    {
        return $this->hasMany(RowSection::class);
    }
    public function classes()
    {
        return collect([
            $this->container === 'boxed' ? 'container mx-auto' : 'w-full',

            "gap-{$this->gap}",

            $this->padding_top > 0
                ? "pt-{$this->padding_top}"
                : null,

            $this->padding_bottom > 0
                ? "pb-{$this->padding_bottom}"
                : null,

        ])
            ->filter()
            ->implode(' ');
    }
    public function gapClass()
    {
        return match($this->gap) {
            0 => 'gap-0',
            1 => 'gap-1',
            2 => 'gap-2',
            4 => 'gap-4',
            6 => 'gap-6',
            8 => 'gap-8',
            10 => 'gap-10',
            default => 'gap-4',
        };
    }
}
