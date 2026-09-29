<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename the second rich-text field from `secondary_body` to `news_body`.
 *
 * `secondary_body` was a placeholder name chosen before the field's purpose was
 * settled, and "Secondary Text" is not a name anyone editing a piece would use
 * out loud. The field is "News Body".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded rather than assumed: on a database where the column is
        // already renamed (or was never added), this migration should be a
        // no-op instead of erroring halfway through a deploy.
        if (! Schema::hasColumn('content_items', 'secondary_body')) {
            return;
        }

        Schema::table('content_items', function (Blueprint $table) {
            $table->renameColumn('secondary_body', 'news_body');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('content_items', 'news_body')) {
            return;
        }

        Schema::table('content_items', function (Blueprint $table) {
            $table->renameColumn('news_body', 'secondary_body');
        });
    }
};
