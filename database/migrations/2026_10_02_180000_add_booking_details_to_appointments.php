<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Name and e-mail as typed in the online form — kept even when the
            // visit was matched to an existing card, so it can still become a
            // new card if the physiotherapist decides it is someone else.
            $table->json('booking_details')->nullable()->after('booking_phone');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('booking_details');
        });
    }
};
