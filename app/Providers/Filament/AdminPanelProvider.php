<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\EditProfile;
use App\Filament\Resources\JournalistProfiles\JournalistProfileResource;
use App\Filament\Widgets\ContentStatusStats;
use App\Filament\Widgets\QuickNewContentWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\RecentlyPublishedWidget;
use App\Filament\Widgets\UnreadContactStats;
use App\Filament\Widgets\UpcomingScheduledWidget;
use App\Models\JournalistProfile;
use App\Support\SiteSettings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            // Sign-in is a single passcode, and this is the only place it can
            // be changed. Enabled explicitly so it is not a hidden URL.
            ->profile(EditProfile::class)
            ->darkMode(false)
            ->colors([
                'primary' => Color::Rose,
            ])
            // Resolve the site name from the admin-editable settings rather than
            // config('app.name'). Wrapped in a closure so it is only read when an
            // admin page actually renders — the panel is registered on every
            // request, and a database read here would break the /up health check
            // and any public page during an outage.
            ->brandName(fn (): string => SiteSettings::siteName())
            ->viteTheme('resources/css/admin.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // ADMIN_PANEL.md §2 — content counts, quick create, recently
                // published, upcoming scheduled, unread messages, activity.
                // Filament's stock AccountWidget/FilamentInfoWidget are omitted:
                // the account controls live in the topbar user menu, and the
                // dashboard should be content, not framework promotion.
                ContentStatusStats::class,
                QuickNewContentWidget::class,
                RecentlyPublishedWidget::class,
                UpcomingScheduledWidget::class,
                UnreadContactStats::class,
                RecentActivityWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                'Content',
                'Journalist',
                'Media',
                'Messages',
                'SEO & Settings',
            ])
            // The author profile is a singleton (one journalist), so its
            // resource is edit-only and Filament deliberately refuses to build
            // a nav item for a resource with no index page
            // (Resources/Resource/Concerns/HasNavigation::getNavigationItems).
            // The nav entry is therefore registered explicitly here, pointing
            // straight at the one profile's form. This is also why it is
            // labelled "Author profile" and not "Profile": /admin/profile is
            // the account page (name, email, passcode).
            ->navigationItems([
                NavigationItem::make('Author profile')
                    ->group('Journalist')
                    ->icon(Heroicon::OutlinedUser)
                    ->url(fn (): string => JournalistProfileResource::getNavigationUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.journalist-profile.*'))
                    // Hidden until a profile exists — there is nothing to edit,
                    // and the URL would have nowhere to point.
                    ->visible(fn (): bool => JournalistProfile::current() !== null
                        && Gate::allows('viewAny', JournalistProfile::class)),
            ]);
    }
}
