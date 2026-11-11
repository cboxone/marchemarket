<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('active_dispute_key')->nullable()->unique();
        });

        DB::table('disputes')
            ->whereIn('status', ['open', 'escalated'])
            ->get(['id', 'order_id', 'vendor_id'])
            ->each(function (object $dispute): void {
                DB::table('disputes')->where('id', $dispute->id)->update([
                    'active_dispute_key' => $dispute->order_id . ':' . $dispute->vendor_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropUnique(['active_dispute_key']);
            $table->dropColumn('active_dispute_key');
        });
    }
};