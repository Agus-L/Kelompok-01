<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected static ?int $createdSubmissionId = null;

    public function test_mahasiswa_can_submit_assignment(): void
    {
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);
        $dosen = User::factory()->create(['role' => 'dosen']);
        $course = Course::factory()->create(['lecturer_id' => $dosen->id]);
        $course->students()->attach($mahasiswa->id);
        $assignment = Assignment::factory()->create(['course_id' => $course->id]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $mahasiswa->id,
            'file_path' => 'submissions/sample.pdf',
            'original_name' => 'tugas.pdf',
            'file_size' => 1024,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        self::$createdSubmissionId = $submission->id;

        $this->assertDatabaseHas('submissions', ['id' => $submission->id]);
    }

    public function test_dosen_can_grade_submission(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);

        $submission = Submission::find(self::$createdSubmissionId);

        $response = $this->actingAs($dosen)->post('/submissions/'.$submission->id.'/grade', [
            'score' => 85,
            'feedback' => 'Bagus sekali',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('grades', [
            'submission_id' => $submission->id,
            'score' => 85,
        ]);
    }
}
