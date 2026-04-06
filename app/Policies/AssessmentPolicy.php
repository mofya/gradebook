<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function view(User $user, Assessment $assessment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $assessment->loadMissing('assessmentGroup.courseOffering');

        return $user->isLecturer() && $assessment->assessmentGroup->courseOffering->isLecturerAssigned($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function update(User $user, Assessment $assessment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $assessment->loadMissing('assessmentGroup.courseOffering');

        return $user->isLecturer() && $assessment->assessmentGroup->courseOffering->isLecturerAssigned($user);
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Assessment $assessment): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Assessment $assessment): bool
    {
        return $user->isAdmin();
    }
}
