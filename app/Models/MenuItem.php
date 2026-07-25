<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $guarded=[];

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->where('status', true)
            ->orderBy('sort');
    }


    public function page()
    {
        return $this->belongsTo(Page::class, 'reference_id');
    }

    public function getLinkAttribute(): string
    {
        return match ($this->type) {

            'page' => $this->page
                ? ($this->page->type === 'home' ? route('home') : route('page.show', $this->page->slug))
                : '#',

            'category' => ($category = Category::find($this->reference_id))
                ? route('categories.show', $category->slug)
                : '#',

            'course' => ($course = Course::find($this->reference_id))
                ? route('courses.show', $course->slug)
                : '#',

            'external' => $this->url ?? '#',
            'home' => '/',

            default => '#',
        };
    }
    public function parent()
    {
        return $this->hasOne(MenuItem::class,'parent_id');
    }
}
