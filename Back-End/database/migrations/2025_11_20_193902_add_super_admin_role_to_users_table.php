<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // This changes the 'role' column to a string and sets the default
            // The change() method ensures we are updating the existing column
            $table->string('role')->default('student')->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert if necessary
            $table->string('role')->default('student')->change();
        });
    }
};