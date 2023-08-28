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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('description');
            $table->string('phone');
            $table->string('email');
            $table->string('address');
            $table->string('address_details');
            $table->string('logo');
            $table->string('favicon');
            $table->string('theme');
            $table->string('currency');
            $table->string('timezone');
            $table->string('language');
            $table->string('country');
            $table->string('city');
            $table->string('zip_code');
            $table->string('state');
            $table->string('national_identificator');
            $table->string('legal_name');
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->longText('observations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
