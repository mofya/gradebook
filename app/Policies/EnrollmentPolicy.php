<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $enrollment->loadMissing('courseOffering');

        return $user->isLecturer() && $enrollment->courseOffering->isLecturerAssigned($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $enrollment->loadMissing('courseOffering');

        return $user->isLecturer() && $enrollment->courseOffering->isLecturerAssigned($user);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdmin();
    }
}
