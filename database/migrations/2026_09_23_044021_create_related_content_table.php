<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('related_content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_content_item_id')->constrained('content_items')->onDelete('cascade');
            $table->unique(['content_item_id', 'related_content_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('related_content');
    }
};
