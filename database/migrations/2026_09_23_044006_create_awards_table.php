<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journalist_profile_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('awarding_body');
            $table->year('year');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('media_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
