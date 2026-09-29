<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Award;
use App\Models\CareerHistory;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContentItem;
use App\Models\Education;
use App\Models\JournalistProfile;
use App\Models\Media;
use App\Models\Page;
use App\Models\Publication;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Topic;
use App\Policies\ActivityLogPolicy;
use App\Policies\AwardPolicy;
use App\Policies\CareerHistoryPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ContactPolicy;
use App\Policies\ContentItemPolicy;
use App\Policies\EducationPolicy;
use App\Policies\JournalistProfilePolicy;
use App\Policies\MediaPolicy;
use App\Policies\PagePolicy;
use App\Policies\PublicationPolicy;
use App\Policies\RedirectPolicy;
use App\Policies\SettingPolicy;
use App\Policies\TagPolicy;
use App\Policies\TopicPolicy;
use App\Support\HostedSections;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerRateLimits();
        $this->registerViewComposers();
    }

    private function registerPolicies(): void
    {
        Gate::policy(ContentItem::class, ContentItemPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Topic::class, TopicPolicy::class);
        Gate::policy(Publication::class, PublicationPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(JournalistProfile::class, JournalistProfilePolicy::class);
        Gate::policy(CareerHistory::class, CareerHistoryPolicy::class);
        Gate::policy(Award::class, AwardPolicy::class);
        Gate::policy(Education::class, EducationPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Redirect::class, RedirectPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
    }

    /**
     * SECURITY.md §9 — rate limits for public endpoints.
     */
    private function registerRateLimits(): void
    {
        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }

    /**
     * The content_type pages (/articles, /investigations, /opinions, ...)
     * list only work hosted on this site, so they are empty for as long as
     * everything lives at the outlet. Rather than ship four permanent dead
     * links in the nav, share whether any on-site section has content and let
     * the layout decide what to show.
     */
    private function registerViewComposers(): void
    {
        View::composer('components.app-layout', function ($view) {
            $view->with('hostedSections', HostedSections::resolve());
        });
    }
}
