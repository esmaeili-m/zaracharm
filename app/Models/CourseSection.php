<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    use HasFactory;
    protected $guarded=[];
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function lessons()
    {
        return $this->hasMany(CourseLesson::class)
            ->orderBy('sort');
    }
}
