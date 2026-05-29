<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VoteSys authenticates via DEORIS portal (external IDs), not local users.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $drop = array_filter(
                ['external_id', 'role'],
                fn (string $col) => Schema::hasColumn('users', $col),
            );
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'external_id')) {
                $table->string('external_id')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('student')->after('external_id');
            }
        });
    }
};
