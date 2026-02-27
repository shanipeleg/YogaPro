<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAnalysisLog extends Model
{
    protected $table = 'video_analysis_log';

    public $timestamps = false;

    protected $fillable = [
        'video_id',
        'gemini_model',
        'prompt_used',
        'raw_response',
        'tokens_prompt',
        'tokens_response',
        'status',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'created_at'   => 'datetime',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
