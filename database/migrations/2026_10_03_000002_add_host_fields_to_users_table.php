<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_host')->default(false)->after('locked_until');
            $table->foreignId('invited_by')->nullable()->after('is_host')->constrained('users')->restrictOnDelete();
            $table->timestamp('invited_at')->nullable()->after('invited_by');
        });

        $userIds = DB::table('users')->orderBy('id')->pluck('id');

        if ($userIds->count() === 1) {
            DB::table('users')->where('id', $userIds->first())->update(['is_host' => true]);
        } elseif ($userIds->count() > 1) {
            $hostId = $userIds->first();
            DB::table('users')->where('id', $hostId)->update(['is_host' => true]);

            foreach ($userIds->slice(1) as $inviteeId) {
                $createdAt = DB::table('users')->where('id', $inviteeId)->value('created_at');

                DB::table('users')->where('id', $inviteeId)->update([
                    'invited_by' => $hostId,
                    'invited_at' => $createdAt,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn(['is_host', 'invited_at']);
        });
    }
};
