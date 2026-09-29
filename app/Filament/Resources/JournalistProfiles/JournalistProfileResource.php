<?php

namespace App\Filament\Resources\JournalistProfiles;

use App\Filament\Resources\JournalistProfiles\Pages\EditJournalistProfile;
use App\Filament\Resources\JournalistProfiles\Schemas\JournalistProfileForm;
use App\Models\JournalistProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The journalist's own profile — a singleton, not a collection.
 *
 * There is one author. The resource deliberately has **no index and no create
 * page**: the nav item goes straight to the edit form for the one record that
 * exists, and there is no route on which a second profile could be made.
 *
 * That is not cosmetic. A bare `JournalistProfile::first()` is
 * non-deterministic once there is more than one row, and the public site
 * resolves the profile in seven separate places (home, about, contact, feed,
 * header, footer, 404). Two profiles would therefore produce a site that shows
 * one biography on the homepage and a different name in the footer, with no
 * error anywhere — a genuinely hard bug to trace. `CareerHistory`, `Education`
 * and `Award` also `belongsTo` a profile through a select box, so a second row
 * silently reassigns the author's own career history and awards to them.
 *
 * See `JournalistProfile::current()` for the accessor the public site uses.
 */
class JournalistProfileResource extends Resource
{
    protected static ?string $model = JournalistProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|UnitEnum|null $navigationGroup = 'Journalist';

    protected static ?string $navigationLabel = 'Author profile';

    protected static ?string $modelLabel = 'author profile';

    protected static ?string $pluralModelLabel = 'author profile';

    // Deliberately not "profile": Filament's account page (name, email,
    // passcode) already owns /admin/profile. This resource is the *public*
    // author identity — bio, photo, beats, career — so it is named to keep the
    // two apart in the nav as well as in the URL.
    protected static ?string $slug = 'journalist-profile';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return JournalistProfileForm::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Edit-only. No `index`, no `create`.
     */
    public static function getPages(): array
    {
        return [
            'edit' => EditJournalistProfile::route('/{record}/edit'),
        ];
    }

    /**
     * Point the nav item straight at the profile's form — there is no list to
     * go to, because there is only ever one row.
     *
     * Falls back to the dashboard when no profile exists yet. That case is
     * reachable (a fresh install seeds none, and several tests run without
     * one), and returning a null record would make the route ungeneratable and
     * 500 every admin page that renders the sidebar.
     */
    public static function getNavigationUrl(): string
    {
        $profile = static::record();

        return $profile
            ? static::getUrl('edit', ['record' => $profile])
            : filament()->getUrl();
    }

    /**
     * Filament's `EditRecord` builds a breadcrumb that links "back" to the
     * resource's index, and throws a LogicException when there is no index
     * page to link to. There is nothing to go "back" to on a singleton, so the
     * breadcrumb trail root is the form itself. If no profile exists yet there
     * is still no edit URL, so the dashboard stands in.
     *
     * Signature mirrors the parent (CanGenerateUrls::getIndexUrl) — narrowing
     * it would be an incompatible declaration.
     */
    public static function getIndexUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
    ): string {
        $profile = $parameters['record'] ?? static::record();

        if ($profile) {
            return static::getUrl('edit', ['record' => $profile], $isAbsolute, $panel, $tenant);
        }

        return filament()->getUrl($panel);
    }

    public static function getNavigationBadge(): ?string
    {
        // A count of "1" is noise on a singleton.
        return null;
    }

    private static function record(): ?JournalistProfile
    {
        return JournalistProfile::current();
    }
}
