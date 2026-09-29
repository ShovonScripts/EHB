<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_item_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('topic_id')->constrained()->onDelete('cascade');
            $table->unique(['content_item_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_item_topic');
    }
};
