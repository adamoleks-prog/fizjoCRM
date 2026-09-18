<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Nullable: the cycle is chosen when the visit is documented, not when booked.
            // nullOnDelete because the cycle is only a grouping label — losing it must
            // never take the clinical record with it.
            $table->foreignId('therapy_cycle_id')->nullable()->after('patient_id')
                ->constrained('therapy_cycles')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('therapy_cycle_id');
        });
    }
};
