<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Logins, refused access, scanners, bots and setting changes. Never form
        // contents, passwords or codes — only what happened, from where, to whom.
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('severity', 10);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['type', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index('created_at');
        });

        // Application errors grouped by where they happened, so one fault seen a
        // hundred times is one row with a counter.
        Schema::create('app_errors', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 40)->unique();
            $table->string('exception');
            $table->text('message')->nullable();
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_errors');
        Schema::dropIfExists('security_events');
    }
};
