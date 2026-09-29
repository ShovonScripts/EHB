<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A piece can legitimately have no dek: the outlet printed none, or the
        // entry is still waiting on a standfirst before it can be published.
        // The list views already guard for a missing summary, and the admin
        // form still requires one to publish (see ContentItemForm).
        Schema::table('content_items', function (Blueprint $table) {
            $table->text('summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->text('summary')->nullable(false)->change();
        });
    }
};
