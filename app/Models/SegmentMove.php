<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SegmentMove extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'video_segment_id',
        'yoga_move_id',
        'role',
        'side',
        'hold_count',
        'ai_confidence',
        'created_at',
    ];

    protected $casts = [
        'created_at'     => 'datetime',
        'ai_confidence'  => 'decimal:2',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(VideoSegment::class, 'video_segment_id');
    }

    public function yogaMove(): BelongsTo
    {
        return $this->belongsTo(YogaMove::class);
    }
}
