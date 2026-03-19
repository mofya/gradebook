<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function view(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isLecturer();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function viewGrades(User $user, Student $student): bool
    {
        if ($user->isAdmin() || $user->isLecturer()) {
            return true;
        }

        return $user->isStudent() && $user->email === $student->email;
    }

    public function viewTranscript(User $user, Student $student): bool
    {
        return $this->viewGrades($user, $student);
    }
}
