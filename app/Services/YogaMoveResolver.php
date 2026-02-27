<?php

namespace App\Services;

use App\Jobs\EnrichYogaMoveJob;
use App\Models\YogaMove;
use Illuminate\Support\Facades\Log;

class YogaMoveResolver
{
    /**
     * Find or create a YogaMove by English name (with optional Sanskrit fallback).
     * If a stub is created, queues EnrichYogaMoveJob automatically.
     */
    public function resolve(string $name, ?string $sanskritName = null): YogaMove
    {
        $name = trim($name);

        // 1. Exact match on name
        $move = YogaMove::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

        if ($move) {
            return $move;
        }

        // 2. Partial match — "Warrior I" vs "Warrior 1", common variations
        $normalized = $this->normalize($name);
        $move = YogaMove::all()->first(function ($m) use ($normalized) {
            return $this->normalize($m->name) === $normalized;
        });

        if ($move) {
            return $move;
        }

        // 3. Sanskrit name match
        if ($sanskritName) {
            $move = YogaMove::whereRaw('LOWER(sanskrit_name) = ?', [strtolower(trim($sanskritName))])->first();
            if ($move) {
                return $move;
            }
        }

        // 4. Not found — create stub and queue enrichment
        $move = YogaMove::create([
            'name'               => $name,
            'sanskrit_name'      => $sanskritName ?: null,
            'data_source'        => 'api',
            'enrichment_status'  => 'pending',
        ]);

        EnrichYogaMoveJob::dispatch($move->id);

        Log::info("YogaMoveResolver: created stub for \"{$name}\" (id={$move->id}), queued enrichment.");

        return $move;
    }

    /**
     * Normalize a pose name for fuzzy matching.
     * Lowercases, replaces Roman numerals and digits, strips punctuation.
     */
    private function normalize(string $name): string
    {
        $name = strtolower(trim($name));

        // Roman numeral → digit normalisation for common poses (Warrior I/1, II/2, III/3)
        $name = str_replace([' i ', ' ii ', ' iii ', ' iv ', ' i', ' ii', ' iii', ' iv'], [' 1 ', ' 2 ', ' 3 ', ' 4 ', ' 1', ' 2', ' 3', ' 4'], $name);

        // Remove possessives and punctuation
        $name = preg_replace("/['\-]/", ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }
}
