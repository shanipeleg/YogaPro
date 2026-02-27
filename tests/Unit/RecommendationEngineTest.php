<?php

namespace Tests\Unit;

use App\Services\RecommendationEngine;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the RecommendationEngine pure-logic methods.
 *
 * We test the internal scoring logic via reflection or by exposing
 * results through the engine's public recommend() — but since that
 * needs DB, we test the pure math methods by making them accessible.
 *
 * These tests use anonymous objects to simulate model data, avoiding
 * any database dependency.
 */
class RecommendationEngineTest extends TestCase
{
    private RecommendationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new RecommendationEngine();
    }

    // ─────────────────────────────────────────────────────────
    // Factor A — Safety Score
    // ─────────────────────────────────────────────────────────

    public function test_avoided_pose_applies_30_point_penalty(): void
    {
        $opinion = $this->makeOpinion(is_avoided: true);
        $move    = $this->makeMove();

        $poses = [
            ['yoga_move_id' => 1, 'yoga_move' => $move, 'opinion' => $opinion, 'duration' => 30],
        ];

        $score = $this->callSafetyScore($poses, []);

        // Base 100 − 30 for avoided = 70
        $this->assertEquals(70.0, $score);
    }

    public function test_lower_back_sore_with_spinal_compression_penalises_20(): void
    {
        $opinion = null;
        $move    = $this->makeMove(spinal_compression: true, benefit_back_pain_lower: 'neutral');
        $poses   = [['yoga_move_id' => 1, 'yoga_move' => $move, 'opinion' => $opinion, 'duration' => 30]];

        $score = $this->callSafetyScore($poses, ['lower_back']);

        // Spinal compression → -20; also neutral back → -5; but the spinal_compression clause covers it
        // Match code: if spinal_compression OR benefit_back_pain_lower = 'avoid' → -20
        // Then: benefit_back_pain_lower = 'neutral' would also fire as -5 unless we check independently
        // Looking at the code: it's an if/elseif, so spinal_compression fires -20 and skips the neutral check
        $this->assertEquals(80.0, $score);
    }

    public function test_lower_back_avoid_classification_penalises_20(): void
    {
        $move  = $this->makeMove(benefit_back_pain_lower: 'avoid');
        $poses = [['yoga_move_id' => 1, 'yoga_move' => $move, 'opinion' => null, 'duration' => 30]];

        $score = $this->callSafetyScore($poses, ['lower_back']);
        $this->assertEquals(80.0, $score);
    }

    public function test_lower_back_neutral_classification_penalises_5(): void
    {
        $move  = $this->makeMove(benefit_back_pain_lower: 'neutral');
        $poses = [['yoga_move_id' => 1, 'yoga_move' => $move, 'opinion' => null, 'duration' => 30]];

        $score = $this->callSafetyScore($poses, ['lower_back']);
        $this->assertEquals(95.0, $score);
    }

    public function test_safety_score_does_not_go_below_zero(): void
    {
        // 4 avoided poses: 4 × 30 = 120 penalty → floor at 0
        $opinion = $this->makeOpinion(is_avoided: true);
        $move    = $this->makeMove();
        $poses   = array_fill(0, 4, ['yoga_move_id' => 1, 'yoga_move' => $move, 'opinion' => $opinion, 'duration' => 10]);

        $score = $this->callSafetyScore($poses, []);
        $this->assertEquals(0.0, $score);
    }

    public function test_no_issues_returns_100(): void
    {
        $move  = $this->makeMove(benefit_back_pain_lower: 'helps');
        $poses = [['yoga_move_id' => 1, 'yoga_move' => $move, 'opinion' => null, 'duration' => 30]];

        $score = $this->callSafetyScore($poses, ['lower_back']);
        $this->assertEquals(100.0, $score);
    }

    // ─────────────────────────────────────────────────────────
    // Factor C — Duration Match
    // ─────────────────────────────────────────────────────────

    public function test_exact_duration_match_scores_100(): void
    {
        $score = $this->callDurationScore(20 * 60, 20);
        $this->assertEquals(100.0, $score);
    }

    public function test_within_20_percent_scores_between_80_and_100(): void
    {
        // Target 20 min, video is 22 min (10% over)
        $score = $this->callDurationScore(22 * 60, 20);
        $this->assertGreaterThanOrEqual(80.0, $score);
        $this->assertLessThanOrEqual(100.0, $score);
    }

    public function test_duration_at_20_percent_over_scores_80(): void
    {
        // Target 20 min (1200s), 20% over = 24 min (1440s)
        $score = $this->callDurationScore(24 * 60, 20);
        $this->assertEqualsWithDelta(80.0, $score, 0.1);
    }

    public function test_duration_30_min_over_scores_much_lower(): void
    {
        // Target 20 min, video is 50 min (30 min over tolerance)
        $score = $this->callDurationScore(50 * 60, 20);
        $this->assertLessThan(50.0, $score);
    }

    public function test_null_time_available_returns_80(): void
    {
        $score = $this->callDurationScore(30 * 60, null);
        $this->assertEquals(80.0, $score);
    }

    // ─────────────────────────────────────────────────────────
    // Factor D — Energy Match
    // ─────────────────────────────────────────────────────────

    public function test_energy_1_with_very_slow_transitions_scores_high(): void
    {
        // Energy 1 expects avg > 10s; video has 12s → perfect
        $score = $this->callEnergyScore(12.0, 1);
        $this->assertGreaterThan(80.0, $score);
    }

    public function test_energy_5_with_fast_transitions_scores_high(): void
    {
        // Energy 5 expects < 2s; video has 1.5s → perfect
        $score = $this->callEnergyScore(1.5, 5);
        $this->assertGreaterThan(80.0, $score);
    }

    public function test_energy_mismatch_high_energy_slow_video_penalised(): void
    {
        // User energy 5 (wants fast), video is 10s transitions → big mismatch
        $score = $this->callEnergyScore(10.0, 5);
        $this->assertLessThan(50.0, $score);
    }

    public function test_energy_score_does_not_go_below_zero(): void
    {
        // Extreme mismatch
        $score = $this->callEnergyScore(100.0, 5);
        $this->assertEquals(0.0, $score);
    }

    // ─────────────────────────────────────────────────────────
    // Goal weight shifting
    // ─────────────────────────────────────────────────────────

    public function test_back_pain_goal_increases_safety_weight(): void
    {
        $defaultWeights   = $this->callGetWeights([]);
        $backPainWeights  = $this->callGetWeights(['back_pain_relief']);

        $this->assertGreaterThan($defaultWeights['safety'], $backPainWeights['safety']);
    }

    public function test_challenge_me_increases_energy_weight(): void
    {
        $defaultWeights   = $this->callGetWeights([]);
        $challengeWeights = $this->callGetWeights(['challenge_me']);

        $this->assertGreaterThan($defaultWeights['energy'], $challengeWeights['energy']);
    }

    public function test_try_new_increases_familiarity_weight(): void
    {
        $defaultWeights = $this->callGetWeights([]);
        $tryNewWeights  = $this->callGetWeights(['try_something_new']);

        $this->assertGreaterThan($defaultWeights['familiarity'], $tryNewWeights['familiarity']);
    }

    // ─────────────────────────────────────────────────────────
    // Recency Penalty
    // ─────────────────────────────────────────────────────────

    public function test_watched_today_penalty_is_20(): void
    {
        $session = $this->makeSession(watched_at: now());
        $video   = $this->makeVideo(userSessions: collect([$session]));

        $penalty = $this->callRecencyPenalty($video);
        $this->assertEquals(20.0, $penalty);
    }

    public function test_watched_5_days_ago_penalty_is_10(): void
    {
        $session = $this->makeSession(watched_at: now()->subDays(5));
        $video   = $this->makeVideo(userSessions: collect([$session]));

        $penalty = $this->callRecencyPenalty($video);
        $this->assertEquals(10.0, $penalty);
    }

    public function test_watched_10_days_ago_penalty_is_3(): void
    {
        $session = $this->makeSession(watched_at: now()->subDays(10));
        $video   = $this->makeVideo(userSessions: collect([$session]));

        $penalty = $this->callRecencyPenalty($video);
        $this->assertEquals(3.0, $penalty);
    }

    public function test_never_watched_penalty_is_0(): void
    {
        $video = $this->makeVideo(userSessions: collect([]));

        $penalty = $this->callRecencyPenalty($video);
        $this->assertEquals(0.0, $penalty);
    }

    // ─────────────────────────────────────────────────────────
    // Reflection helpers (call private methods)
    // ─────────────────────────────────────────────────────────

    private function callSafetyScore(array $poses, array $soreZones): float
    {
        $ref = new \ReflectionMethod($this->engine, 'computeSafetyScore');
        $ref->setAccessible(true);
        return $ref->invoke($this->engine, $poses, $soreZones);
    }

    private function callDurationScore(int $videoDuration, ?int $targetMinutes): float
    {
        $ref = new \ReflectionMethod($this->engine, 'computeDurationScore');
        $ref->setAccessible(true);
        return $ref->invoke($this->engine, $videoDuration, $targetMinutes);
    }

    private function callEnergyScore(float $avgTransition, int $energyLevel): float
    {
        $ref = new \ReflectionMethod($this->engine, 'computeEnergyScore');
        $ref->setAccessible(true);
        return $ref->invoke($this->engine, $avgTransition, $energyLevel);
    }

    private function callGetWeights(array $goals): array
    {
        $ref = new \ReflectionMethod($this->engine, 'getWeights');
        $ref->setAccessible(true);
        return $ref->invoke($this->engine, $goals);
    }

    private function callRecencyPenalty(object $video): float
    {
        $ref = new \ReflectionMethod($this->engine, 'computeRecencyPenalty');
        $ref->setAccessible(true);
        return $ref->invoke($this->engine, $video);
    }

    // ─────────────────────────────────────────────────────────
    // Fake object factories
    // ─────────────────────────────────────────────────────────

    private function makeMove(array $attrs = [], ...$kwargs): object
    {
        $defaults = [
            'id'                     => 1,
            'name'                   => 'Downward Facing Dog',
            'targets_lower_back'     => false,
            'targets_upper_back'     => false,
            'targets_mid_back'       => false,
            'targets_shoulders'      => false,
            'targets_neck'           => false,
            'targets_hips'           => false,
            'targets_hamstrings'     => false,
            'targets_hip_flexors'    => false,
            'targets_glutes'         => false,
            'targets_core'           => false,
            'targets_chest'          => false,
            'targets_quads'          => false,
            'targets_calves'         => false,
            'targets_ankles'         => false,
            'targets_wrists'         => false,
            'targets_pelvis'         => false,
            'benefit_back_pain_lower'  => 'neutral',
            'benefit_back_pain_upper'  => 'neutral',
            'benefit_back_pain_general'=> 'neutral',
            'spinal_compression'     => false,
            'spinal_flexion'         => false,
            'spinal_extension'       => false,
            'spinal_rotation'        => false,
            'is_inversion'           => false,
            'weight_bearing_joints'  => [],
        ];

        $merged = array_merge($defaults, $kwargs, $attrs);
        return (object) $merged;
    }

    private function makeOpinion(array $attrs = [], ...$kwargs): object
    {
        $defaults = [
            'yoga_move_id'           => 1,
            'personal_difficulty'    => null,
            'comfort_level'          => null,
            'is_avoided'             => false,
            'avoid_reason'           => null,
            'conditional_avoidance'  => null,
            'personal_notes'         => null,
        ];

        $merged = array_merge($defaults, $kwargs, $attrs);
        return (object) $merged;
    }

    private function makeSession(array $attrs = [], ...$kwargs): object
    {
        $defaults = [
            'id'                  => 1,
            'video_id'            => 1,
            'watched_at'          => now(),
            'completed_full_video'=> true,
            'overall_rating'      => 4,
            'body_state'          => null,
            'energy_level'        => 3,
            'goals'               => null,
        ];

        $merged = array_merge($defaults, $kwargs, $attrs);
        $obj = (object) $merged;
        return $obj;
    }

    private function makeVideo(array $attrs = [], ...$kwargs): object
    {
        $defaults = [
            'id'              => 1,
            'title'           => 'Test Video',
            'duration_seconds'=> 1200,
            'analysis_status' => 'analyzed',
            'userSessions'    => collect([]),
            'segments'        => collect([]),
        ];

        $merged = array_merge($defaults, $kwargs, $attrs);
        return (object) $merged;
    }
}
