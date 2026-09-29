<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second rich-text field on a content item, edited with the application's
 * own editor (App\Filament\Forms\Components\RichTextField) rather than
 * Filament's third-party one.
 *
 * Nullable, and independent of `body`: a piece may fill either, both, or
 * neither. `source_type` does not gate this column — the field is offered for
 * every piece, so text can be captured against an external piece without
 * changing whether the public page reproduces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->longText('secondary_body')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropColumn('secondary_body');
        });
    }
};
