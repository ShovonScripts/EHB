<?php

namespace Tests;

use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->flushLivewireRenderState();
        $this->flushSiteSettingsMemo();
    }

    /**
     * Drop SiteSettings' per-request memo.
     *
     * `SiteSettings::$memo` is static and only reset by `SiteSettings::flush()`,
     * which fires on a model save. In a fresh process per request that is
     * exactly right, but a test suite runs many tests in one process — so a
     * value written by one test was still being read by the next. That produced
     * failures which looked like application bugs and were not: "no twitter
     * tags without a handle" failed because an earlier test had set one.
     */
    private function flushSiteSettingsMemo(): void
    {
        SiteSettings::flush();
    }

    /**
     * Reset Livewire's per-request asset-injection flag.
     *
     * `SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest` is static
     * and only cleared by Livewire's own `flush-state` hook, which fires on a
     * Livewire request. A Filament test that renders a component therefore
     * leaves it set, and any *later* test in the same process gets Livewire's
     * script tag — carrying a per-session `data-csrf` token — injected into
     * the public page markup it renders.
     *
     * That made assertions order-dependent: a response cache test asserting
     * byte-identical bodies passed in isolation and failed in a full run,
     * purely because of which tests happened to run before it.
     */
    private function flushLivewireRenderState(): void
    {
        if (app()->bound('livewire')) {
            app('livewire')->flushState();
        }
    }
}
