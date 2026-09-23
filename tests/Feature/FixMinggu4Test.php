<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixMinggu4Test extends TestCase
{
    use RefreshDatabase;

    protected User $dosen;
    protected User $otherDosen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->create([
            'name' => 'Dosen Test',
            'email' => 'dosentest@example.com',
            'role' => 'dosen',
        ]);

        $this->otherDosen = User::factory()->create([
            'name' => 'Dosen Lain',
            'email' => 'dosenlain@example.com',
            'role' => 'dosen',
        ]);
    }

    /**
     * FIX 1 - Test 1: Data yang valid dapat diproses dan disimpan ke database
     */
    public function test_fix1_valid_course_can_be_stored(): void
    {
        $response = $this->actingAs($this->dosen)->post('/courses', [
            'code' => 'CS101',
            'name' => 'Dasar Pemrograman',
            'description' => 'Mata kuliah pemrograman dasar',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('courses.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('courses', [
            'code' => 'CS101',
            'name' => 'Dasar Pemrograman',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);
    }

    /**
     * FIX 1 - Test 2: Data tidak valid ditolak oleh server (bypassing frontend validation)
     */
    public function test_fix1_invalid_course_rejected_by_server_validation(): void
    {
        // Mengirim data tidak valid langsung ke endpoint POST:
        // - sks = 99 (melebihi batas max 6)
        // - lecturer_id = 99999 (foreign key tidak ada di tabel users)
        // - status = 'unauthorized_status' (di luar enum draft, active, archived)
        // - name = '' (kosong padahal required)
        $response = $this->actingAs($this->dosen)->post('/courses', [
            'code' => 'INVALID01',
            'name' => '',
            'sks' => 99,
            'lecturer_id' => 99999,
            'status' => 'unauthorized_status',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'sks', 'lecturer_id', 'status']);
        $this->assertDatabaseMissing('courses', [
            'code' => 'INVALID01',
        ]);
    }

    /**
     * FIX 1 - Test 3: Duplikasi kode pada STORE ditolak oleh server
     */
    public function test_fix1_duplicate_code_rejected_on_store(): void
    {
        Course::create([
            'code' => 'CS102',
            'name' => 'Struktur Data',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->dosen)->post('/courses', [
            'code' => 'CS102', // Duplikat
            'name' => 'Struktur Data Kelas B',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    /**
     * FIX 2 - Test 1: Update Course tanpa mengubah code unik berhasil (tidak menolak dirinya sendiri)
     */
    public function test_fix2_update_course_without_changing_unique_code_succeeds(): void
    {
        $course = Course::create([
            'code' => 'CS201',
            'name' => 'Basis Data',
            'description' => 'Deskripsi lama',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        // Kirim update dengan kode yang sama persis ('CS201')
        $response = $this->actingAs($this->dosen)->put(route('courses.update', $course), [
            'code' => 'CS201', // Kode tetap sama
            'name' => 'Basis Data Lanjut (Updated)',
            'description' => 'Deskripsi baru yang diperbarui',
            'sks' => 4,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('courses.show', $course));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'code' => 'CS201',
            'name' => 'Basis Data Lanjut (Updated)',
            'sks' => 4,
        ]);
    }

    /**
     * FIX 2 - Test 2: Update Course menggunakan code milik Course lain ditolak (validasi duplicate tetap bekerja)
     */
    public function test_fix2_update_course_with_another_courses_code_fails(): void
    {
        $courseA = Course::create([
            'code' => 'CS301',
            'name' => 'Jaringan Komputer',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        $courseB = Course::create([
            'code' => 'CS302',
            'name' => 'Sistem Operasi',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        // Coba perbarui courseB dengan kode milik courseA ('CS301')
        $response = $this->actingAs($this->dosen)->put(route('courses.update', $courseB), [
            'code' => 'CS301', // Milik Course A
            'name' => 'Sistem Operasi Modifikasi',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertDatabaseHas('courses', [
            'id' => $courseB->id,
            'code' => 'CS302', // Tetap CS302, tidak berubah
        ]);
    }
}
