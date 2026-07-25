<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Morilog\Jalali\Jalalian;

class Course extends Model
{
    use SoftDeletes,HasFactory;
    protected $guarded = [];
    protected $casts = [
        'status' => 'boolean',
        'published_at' => 'datetime',
        'discount_price' => 'integer',

    ];
    public function getFinalPriceAttribute(): int
    {
        if ($this->is_free) {
            return 0;
        }

        return $this->discount_price ?: $this->price;
    }
    public function getPriceLabelAttribute(): string
    {
        if ($this->is_free) {
            return 'رایگان';
        }

        return number_format($this->final_price) . ' تومان';
    }
    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_price > 0
            && $this->discount_price < $this->price;
    }
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('purchased_at');
    }
    public function students()
    {
        return $this->belongsToMany(User::class, 'course_user');
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function sections()
    {
        return $this->hasMany(CourseSection::class)
            ->orderBy('sort');
    }
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    public function lessons()
    {
        return $this->hasManyThrough(
            CourseLesson::class,
            CourseSection::class,
            'course_id',          // FK on sections
            'course_section_id',  // FK on lessons
            'id',                 // Local key on courses
            'id'                  // Local key on sections
        );
    }
    protected function publishedAt(): Attribute
    {
        return Attribute::make(

            get: function ($value) {

                if (!$value) return null;

                return Jalalian::fromCarbon(
                    Carbon::parse($value)
                )->format('Y/m/d');
            },


            set: fn ($value) => $value
                ? Jalalian::fromFormat('Y/m/d', $value)->toCarbon()
                : null,
        );
    }
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function featuredImage()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'featured_image');
    }
    public function approvedComments()
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->whereNull('parent_id')
            ->where('is_approved', true);
    }
    public function getLevelFaAttribute()
    {
        return match ($this->level) {
            'beginner' => 'مبتدی',
            'intermediate' => 'متوسط',
            'advanced' => 'پیشرفته',
            default => 'نامشخص',
        };
    }
    protected function featuredImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->featuredImage
                ? url('/storage/' . $this->featuredImage->file_path)
                : null
        );
    }

    public function bannerImage()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'banner_image');
    }

    protected function bannerImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->bannerImage
                ? url('/storage/' . $this->bannerImage->file_path)
                : null
        );
    }

    public function getLinkAttribute(): string
    {
        return route('courses.show', $this->slug);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
