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

    /**
     * FIX 3 - Test: Filter pencarian dan status tidak disimpan ke session (stateless)
     */
    public function test_fix3_filters_are_stateless_and_not_stored_in_session(): void
    {
        Course::create([
            'code' => 'CS101',
            'name' => 'Algoritma dan Pemrograman',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        Course::create([
            'code' => 'CS102',
            'name' => 'Basis Data Lanjutan',
            'sks' => 3,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        // Request 1: Cari Algoritma
        $response1 = $this->actingAs($this->dosen)->get('/courses?search=Algoritma');
        $response1->assertOk();
        $response1->assertSee('Algoritma dan Pemrograman');
        $response1->assertDontSee('Basis Data Lanjutan');

        // Pastikan session TIDAK menyimpan parameter pencarian
        $this->assertFalse(session()->has('course_search'));
        $this->assertFalse(session()->has('course_status'));

        // Request 2: Akses halaman /courses tanpa query parameter
        // Harus menampilkan semua course aktif (tidak lengket ke filter sebelumnya)
        $response2 = $this->actingAs($this->dosen)->get('/courses');
        $response2->assertOk();
        $response2->assertSee('Algoritma dan Pemrograman');
        $response2->assertSee('Basis Data Lanjutan');
    }

    /**
     * FIX 4 - Test: Pagination mempertahankan query string pencarian dan filter
     */
    public function test_fix4_pagination_preserves_query_string(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Course::create([
                'code' => sprintf('CS4%02d', $i),
                'name' => 'Course Test ' . $i,
                'sks' => 3,
                'lecturer_id' => $this->dosen->id,
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($this->dosen)->get('/courses?search=Course&status=active');
        $response->assertOk();

        // Cek apakah link pagination mengandung parameter query string
        $response->assertSee('search=Course');
        $response->assertSee('status=active');
    }

    /**
     * FIX 5 - Test: Method store me-redirect ke courses.index dengan pesan sukses (pola Post/Redirect/Get)
     */
    public function test_fix5_store_redirects_to_index_with_flash_message(): void
    {
        $response = $this->actingAs($this->dosen)->post('/courses', [
            'code' => 'CS501',
            'name' => 'Rekayasa Perangkat Lunak',
            'description' => 'Mata kuliah RPL',
            'sks' => 4,
            'lecturer_id' => $this->dosen->id,
            'status' => 'active',
        ]);

        // Memastikan redirect 302, BUKAN return view 200
        $response->assertStatus(302);
        $response->assertRedirect(route('courses.index'));
        $response->assertSessionHas('success', 'Mata kuliah berhasil ditambahkan.');
    }

    /**
     * FIX 6 - Test: Form create menyertakan CSRF token
     */
    public function test_fix6_create_form_contains_csrf_token(): void
    {
        $response = $this->actingAs($this->dosen)->get(route('courses.create'));
        $response->assertOk();
        $response->assertSee('name="_token"', false);
    }
}
