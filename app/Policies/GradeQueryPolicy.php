<?php

namespace App\Policies;

use App\Models\GradeQuery;
use App\Models\User;

class GradeQueryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GradeQuery $gradeQuery): bool
    {
        if ($user->isAdmin() || $user->isLecturer()) {
            return true;
        }

        return $user->isStudent() && $gradeQuery->student?->email === $user->email;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, GradeQuery $gradeQuery): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function delete(User $user, GradeQuery $gradeQuery): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, GradeQuery $gradeQuery): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, GradeQuery $gradeQuery): bool
    {
        return $user->isAdmin();
    }
}
