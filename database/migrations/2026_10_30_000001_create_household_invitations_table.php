<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Initial owner setup inserts id 1 explicitly. Advance the normal
            // sequence past all allocated IDs so deleted accounts never get reused.
            // The lock keeps account inserts out while synchronizing the sequence.
            DB::statement('LOCK TABLE users IN SHARE ROW EXCLUSIVE MODE');
            DB::select(<<<'SQL'
                SELECT setval(
                    sequence_name::regclass,
                    GREATEST(
                        COALESCE((SELECT MAX(id) FROM users), 1),
                        COALESCE((
                            SELECT last_value FROM pg_sequences
                            WHERE format('%I.%I', schemaname, sequencename)::regclass = sequence_name::regclass
                        ), 1)
                    ),
                    true
                )
                FROM (SELECT pg_get_serial_sequence('users', 'id') AS sequence_name) AS user_sequence
                SQL);
        }
        // The existing journal, cooling sessions, and owner account stay in place.
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 254)->index();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_invitations');
    }
};
