<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ballot extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'image_hash',
        'image_path',
        'scanned_at',
        'scanned_by',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
