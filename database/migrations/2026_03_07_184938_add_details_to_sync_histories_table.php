<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_histories', function (Blueprint $table) {
            // Adding the columns exactly as you did in SQL
            $table->integer('new_records')->default(0)->after('records_synced');
            $table->integer('updated_records')->default(0)->after('new_records');
            
            // Laravel migrations usually include timestamps by default, 
            // but if you only need the updated_at column:
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sync_histories', function (Blueprint $table) {
            $table->dropColumn(['new_records', 'updated_records', 'updated_at']);
        });
    }
};
