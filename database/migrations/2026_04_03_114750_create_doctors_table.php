<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->increments('id');                        // INT UNSIGNED — matches doctors.id for child tables
            $table->unsignedInteger('doc_id')->unique();     // INT UNSIGNED — matches users.id (increments)
            $table->string('category')->nullable();
            $table->integer('experience')->default(0);
            $table->integer('patients')->default(0);
            $table->text('bio_data')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('rating', 3, 1)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->string('hospital')->nullable();
            $table->string('education', 1000)->nullable();
            $table->string('address', 500)->nullable();
            $table->json('languages')->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('available_from', 10)->nullable();
            $table->string('available_to', 10)->nullable();
            $table->timestamps();

            $table->foreign('doc_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};