<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (config('database.default') === 'mysql') {
            // MySQL: modify column
            DB::statement('ALTER TABLE `users` MODIFY `email` VARCHAR(191) NULL');
        } else {
            // SQLite: workaround by recreating table
            Schema::table('users', function (Blueprint $table) {
                $table->string('email', 191)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `email` VARCHAR(191) NOT NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                // SQLite: cannot reliably revert if NULL exists
                // Skip or handle manually in dev
            });
        }
    }
};