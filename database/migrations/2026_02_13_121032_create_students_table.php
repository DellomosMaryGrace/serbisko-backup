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
        Schema::create('students', function (Blueprint $table) {
            $table->string('lrn')->primary(); // LRN from form
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('sex', ['Male', 'Female']); // Sex field
            $table->integer('age'); // Age field
            $table->string('place_of_birth'); // Place of Birth
            $table->string('mother_tongue'); // Mother Tongue
            $table->string('contact_number'); // Contact Number
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
