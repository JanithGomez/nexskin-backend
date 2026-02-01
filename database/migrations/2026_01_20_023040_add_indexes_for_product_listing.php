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
        // products
        // -------------------------
        Schema::table('products', function (Blueprint $table) {
            // single-column indexes for filters
            $table->index('is_active', 'idx_products_is_active');
            $table->index('stock', 'idx_products_stock');
            $table->index('price', 'idx_products_price');

            $table->index('brand_id', 'idx_products_brand_id');

            // ✅ Use the correct column name in YOUR table:
            // If your products table has product_type_id use this:
            if (Schema::hasColumn('products', 'product_type_id')) {
                $table->index('product_type_id', 'idx_products_product_type_id');
            }

            // If your products table uses category_id instead (you showed category_id in older migration):
            if (Schema::hasColumn('products', 'category_id')) {
                $table->index('category_id', 'idx_products_category_id');
            }

            $table->index('skin_type_id', 'idx_products_skin_type_id');

            // optional: helps pagination + active filter together
            $table->index(['is_active', 'id'], 'idx_products_is_active_id');
        });

        // -------------------------
        // product_images
        // -------------------------
        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->index('product_id', 'idx_product_images_product_id');

                if (Schema::hasColumn('product_images', 'is_primary')) {
                    $table->index(['product_id', 'is_primary'], 'idx_product_images_product_id_is_primary');
                }

                if (Schema::hasColumn('product_images', 'sort')) {
                    $table->index(['product_id', 'sort'], 'idx_product_images_product_id_sort');
                }
            });
        }

        // -------------------------
        // product_target_group pivot
        // -------------------------
        if (Schema::hasTable('product_target_group')) {
            Schema::table('product_target_group', function (Blueprint $table) {
                $table->index(['product_id', 'target_group_id'], 'idx_ptg_product_target');
                $table->index(['target_group_id', 'product_id'], 'idx_ptg_target_product');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // products
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_is_active');
            $table->dropIndex('idx_products_stock');
            $table->dropIndex('idx_products_price');

            $table->dropIndex('idx_products_brand_id');

            if (Schema::hasColumn('products', 'product_type_id')) {
                $table->dropIndex('idx_products_product_type_id');
            }

            if (Schema::hasColumn('products', 'category_id')) {
                $table->dropIndex('idx_products_category_id');
            }

            $table->dropIndex('idx_products_skin_type_id');
            $table->dropIndex('idx_products_is_active_id');
        });

        // product_images
        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->dropIndex('idx_product_images_product_id');

                if (Schema::hasColumn('product_images', 'is_primary')) {
                    $table->dropIndex('idx_product_images_product_id_is_primary');
                }

                if (Schema::hasColumn('product_images', 'sort')) {
                    $table->dropIndex('idx_product_images_product_id_sort');
                }
            });
        }

        // pivot
        if (Schema::hasTable('product_target_group')) {
            Schema::table('product_target_group', function (Blueprint $table) {
                $table->dropIndex('idx_ptg_product_target');
                $table->dropIndex('idx_ptg_target_product');
            });
        }
    }
};