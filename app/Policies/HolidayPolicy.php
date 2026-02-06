<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $user->id === $holiday->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->id === $holiday->user_id;
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->id === $holiday->user_id;
    }

    public function restore(User $user, Holiday $holiday): bool
    {
        return $user->id === $holiday->user_id;
    }

    public function forceDelete(User $user, Holiday $holiday): bool
    {
        return $user->id === $holiday->user_id;
    }
}
