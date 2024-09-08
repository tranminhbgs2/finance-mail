<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('gmail_id')->unique();
            $table->string('subject')->nullable();
            $table->string('from_email')->nullable();
            $table->string('bank')->nullable();
            $table->string('name_receiver')->nullable();
            $table->double('price')->nullable();
            $table->timestamp('date_success')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
