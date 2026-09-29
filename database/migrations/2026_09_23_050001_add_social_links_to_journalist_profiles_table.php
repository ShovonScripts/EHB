<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journalist_profiles', function (Blueprint $table) {
            // FR-307: social/professional profile links (X/Twitter, LinkedIn, email).
            $table->json('social_links')->nullable()->after('skills');
        });
    }

    public function down(): void
    {
        Schema::table('journalist_profiles', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
