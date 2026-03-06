<?php

namespace App\Policies;

use App\Models\Semester;
use App\Models\User;

class SemesterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function view(User $user, Semester $semester): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Semester $semester): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Semester $semester): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Semester $semester): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Semester $semester): bool
    {
        return $user->isAdmin();
    }
}
