<?php

declare(strict_types=1);

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
        Schema::table(config('memory.table', 'memories'), function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index()->after('embedding');
            $table->string('type')->nullable()->index()->after('embedding');

            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('memory.table', 'memories'), function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type']);
            $table->dropColumn(['expires_at', 'type']);
        });
    }
};
