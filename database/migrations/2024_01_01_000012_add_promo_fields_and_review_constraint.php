<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->nullable()->after('base_price');
            $table->timestamp('promo_ends_at')->nullable()->after('discount_percent');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unique(['product_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'promo_ends_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'user_id']);
        });
    }
};
