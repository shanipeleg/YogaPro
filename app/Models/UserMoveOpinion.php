<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMoveOpinion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'yoga_move_id',
        'personal_difficulty',
        'comfort_level',
        'is_avoided',
        'avoid_reason',
        'conditional_avoidance',
        'personal_notes',
        'updated_at',
    ];

    protected $casts = [
        'is_avoided'            => 'boolean',
        'conditional_avoidance' => 'array',
        'updated_at'            => 'datetime',
    ];

    public function yogaMove(): BelongsTo
    {
        return $this->belongsTo(YogaMove::class);
    }
}
