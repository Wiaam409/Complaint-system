<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/2025_03_03_create_notifications_table.php

public function up()
{
    Schema::create('notifications', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->text('message');
        $table->enum('type', ['status_update', 'new_complaint', 'info_request', 'general']);
        $table->foreignId('complaint_id')->nullable()->constrained()->onDelete('cascade');
        $table->boolean('is_read')->default(false);
        $table->timestamp('read_at')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        
        // فهرسة لتحسين الأداء
        $table->index(['user_id', 'is_read', 'created_at']);
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
