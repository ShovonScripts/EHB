<?php

namespace App\Policies;

use App\Models\User;

class ActivityLogPolicy extends BasePolicy
{
    public function create(User $user): bool
    {
        return false; // Activity logs are auto-created, never manually
    }

    public function update(User $user): bool
    {
        return false; // Activity logs are immutable
    }

    public function delete(User $user): bool
    {
        return false; // Activity logs cannot be deleted from admin
    }
}
