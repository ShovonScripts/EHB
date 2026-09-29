<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journalist_profiles', function (Blueprint $table) {
            // FR-306: skills/areas of reporting (beats) as a simple tag-like list.
            $table->json('skills')->nullable()->after('long_bio');
        });
    }

    public function down(): void
    {
        Schema::table('journalist_profiles', function (Blueprint $table) {
            $table->dropColumn('skills');
        });
    }
};
