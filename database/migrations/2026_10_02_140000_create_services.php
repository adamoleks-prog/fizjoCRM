<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kinds of visit the practice offers (physiotherapy, massage, …), managed by
        // the admin. Physiotherapy is the default and cannot be removed.
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('online_bookable')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        DB::table('services')->insert([
            ['name' => 'Fizjoterapia', 'duration_minutes' => 60, 'online_bookable' => true, 'is_default' => true, 'active' => true, 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Masaż', 'duration_minutes' => 60, 'online_bookable' => true, 'is_default' => false, 'active' => true, 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('appointments', function (Blueprint $table) {
            // Null on visits booked before services existed — shown as the default.
            $table->foreignId('service_id')->nullable()->after('patient_id')->constrained('services')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });

        Schema::dropIfExists('services');
    }
};
