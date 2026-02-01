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
            // ✅ allow guest reviews
            $table->foreignId('user_id')->nullable()->change();

            // ✅ store guest identity (optional)
            $table->string('guest_name', 100)->nullable()->after('user_id');
            $table->string('guest_email', 150)->nullable()->after('guest_name');

            // ✅ anonymous toggle
            $table->boolean('is_anonymous')->default(false)->after('is_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_email', 'is_anonymous']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
