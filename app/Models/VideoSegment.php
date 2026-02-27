<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'order_index',
        'segment_type',
        'start_time_seconds',
        'end_time_seconds',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function segmentMoves(): HasMany
    {
        return $this->hasMany(SegmentMove::class);
    }
}
