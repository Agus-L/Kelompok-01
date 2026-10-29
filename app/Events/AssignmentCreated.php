<?php

namespace App\Events;

use App\Models\Assignment;
// use Illuminate\Broadcasting\Channel;, 
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Assignment $assignment)
    {
    }

    // public function broadcastOn(): array
    // {
    //     return [
    //         new Channel('course.'.$this->assignment->course_id),
    //     ];
    // }
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('course.'.$this->assignment->course_id),
        ];
    }
    // Event AssignmentCreated.php memancarkan siaran menggunakan channel publik new Channel(...) bukan PrivateChannel, sehingga data penugasan dapat disadap dan didengarkan oleh siapa pun yang terhubung ke server websocket tanpa proses otorisasi pendaftaran mata kuliah, yang seharusnya dilindungi menggunakan new PrivateChannel('course.'.$this->assignment->course_id).
}
