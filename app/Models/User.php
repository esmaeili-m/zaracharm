<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\WithdrawalAccount;
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable,HasRoles,SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function rewardPointTransactions(): HasMany
    {
        return $this->hasMany(RewardPointTransaction::class);
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    public function avatar()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'avatar');
    }
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }
    public function hasPurchasedCourse($courseId): bool
    {
        return $this->courses()
            ->where('course_id', $courseId)
            ->exists();
    }
    public function notificationPreference()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class);
    }
    public function socials()
    {
        return $this->hasMany(TeacherSocial::class, 'teacher_id');
    }
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }
    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }
    public function wishlistCourses()
    {
        return $this->belongsToMany(Course::class, 'wishlists');
    }
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar
                ? url('/storage/' . $this->avatar->file_path)
                : asset('main/images/panel/default-avatar.png')
        );
    }
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->first_name.' '.$this->last_name

        );
    }
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
    public function scopeTeachers($query)
    {
        return $query->role('teacher');
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function withdrawalAccounts(): HasMany
    {
        return $this->hasMany(WithdrawalAccount::class);
    }

    public function getRoleLabelAttribute()
    {
        return [
            'teacher' => 'مدرس',
            'student' => 'دانشجو',
            'admin' => 'ادمین',
        ][$this->getRoleNames()->first()] ?? null;
    }
    public function courses()
    {
        return $this->belongsToMany(Course::class)
            ->withPivot('purchased_at');
    }
    public function lastMessage()
    {
        return $this->hasOne(TicketMessage::class)->latestOfMany();
    }
}
