<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;

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
        Course::create([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'sks' => $request->sks,
            'lecturer_id' => $request->lecturer_id,
            'status' => $request->status ?? 'draft',
        ]);

        $courses = Course::paginate(10);

        return view('courses.index', compact('courses'));
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
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:courses,code',
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
        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Mata kuliah berhasil dihapus.');
    }
}
