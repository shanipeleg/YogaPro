<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YogaMove extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sanskrit_name',
        'aliases',
        'description',
        'category',
        'difficulty_base',
        'targets_lower_back',
        'targets_upper_back',
        'targets_mid_back',
        'targets_pelvis',
        'targets_hips',
        'targets_hamstrings',
        'targets_hip_flexors',
        'targets_glutes',
        'targets_core',
        'targets_shoulders',
        'targets_neck',
        'targets_chest',
        'targets_quads',
        'targets_calves',
        'targets_ankles',
        'targets_wrists',
        'benefit_back_pain_lower',
        'benefit_back_pain_upper',
        'benefit_back_pain_general',
        'benefit_pelvic_floor',
        'benefit_hip_mobility',
        'benefit_flexibility',
        'benefit_strength',
        'benefit_balance',
        'benefit_stress_relief',
        'benefit_circulation',
        'benefit_digestion',
        'benefit_posture',
        'contraindications',
        'spinal_compression',
        'spinal_flexion',
        'spinal_extension',
        'spinal_rotation',
        'high_impact',
        'weight_bearing_joints',
        'is_inversion',
        'modifications_available',
        'modifications_description',
        'image_url',
        'source_url',
        'data_source',
        'enrichment_status',
    ];

    protected $casts = [
        'aliases'             => 'array',
        'contraindications'   => 'array',
        'weight_bearing_joints' => 'array',
    ];

    public function userOpinion(): HasOne
    {
        return $this->hasOne(UserMoveOpinion::class);
    }

    public function segmentMoves(): HasMany
    {
        return $this->hasMany(SegmentMove::class);
    }
}
