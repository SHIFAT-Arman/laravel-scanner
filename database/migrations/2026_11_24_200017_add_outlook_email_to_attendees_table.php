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
        if (! Schema::hasTable('attendees')) {
            return;
        }

        Schema::table('attendees', function (Blueprint $table) {
            $table->string('outlook_email')
                ->nullable()
                ->storedAs("aiub_id || '@student.aiub.edu'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('attendees')) {
            return;
        }

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropColumn('outlook_email');
        });
    }
};
