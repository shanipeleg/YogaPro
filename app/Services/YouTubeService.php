<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    private string $apiKey;
    private string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->apiKey = config('services.youtube.api_key');
    }

    /**
     * Get the uploads playlist ID for a channel.
     */
    public function getUploadsPlaylistId(string $channelId): string
    {
        $response = Http::get("{$this->baseUrl}/channels", [
            'part'   => 'contentDetails',
            'id'     => $channelId,
            'key'    => $this->apiKey,
        ])->throw()->json();

        $items = $response['items'] ?? [];

        if (empty($items)) {
            throw new \RuntimeException("Channel not found: {$channelId}");
        }

        return $items[0]['contentDetails']['relatedPlaylists']['uploads'];
    }

    /**
     * Get channel snippet (name, handle, description, thumbnail).
     */
    public function getChannelInfo(string $channelId): array
    {
        $response = Http::get("{$this->baseUrl}/channels", [
            'part'   => 'snippet',
            'id'     => $channelId,
            'key'    => $this->apiKey,
        ])->throw()->json();

        $items = $response['items'] ?? [];

        if (empty($items)) {
            throw new \RuntimeException("Channel not found: {$channelId}");
        }

        $snippet = $items[0]['snippet'];

        return [
            'youtube_channel_id' => $channelId,
            'name'               => $snippet['title'] ?? '',
            'handle'             => $snippet['customUrl'] ?? null,
            'description'        => $snippet['description'] ?? null,
            'thumbnail_url'      => $snippet['thumbnails']['high']['url']
                                    ?? $snippet['thumbnails']['default']['url']
                                    ?? null,
        ];
    }

    /**
     * Paginate all video IDs from an uploads playlist.
     * Returns an array of YouTube video IDs.
     */
    public function getAllVideoIds(string $uploadsPlaylistId): array
    {
        $videoIds  = [];
        $pageToken = null;

        do {
            $params = [
                'part'       => 'contentDetails',
                'playlistId' => $uploadsPlaylistId,
                'maxResults' => 50,
                'key'        => $this->apiKey,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response  = Http::get("{$this->baseUrl}/playlistItems", $params)->throw()->json();
            $pageToken = $response['nextPageToken'] ?? null;

            foreach ($response['items'] ?? [] as $item) {
                $videoIds[] = $item['contentDetails']['videoId'];
            }
        } while ($pageToken);

        return $videoIds;
    }

    /**
     * Fetch full details for up to 50 video IDs at once.
     * Returns an array keyed by youtube_id.
     */
    public function getVideoDetails(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        $response = Http::get("{$this->baseUrl}/videos", [
            'part' => 'snippet,contentDetails,statistics',
            'id'   => implode(',', $videoIds),
            'key'  => $this->apiKey,
        ])->throw()->json();

        $results = [];

        foreach ($response['items'] ?? [] as $item) {
            $youtubeId = $item['id'];
            $snippet   = $item['snippet'];
            $content   = $item['contentDetails'];
            $stats     = $item['statistics'];

            $results[$youtubeId] = [
                'youtube_id'       => $youtubeId,
                'title'            => $snippet['title'] ?? '',
                'description'      => $snippet['description'] ?? null,
                'url'              => "https://www.youtube.com/watch?v={$youtubeId}",
                'thumbnail_url'    => $snippet['thumbnails']['high']['url']
                                      ?? $snippet['thumbnails']['default']['url']
                                      ?? null,
                'duration_seconds' => $this->iso8601ToSeconds($content['duration'] ?? 'PT0S'),
                'published_at'     => $snippet['publishedAt'] ?? null,
                'view_count'       => (int) ($stats['viewCount'] ?? 0),
                'like_count'       => (int) ($stats['likeCount'] ?? 0),
            ];
        }

        return $results;
    }

    /**
     * Convert ISO 8601 duration (e.g. "PT1H3M45S") to total seconds.
     */
    private function iso8601ToSeconds(string $duration): int
    {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);

        $hours   = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}
