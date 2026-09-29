<?php

namespace App\Policies;

use App\Models\User;

class ContactPolicy extends BasePolicy
{
    public function view(User $user): bool
    {
        return $user->isOwner() || $user->isEditor();
    }

    public function create(User $user): bool
    {
        return false; // Contacts come from public form only
    }

    public function update(User $user): bool
    {
        return $user->isOwner(); // Only to mark as read
    }

    public function delete(User $user): bool
    {
        return $user->isOwner();
    }
}
