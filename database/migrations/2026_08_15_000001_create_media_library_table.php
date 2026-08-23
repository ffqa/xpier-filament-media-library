<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 50)->default('public');
            $table->string('path')->unique();
            $table->string('original_name')->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('type', 30)->default('image');
            $table->string('source', 20)->default('admin');
            $table->string('folder', 100)->nullable();
            $table->string('alt_text')->nullable();
            $table->timestamps();

            $table->index(['type']);
            $table->index(['folder']);
            $table->index(['source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library');
    }
};
