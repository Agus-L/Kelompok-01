<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Course::class);

        $query = Course::with('lecturer')->withCount('students');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('code', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }

        $courses = $query->paginate(10)->withQueryString();

        return view('courses.index', compact('courses'));
    }

    public function create()
    {
        Gate::authorize('create', Course::class);

        // $lecturers = User::where('role', 'dosen')->get();
        // Pemanggilan User::where('role', 'dosen')->get() mengeksekusi SELECT * yang menarik seluruh kolom tabel users (termasuk password, remember_token, email, dll) ke memori aplikasi, padahal pada view courses/create.blade.php dan courses/edit.blade.php hanya kolom id dan name yang dipakai untuk dropdown pilihan dosen pengampu, sehingga diefisienkan menggunakan ->select('id', 'name').
        $lecturers = User::where('role', 'dosen')->select('id', 'name')->get();

        return view('courses.create', compact('lecturers'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Course::class);

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:courses,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sks' => 'required|integer|min:1|max:6',
            'lecturer_id' => 'required|exists:users,id',
            'status' => 'required|in:draft,active,archived',
        ]);

        Course::create($validated);

        return redirect()->route('courses.index')->with('success', 'Mata kuliah berhasil ditambahkan.');
    }

    public function show(Course $course)
    {
        Gate::authorize('view', $course);

        // $course->load(['lecturer', 'materials']);
        // Method show pada CourseController.php hanya memuat relasi lecturer dan materials tanpa melakukan eager loading bertingkat untuk penugasan, sehingga saat view courses/show.blade.php merender $course->assignments, $assignment->submissions, hingga nama mahasiswa $submission->student->name, Laravel menembakkan puluhan hingga ratusan query tambahan secara berulang (N+1 pada 3 tingkat relasi) yang memperlambat rendering halaman mata kuliah secara drastis, yang seharusnya dicegah dengan menambahkan 'assignments.submissions.student' ke dalam pemanggilan $course->load(...).
        $course->load(['lecturer', 'materials', 'assignments.submissions.student']);

        return view('courses.show', compact('course'));
    }

    public function edit(Course $course)
    {
        Gate::authorize('update', $course);

        // $lecturers = User::where('role', 'dosen')->get();
        // Pemanggilan User::where('role', 'dosen')->get() mengeksekusi SELECT * yang menarik seluruh kolom tabel users, padahal view courses/edit.blade.php hanya membutuhkan kolom id dan name untuk dropdown dosen pengampu, sehingga diefisienkan menggunakan ->select('id', 'name').
        $lecturers = User::where('role', 'dosen')->select('id', 'name')->get();

        return view('courses.edit', compact('course', 'lecturers'));
    }

    public function update(Request $request, Course $course)
    {
        Gate::authorize('update', $course);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($course->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sks' => 'required|integer|min:1|max:6',
            'lecturer_id' => 'required|exists:users,id',
            'status' => 'required|in:draft,active,archived',
        ]);

        $course->update($validated);

        return redirect()->route('courses.show', $course)->with('success', 'Mata kuliah berhasil diperbarui.');
    }

    public function destroy(Course $course)
    {
        Gate::authorize('delete', $course);

        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Mata kuliah berhasil dihapus.');
    }
}
