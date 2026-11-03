<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Grade;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    public function store(Request $request, Assignment $assignment)
    {
        $user = $request->user();

        // Check if student is enrolled in course
        if ($user->role === 'mahasiswa' && ! $assignment->course->students()->where('user_id', $user->id)->exists()) {
            abort(403, 'Anda tidak terdaftar dalam mata kuliah ini.');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
            // 'file' => 'required|file|mimes:pdf,zip,rar,docx|max:'.env('MAX_SUBMISSION_SIZE', 10240),
            // Memanggil helper env() di luar direktori config/ (seperti di Controller) adalah bad practice di Laravel.
            // Saat perintah 'php artisan config:cache' dijalankan di production, berkas .env tidak akan dimuat lagi.
            // Akibatnya, semua pemanggilan env() di luar config/ akan selalu mengembalikan null (atau nilai fallback-nya),
            // sehingga konfigurasi di file .env diabaikan.
            // Jadi solusinya adalah nilai environment didaftarkan di berkas config (misal: config/app.php -> 'max_submission_size'),
            // lalu dipanggil di Controller menggunakan helper config().
            'file' => 'required|file|mimes:pdf,zip,rar,docx|max:'.config('app.max_submission_size', 10240),
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();

        // Store with secure random filename in private storage
        $hashedName = Str::random(40).'.'.$file->getClientOriginalExtension();
        $filePath = $file->storeAs('submissions', $hashedName, 'local');

        Submission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'user_id' => $user->id],
            [
                'file_path' => $filePath,
                'original_name' => $originalName,
                'file_size' => $fileSize,
                'notes' => $validated['notes'] ?? null,
                'submitted_at' => now(),
                'status' => 'submitted',
            ]
        );

        return redirect()->route('courses.show', $assignment->course_id)->with('success', 'Tugas berhasil dikirim.');
    }

    public function download(Submission $submission)
    {
        Gate::authorize('download', $submission);

        if (! $submission->file_path || ! Storage::disk('local')->exists($submission->file_path)) {
            abort(404, 'Berkas submission tidak ditemukan.');
        }

        return Storage::disk('local')->download($submission->file_path, $submission->original_name);
    }

    public function grade(Request $request, Submission $submission)
    {
        Gate::authorize('grade', $submission);

        $validated = $request->validate([
            'score' => 'required|integer|min:0|max:'.$submission->assignment->max_score,
            'feedback' => 'nullable|string',
        ]);

        Grade::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'graded_by' => $request->user()->id,
                'score' => $validated['score'],
                'feedback' => $validated['feedback'] ?? null,
                'graded_at' => now(),
            ]
        );

        $submission->update(['status' => 'graded']);

        return redirect()->back()->with('success', 'Nilai berhasil disimpan.');
    }
}
