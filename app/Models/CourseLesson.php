<?php

namespace App\Models;

use App\Enums\LessonType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class CourseLesson extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'status' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(CourseSection::class,'course_section_id');
    }
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
    protected function publishedAt(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (blank($value)) {
                    return null;
                }

                return Jalalian::fromCarbon(
                    Carbon::parse($value)
                )->format('Y/m/d');
            },

            set: function ($value) {
                if (blank($value)) {
                    return null;
                }

                if ($value instanceof Carbon) {
                    return $value->toDateTimeString();
                }

                return Jalalian::fromFormat(
                    'Y/m/d',
                    trim($value)
                )->toCarbon()->toDateTimeString();
            },
        );
    }

    public function isPublished(): bool
    {
        return (bool) $this->status && $this->published_at !== null;
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
