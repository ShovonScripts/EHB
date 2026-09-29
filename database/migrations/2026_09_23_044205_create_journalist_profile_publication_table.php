<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journalist_profile_publication', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journalist_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('publication_id')->constrained()->onDelete('cascade');
            $table->unique(['journalist_profile_id', 'publication_id'], 'jpp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journalist_profile_publication');
    }
};
