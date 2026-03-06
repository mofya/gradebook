<?php

namespace App\Policies;

use App\Models\GradingScheme;
use App\Models\User;

class GradingSchemePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function view(User $user, GradingScheme $gradingScheme): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, GradingScheme $gradingScheme): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GradingScheme $gradingScheme): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, GradingScheme $gradingScheme): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, GradingScheme $gradingScheme): bool
    {
        return $user->isAdmin();
    }
}
