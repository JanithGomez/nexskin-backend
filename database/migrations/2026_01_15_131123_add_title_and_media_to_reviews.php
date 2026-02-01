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
        Schema::table('reviews', function (Blueprint $table) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->string('review_title', 140)->nullable()->after('rating');
                $table->json('media')->nullable()->after('comment'); // store array of URLs
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropColumn(['review_title', 'media']);
            });
        });
    }
};
