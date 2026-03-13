<?php

namespace App\Policies;

use App\Models\DigitisationJob;
use App\Models\User;

class DigitisationJobPolicy
{
    /**
     * Users may only view, process, or download results for their own jobs.
     * Admins are not granted elevated access to other users' jobs.
     */
    private function isOwner(User $user, DigitisationJob $job): bool
    {
        return $user->id === $job->user_id;
    }

    public function view(User $user, DigitisationJob $job): bool
    {
        return $this->isOwner($user, $job);
    }

    public function processResults(User $user, DigitisationJob $job): bool
    {
        return $this->isOwner($user, $job) && $job->canImportResults();
    }

    public function download(User $user, DigitisationJob $job): bool
    {
        return $this->isOwner($user, $job) && $job->canImportResults();
    }
}
