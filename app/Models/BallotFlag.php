<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BallotFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'ballot_id',
        'reason',
        'flagged_by',
        'flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'flagged_at' => 'datetime',
        ];
    }

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function flagger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }
}
