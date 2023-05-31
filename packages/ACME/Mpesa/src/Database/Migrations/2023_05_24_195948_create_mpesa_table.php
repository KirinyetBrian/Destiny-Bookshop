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
        Schema::create('mpesa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('TransID')->nullable();
            $table->string('MerchantRequestID')->nullable();
            $table->integer('cart_id')->unsigned();
            $table->foreign('cart_id')->references('id')->on('cart')->onDelete('cascade')->onUpdate('cascade');
            $table->string('CheckoutRequestID')->nullable();
            $table->decimal('TransAmount', $precision = 8,$scale = 2);
            $table->string('BillRefNumber')->nullable();
            $table->string('OrgAccountBalance')->nullable();
            $table->string('ThirdPartyTransId')->nullable();
            $table->string('Phone');
            $table->string('FName')->nullable();
            $table->string('mname')->nullable();
            $table->string('lname')->nullable();
            $table->string('trans_time')->nullable();
            $table->string('resultcode')->nullable();
            $table->string('ResultDesc')->nullable();
            $table->timestamps();;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa');
    }
};
