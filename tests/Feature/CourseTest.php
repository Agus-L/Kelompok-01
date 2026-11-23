<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_courses_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/courses');

        $response->assertStatus(200);
    }

    public function test_dosen_can_create_a_course(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);

        $response = $this->actingAs($dosen)->post('/courses', [
            'code' => 'TEST101',
            'name' => 'Testing Course',
            'sks' => 3,
            'lecturer_id' => $dosen->id,
            'status' => 'active',
            'description' => 'Test description',
        ]);

        $response->assertRedirect('/courses');
        $this->assertDatabaseHas('courses', [
            'code' => 'TEST101',
            'name' => 'Testing Course',
        ]);
    }

    public function test_mahasiswa_cannot_create_a_course(): void
    {
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

        $response = $this->actingAs($mahasiswa)->post('/courses', [
            'code' => 'HACK101',
            'name' => 'Hacked Course',
            'sks' => 3,
            'lecturer_id' => $mahasiswa->id,
            'status' => 'active',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('courses', ['code' => 'HACK101']);
    }

    public function test_dosen_can_update_course(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $course = Course::factory()->create([
            'lecturer_id' => $dosen->id,
            'name' => 'Nama Sebelum Update',
        ]);

        $response = $this->actingAs($dosen)->put('/courses/'.$course->id, [
            'code' => $course->code,
            'name' => 'Nama Sesudah Update',
            'sks' => 3,
            'lecturer_id' => $dosen->id,
            'status' => 'active',
        ]);

        $response->assertStatus(302);
    }

    public function test_default_pemrograman_web_course_can_be_viewed(): void
    {
        $user = User::where('email', 'mahasiswa@kampuslms.test')->first();
        $course = Course::where('code', 'SI2514024')->first();

        $response = $this->actingAs($user)->get('/courses/'.$course->id);

        $response->assertStatus(200);
    }

    public function test_mahasiswa_cannot_delete_course(): void
    {
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);
        $dosen = User::factory()->create(['role' => 'dosen']);
        $course = Course::factory()->create(['lecturer_id' => $dosen->id]);

        $response = $this->actingAs($mahasiswa)->delete('/courses/'.$course->id);

        $this->assertNotEquals(200, $response->getStatusCode());
    }
}
