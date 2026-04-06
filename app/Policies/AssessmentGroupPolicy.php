<?php

namespace App\Policies;

use App\Models\AssessmentGroup;
use App\Models\User;

class AssessmentGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function view(User $user, AssessmentGroup $assessmentGroup): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $assessmentGroup->loadMissing('courseOffering');

        return $user->isLecturer() && $assessmentGroup->courseOffering->isLecturerAssigned($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function update(User $user, AssessmentGroup $assessmentGroup): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $assessmentGroup->loadMissing('courseOffering');

        return $user->isLecturer() && $assessmentGroup->courseOffering->isLecturerAssigned($user);
    }

    public function delete(User $user, AssessmentGroup $assessmentGroup): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, AssessmentGroup $assessmentGroup): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, AssessmentGroup $assessmentGroup): bool
    {
        return $user->isAdmin();
    }
}
