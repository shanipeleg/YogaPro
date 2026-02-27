<?php

namespace App\Jobs;

use App\Models\SessionMoveFlag;
use App\Models\UserMoveOpinion;
use App\Models\UserSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a session is saved with move flags.
 * Updates user_move_opinions based on flag data:
 *   - loved          → increase comfort_level (cap 5)
 *   - uncomfortable  → decrease comfort_level (floor 1)
 *   - avoided (perm) → set is_avoided = true
 *   - avoided (cond) → write zone array to conditional_avoidance
 *   - too_hard       → increase personal_difficulty
 *   - too_easy       → decrease personal_difficulty
 */
class ProcessSessionFeedbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public array $backoff = [30, 120];

    public function __construct(public readonly int $sessionId) {}

    public function handle(): void
    {
        $session = UserSession::with('moveFlags')->find($this->sessionId);
        if (!$session) {
            return;
        }

        foreach ($session->moveFlags as $flag) {
            $this->applyFlag($flag);
        }
    }

    private function applyFlag(SessionMoveFlag $flag): void
    {
        $opinion = UserMoveOpinion::firstOrNew(
            ['yoga_move_id' => $flag->yoga_move_id],
            ['updated_at'   => now()]
        );

        switch ($flag->flag) {
            case 'loved':
                if ($opinion->comfort_level === null) {
                    $opinion->comfort_level = 4;
                } else {
                    $opinion->comfort_level = min(5, $opinion->comfort_level + 1);
                }
                break;

            case 'uncomfortable':
                $current = $opinion->comfort_level ?? 3;
                $opinion->comfort_level = max(1, $current - 1);
                if (!$opinion->personal_notes && $flag->notes) {
                    $opinion->personal_notes = $flag->notes;
                }
                break;

            case 'avoided':
                $cond = $flag->conditional_avoidance;
                if ($cond && isset($cond['permanent']) && $cond['permanent']) {
                    $opinion->is_avoided = true;
                    if ($flag->notes) {
                        $opinion->avoid_reason = $flag->notes;
                    }
                } elseif ($cond && !empty($cond['zones'])) {
                    // Merge new conditional avoidance rule
                    $existing = (array) ($opinion->conditional_avoidance ?? []);
                    $existing[] = [
                        'zones'     => $cond['zones'],
                        'permanent' => false,
                    ];
                    $opinion->conditional_avoidance = $existing;
                } else {
                    // Treat as permanent if no context given
                    $opinion->is_avoided = true;
                }
                break;

            case 'too_hard':
                if ($opinion->personal_difficulty !== null) {
                    $opinion->personal_difficulty = min(10, $opinion->personal_difficulty + 1);
                }
                break;

            case 'too_easy':
                if ($opinion->personal_difficulty !== null) {
                    $opinion->personal_difficulty = max(1, $opinion->personal_difficulty - 1);
                }
                break;

            case 'unclear_instructions':
                // Record as a note — not a difficulty signal
                if ($flag->notes) {
                    $existing = $opinion->personal_notes ? $opinion->personal_notes . "\n" : '';
                    $opinion->personal_notes = $existing . 'Unclear instructions: ' . $flag->notes;
                }
                break;
        }

        $opinion->updated_at = now();
        $opinion->save();
    }
}
