<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentPoint extends Model
{
    protected $fillable = [
        'comment_id',
        'type',
        'body',
    ];

    /**
     * Comment
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Is positive point?
     */
    public function isPositive(): bool
    {
        return $this->type === 'positive';
    }

    /**
     * Is negative point?
     */
    public function isNegative(): bool
    {
        return $this->type === 'negative';
    }
}
