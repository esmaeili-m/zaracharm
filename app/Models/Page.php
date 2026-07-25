<?php

namespace App\Models;
use App\Traits\HasSeoMeta;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasSeoMeta;
    protected $guarded=[];
    public function sections()
    {
        return $this->hasMany(PageSection::class)
            ->orderBy('sort');
    }

    public function publishedSections()
    {
        return $this->hasMany(PageSection::class)
            ->where('status', 'published')
            ->orderBy('sort');
    }
}
