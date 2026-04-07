<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_order',
        'votes_allowed',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'votes_allowed' => 'integer',
        ];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
