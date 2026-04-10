<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('patient_id');           // matches users.id (increments = INT UNSIGNED)
            $table->unsignedInteger('doctor_id');            // matches doctors.id (increments = INT UNSIGNED)
            $table->date('appointment_date');
            $table->string('appointment_time', 10);          // store as "09:00" string — simpler than time()
            $table->string('status')->default('pending');    // pending, confirmed, completed, cancelled
            $table->string('type')->default('in_person');    // in_person, video
            $table->text('notes')->nullable();
            $table->text('prescription')->nullable();
            $table->text('doctor_notes')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('doctor_id')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};