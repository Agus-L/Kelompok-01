<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with('lecturer')->withCount('students');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                  ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }

        // $courses = $query->paginate(10);, pada baris kode ini CourseController.php memanggil paginate(10) tanpa withQueryString(), sehingga ketika user berpindah halaman (klik halaman 2, 3, dst.), parameter filter seperti ?search=... dan ?status=... ikut hilang dari URL, yang menyebabkan hasil filter ter-reset ke kondisi awal meski user sudah melakukan pencarian, seharusnya ditambahkan ->withQueryString() agar query string tetap diteruskan ke setiap link halaman pagination.
        $courses = $query->paginate(10)->withQueryString();

        return view('courses.index', compact('courses'));
    }

    public function create()
    {
        $lecturers = User::where('role', 'dosen')->get();

        return view('courses.create', compact('lecturers'));
    }

    public function store(Request $request)
    {
        // Sebelumnya validasi hanya dilakukan di sisi frontend (HTML required/min/max) tanpa validasi server-side pada method store(), sehingga request langsung dapat memasukkan data tidak valid atau memicu celah mass assignment. Diperbaiki dengan validasi server-side menggunakan $request->validate() dan hanya menyimpan field terverifikasi ($validated).
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:courses,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sks' => 'required|integer|min:1|max:6',
            'lecturer_id' => 'required|exists:users,id',
            'status' => 'required|in:draft,active,archived',
        ], [
            'code.required' => 'Kode mata kuliah wajib diisi.',
            'code.unique' => 'Kode mata kuliah ini sudah dipakai.',
            'name.required' => 'Nama mata kuliah wajib diisi.',
            'sks.required' => 'Jumlah SKS wajib diisi.',
            'sks.integer' => 'SKS harus berupa bilangan bulat.',
            'sks.min' => 'SKS minimal 1.',
            'sks.max' => 'SKS maksimal 6.',
            'lecturer_id.required' => 'Dosen pengampu wajib dipilih.',
            'lecturer_id.exists' => 'Dosen pengampu tidak valid.',
            'status.required' => 'Status publikasi wajib dipilih.',
            'status.in' => 'Status publikasi tidak valid.',
        ]);

        Course::create($validated);

        // $courses = Course::paginate(10);
        // return view('courses.index', compact('courses'));, pada baris kode ini method store langsung mengembalikan view courses.index tanpa melakukan redirect, sehingga melanggar pola Post/Redirect/Get (PRG) dan berisiko memicu form resubmission (duplikasi data saat halaman di-refresh), serta memotong logika filter dan eager loading relasi yang ada di method index(). Seharusnya diarahkan menggunakan redirect() ke route courses.index dengan membawa flash message notifikasi berhasil.
        return redirect()->route('courses.index')->with('success', 'Mata kuliah berhasil ditambahkan.');
    }

    public function show(Course $course)
    {
        $course->load(['lecturer', 'materials', 'assignments.submissions']);

        return view('courses.show', compact('course'));
    }

    public function edit(Course $course)
    {
        $lecturers = User::where('role', 'dosen')->get();

        return view('courses.edit', compact('course', 'lecturers'));
    }

    public function update(Request $request, Course $course)
    {
        // Sebelumnya 'code' => 'required|string|max:20|unique:courses,code' menyebabkan kegagalan validasi saat UPDATE jika kode mata kuliah tidak diubah, karena query unique menganggap record yang sedang diedit sebagai duplikat dirinya sendiri. Diperbaiki menggunakan Rule::unique('courses', 'code')->ignore($course->id).
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($course->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sks' => 'required|integer|min:1|max:6',
            'lecturer_id' => 'required|exists:users,id',
            'status' => 'required|in:draft,active,archived',
        ], [
            'code.required' => 'Kode mata kuliah wajib diisi.',
            'code.unique' => 'Kode mata kuliah ini sudah dipakai.',
            'name.required' => 'Nama mata kuliah wajib diisi.',
            'sks.required' => 'Jumlah SKS wajib diisi.',
            'sks.integer' => 'SKS harus berupa bilangan bulat.',
            'sks.min' => 'SKS minimal 1.',
            'sks.max' => 'SKS maksimal 6.',
            'lecturer_id.required' => 'Dosen pengampu wajib dipilih.',
            'lecturer_id.exists' => 'Dosen pengampu tidak valid.',
            'status.required' => 'Status publikasi wajib dipilih.',
            'status.in' => 'Status publikasi tidak valid.',
        ]);

        $course->update($validated);

        return redirect()->route('courses.show', $course)->with('success', 'Mata kuliah berhasil diperbarui.');
    }

    public function destroy(Course $course)
    {
        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Mata kuliah berhasil dihapus.');
    }
}
