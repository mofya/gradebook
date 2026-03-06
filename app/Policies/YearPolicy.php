<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Year;

class YearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function view(User $user, Year $year): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Year $year): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Year $year): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Year $year): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Year $year): bool
    {
        return $user->isAdmin();
    }
}
