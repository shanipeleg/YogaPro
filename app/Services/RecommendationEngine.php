<?php

namespace App\Services;

use App\Models\UserMoveOpinion;
use App\Models\Video;
use App\Models\UserSession;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    /**
     * Zone name → targets_* column on yoga_moves
     */
    private const ZONE_TARGET_COLUMNS = [
        'lower_back'  => 'targets_lower_back',
        'upper_back'  => 'targets_upper_back',
        'mid_back'    => 'targets_mid_back',
        'pelvis'      => 'targets_pelvis',
        'hips'        => 'targets_hips',
        'hamstrings'  => 'targets_hamstrings',
        'hip_flexors' => 'targets_hip_flexors',
        'glutes'      => 'targets_glutes',
        'core'        => 'targets_core',
        'shoulders'   => 'targets_shoulders',
        'neck'        => 'targets_neck',
        'chest'       => 'targets_chest',
        'quads'       => 'targets_quads',
        'calves'      => 'targets_calves',
        'ankles'      => 'targets_ankles',
        'wrists'      => 'targets_wrists',
    ];

    /**
     * Goal → factor weight overrides [A, B, C, D, E, F]
     * Keys: safety, relevance, duration, energy, familiarity, history
     * Weights must sum to 1.0 per goal set.
     */
    private const GOAL_WEIGHTS = [
        'default'         => ['safety' => 0.35, 'relevance' => 0.25, 'duration' => 0.15, 'energy' => 0.10, 'familiarity' => 0.10, 'history' => 0.05],
        'back_pain_relief'=> ['safety' => 0.55, 'relevance' => 0.20, 'duration' => 0.15, 'energy' => 0.05, 'familiarity' => 0.05, 'history' => 0.00],
        'challenge_me'    => ['safety' => 0.20, 'relevance' => 0.20, 'duration' => 0.15, 'energy' => 0.25, 'familiarity' => 0.15, 'history' => 0.05],
        'try_something_new' => ['safety' => 0.25, 'relevance' => 0.15, 'duration' => 0.15, 'energy' => 0.10, 'familiarity' => 0.30, 'history' => 0.05],
        'relax'           => ['safety' => 0.40, 'relevance' => 0.20, 'duration' => 0.15, 'energy' => 0.10, 'familiarity' => 0.10, 'history' => 0.05],
    ];

    /**
     * Energy level → expected avg transition seconds (midpoint for scoring)
     */
    private const ENERGY_MIDPOINTS = [
        1 => 12.0,
        2 => 8.5,
        3 => 5.5,
        4 => 3.0,
        5 => 1.5,
    ];

    /**
     * Main entry point. Returns a ranked array of video results.
     *
     * @param array $params {
     *   body_state: [{zone: string, mode: 'sore'|'target'}],
     *   energy_level: int 1-5,
     *   time_min: int|null (minutes, null = no lower bound),
     *   time_max: int|null (minutes, null = no upper bound),
     *   goals: string[]
     * }
     */
    public function recommend(array $params): array
    {
        $energyLevel = (int) ($params['energy_level'] ?? 3);
        $timeMin     = isset($params['time_min']) ? (int) $params['time_min'] : null;
        $timeMax     = isset($params['time_max']) ? (int) $params['time_max'] : null;
        $goals       = $params['goals'] ?? [];
        $bodyState   = $params['body_state'] ?? [];

        $soreZones   = collect($bodyState)->where('mode', 'sore')->pluck('zone')->all();
        $targetZones = collect($bodyState)->where('mode', 'target')->pluck('zone')->all();

        // Load all analyzed videos with their full segment/move data
        $videos = Video::where('analysis_status', 'analyzed')
            ->with([
                'segments.segmentMoves.yogaMove',
                'userSessions' => fn($q) => $q->latest('watched_at'),
            ])
            ->get();

        // Load all user opinions keyed by yoga_move_id
        $opinions = UserMoveOpinion::all()->keyBy('yoga_move_id');

        // Hard filter
        $candidates = $this->hardFilter($videos, $timeMin, $timeMax, $energyLevel);

        // Score each candidate
        $results = $candidates->map(function (Video $video) use (
            $soreZones, $targetZones, $energyLevel, $timeMin, $timeMax, $goals, $opinions
        ) {
            return $this->scoreVideo($video, $soreZones, $targetZones, $energyLevel, $timeMin, $timeMax, $goals, $opinions);
        });

        // Sort descending by final score
        return $results->sortByDesc('score')->values()->all();
    }

    // ─────────────────────────────────────────────────────────
    // Hard filter
    // ─────────────────────────────────────────────────────────

    private function hardFilter(Collection $videos, ?int $timeMin, ?int $timeMax, int $energyLevel): Collection
    {
        return $videos->filter(function (Video $video) use ($timeMin, $timeMax, $energyLevel) {
            // Filter 1: must be analyzed (already done in query, but belt+suspenders)
            if ($video->analysis_status !== 'analyzed') {
                return false;
            }

            // Filter 2: must be a real practice — at least 5 pose segments
            // Excludes: discussion videos, YouTube Shorts, single-pose tutorials
            $poseCount = $video->segments->where('segment_type', 'pose')->count();
            if ($poseCount < 5) {
                return false;
            }

            // Filter 3: minimum 5-minute duration (always)
            if ($video->duration_seconds < 300) {
                return false;
            }

            // Filter 4: duration range window (only if a range is specified)
            // Use 30% tolerance below min and 40% tolerance above max so scoring can still differentiate
            if ($timeMin !== null) {
                $hardMin = $timeMin * 60 * 0.70;
                if ($video->duration_seconds < $hardMin) {
                    return false;
                }
            }
            if ($timeMax !== null) {
                $hardMax = $timeMax * 60 * 1.40;
                if ($video->duration_seconds > $hardMax) {
                    return false;
                }
            }

            // Filter 5: energy = 1 AND avg transition < 1.5s → too intense
            if ($energyLevel === 1) {
                $avgTransition = $this->computeAvgTransition($video);
                if ($avgTransition < 1.5) {
                    return false;
                }
            }

            return true;
        });
    }

    // ─────────────────────────────────────────────────────────
    // Main scoring function per video
    // ─────────────────────────────────────────────────────────

    private function scoreVideo(
        Video $video,
        array $soreZones,
        array $targetZones,
        int   $energyLevel,
        ?int  $timeMin,
        ?int  $timeMax,
        array $goals,
        Collection $opinions
    ): array {
        // Extract poses and transitions from segments
        $poses       = $this->extractPosesFromVideo($video, $opinions);
        $avgTransition = $this->computeAvgTransition($video);

        // Compute all factors
        $safetyScore      = $this->computeSafetyScore($poses, $soreZones);
        $relevanceScore   = $this->computeRelevanceScore($video, $targetZones);
        $durationScore    = $this->computeDurationScore($video->duration_seconds, $timeMin, $timeMax);
        $energyScore      = $this->computeEnergyScore($avgTransition, $energyLevel);
        [$familiarScore, $favCount, $newPoseCount, $avgComfort] = $this->computeFamiliarityScore($poses, $goals);
        $favBonus         = min(20, $favCount * 4);
        $historyScore     = $this->computeHistoryScore($video, $soreZones, $energyLevel, $goals);
        $recencyPenalty   = $this->computeRecencyPenalty($video);

        // Get weights for selected goals
        $weights = $this->getWeights($goals);

        // Compose final score
        $rawScore = (
            $safetyScore    * $weights['safety']     +
            $relevanceScore * $weights['relevance']  +
            $durationScore  * $weights['duration']   +
            $energyScore    * $weights['energy']     +
            $familiarScore  * $weights['familiarity']+
            $historyScore   * $weights['history']
        );

        $finalScore = max(0.0, min(100.0, $rawScore + $favBonus - $recencyPenalty));

        // Count avoided poses in this video
        $avoidedCount = collect($poses)->filter(
            fn($p) => $p['opinion'] && $p['opinion']->is_avoided
        )->count();

        // Generate explanation chips
        $chips = $this->generateChips(
            safetyScore:    $safetyScore,
            relevanceScore: $relevanceScore,
            avgTransition:  $avgTransition,
            favCount:       $favCount,
            newPoseCount:   $newPoseCount,
            historyScore:   $historyScore,
            avoidedCount:   $avoidedCount,
            targetZones:    $targetZones,
            video:          $video
        );

        // Session history stats (used for context chips in the UI)
        $sessions      = $video->userSessions;
        $sessionCount  = $sessions->count();
        $lastSession   = $sessions->first(); // already ordered by watched_at desc
        $lastRating    = $lastSession?->overall_rating;
        $lastWatchedAt = $lastSession?->watched_at;

        return [
            'video'          => $video,
            'score'          => round($finalScore, 1),
            'chips'          => $chips,
            'key_factors'    => [
                'safety'     => round($safetyScore, 1),
                'relevance'  => round($relevanceScore, 1),
                'duration'   => round($durationScore, 1),
                'energy'     => round($energyScore, 1),
                'familiarity'=> round($familiarScore, 1),
                'history'    => round($historyScore, 1),
                'fav_bonus'  => $favBonus,
                'recency_penalty' => $recencyPenalty,
            ],
            'avg_transition' => round($avgTransition, 1),
            'avoided_count'  => $avoidedCount,
            'fav_count'      => $favCount,
            'new_pose_count' => $newPoseCount,
            'session_count'  => $sessionCount,
            'last_rating'    => $lastRating,
            'last_watched_at'=> $lastWatchedAt?->toDateString(),
        ];
    }

    // ─────────────────────────────────────────────────────────
    // Factor A — Safety Score
    // ─────────────────────────────────────────────────────────

    private function computeSafetyScore(array $poses, array $soreZones): float
    {
        $score = 100.0;

        foreach ($poses as $pose) {
            $move    = $pose['yoga_move'];
            $opinion = $pose['opinion'];

            if ($move === null) {
                continue;
            }

            // Personal avoid flag (hard)
            if ($opinion && $opinion->is_avoided) {
                $score -= 30;
                continue;
            }

            // Conditional avoidance — check if current sore zones trigger it
            if ($opinion && !empty($opinion->conditional_avoidance)) {
                foreach ((array) $opinion->conditional_avoidance as $rule) {
                    $ruleZones = (array) ($rule['zones'] ?? []);
                    if (array_intersect($ruleZones, $soreZones)) {
                        $score -= 30;
                        continue 2;
                    }
                }
            }

            // Zone-based risk analysis
            foreach ($soreZones as $zone) {
                $score -= $this->getZoneRiskPenalty($move, $zone);
            }
        }

        return max(0.0, $score);
    }

    private function getZoneRiskPenalty(object $move, string $zone): float
    {
        $penalty = 0.0;
        $weightBearing = (array) ($move->weight_bearing_joints ?? []);

        match ($zone) {
            'lower_back' => (function () use ($move, &$penalty) {
                if ($move->spinal_compression || $move->benefit_back_pain_lower === 'avoid') {
                    $penalty += 20;
                } elseif ($move->benefit_back_pain_lower === 'neutral') {
                    $penalty += 5;
                }
            })(),
            'upper_back' => (function () use ($move, &$penalty) {
                if ($move->benefit_back_pain_upper === 'avoid') {
                    $penalty += 20;
                } elseif ($move->benefit_back_pain_upper === 'neutral') {
                    $penalty += 5;
                }
            })(),
            'shoulders' => (function () use ($move, $weightBearing, &$penalty) {
                if ($move->targets_shoulders && in_array('wrists', $weightBearing)) {
                    $penalty += 15;
                }
            })(),
            'wrists' => (function () use ($weightBearing, &$penalty) {
                if (in_array('wrists', $weightBearing)) {
                    $penalty += 15;
                }
            })(),
            'neck' => (function () use ($move, &$penalty) {
                if ($move->targets_neck && $move->is_inversion) {
                    $penalty += 15;
                }
            })(),
            'knees' => (function () use ($weightBearing, &$penalty) {
                if (in_array('knees', $weightBearing)) {
                    $penalty += 10;
                }
            })(),
            default => null,
        };

        return $penalty;
    }

    // ─────────────────────────────────────────────────────────
    // Factor B — Body Area Relevance Score
    // ─────────────────────────────────────────────────────────

    private function computeRelevanceScore(Video $video, array $targetZones): float
    {
        if (empty($targetZones)) {
            return 50.0; // Neutral if no zones selected
        }

        $totalWeight   = 0.0;
        $relevantWeight = 0.0;

        foreach ($video->segments as $segment) {
            if ($segment->segment_type !== 'pose') {
                continue;
            }

            $duration = $segment->duration_seconds ?? 0;
            $totalWeight += $duration;

            $mainMove = $segment->segmentMoves->firstWhere('role', 'main');
            if (!$mainMove || !$mainMove->yogaMove) {
                continue;
            }

            $move = $mainMove->yogaMove;
            $hits = 0;

            foreach ($targetZones as $zone) {
                $col = self::ZONE_TARGET_COLUMNS[$zone] ?? null;
                if ($col && $move->{$col}) {
                    $hits++;
                }
            }

            if ($hits > 0) {
                $relevantWeight += $duration;
            }
        }

        if ($totalWeight === 0.0) {
            return 0.0;
        }

        return min(100.0, ($relevantWeight / $totalWeight) * 100.0);
    }

    // ─────────────────────────────────────────────────────────
    // Factor C — Duration Match Score
    // ─────────────────────────────────────────────────────────

    private function computeDurationScore(int $videoDuration, ?int $timeMin, ?int $timeMax): float
    {
        // No preference at all → neutral good score
        if ($timeMin === null && $timeMax === null) {
            return 80.0;
        }

        $minSeconds = $timeMin !== null ? $timeMin * 60 : null;
        $maxSeconds = $timeMax !== null ? $timeMax * 60 : null;

        // Video falls within the range → perfect score
        $aboveMin = $minSeconds === null || $videoDuration >= $minSeconds;
        $belowMax = $maxSeconds === null || $videoDuration <= $maxSeconds;
        if ($aboveMin && $belowMax) {
            return 100.0;
        }

        // Too short (below range minimum)
        if ($minSeconds !== null && $videoDuration < $minSeconds) {
            $shortfall = ($minSeconds - $videoDuration) / 60.0;
            return max(0.0, 100.0 - $shortfall * 10.0);
        }

        // Too long (above range maximum, only when max is specified)
        if ($maxSeconds !== null && $videoDuration > $maxSeconds) {
            $excess = ($videoDuration - $maxSeconds) / 60.0;
            return max(0.0, 100.0 - $excess * 10.0);
        }

        return 80.0;
    }

    // ─────────────────────────────────────────────────────────
    // Factor D — Energy Match Score
    // ─────────────────────────────────────────────────────────

    private function computeEnergyScore(float $avgTransition, int $energyLevel): float
    {
        $expectedMidpoint = self::ENERGY_MIDPOINTS[$energyLevel] ?? 5.5;
        $delta            = abs($avgTransition - $expectedMidpoint);

        return max(0.0, 100.0 - ($delta * 8.0));
    }

    // ─────────────────────────────────────────────────────────
    // Factor E — Familiarity Score + Fav count
    // Returns [score, fav_count, new_pose_count, avg_comfort]
    // ─────────────────────────────────────────────────────────

    private function computeFamiliarityScore(array $poses, array $goals): array
    {
        $total     = count($poses);
        $known     = 0;
        $favCount  = 0;
        $comfortSum = 0.0;
        $comfortCount = 0;

        foreach ($poses as $pose) {
            $opinion = $pose['opinion'];
            if ($opinion !== null) {
                $known++;
                if ($opinion->comfort_level !== null) {
                    $comfortSum += $opinion->comfort_level;
                    $comfortCount++;
                    if ($opinion->comfort_level >= 4) {
                        $favCount++;
                    }
                }
            }
        }

        $newCount   = $total - $known;
        $knownPct   = $total > 0 ? ($known / $total * 100.0) : 50.0;
        $newPct     = 100.0 - $knownPct;
        $avgComfort = $comfortCount > 0 ? ($comfortSum / $comfortCount) : 3.0;

        // Choose scoring strategy based on primary goal
        if (in_array('try_something_new', $goals)) {
            $score = $newPct;
        } elseif (in_array('my_favorites', $goals)) {
            $score = $knownPct * ($avgComfort / 5.0);
        } elseif (in_array('relax', $goals) || in_array('back_pain_relief', $goals)) {
            $score = $knownPct;
        } else {
            $score = 50.0 + ($knownPct * 0.3) - ($newPct * 0.1);
        }

        return [min(100.0, max(0.0, $score)), $favCount, $newCount, $avgComfort];
    }

    // ─────────────────────────────────────────────────────────
    // Factor F — Historical Score
    // ─────────────────────────────────────────────────────────

    private function computeHistoryScore(Video $video, array $soreZones, int $energyLevel, array $goals): float
    {
        $sessions = $video->userSessions;

        if ($sessions->isEmpty()) {
            return 50.0;
        }

        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($sessions as $session) {
            if ($session->overall_rating === null) {
                continue;
            }

            $contextMatch = $this->computeContextMatch($session, $soreZones, $energyLevel, $goals);
            $weight       = max(0.1, $contextMatch); // minimum weight so even old sessions count a little

            $weightedSum  += ($session->overall_rating * $weight);
            $totalWeight  += $weight;
        }

        if ($totalWeight === 0.0) {
            return 50.0;
        }

        // Convert 1-5 rating to 0-100
        $weightedAvgRating = $weightedSum / $totalWeight;
        return $weightedAvgRating * 20.0;
    }

    private function computeContextMatch(UserSession $session, array $soreZones, int $energyLevel, array $goals): float
    {
        $score = 0.0;
        $total = 3.0;

        // Zone overlap (Jaccard similarity)
        $sessionZones = collect((array) ($session->body_state ?? []))
            ->where('mode', 'sore')
            ->pluck('zone')
            ->all();

        $unionCount        = count(array_unique(array_merge($soreZones, $sessionZones)));
        $intersectionCount = count(array_intersect($soreZones, $sessionZones));
        $zoneJaccard       = $unionCount > 0 ? $intersectionCount / $unionCount : 1.0;
        $score += $zoneJaccard;

        // Energy level similarity (0–1)
        $sessionEnergy = $session->energy_level ?? 3;
        $energyDelta   = abs($energyLevel - $sessionEnergy);
        $score += max(0.0, 1.0 - ($energyDelta / 4.0));

        // Goal overlap
        $sessionGoals     = (array) ($session->goals ?? []);
        $goalUnion        = count(array_unique(array_merge($goals, $sessionGoals)));
        $goalIntersection = count(array_intersect($goals, $sessionGoals));
        $goalScore        = $goalUnion > 0 ? $goalIntersection / $goalUnion : 1.0;
        $score += $goalScore;

        return $score / $total; // 0.0–1.0
    }

    // ─────────────────────────────────────────────────────────
    // Factor G — Recency Penalty
    // ─────────────────────────────────────────────────────────

    private function computeRecencyPenalty(object $video): float
    {
        $lastSession = $video->userSessions->sortByDesc('watched_at')->first();

        if ($lastSession === null) {
            return 0.0;
        }

        $daysSince = abs(now()->diffInDays($lastSession->watched_at));

        return match (true) {
            $daysSince <= 3  => 20.0,
            $daysSince <= 7  => 10.0,
            $daysSince <= 14 => 3.0,
            default          => 0.0,
        };
    }

    // ─────────────────────────────────────────────────────────
    // Weight composition
    // ─────────────────────────────────────────────────────────

    private function getWeights(array $goals): array
    {
        // Primary goal determines weights (first matching goal wins)
        $priorityGoals = ['back_pain_relief', 'challenge_me', 'try_something_new', 'relax'];
        foreach ($priorityGoals as $goal) {
            if (in_array($goal, $goals)) {
                return self::GOAL_WEIGHTS[$goal];
            }
        }

        return self::GOAL_WEIGHTS['default'];
    }

    // ─────────────────────────────────────────────────────────
    // Chip explanation generator
    // ─────────────────────────────────────────────────────────

    private function generateChips(
        float  $safetyScore,
        float  $relevanceScore,
        float  $avgTransition,
        int    $favCount,
        int    $newPoseCount,
        float  $historyScore,
        int    $avoidedCount,
        array  $targetZones,
        Video  $video
    ): array {
        $chips = [];

        // Safety
        if ($avoidedCount === 0) {
            $chips[] = '✅ No avoided poses';
        } elseif ($avoidedCount === 1) {
            $chips[] = '⚠ 1 avoided pose';
        } else {
            $chips[] = "⚠ {$avoidedCount} avoided poses";
        }

        // Body area relevance
        if ($relevanceScore > 60 && !empty($targetZones)) {
            $topZones = array_slice(array_map(fn($z) => ucwords(str_replace('_', ' ', $z)), $targetZones), 0, 2);
            $chips[]  = '🎯 Targets: ' . implode(', ', $topZones);
        }

        // Flow speed
        if ($avgTransition < 3.0) {
            $chips[] = '💨 Fast flow';
        } elseif ($avgTransition > 8.0) {
            $chips[] = '🌊 Gentle, slow pace';
        } elseif ($avgTransition >= 4.0 && $avgTransition <= 8.0) {
            $chips[] = '🧘 Moderate pace';
        }

        // Favorites
        if ($favCount >= 3) {
            $chips[] = "⭐ {$favCount} of your favorites";
        } elseif ($favCount > 0) {
            $chips[] = "⭐ {$favCount} fav pose" . ($favCount > 1 ? 's' : '');
        }

        // New poses
        if ($newPoseCount > 0) {
            $chips[] = "🆕 {$newPoseCount} new pose" . ($newPoseCount > 1 ? 's' : '');
        }

        // History-based
        if ($historyScore > 75) {
            $chips[] = '💚 Worked well in a similar moment';
        } elseif ($historyScore > 0 && $historyScore < 50) {
            $chips[] = '🔁 You\'ve done this before';
        }

        return $chips;
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    private function computeAvgTransition(Video $video): float
    {
        $transitions = $video->segments->where('segment_type', 'transition');

        if ($transitions->isEmpty()) {
            return 5.0; // fallback neutral
        }

        $total = $transitions->sum('duration_seconds');
        return $total / $transitions->count();
    }

    private function extractPosesFromVideo(Video $video, Collection $opinions): array
    {
        $poses = [];

        foreach ($video->segments as $segment) {
            if ($segment->segment_type !== 'pose') {
                continue;
            }

            $mainMove = $segment->segmentMoves->firstWhere('role', 'main');
            if (!$mainMove || !$mainMove->yogaMove) {
                continue;
            }

            $move    = $mainMove->yogaMove;
            $opinion = $opinions->get($move->id);

            $poses[] = [
                'yoga_move_id' => $move->id,
                'yoga_move'    => $move,
                'opinion'      => $opinion,
                'duration'     => $segment->duration_seconds ?? 0,
            ];
        }

        return $poses;
    }
}
