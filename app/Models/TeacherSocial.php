<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherSocial extends Model
{
    protected $fillable = [
        'teacher_id',
        'platform',
        'url',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
