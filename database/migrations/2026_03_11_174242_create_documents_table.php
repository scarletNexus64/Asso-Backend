<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type'); // pdf, docx, xlsx, etc.
            $table->bigInteger('file_size'); // en bytes
            $table->string('mime_type');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->enum('visibility', ['public', 'private', 'restricted'])->default('private');
            $table->json('allowed_users')->nullable(); // IDs des utilisateurs autorisés
            $table->integer('download_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
