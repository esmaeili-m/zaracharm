<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'parent_id',
        'body',
        'name',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'pros' => 'array',
        'cons' => 'array',
        'is_verified' => 'boolean',
    ];
    public function commentable()
    {
        return $this->morphTo();
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
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


    public function parent()
    {
        return $this->belongsTo(Comment::class);
    }
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
    /**
     * Comment points
     */
    public function points(): HasMany
    {
        return $this->hasMany(CommentPoint::class);
    }

    /**
     * Comment votes
     */
    public function votes(): HasMany
    {
        return $this->hasMany(CommentVote::class);
    }

    /**
     * Positive points
     */
    public function positivePoints(): HasMany
    {
        return $this->hasMany(CommentPoint::class)
            ->where('type', 'positive');
    }

    /**
     * Negative points
     */
    public function negativePoints(): HasMany
    {
        return $this->hasMany(CommentPoint::class)
            ->where('type', 'negative');
    }
    public function helpfulVotes(): HasMany
    {
        return $this->hasMany(CommentVote::class)
            ->where('is_helpful', true);
    }

    public function unhelpfulVotes(): HasMany
    {
        return $this->hasMany(CommentVote::class)
            ->where('is_helpful', false);
    }

}
