<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dosen_can_create_assignment(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $course = Course::factory()->create(['lecturer_id' => $dosen->id]);

        $response = $this->actingAs($dosen)->post('/courses/'.$course->id.'/assignments', [
            'title' => 'Tugas Praktikum 14',
            'description' => 'Kerjakan pengujian otomatis',
            'due_at' => now()->addDays(5)->toDateTimeString(),
            'max_score' => 100,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assignments', [
            'course_id' => $course->id,
            'title' => 'Tugas Praktikum 14',
        ]);
    }

    public function test_dosen_can_delete_assignment(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $course = Course::factory()->create(['lecturer_id' => $dosen->id]);
        $assignment = Assignment::factory()->create(['course_id' => $course->id]);

        $response = $this->actingAs($dosen)->delete('/assignments/'.$assignment->id);

        $response->assertRedirect('/courses/'.$course->id);
    }
}
