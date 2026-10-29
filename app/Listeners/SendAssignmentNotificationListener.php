<?php

namespace App\Listeners;

use App\Events\AssignmentCreated;
use App\Notifications\NewAssignmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAssignmentNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AssignmentCreated $event): void
    {
        $students = $event->assignment->course->students;

        foreach ($students as $student) {
            $student->notify(new NewAssignmentNotification($event->assignment));
        }
    }
}
