<?php

namespace Tests\Feature;

use App\Models\UserMoveOpinion;
use App\Models\Video;
use App\Models\YogaMove;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_page_returns_200(): void
    {
        $response = $this->get('/stats');

        $response->assertStatus(200);
        $response->assertViewIs('stats.index');
    }

    public function test_stats_page_loads_with_no_data(): void
    {
        $response = $this->get('/stats');

        $response->assertStatus(200);
        // Should not throw — graceful empty state
    }

    public function test_stats_page_shows_pipeline_status_counts(): void
    {
        Video::factory()->analyzed()->count(5)->create();
        Video::factory()->pending()->count(3)->create();
        Video::factory()->failed()->count(2)->create();

        $response = $this->get('/stats');

        $response->assertStatus(200);
        $response->assertSee('5');  // analyzed count
        $response->assertSee('3');  // pending count
        $response->assertSee('2');  // failed count
    }

    public function test_stats_page_shows_yoga_move_counts(): void
    {
        YogaMove::factory()->count(10)->create(['enrichment_status' => 'enriched']);
        YogaMove::factory()->pending()->count(2)->create();

        $response = $this->get('/stats');

        $response->assertStatus(200);
        // The view has total poses visible
        $response->assertSee('12'); // total
    }

    public function test_stats_page_shows_avoided_moves(): void
    {
        $move = YogaMove::factory()->create(['name' => 'Headstand', 'spinal_compression' => true]);
        UserMoveOpinion::factory()->avoided()->create(['yoga_move_id' => $move->id]);

        $response = $this->get('/stats');

        $response->assertStatus(200);
        // The back-pain safety section should appear
        $response->assertSee('Headstand');
    }
}
