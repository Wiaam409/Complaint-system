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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('endpoint');             // اسم المسار
            $table->string('method');               // GET / POST / PUT ...
            $table->integer('status_code');         // 200 / 404 /500

            $table->longText('response_payload')->nullable();

            $table->boolean('is_error')->default(false);
            $table->string('error_message')->nullable();
            $table->string('error_type')->nullable();

            $table->float('execution_time_ms');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
