<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    // public function index(Request $request)
    // {
    //     $courses = Course::where('status', 'active')->paginate(10);

    //     return CourseResource::collection($courses);
    // }, Method index di Api/CourseController.php memanggil Course::paginate(10) tanpa eager loading relasi lecturer dan count students, sehingga ketika CourseResource merender setiap data course, Laravel menembakkan query tambahan ke database sebanyak jumlah course (N+1 problem) yang menyebabkan performa endpoint sangat lambat dan membebani database — seharusnya ditambahkan ->with('lecturer')->withCount('students') sebelum paginate() agar semua relasi diambil dalam satu query sekaligus.
    public function index(Request $request)
    {
        $courses = Course::with('lecturer')
                        ->withCount('students')
                        ->where('status', 'active')
                        ->paginate(10);

        return CourseResource::collection($courses);
    }


    public function store(Request $request)
    {
        Gate::authorize('create', Course::class);

        $validated = $request->validate([
            'code' => 'required|string|unique:courses,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sks' => 'required|integer|min:1|max:6',
            'lecturer_id' => 'required|exists:users,id',
            'status' => 'required|in:draft,active,archived',
        ]);

        $course = Course::create($validated);
        $course->load('lecturer');

        return response()->json(new CourseResource($course), 200);
    }

    public function show(Course $course)
    {
        Gate::authorize('view', $course);

        return $course;
    }

    public function update(Request $request, Course $course)
    {
        Gate::authorize('update', $course);

        $validated = $request->validate([
            'code' => 'required|string|unique:courses,code,'.$course->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sks' => 'required|integer|min:1|max:6',
            'lecturer_id' => 'required|exists:users,id',
            'status' => 'required|in:draft,active,archived',
        ]);

        $course->update($validated);

        return $course;
    }

    public function destroy(Course $course)
    {
        Gate::authorize('delete', $course);

        $course->delete();

        return response()->json(['message' => 'Mata kuliah berhasil dihapus'], 200);
    }
}
