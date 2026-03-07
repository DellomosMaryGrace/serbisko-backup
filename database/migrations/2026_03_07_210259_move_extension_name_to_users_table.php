<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the column to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('extension_name', 10)->nullable()->after('last_name');
        });

        // 2. Transfer existing data from students to users
        $studentsWithExtensions = DB::table('students')
            ->whereNotNull('extension_name')
            ->get();

        foreach ($studentsWithExtensions as $student) {
            DB::table('users')
                ->where('id', $student->user_id)
                ->update(['extension_name' => $student->extension_name]);
        }

        // 3. Remove the column from students
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('extension_name');
        });
    }

    public function down(): void
    {
        // Reverse the process: Add back to students and drop from users
        Schema::table('students', function (Blueprint $table) {
            $table->string('extension_name', 10)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('extension_name');
        });
    }
};
