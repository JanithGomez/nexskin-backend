<?php

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
        // -------------------------
        // reviews
        // -------------------------
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                // prevent duplicate creation if you re-run / already added manually
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('reviews');

                if (! isset($indexes['idx_reviews_product_approved_created'])) {
                    if (
                        Schema::hasColumn('reviews', 'product_id') &&
                        Schema::hasColumn('reviews', 'is_approved') &&
                        Schema::hasColumn('reviews', 'created_at')
                    ) {
                        $table->index(
                            ['product_id', 'is_approved', 'created_at'],
                            'idx_reviews_product_approved_created'
                        );
                    }
                }
            });
        }

        // -------------------------
        // product_ingredient pivot
        // -------------------------
        if (Schema::hasTable('product_ingredient')) {
            Schema::table('product_ingredient', function (Blueprint $table) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('product_ingredient');

                if (! isset($indexes['idx_pi_product_ingredient'])) {
                    $table->index(['product_id', 'ingredient_id'], 'idx_pi_product_ingredient');
                }

                if (! isset($indexes['idx_pi_ingredient_product'])) {
                    $table->index(['ingredient_id', 'product_id'], 'idx_pi_ingredient_product');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // reviews
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropIndex('idx_reviews_product_approved_created');
            });
        }

        // product_ingredient
        if (Schema::hasTable('product_ingredient')) {
            Schema::table('product_ingredient', function (Blueprint $table) {
                $table->dropIndex('idx_pi_product_ingredient');
                $table->dropIndex('idx_pi_ingredient_product');
            });
        }
    }
};