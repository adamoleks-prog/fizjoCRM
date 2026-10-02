<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // The phone given when booking online. Texts about that booking go
            // here — it may differ from the number on the patient's card.
            $table->string('booking_phone', 20)->nullable()->after('cancel_token');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('booking_phone');
        });
    }
};
