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
        Schema::create('users', function (Blueprint $table) {
            $table->id(); 
            
            // Name Fields
            $table->string('first_name')->index(); 
            $table->string('last_name')->index();
            $table->string('middle_name')->nullable();
            $table->string('extension_name', 10)->nullable(); 
            
            // Login Identifiers
            $table->date('birthday')->index(); 
            $table->string('password'); 
            $table->timestamp('password_changed_at')->nullable();
            
            // Updated to varchar(45) as per your change
            $table->string('role', 45)->default('student'); 
            
            // Soft Deletes - REQUIRED for "Revoke Access" logic
            $table->softDeletes(); 
            
            $table->timestamps(); 
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};