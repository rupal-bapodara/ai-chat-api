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
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('original_name');
            $table->unsignedInteger('file_size');
            $table->string('path');
            $table->string('status')->default('pending');
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->longText('content');
            $table->timestamps();
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('page_number')->nullable();
            $table->longText('content');
            $table->timestamps();
        });

        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chunk_id')->constrained('document_chunks')->cascadeOnDelete();
            $table->longText('embedding');
            $table->timestamps();
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn('document_id');
        });

        Schema::dropIfExists('document_embeddings');
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('document_contents');
        Schema::dropIfExists('documents');
    }
};
