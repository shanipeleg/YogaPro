<?php

namespace Tests\Feature;

use App\Models\SegmentMove;
use App\Models\UserMoveOpinion;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Models\YogaMove;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoseLibraryTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────
    // GET /poses — browse
    // ─────────────────────────────────────────────────────────

    public function test_poses_index_returns_200(): void
    {
        $response = $this->get('/poses');

        $response->assertStatus(200);
        $response->assertViewIs('poses.index');
    }

    public function test_poses_index_shows_seeded_move(): void
    {
        YogaMove::factory()->create(['name' => 'Downward Facing Dog']);

        $response = $this->get('/poses');

        $response->assertSee('Downward Facing Dog');
    }

    public function test_poses_search_filters_by_name(): void
    {
        YogaMove::factory()->create(['name' => 'Downward Facing Dog']);
        YogaMove::factory()->create(['name' => 'Mountain Pose']);

        $response = $this->get('/poses?search=Downward');

        $response->assertSee('Downward Facing Dog');
        $response->assertDontSee('Mountain Pose');
    }

    public function test_poses_search_filters_by_sanskrit_name(): void
    {
        YogaMove::factory()->create(['name' => 'Mountain Pose', 'sanskrit_name' => 'Tadasana']);
        YogaMove::factory()->create(['name' => 'Tree Pose', 'sanskrit_name' => 'Vrksasana']);

        $response = $this->get('/poses?search=Tadasana');

        $response->assertSee('Mountain Pose');
        $response->assertDontSee('Tree Pose');
    }

    public function test_poses_filter_by_category(): void
    {
        YogaMove::factory()->create(['name' => 'Warrior I', 'category' => 'standing']);
        YogaMove::factory()->create(['name' => 'Seated Forward Fold', 'category' => 'seated']);

        $response = $this->get('/poses?category=standing');

        $response->assertSee('Warrior I');
        $response->assertDontSee('Seated Forward Fold');
    }

    public function test_poses_filter_favorites_shows_high_comfort_moves(): void
    {
        $fav  = YogaMove::factory()->create(['name' => 'Loved Pose']);
        $meh  = YogaMove::factory()->create(['name' => 'Meh Pose']);
        UserMoveOpinion::factory()->create(['yoga_move_id' => $fav->id, 'comfort_level' => 5]);
        UserMoveOpinion::factory()->create(['yoga_move_id' => $meh->id, 'comfort_level' => 2]);

        $response = $this->get('/poses?rating=favorites');

        $response->assertSee('Loved Pose');
        $response->assertDontSee('Meh Pose');
    }

    public function test_poses_filter_avoided_shows_avoided_moves(): void
    {
        $avoided    = YogaMove::factory()->create(['name' => 'Painful Pose']);
        $notAvoided = YogaMove::factory()->create(['name' => 'Fine Pose']);
        UserMoveOpinion::factory()->avoided()->create(['yoga_move_id' => $avoided->id]);
        UserMoveOpinion::factory()->create(['yoga_move_id' => $notAvoided->id, 'is_avoided' => false]);

        $response = $this->get('/poses?rating=avoided');

        $response->assertSee('Painful Pose');
        $response->assertDontSee('Fine Pose');
    }

    public function test_poses_filter_unrated_excludes_rated_moves(): void
    {
        $rated   = YogaMove::factory()->create(['name' => 'Rated Pose']);
        $unrated = YogaMove::factory()->create(['name' => 'Unrated Pose']);
        UserMoveOpinion::factory()->create(['yoga_move_id' => $rated->id]);

        $response = $this->get('/poses?rating=unrated');

        $response->assertSee('Unrated Pose');
        $response->assertDontSee('Rated Pose');
    }

    public function test_poses_index_shows_unrated_count(): void
    {
        YogaMove::factory()->count(4)->create();
        $rated = YogaMove::factory()->create();
        UserMoveOpinion::factory()->create(['yoga_move_id' => $rated->id]);

        $response = $this->get('/poses');

        $response->assertStatus(200);
        $response->assertViewHas('unratedCount', 4);
    }

    // ─────────────────────────────────────────────────────────
    // GET /poses/{move} — detail
    // ─────────────────────────────────────────────────────────

    public function test_pose_detail_returns_200(): void
    {
        $move = YogaMove::factory()->create();

        $response = $this->get("/poses/{$move->id}");

        $response->assertStatus(200);
        $response->assertViewIs('poses.show');
    }

    public function test_pose_detail_shows_move_name(): void
    {
        $move = YogaMove::factory()->create(['name' => 'Cat-Cow Stretch', 'sanskrit_name' => 'Marjaryasana']);

        $response = $this->get("/poses/{$move->id}");

        $response->assertSee('Cat-Cow Stretch');
        $response->assertSee('Marjaryasana');
    }

    public function test_pose_detail_404_for_missing(): void
    {
        $response = $this->get('/poses/99999');

        $response->assertStatus(404);
    }

    public function test_pose_detail_shows_appears_in_videos(): void
    {
        $move    = YogaMove::factory()->create(['name' => 'Warrior II']);
        $video   = Video::factory()->analyzed()->create(['title' => 'Strong Flow Class']);
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

        $response = $this->get("/poses/{$move->id}");

        $response->assertSee('Strong Flow Class');
    }

    public function test_pose_detail_does_not_show_unanalyzed_videos(): void
    {
        $move    = YogaMove::factory()->create();
        $video   = Video::factory()->pending()->create(['title' => 'Pending Video']);
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

        $response = $this->get("/poses/{$move->id}");

        $response->assertDontSee('Pending Video');
    }

    // ─────────────────────────────────────────────────────────
    // PUT /api/moves/{move}/opinion
    // ─────────────────────────────────────────────────────────

    public function test_opinion_upsert_creates_new_opinion(): void
    {
        $move = YogaMove::factory()->create();

        $response = $this->putJson("/api/moves/{$move->id}/opinion", [
            'comfort_level'       => 4,
            'personal_difficulty' => 5,
            'is_avoided'          => false,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_move_opinions', [
            'yoga_move_id'  => $move->id,
            'comfort_level' => 4,
        ]);
    }

    public function test_opinion_upsert_updates_existing_opinion(): void
    {
        $move    = YogaMove::factory()->create();
        UserMoveOpinion::factory()->create(['yoga_move_id' => $move->id, 'comfort_level' => 2]);

        $this->putJson("/api/moves/{$move->id}/opinion", ['comfort_level' => 5]);

        $this->assertDatabaseHas('user_move_opinions', [
            'yoga_move_id'  => $move->id,
            'comfort_level' => 5,
        ]);
        $this->assertDatabaseCount('user_move_opinions', 1); // No duplicate created
    }

    public function test_opinion_upsert_sets_avoided_with_reason(): void
    {
        $move = YogaMove::factory()->create();

        $this->putJson("/api/moves/{$move->id}/opinion", [
            'is_avoided'   => true,
            'avoid_reason' => 'Hurts my lower back',
        ]);

        $this->assertDatabaseHas('user_move_opinions', [
            'yoga_move_id' => $move->id,
            'is_avoided'   => true,
            'avoid_reason' => 'Hurts my lower back',
        ]);
    }

    public function test_opinion_validates_comfort_level_max(): void
    {
        $move = YogaMove::factory()->create();

        $response = $this->putJson("/api/moves/{$move->id}/opinion", ['comfort_level' => 6]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['comfort_level']);
    }

    public function test_opinion_validates_comfort_level_min(): void
    {
        $move = YogaMove::factory()->create();

        $response = $this->putJson("/api/moves/{$move->id}/opinion", ['comfort_level' => 0]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['comfort_level']);
    }

    public function test_opinion_validates_personal_difficulty_range(): void
    {
        $move = YogaMove::factory()->create();

        $response = $this->putJson("/api/moves/{$move->id}/opinion", ['personal_difficulty' => 11]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['personal_difficulty']);
    }

    public function test_opinion_404_for_missing_move(): void
    {
        $response = $this->putJson('/api/moves/99999/opinion', ['comfort_level' => 3]);

        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/moves/unrated
    // ─────────────────────────────────────────────────────────

    public function test_unrated_returns_enriched_moves_without_opinions(): void
    {
        $enriched  = YogaMove::factory()->count(3)->create(['enrichment_status' => 'enriched']);
        $pending   = YogaMove::factory()->pending()->create();
        $rated     = YogaMove::factory()->create(['enrichment_status' => 'enriched']);
        UserMoveOpinion::factory()->create(['yoga_move_id' => $rated->id]);

        $response = $this->getJson('/api/moves/unrated');

        $response->assertStatus(200);
        $response->assertJsonStructure(['moves', 'total']);

        $returnedIds = collect($response->json('moves'))->pluck('id')->all();
        foreach ($enriched as $move) {
            $this->assertContains($move->id, $returnedIds);
        }
        $this->assertNotContains($rated->id, $returnedIds);
        $this->assertNotContains($pending->id, $returnedIds);
    }

    public function test_unrated_returns_correct_total_count(): void
    {
        YogaMove::factory()->count(5)->create(['enrichment_status' => 'enriched']);
        $rated = YogaMove::factory()->create(['enrichment_status' => 'enriched']);
        UserMoveOpinion::factory()->create(['yoga_move_id' => $rated->id]);

        $response = $this->getJson('/api/moves/unrated');

        $response->assertJsonPath('total', 5);
    }
}
