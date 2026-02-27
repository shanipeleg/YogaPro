<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'watched_at',
        'completed_full_video',
        'overall_rating',
        'notes',
        'body_state',
        'energy_level',
        'time_available',
        'goals',
        'tags',
    ];

    protected $casts = [
        'watched_at'           => 'datetime',
        'completed_full_video' => 'boolean',
        'body_state'           => 'array',
        'goals'                => 'array',
        'tags'                 => 'array',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function moveFlags(): HasMany
    {
        return $this->hasMany(SessionMoveFlag::class);
    }
}
