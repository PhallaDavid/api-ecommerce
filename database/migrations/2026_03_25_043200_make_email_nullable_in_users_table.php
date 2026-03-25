<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeEmailNullableInUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // MySQL-specific; avoids doctrine/dbal requirement for change()
        // Keep length at 191 to stay within index key limits on older MySQL setups
        DB::statement('ALTER TABLE `users` MODIFY `email` VARCHAR(191) NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Note: this will fail if any rows have NULL email
        DB::statement('ALTER TABLE `users` MODIFY `email` VARCHAR(191) NOT NULL');
    }
}
