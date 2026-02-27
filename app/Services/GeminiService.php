<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * Text-only Gemini call — used for yoga move enrichment.
     * Returns the parsed JSON array/object from Gemini's response,
     * or throws on failure.
     *
     * @throws \RuntimeException
     */
    public function generateJson(string $prompt, string $model = 'gemini-2.5-flash'): array
    {
        $response = Http::timeout(60)
            ->post("{$this->baseUrl}/{$model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature'      => 0.1,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Gemini API error ({$response->status()}): " . $response->body()
            );
        }

        $body = $response->json();

        // Extract text from first candidate
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new \RuntimeException('Gemini returned no text in response: ' . json_encode($body));
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Gemini returned invalid JSON: ' . $text);
        }

        return $decoded;
    }

    /**
     * Video analysis call — sends a YouTube video URL to Gemini at 0.5 fps.
     * Returns the parsed JSON response array.
     * Used in Task 5 (video analysis pipeline).
     *
     * @throws \RuntimeException
     */
    public function analyzeVideo(string $youtubeUrl, string $prompt, string $model = 'gemini-2.5-flash'): array
    {
        $response = Http::timeout(300)
            ->post("{$this->baseUrl}/{$model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'file_data' => [
                                    'file_uri' => $youtubeUrl,
                                ],
                                'video_metadata' => [
                                    'fps' => 0.5,
                                ],
                            ],
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature'      => 0.1,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Gemini video API error ({$response->status()}): " . $response->body()
            );
        }

        $body = $response->json();

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new \RuntimeException('Gemini returned no text for video: ' . json_encode($body));
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Gemini returned invalid JSON for video: ' . $text);
        }

        return $decoded;
    }

    /**
     * Returns token usage from the last response body, if available.
     */
    public function extractTokenUsage(array $responseBody): array
    {
        $usage = $responseBody['usageMetadata'] ?? [];

        return [
            'prompt_tokens'   => $usage['promptTokenCount'] ?? null,
            'response_tokens' => $usage['candidatesTokenCount'] ?? null,
            'total_tokens'    => $usage['totalTokenCount'] ?? null,
        ];
    }
}
