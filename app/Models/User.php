<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADVISER = 'adviser';
    public const ROLE_FACILITATOR = 'facilitator';

    protected $fillable = [
        'name',
        'username',
        'grade_level',
        'email',
        'google_id',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdviser(): bool
    {
        return $this->role === self::ROLE_ADVISER;
    }

    public function facilitatedElections(): HasMany
    {
        return $this->hasMany(Election::class, 'facilitator_id');
    }

    public function assignedElections(): BelongsToMany
    {
        return $this->belongsToMany(Election::class, 'election_facilitator', 'facilitator_id', 'election_id')
            ->withTimestamps();
    }
}
