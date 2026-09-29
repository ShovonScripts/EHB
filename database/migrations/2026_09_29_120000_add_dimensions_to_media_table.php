<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored pixel dimensions for share-card tags (SEO.md §4).
     *
     * Facebook/LinkedIn/X read og:image:width/height to lay out the card
     * before the image finishes downloading; without them the first scrape
     * of every URL renders a collapsed card and sometimes no image at all
     * until the crawler revisits. Measuring at render time would cost a
     * filesystem read per request, so the size of the stored bytes is
     * captured once — on Media save — and read from here.
     *
     * Nullable because non-image rows (video, audio, document) never have
     * dimensions, and because rows created before this migration are filled
     * in by `php artisan media:backfill-dimensions`, not here: a migration
     * must not fail a deploy because an old file is missing from disk.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('caption');
            $table->unsignedInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
    }
};
