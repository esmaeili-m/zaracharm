<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $guarded = [];

    public function commentable()
    {
        return $this->morphTo();
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }
    public function getCommentableLabelAttribute()
    {
        return match ($this->commentable_type) {

            \App\Models\Course::class => 'دوره',

            \App\Models\Article::class => 'مقاله',

            default => 'نامشخص',
        };
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class);
    }
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }


}
