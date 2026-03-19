<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_date',
        'ballot_print_quantity',
    ];

    protected function casts(): array
    {
        return [
            'election_date' => 'datetime',
            'ballot_print_quantity' => 'integer',
        ];
    }

    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn () => sprintf('Election %s', $this->election_date?->format('F j, Y g:i A')),
        );
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
