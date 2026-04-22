<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_name',
        'election_date',
        'facilitator_id',
        'ballot_print_quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'election_date' => 'datetime',
            'ballot_print_quantity' => 'integer',
            'status' => 'string',
        ];
    }

    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->election_name
                ? sprintf('%s (%s)', $this->election_name, $this->election_date?->format('F j, Y g:i A'))
                : sprintf('Election %s', $this->election_date?->format('F j, Y g:i A')),
        );
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class);
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function facilitators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'election_facilitator', 'election_id', 'facilitator_id')
            ->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
