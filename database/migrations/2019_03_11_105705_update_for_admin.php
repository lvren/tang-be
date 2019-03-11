<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateForAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            // 用户是否是 admin
            $table->tinyInteger('isAdmin')->default(0);
        });

        Schema::table('product_type', function (Blueprint $table) {
            // 产品类型价格与描述
            $table->integer('price')->default(0);
            $table->text('desc');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('isAdmin');
        });

        Schema::table('product_type', function (Blueprint $table) {
            // 产品类型价格与描述
            $table->dropColumn('price');
            $table->dropColumn('desc');
        });
    }
}
