<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_item_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('media_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('caption')->nullable();
            $table->unique(['content_item_id', 'media_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_item_media');
    }
};
