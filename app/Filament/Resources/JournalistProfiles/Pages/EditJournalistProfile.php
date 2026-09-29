<?php

namespace App\Filament\Resources\JournalistProfiles\Pages;

use App\Filament\Resources\JournalistProfiles\JournalistProfileResource;
use Filament\Navigation\Breadcrumbs\Breadcrumb;
use Filament\Resources\Pages\EditRecord;

/**
 * Edit form for the one and only journalist profile.
 *
 * There is no delete action, deliberately. The profile is a singleton that the
 * entire public site depends on — site name, header, footer, /about, /contact
 * and the 404 page all read from it — so removing it would break the site with
 * no obvious way back. The rule is enforced in JournalistProfilePolicy as well,
 * so it holds for any path, not just this screen.
 */
class EditJournalistProfile extends EditRecord
{
    protected static string $resource = JournalistProfileResource::class;

    /**
     * No breadcrumb.
     *
     * With the index page gone there is no hierarchy to show, and Filament's
     * default degrades to the record title plus the word "Edit" — a trail
     * reading "Emrul Hasan Bappi Edit", which is not navigation. The nav item
     * highlights this page, and the H1 names it.
     *
     * @return array<int, Breadcrumb>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }
}
