<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Assignment $assignment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'title' => $this->assignment->title,
            'course_name' => $this->assignment->course->name,
            'due_at' => $this->assignment->due_at->toIso8601String(),
            'message' => "Tugas baru '{$this->assignment->title}' telah ditambahkan.",
            // 'student_grades' => $this->assignment->course->students->map(function ($student) {
            //     return [
            //         'student_id' => $student->id,
            //         'name' => $student->name,
            //         'score' => $student->submissions()->with('grade')->get()->pluck('grade.score')->filter()->avg(),
            //     ];
            // }), mencantumkan daftar nama dan nilai rata-rata seluruh mahasiswa (student_grades) ke dalam data notifikasi tugas baru, dampaknya memicu kebocoran data pribadi di mana setiap mahasiswa dapat mengintip nilai teman sekelasnya dari payload notifikasi serta menyebabkan query database yang sangat lambat (N+1 queries), sehingga seharusnya bagian student_grades dihapus sepenuhnya agar notifikasi hanya memuat atribut dasar yang relevan dengan tugas baru.
        ];
    }
}
