<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bucket_id')->constrained()->onDelete('cascade');
            $table->string('key'); // Nama file atau folder
            $table->string('path'); // Path di storage
            $table->string('version')->nullable(); // Untuk versioning
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->string('version_id')->nullable(); // ID versi file
            $table->boolean('delete_marker')->default(false); // Untuk soft delete jika pakai versioning
            $table->timestamp('locked_until')->nullable(); // Object Lock
            $table->softDeletes();
            $table->timestamps();

            // $table->unique(['bucket_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objects');
    }
};

