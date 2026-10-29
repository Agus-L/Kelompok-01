<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Notifications\NewAssignmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendAssignmentNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public Assignment $assignment)
    {
    }

    public function handle(): void
    {
        $students = $this->assignment->course->students;

        // foreach ($students as $student) {
        //     $student->notify(new NewAssignmentNotification($this->assignment));
        // }
        // Mengirim notifikasi satu per satu di dalam loop (N+1) menyebabkan inefisiensi performa karena mengeksekusi operasi dan query secara individual sebanyak jumlah siswa. Diganti menggunakan Notification::send($students, ...) agar pengiriman dikirim secara massal (bulk) dalam satu operasi.
        Notification::send($students, new NewAssignmentNotification($this->assignment));
    }
}
