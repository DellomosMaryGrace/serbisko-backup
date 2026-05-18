<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_forms', function (Blueprint $table) {
            $table->id();

            // References the existing `users` table already in serbisko_db
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('school_year', 20)->default('2026-2027');

            // JSON array of question objects.
            // Each question's `id` == `field_id` == the sync.js raw.* field name
            // e.g. "lrn", "first_name", "last_name", "birthday"
            $table->json('schema');

            // Firestore document ID in `form_schemas` collection.
            // Set after first successful push from FormBuilderController.
            $table->string('firestore_doc_id')->nullable()->unique();

            // Token used in the Student View URL: ?id=<share_token>
            $table->string('share_token', 64)->unique();

            // Soft-delete keeps the record so synced responses aren't orphaned
            $table->softDeletes();
            $table->timestamps();

            $table->index(['created_by', 'deleted_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('custom_forms', 'school_year')) {
            Schema::table('custom_forms', function (Blueprint $table) {
                $table->dropColumn('school_year');
            });
        }
    }
};
