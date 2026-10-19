<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'dosen']);
    }

    public function view(User $user, Submission $submission): bool
    {
        if ($user->role === 'admin' || $submission->assignment->course->lecturer_id === $user->id) {
            return true;
        }

        return $submission->user_id === $user->id;
    }

    public function download(User $user, Submission $submission): bool
    {
        return $this->view($user, $submission);
    }

    public function grade(User $user, Submission $submission): bool
    {
        return $user->role === 'admin' || $submission->assignment->course->lecturer_id === $user->id;
    }
}
