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
            $table->string('invitation_email_status')->nullable();
            $table->timestamp('invitation_email_sent_at')->nullable();
            $table->text('invitation_email_error')->nullable();
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
            $table->dropColumn([
                'invitation_email_status',
                'invitation_email_sent_at',
                'invitation_email_error',
            ]);
        });
    }
};
