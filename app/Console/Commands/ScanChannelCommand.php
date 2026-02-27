<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScanChannelCommand extends Command
{
    protected $signature   = 'channel:scan {--channel-id= : YouTube channel ID (overrides .env)}';
    protected $description = 'Fetch all videos from the configured YouTube channel and upsert into the database';

    public function handle(YouTubeService $youtube): int
    {
        $channelId = $this->option('channel-id') ?: config('services.youtube.channel_id');

        if (! $channelId) {
            $this->error('No channel ID set. Add YOUTUBE_CHANNEL_ID to .env or pass --channel-id=');
            return self::FAILURE;
        }

        $this->info("Scanning channel: {$channelId}");

        // 1. Upsert the channel record
        $channelInfo = $youtube->getChannelInfo($channelId);
        $channel     = Channel::updateOrCreate(
            ['youtube_channel_id' => $channelId],
            $channelInfo
        );

        $this->info("Channel: {$channel->name}");

        // 2. Get uploads playlist
        $uploadsId = $youtube->getUploadsPlaylistId($channelId);
        $this->info("Uploads playlist: {$uploadsId}");

        // 3. Page through all video IDs
        $this->info('Fetching video list...');
        $allIds = $youtube->getAllVideoIds($uploadsId);
        $this->info("Found " . count($allIds) . " videos total.");

        // 4. Batch-fetch video details (50 per request) and upsert
        $new     = 0;
        $updated = 0;
        $chunks  = array_chunk($allIds, 50);

        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as $chunk) {
            $details = $youtube->getVideoDetails($chunk);

            foreach ($details as $youtubeId => $data) {
                // Skip videos under 5 minutes — likely non-yoga content (intros, announcements, etc.)
                if (($data['duration_seconds'] ?? 0) < 300) {
                    continue;
                }

                $exists = Video::where('youtube_id', $youtubeId)->exists();

                Video::updateOrCreate(
                    ['youtube_id' => $youtubeId],
                    array_merge($data, ['channel_id' => $channel->id])
                    // analysis_status defaults to 'pending' on new rows; don't overwrite on existing
                );

                $exists ? $updated++ : $new++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $channel->update(['last_scanned_at' => now()]);

        $this->info("Done. New: {$new}  Updated: {$updated}");

        return self::SUCCESS;
    }
}
