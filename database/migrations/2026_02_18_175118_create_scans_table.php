<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('doc_type'); // report_card, birth_cert, etc.
            $table->string('image_path'); // Where the file is stored
            $table->string('lrn')->nullable(); // Only for Report Cards
            $table->string('status')->default('pending'); // pending, verified, failed
            $table->string('remarks')->nullable(); // e.g., "Grade 10 Verified"
            $table->timestamps();
        });
    }
}