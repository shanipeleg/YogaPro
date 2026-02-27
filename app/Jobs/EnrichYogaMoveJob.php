<?php

namespace App\Jobs;

use App\Models\YogaMove;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichYogaMoveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 90;

    public function __construct(public readonly int $yogaMoveId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(GeminiService $gemini): void
    {
        $move = YogaMove::find($this->yogaMoveId);

        if (! $move) {
            Log::warning("EnrichYogaMoveJob: yoga_move {$this->yogaMoveId} not found, skipping.");
            return;
        }

        if ($move->enrichment_status === 'enriched') {
            return; // Already done (idempotent)
        }

        $sanskritPart = $move->sanskrit_name ? " ({$move->sanskrit_name})" : '';
        $prompt       = $this->buildPrompt($move->name, $sanskritPart);

        $data = $gemini->generateJson($prompt);

        $move->update($this->mapToColumns($data));

        Log::info("EnrichYogaMoveJob: enriched \"{$move->name}\" (id={$move->id})");
    }

    private function buildPrompt(string $name, string $sanskritPart): string
    {
        return <<<PROMPT
You are a yoga expert and physiotherapist. Given the yoga pose "{$name}"{$sanskritPart}, return ONLY valid JSON with exactly this structure — no markdown, no explanation:
{
  "category": "standing|seated|supine|prone|inversion|balancing|restorative|transition",
  "difficulty_base": 1,
  "targets_lower_back": true,
  "targets_upper_back": false,
  "targets_mid_back": false,
  "targets_pelvis": false,
  "targets_hips": false,
  "targets_hamstrings": false,
  "targets_hip_flexors": false,
  "targets_glutes": false,
  "targets_core": true,
  "targets_shoulders": false,
  "targets_neck": false,
  "targets_chest": false,
  "targets_quads": false,
  "targets_calves": false,
  "targets_ankles": false,
  "targets_wrists": false,
  "benefit_back_pain_lower": "helps",
  "benefit_back_pain_upper": "neutral",
  "benefit_back_pain_general": "helps",
  "benefit_pelvic_floor": "neutral",
  "benefit_hip_mobility": "neutral",
  "benefit_flexibility": true,
  "benefit_strength": true,
  "benefit_balance": true,
  "benefit_stress_relief": false,
  "benefit_circulation": false,
  "benefit_digestion": false,
  "benefit_posture": false,
  "contraindications": ["example condition"],
  "spinal_compression": false,
  "spinal_flexion": false,
  "spinal_extension": false,
  "spinal_rotation": false,
  "high_impact": false,
  "weight_bearing_joints": ["wrists"],
  "is_inversion": false,
  "modifications_available": true,
  "modifications_description": "Description of modifications or null"
}

Replace all values with accurate information for the pose "{$name}"{$sanskritPart}. difficulty_base must be an integer 1-10. All boolean fields must be true or false. benefit_* fields must be "helps", "neutral", or "avoid". contraindications and weight_bearing_joints must be arrays of strings (empty array [] if none). modifications_description must be a string or null.
PROMPT;
    }

    /**
     * Map the Gemini JSON response to YogaMove column names.
     * Only include fields we trust from Gemini; sensitive boolean defaults stay as-is if missing.
     */
    private function mapToColumns(array $data): array
    {
        $validCategories = ['standing', 'seated', 'supine', 'prone', 'inversion', 'balancing', 'restorative', 'transition'];
        $validBenefits   = ['helps', 'neutral', 'avoid'];

        $category = in_array($data['category'] ?? '', $validCategories) ? $data['category'] : null;

        $benefitVal = fn ($key) => in_array($data[$key] ?? '', $validBenefits) ? $data[$key] : 'neutral';
        $boolVal    = fn ($key) => isset($data[$key]) ? (bool) $data[$key] : false;
        $arrVal     = fn ($key) => is_array($data[$key] ?? null) ? $data[$key] : [];
        $intVal     = fn ($key, $min, $max) => isset($data[$key])
            ? max($min, min($max, (int) $data[$key]))
            : null;

        return [
            'category'                  => $category,
            'difficulty_base'           => $intVal('difficulty_base', 1, 10),
            'targets_lower_back'        => $boolVal('targets_lower_back'),
            'targets_upper_back'        => $boolVal('targets_upper_back'),
            'targets_mid_back'          => $boolVal('targets_mid_back'),
            'targets_pelvis'            => $boolVal('targets_pelvis'),
            'targets_hips'              => $boolVal('targets_hips'),
            'targets_hamstrings'        => $boolVal('targets_hamstrings'),
            'targets_hip_flexors'       => $boolVal('targets_hip_flexors'),
            'targets_glutes'            => $boolVal('targets_glutes'),
            'targets_core'              => $boolVal('targets_core'),
            'targets_shoulders'         => $boolVal('targets_shoulders'),
            'targets_neck'              => $boolVal('targets_neck'),
            'targets_chest'             => $boolVal('targets_chest'),
            'targets_quads'             => $boolVal('targets_quads'),
            'targets_calves'            => $boolVal('targets_calves'),
            'targets_ankles'            => $boolVal('targets_ankles'),
            'targets_wrists'            => $boolVal('targets_wrists'),
            'benefit_back_pain_lower'   => $benefitVal('benefit_back_pain_lower'),
            'benefit_back_pain_upper'   => $benefitVal('benefit_back_pain_upper'),
            'benefit_back_pain_general' => $benefitVal('benefit_back_pain_general'),
            'benefit_pelvic_floor'      => $benefitVal('benefit_pelvic_floor'),
            'benefit_hip_mobility'      => $benefitVal('benefit_hip_mobility'),
            'benefit_flexibility'       => $boolVal('benefit_flexibility'),
            'benefit_strength'          => $boolVal('benefit_strength'),
            'benefit_balance'           => $boolVal('benefit_balance'),
            'benefit_stress_relief'     => $boolVal('benefit_stress_relief'),
            'benefit_circulation'       => $boolVal('benefit_circulation'),
            'benefit_digestion'         => $boolVal('benefit_digestion'),
            'benefit_posture'           => $boolVal('benefit_posture'),
            'contraindications'         => $arrVal('contraindications'),
            'spinal_compression'        => $boolVal('spinal_compression'),
            'spinal_flexion'            => $boolVal('spinal_flexion'),
            'spinal_extension'          => $boolVal('spinal_extension'),
            'spinal_rotation'           => $boolVal('spinal_rotation'),
            'high_impact'               => $boolVal('high_impact'),
            'weight_bearing_joints'     => $arrVal('weight_bearing_joints'),
            'is_inversion'              => $boolVal('is_inversion'),
            'modifications_available'   => $boolVal('modifications_available'),
            'modifications_description' => $data['modifications_description'] ?? null,
            'enrichment_status'         => 'enriched',
        ];
    }
}
