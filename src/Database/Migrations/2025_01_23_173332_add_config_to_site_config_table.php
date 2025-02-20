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
        Schema::table('site_config', function (Blueprint $table) {
            // add config json field
            $table->json('config')->nullable()->after("recommend")->comment('config json field');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_config', function (Blueprint $table) {
            //
            $table->dropColumn('config');
        });
    }
};
