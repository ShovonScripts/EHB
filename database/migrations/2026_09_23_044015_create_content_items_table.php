<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->enum('content_type', ['news', 'investigation', 'interview', 'opinion', 'video', 'photo_story', 'other']);
            $table->enum('source_type', ['internal', 'external']);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->longText('body')->nullable();
            $table->foreignId('featured_image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('publication_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_url')->nullable();
            $table->string('interviewee_name')->nullable();
            $table->string('interviewee_title')->nullable();
            $table->string('video_url')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('reading_time_minutes')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('seo_og_image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('canonical_url_override')->nullable();
            $table->timestamps();

            $table->index(['content_type', 'status']);
            $table->index(['source_type', 'status']);
            $table->index('published_at');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
