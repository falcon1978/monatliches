<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        Account::createDefaultsForUser($user);
    }
}
