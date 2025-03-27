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
        Schema::create('buckets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('storage_path'); // Path penyimpanan
            $table->string('access_key')->unique(); // ID Key
            $table->string('secret_key'); // Secret
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->boolean('versioning')->default(false); // Aktif/tidak versioning
            $table->boolean('object_lock')->default(false); // Aktif/tidak object lock
            // $table->string('storage_provider')->default('local'); // Bisa MinIO, S3, dsb.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buckets');
    }
};
