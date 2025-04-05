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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('disk_id')->constrained('disks')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('type');
            $table->string('hash');
            $table->unsignedBigInteger('size');
            $table->json('meta');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'disk_id', 'name', 'path', 'hash']);
            $table->index(['user_id', 'disk_id', 'name', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
