<?php

namespace App\Policies;

use App\Models\ContentItem;
use App\Models\User;

class ContentItemPolicy extends BasePolicy
{
    /**
     * Determine whether the user can publish the content item.
     */
    public function publish(User $user, ContentItem $item): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can unpublish the content item.
     */
    public function unpublish(User $user, ContentItem $item): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can schedule the content item.
     */
    public function schedule(User $user, ContentItem $item): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can duplicate the content item.
     */
    public function duplicate(User $user, ContentItem $item): bool
    {
        return $user->isOwner();
    }
}
