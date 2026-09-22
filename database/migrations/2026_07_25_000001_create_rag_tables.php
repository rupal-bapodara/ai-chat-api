<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->timestamps();
        });

        // Laravel's Blueprint has no native pgvector column type, so the
        // embedding column and its ANN index are added via raw SQL. 384
        // dimensions matches sentence-transformers/all-MiniLM-L6-v2 (see
        // config/embeddings.php). HNSW is used over IVFFlat because IVFFlat
        // needs a `lists` parameter tuned to the eventual row count and
        // retraining as data grows -- a poor fit for a table that starts
        // empty and grows one upload at a time. vector_cosine_ops matches
        // how sentence-transformer embeddings are intended to be compared.
        DB::statement('ALTER TABLE document_embeddings ADD COLUMN embedding vector(384) NOT NULL');
        DB::statement('CREATE INDEX document_embeddings_embedding_hnsw_idx ON document_embeddings USING hnsw (embedding vector_cosine_ops)');

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
