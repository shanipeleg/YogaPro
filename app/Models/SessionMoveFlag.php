<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionMoveFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_session_id',
        'yoga_move_id',
        'flag',
        'conditional_avoidance',
        'notes',
    ];

    protected $casts = [
        'conditional_avoidance' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(UserSession::class, 'user_session_id');
    }

    public function yogaMove(): BelongsTo
    {
        return $this->belongsTo(YogaMove::class);
    }
}
