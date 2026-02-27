<?php

namespace Tests\Feature;

use App\Jobs\ProcessSessionFeedbackJob;
use App\Models\SessionMoveFlag;
use App\Models\UserSession;
use App\Models\Video;
use App\Models\YogaMove;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SessionHistoryTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────
    // GET /history
    // ─────────────────────────────────────────────────────────

    public function test_history_page_returns_200(): void
    {
        $response = $this->get('/history');

        $response->assertStatus(200);
        $response->assertViewIs('history.index');
    }

    public function test_history_page_shows_logged_sessions(): void
    {
        $video   = Video::factory()->analyzed()->create(['title' => 'Hip Opening Flow']);
        UserSession::factory()->create(['video_id' => $video->id]);

        $response = $this->get('/history');

        $response->assertSee('Hip Opening Flow');
    }

    public function test_history_page_shows_summary_stats(): void
    {
        UserSession::factory()->count(3)->create();

        $response = $this->get('/history');

        $response->assertStatus(200);
        $response->assertViewHas('totalSessions', 3);
    }

    public function test_history_page_shows_insights_section(): void
    {
        $move    = YogaMove::factory()->create(['name' => 'Child\'s Pose']);
        $session = UserSession::factory()->create();
        SessionMoveFlag::factory()->create([
            'user_session_id' => $session->id,
            'yoga_move_id'    => $move->id,
            'flag'            => 'loved',
        ]);

        $response = $this->get('/history');

        $response->assertStatus(200);
        $response->assertViewHas('lovedPoses');
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/sessions
    // ─────────────────────────────────────────────────────────

    public function test_store_session_creates_record(): void
    {
        $video = Video::factory()->analyzed()->create();

        $response = $this->postJson('/api/sessions', [
            'video_id'       => $video->id,
            'overall_rating' => 4,
            'energy_level'   => 3,
            'goals'          => ['stretch'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('user_sessions', [
            'video_id'       => $video->id,
            'overall_rating' => 4,
        ]);
    }

    public function test_store_session_defaults_watched_at_to_now(): void
    {
        $video = Video::factory()->analyzed()->create();

        $response = $this->postJson('/api/sessions', ['video_id' => $video->id]);

        $response->assertStatus(201);
        $session = UserSession::first();
        $this->assertNotNull($session->watched_at);
    }

    public function test_store_session_requires_video_id(): void
    {
        $response = $this->postJson('/api/sessions', [
            'overall_rating' => 4,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['video_id']);
    }

    public function test_store_session_validates_video_exists(): void
    {
        $response = $this->postJson('/api/sessions', [
            'video_id' => 99999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['video_id']);
    }

    public function test_store_session_validates_overall_rating_range(): void
    {
        $video = Video::factory()->analyzed()->create();

        $response = $this->postJson('/api/sessions', [
            'video_id'       => $video->id,
            'overall_rating' => 6,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['overall_rating']);
    }

    public function test_store_session_validates_energy_level_range(): void
    {
        $video = Video::factory()->analyzed()->create();

        $response = $this->postJson('/api/sessions', [
            'video_id'     => $video->id,
            'energy_level' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['energy_level']);
    }

    public function test_store_session_response_includes_video_relation(): void
    {
        $video = Video::factory()->analyzed()->create(['title' => 'Evening Yin']);

        $response = $this->postJson('/api/sessions', ['video_id' => $video->id]);

        $response->assertStatus(201);
        $response->assertJsonPath('video.title', 'Evening Yin');
    }

    // ─────────────────────────────────────────────────────────
    // PUT /api/sessions/{session}
    // ─────────────────────────────────────────────────────────

    public function test_update_session_modifies_fields(): void
    {
        $session = UserSession::factory()->create(['overall_rating' => 2]);

        $response = $this->putJson("/api/sessions/{$session->id}", [
            'overall_rating' => 5,
            'notes'          => 'Felt amazing today',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_sessions', [
            'id'             => $session->id,
            'overall_rating' => 5,
            'notes'          => 'Felt amazing today',
        ]);
    }

    public function test_update_session_validates_rating(): void
    {
        $session = UserSession::factory()->create();

        $response = $this->putJson("/api/sessions/{$session->id}", ['overall_rating' => 0]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['overall_rating']);
    }

    public function test_update_session_404_for_missing(): void
    {
        $response = $this->putJson('/api/sessions/99999', ['overall_rating' => 3]);

        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/sessions/{session}
    // ─────────────────────────────────────────────────────────

    public function test_show_session_returns_session_with_relations(): void
    {
        $session = UserSession::factory()->create();

        $response = $this->getJson("/api/sessions/{$session->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'video_id', 'overall_rating', 'video', 'move_flags']);
    }

    public function test_show_session_404_for_missing(): void
    {
        $response = $this->getJson('/api/sessions/99999');

        $response->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/sessions
    // ─────────────────────────────────────────────────────────

    public function test_list_sessions_returns_paginated_json(): void
    {
        UserSession::factory()->count(5)->create();

        $response = $this->getJson('/api/sessions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
        $this->assertCount(5, $response->json('data'));
    }

    // ─────────────────────────────────────────────────────────
    // POST /api/sessions/{session}/flags
    // ─────────────────────────────────────────────────────────

    public function test_store_flags_creates_flag_records(): void
    {
        $session = UserSession::factory()->create();
        $move    = YogaMove::factory()->create();

        $response = $this->postJson("/api/sessions/{$session->id}/flags", [
            'flags' => [
                ['yoga_move_id' => $move->id, 'flag' => 'loved'],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('session_move_flags', [
            'user_session_id' => $session->id,
            'yoga_move_id'    => $move->id,
            'flag'            => 'loved',
        ]);
    }

    public function test_store_flags_dispatches_feedback_job(): void
    {
        Queue::fake();

        $session = UserSession::factory()->create();
        $move    = YogaMove::factory()->create();

        $this->postJson("/api/sessions/{$session->id}/flags", [
            'flags' => [
                ['yoga_move_id' => $move->id, 'flag' => 'loved'],
            ],
        ]);

        Queue::assertPushed(ProcessSessionFeedbackJob::class, function ($job) use ($session) {
            return $job->sessionId === $session->id;
        });
    }

    public function test_store_flags_validates_flag_value(): void
    {
        $session = UserSession::factory()->create();
        $move    = YogaMove::factory()->create();

        $response = $this->postJson("/api/sessions/{$session->id}/flags", [
            'flags' => [
                ['yoga_move_id' => $move->id, 'flag' => 'hate_it'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['flags.0.flag']);
    }

    public function test_store_flags_requires_yoga_move_to_exist(): void
    {
        $session = UserSession::factory()->create();

        $response = $this->postJson("/api/sessions/{$session->id}/flags", [
            'flags' => [
                ['yoga_move_id' => 99999, 'flag' => 'loved'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['flags.0.yoga_move_id']);
    }

    public function test_store_flags_accepts_multiple_flags(): void
    {
        $session = UserSession::factory()->create();
        $move1   = YogaMove::factory()->create();
        $move2   = YogaMove::factory()->create();

        $response = $this->postJson("/api/sessions/{$session->id}/flags", [
            'flags' => [
                ['yoga_move_id' => $move1->id, 'flag' => 'loved'],
                ['yoga_move_id' => $move2->id, 'flag' => 'too_hard'],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('session_move_flags', 2);
    }

    public function test_store_flags_accepts_all_valid_flag_types(): void
    {
        $session    = UserSession::factory()->create();
        $validFlags = ['loved', 'uncomfortable', 'avoided', 'unclear_instructions', 'too_hard', 'too_easy'];

        foreach ($validFlags as $flag) {
            $move = YogaMove::factory()->create();
            $response = $this->postJson("/api/sessions/{$session->id}/flags", [
                'flags' => [['yoga_move_id' => $move->id, 'flag' => $flag]],
            ]);
            $response->assertStatus(201, "Flag '$flag' should be valid");
        }
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/videos/search
    // ─────────────────────────────────────────────────────────

    public function test_video_search_returns_matching_videos(): void
    {
        Video::factory()->create(['title' => 'Morning Yoga Flow']);
        Video::factory()->create(['title' => 'Evening Meditation']);

        $response = $this->getJson('/api/videos/search?q=Morning');

        $response->assertStatus(200);
        $response->assertJsonStructure(['results']);
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertEquals('Morning Yoga Flow', $results[0]['title']);
    }

    public function test_video_search_returns_empty_for_no_match(): void
    {
        Video::factory()->create(['title' => 'Morning Yoga Flow']);

        $response = $this->getJson('/api/videos/search?q=XyzNotFound');

        $response->assertStatus(200);
        $response->assertJsonPath('results', []);
    }

    public function test_video_search_returns_empty_query_results(): void
    {
        Video::factory()->count(3)->create();

        $response = $this->getJson('/api/videos/search?q=');

        $response->assertStatus(200);
        // Empty query matches all (LIKE %%) — up to 10 results
        $this->assertCount(3, $response->json('results'));
    }

    public function test_video_search_limits_to_10_results(): void
    {
        Video::factory()->count(15)->create(['title' => 'Yoga Session']);

        $response = $this->getJson('/api/videos/search?q=Yoga');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(10, count($response->json('results')));
    }
}
