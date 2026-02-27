<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\VideoAnalysisLog;
use App\Services\GeminiService;
use App\Services\VideoAnalysisParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 600; // 10 min — video analysis can be slow

    private const MODEL = 'gemini-2.5-flash';

    private const PROMPT = 'Watch this yoga video and produce a precise chronological log of every pose and every transition between poses. A transition is the movement between one pose and the next — it starts the moment the previous pose ends and ends the moment the new pose is settled. Do not skip transitions, even fast ones. IMPORTANT: Cat and Cow always form a single combined pose — log them together as one segment named "Cat-Cow Pose", never as separate Cat and Cow entries. Return ONLY valid JSON with this exact structure, no markdown, no explanation:
{
  "segments": [
    {
      "order": 1,
      "type": "pose",
      "start_time_seconds": 0,
      "end_time_seconds": 30,
      "name": "Standard English name of the pose (e.g. Downward Facing Dog)",
      "sanskrit_name": "Sanskrit name if known, else null",
      "side": "left|right|both|n_a",
      "hold_count": null,
      "confidence": 0.95
    },
    {
      "order": 2,
      "type": "transition",
      "start_time_seconds": 30,
      "end_time_seconds": 35,
      "from_name": "pose being left",
      "from_sanskrit": "Sanskrit name if known, else null",
      "to_name": "pose being entered",
      "to_sanskrit": "Sanskrit name if known, else null",
      "confidence": 0.90
    }
  ]
}';

    /** Holds the raw Gemini API response body for logging, even if analysis fails mid-way. */
    private ?array $lastGeminiBody = null;

    public function __construct(public readonly int $videoId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(GeminiService $gemini, VideoAnalysisParser $parser): void
    {
        $video = Video::find($this->videoId);

        if (! $video) {
            Log::warning("AnalyzeVideoJob: video {$this->videoId} not found.");
            return;
        }

        if ($video->analysis_status === 'analyzed') {
            return; // Already done — idempotent
        }

        // Mark as processing to prevent concurrent double-processing
        $video->update(['analysis_status' => 'processing']);

        $rawResponse = null;

        try {
            // Call Gemini with the video URL
            $rawResponse = $this->callGemini($gemini, $video->url);
            $data        = $rawResponse['parsed'];

            // Parse segments and write to DB
            $parser->parse($video, $data);

            // Save raw log
            $this->logSuccess($video, $rawResponse['body']);

            // Mark as analyzed
            $video->update([
                'analysis_status'   => 'analyzed',
                'analyzed_at'       => now(),
                'analysis_error'    => null,
                'gemini_tokens_used' => $rawResponse['total_tokens'] ?? null,
            ]);

            Log::info("AnalyzeVideoJob: analyzed video {$video->id} ({$video->youtube_id})");

        } catch (\Throwable $e) {
            Log::error("AnalyzeVideoJob: failed video {$video->id} — {$e->getMessage()}");

            $this->logFailure($video, $e->getMessage(), $rawResponse ?? ['body' => $this->lastGeminiBody]);

            $video->update([
                'analysis_status' => 'failed',
                'analysis_error'  => $e->getMessage(),
            ]);

            throw $e; // Re-throw so the queue retries
        }
    }

    private function callGemini(GeminiService $gemini, string $videoUrl): array
    {
        // Use the raw HTTP client directly to capture the full response body for logging
        $apiKey  = config('services.gemini.api_key');
        $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

        $response = \Illuminate\Support\Facades\Http::timeout(600)
            ->post("{$baseUrl}/" . self::MODEL . ":generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'file_data'      => ['file_uri' => $videoUrl],
                                'video_metadata' => ['fps' => 0.25],
                            ],
                            ['text' => self::PROMPT],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature'      => 0.1,
                    'maxOutputTokens'  => 65536,
                    'thinkingConfig'   => ['thinkingBudget' => 0], // Disable thinking — it consumed ~63K of the token budget, leaving almost none for JSON output
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Gemini API error ({$response->status()}): " . $response->body()
            );
        }

        $body         = $response->json();
        $this->lastGeminiBody = $body; // Capture early so logFailure can use it even if we throw below

        $candidate    = $body['candidates'][0] ?? null;
        $text         = $candidate['content']['parts'][0]['text'] ?? null;
        $finishReason = $candidate['finishReason'] ?? null;
        $usage        = $body['usageMetadata'] ?? [];

        if ($text === null) {
            throw new \RuntimeException('Gemini returned no text content for video. Body: ' . json_encode($body));
        }

        // Detect truncation before attempting JSON parse
        if ($finishReason === 'MAX_TOKENS') {
            throw new \RuntimeException(
                "Gemini hit MAX_TOKENS limit — response was truncated. " .
                "Output tokens: " . ($usage['candidatesTokenCount'] ?? '?') . ". " .
                "Consider lowering fps or using a chunked analysis approach for long videos."
            );
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Store the raw body so the failure log captures the actual response
            throw new \RuntimeException(
                'Gemini returned invalid JSON (finish_reason=' . ($finishReason ?? 'unknown') . '): ' .
                substr($text, 0, 500)
            );
        }

        return [
            'body'         => $body,
            'parsed'       => $decoded,
            'total_tokens' => $usage['totalTokenCount'] ?? null,
        ];
    }

    private function logSuccess(Video $video, array $body): void
    {
        $usage = $body['usageMetadata'] ?? [];

        VideoAnalysisLog::create([
            'video_id'        => $video->id,
            'gemini_model'    => self::MODEL,
            'prompt_used'     => self::PROMPT,
            'raw_response'    => $body,
            'tokens_prompt'   => $usage['promptTokenCount'] ?? null,
            'tokens_response' => $usage['candidatesTokenCount'] ?? null,
            'status'          => 'success',
            'error_message'   => null,
            'created_at'      => now(),
        ]);
    }

    private function logFailure(Video $video, string $message, ?array $rawResponse): void
    {
        VideoAnalysisLog::create([
            'video_id'        => $video->id,
            'gemini_model'    => self::MODEL,
            'prompt_used'     => self::PROMPT,
            'raw_response'    => $rawResponse['body'] ?? null,
            'tokens_prompt'   => null,
            'tokens_response' => null,
            'status'          => 'error',
            'error_message'   => $message,
            'created_at'      => now(),
        ]);
    }
}
