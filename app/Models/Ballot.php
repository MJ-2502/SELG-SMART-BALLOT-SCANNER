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
        'election_id',
        'ballot_number',
        'uuid',
        'image_hash',
        'image_path',
        'scanned_at',
        'scanned_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'ballot_number' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
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
