<?php

namespace Tests\Feature;

use App\Models\BodyStatePreset;
use App\Models\Channel;
use App\Models\SegmentMove;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Models\YogaMove;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MomentFinderTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────
    // Home page
    // ─────────────────────────────────────────────────────────

    public function test_home_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('home');
        $response->assertViewHas('presets');
    }

    public function test_home_page_includes_presets_from_db(): void
    {
        BodyStatePreset::factory()->create(['name' => 'Morning Back Care']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Morning Back Care');
    }

    // ─────────────────────────────────────────────────────────
    // Video detail page
    // ─────────────────────────────────────────────────────────

    public function test_video_detail_returns_200_for_analyzed_video(): void
    {
        $video = Video::factory()->analyzed()->create();

        $response = $this->get("/videos/{$video->id}");

        $response->assertStatus(200);
        $response->assertViewIs('videos.show');
        $response->assertViewHas('video');
    }

    public function test_video_detail_shows_video_title(): void
    {
        $video = Video::factory()->analyzed()->create(['title' => 'Morning Flow for Beginners']);

        $response = $this->get("/videos/{$video->id}");

        $response->assertSee('Morning Flow for Beginners');
    }

    public function test_video_detail_404_for_missing_id(): void
    {
        $response = $this->get('/videos/99999');

        $response->assertStatus(404);
    }

    public function test_video_detail_includes_body_map(): void
    {
        $video = Video::factory()->analyzed()->create();
        $move  = YogaMove::factory()->create(['targets_hips' => true]);

        // Create a pose segment for the video
        $segment = VideoSegment::factory()->create([
            'video_id'           => $video->id,
            'segment_type'       => 'pose',
            'start_time_seconds' => 0,
            'end_time_seconds'   => 30,
        ]);
        SegmentMove::factory()->create([
            'video_segment_id' => $segment->id,
            'yoga_move_id'     => $move->id,
            'role'             => 'main',
        ]);

        $response = $this->get("/videos/{$video->id}");

        $response->assertStatus(200);
        $response->assertViewHas('bodyMap');
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/recommendations
    // ─────────────────────────────────────────────────────────

    public function test_recommendations_returns_empty_when_no_analyzed_videos(): void
    {
        Video::factory()->pending()->count(3)->create();

        $response = $this->postJson('/api/recommendations', [
            'energy_level'   => 3,
            'time_available' => 20,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('total', 0);
        $response->assertJsonPath('results', []);
    }

    public function test_recommendations_returns_results_for_analyzed_videos(): void
    {
        Video::factory()->analyzed()->count(3)->create();

        $response = $this->postJson('/api/recommendations', [
            'energy_level'   => 3,
            'time_available' => 20,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results' => [
                '*' => ['video', 'score', 'chips'],
            ],
            'total',
        ]);
        $this->assertGreaterThan(0, $response->json('total'));
    }

    public function test_recommendations_response_includes_required_video_fields(): void
    {
        Video::factory()->analyzed()->create();

        $response = $this->postJson('/api/recommendations', ['energy_level' => 3]);

        $response->assertStatus(200);
        $result = $response->json('results.0.video');
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('youtube_id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('duration_seconds', $result);
    }

    public function test_recommendations_accepts_empty_body(): void
    {
        $response = $this->postJson('/api/recommendations', []);

        $response->assertStatus(200);
    }

    public function test_recommendations_validates_energy_level_range(): void
    {
        $response = $this->postJson('/api/recommendations', ['energy_level' => 6]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['energy_level']);
    }

    public function test_recommendations_validates_invalid_goal(): void
    {
        $response = $this->postJson('/api/recommendations', ['goals' => ['levitate']]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['goals.0']);
    }

    public function test_recommendations_validates_body_state_mode(): void
    {
        $response = $this->postJson('/api/recommendations', [
            'body_state' => [['zone' => 'lower_back', 'mode' => 'painful']],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body_state.0.mode']);
    }

    public function test_recommendations_accepts_all_valid_goals(): void
    {
        $response = $this->postJson('/api/recommendations', [
            'goals' => ['stretch', 'relax', 'back_pain_relief'],
        ]);

        $response->assertStatus(200);
    }

    public function test_recommendations_never_includes_unanalyzed_videos(): void
    {
        Video::factory()->pending()->count(5)->create();
        Video::factory()->failed()->count(3)->create();
        // No analyzed videos
        $analyzed = Video::factory()->analyzed()->count(2)->create();

        $response = $this->postJson('/api/recommendations', []);

        $response->assertStatus(200);
        $returnedIds = collect($response->json('results'))->pluck('video.id')->all();
        $allowedIds  = $analyzed->pluck('id')->all();

        foreach ($returnedIds as $id) {
            $this->assertContains($id, $allowedIds, "Unanalyzed video $id appeared in results");
        }
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/presets
    // ─────────────────────────────────────────────────────────

    public function test_presets_index_returns_list(): void
    {
        BodyStatePreset::factory()->count(3)->create();

        $response = $this->getJson('/api/presets');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_presets_index_returns_empty_array_when_none(): void
    {
        $response = $this->getJson('/api/presets');

        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/presets
    // ─────────────────────────────────────────────────────────

    public function test_preset_create_stores_preset(): void
    {
        $response = $this->postJson('/api/presets', [
            'name'  => 'Lower Back Day',
            'zones' => [['zone' => 'lower_back', 'mode' => 'sore']],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('name', 'Lower Back Day');
        $this->assertDatabaseHas('body_state_presets', ['name' => 'Lower Back Day']);
    }

    public function test_preset_create_validates_name_required(): void
    {
        $response = $this->postJson('/api/presets', [
            'zones' => [['zone' => 'hips', 'mode' => 'sore']],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_preset_create_validates_zone_mode(): void
    {
        $response = $this->postJson('/api/presets', [
            'name'  => 'Bad Preset',
            'zones' => [['zone' => 'hips', 'mode' => 'broken']],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['zones.0.mode']);
    }

    public function test_preset_create_validates_zones_required(): void
    {
        $response = $this->postJson('/api/presets', [
            'name' => 'No Zones',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['zones']);
    }

    // ─────────────────────────────────────────────────────────
    // DELETE /api/presets/{preset}
    // ─────────────────────────────────────────────────────────

    public function test_preset_delete_removes_preset(): void
    {
        $preset = BodyStatePreset::factory()->create(['name' => 'Hip Day']);

        $response = $this->deleteJson("/api/presets/{$preset->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
        $this->assertDatabaseMissing('body_state_presets', ['id' => $preset->id]);
    }

    public function test_preset_delete_404_for_missing(): void
    {
        $response = $this->deleteJson('/api/presets/99999');

        $response->assertStatus(404);
    }
}
