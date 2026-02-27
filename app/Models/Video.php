<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'channel_id',
        'youtube_id',
        'title',
        'description',
        'url',
        'thumbnail_url',
        'duration_seconds',
        'published_at',
        'view_count',
        'like_count',
        'analysis_status',
        'analyzed_at',
        'analysis_error',
        'gemini_tokens_used',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'analyzed_at'  => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(VideoSegment::class)->orderBy('order_index');
    }

    public function analysisLogs(): HasMany
    {
        return $this->hasMany(VideoAnalysisLog::class);
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Returns an ordered array of {image_url, name} for poses in this video that have images.
     * Requires segments.segmentMoves.yogaMove to already be eager-loaded.
     */
    public function posePreviewData(int $limit = 20): array
    {
        return $this->segments
            ->where('segment_type', 'pose')
            ->flatMap(fn($seg) => $seg->segmentMoves
                ->where('role', 'main')
                ->filter(fn($sm) => $sm->yogaMove?->image_url)
                ->map(fn($sm) => [
                    'image_url' => $sm->yogaMove->image_url,
                    'name'      => $sm->yogaMove->name,
                ])
            )
            ->unique('image_url')
            ->take($limit)
            ->values()
            ->toArray();
    }
}
