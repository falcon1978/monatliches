<?php

namespace App\Policies;

use App\Models\AccountBalance;
use App\Models\User;

class AccountBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AccountBalance $balance): bool
    {
        return $user->id === $balance->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AccountBalance $balance): bool
    {
        return $user->id === $balance->user_id;
    }

    public function delete(User $user, AccountBalance $balance): bool
    {
        return $user->id === $balance->user_id;
    }
}
