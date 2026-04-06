<?php

namespace App\Policies;

use App\Models\CourseOffering;
use App\Models\User;

class CourseOfferingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function view(User $user, CourseOffering $courseOffering): bool
    {
        return $user->isAdmin() || ($user->isLecturer() && $courseOffering->isLecturerAssigned($user));
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function update(User $user, CourseOffering $courseOffering): bool
    {
        return $user->isAdmin() || ($user->isLecturer() && $courseOffering->isLecturerAssigned($user));
    }

    public function delete(User $user, CourseOffering $courseOffering): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, CourseOffering $courseOffering): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, CourseOffering $courseOffering): bool
    {
        return $user->isAdmin();
    }
}
