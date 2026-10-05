<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'apiuser@test.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'apiuser@test.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_unauthenticated_api_request_returns_401(): void
    {
        $response = $this->getJson('/api/courses');

        $response->assertStatus(401);
    }

    public function test_authenticated_api_request_returns_course_list(): void
    {
        $user = User::factory()->create();
        Course::factory()->create(['lecturer_id' => $user->id, 'status' => 'active']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/courses');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_api_store_returns_201_created(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);

        $response = $this->actingAs($dosen, 'sanctum')->postJson('/api/courses', [
            'code' => 'API101',
            'name' => 'API Course Test',
            'sks' => 3,
            'lecturer_id' => $dosen->id,
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'API101');
    }

    public function test_api_destroy_returns_204_no_content(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $course = Course::factory()->create(['lecturer_id' => $dosen->id]);

        $response = $this->actingAs($dosen, 'sanctum')->deleteJson("/api/courses/{$course->id}");

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }
}
