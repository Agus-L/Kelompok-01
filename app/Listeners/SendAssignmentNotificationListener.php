<?php

namespace App\Listeners;

use App\Events\AssignmentCreated;
use App\Notifications\NewAssignmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendAssignmentNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AssignmentCreated $event): void
    {
        $students = $event->assignment->course->students;

        // foreach ($students as $student) {
        //     $student->notify(new NewAssignmentNotification($event->assignment));
        // }
        // Mengirim notifikasi satu per satu di dalam loop (N+1) menyebabkan inefisiensi performa karena mengeksekusi proses dan query pengiriman berulang kali sebanyak jumlah siswa ($students). Diganti menggunakan Notification::send() agar notifikasi dikirim secara massal (bulk) ke seluruh koleksi siswa dalam satu operasi.
        Notification::send($students, new NewAssignmentNotification($event->assignment));
    }
}
