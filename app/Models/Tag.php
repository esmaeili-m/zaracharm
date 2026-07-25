<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Rayiumir\Slugable\Traits\HasSlugable;

class Tag extends Model
{
    use HasSlugable;
    protected $guarded= [];
    protected $slugSourceField = 'title';
    protected $slugLanguage = 'fa';
    public function articles(): MorphToMany
    {
        return $this->morphedByMany(Article::class, 'taggable');
    }
}
