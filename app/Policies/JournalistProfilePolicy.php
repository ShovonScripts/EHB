<?php

namespace App\Policies;

use App\Models\User;

/**
 * The journalist profile is a singleton, and the whole public site hangs off it.
 *
 * Editing is a normal owner capability. **Deleting is not available at all**,
 * and the restriction lives here rather than only in the UI so that it holds
 * for every path — the Filament delete action, a tinker call, a future API
 * route, or a direct `->delete()` on the model.
 *
 * Why it matters concretely: the profile is the only source of the site name in
 * the header, the bio in the footer, the whole /about page, the /contact
 * details, the 404 page, and the Person structured data. One click on a delete
 * button would leave a site whose own name and biography have vanished and
 * whose /about 404s — with no obvious way back, because the admin panel's own
 * branding and navigation also read from the profile.
 *
 * `BasePolicy` allows delete for the owner; this is the deliberate exception.
 * The record is seeded and is not meant to be removable. If the profile is ever
 * genuinely wrong, the fix is to correct its fields.
 *
 * Signatures match BasePolicy exactly (which takes no $model argument) —
 * widening them is an incompatible declaration, not a refinement.
 */
class JournalistProfilePolicy extends BasePolicy
{
    public function delete(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }

    public function restore(User $user): bool
    {
        return false;
    }
}
