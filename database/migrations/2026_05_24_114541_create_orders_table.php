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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 20)->unique(); // Format: ORD-YYYYMMDD-XXXX
            $table->foreignUuid('table_id')
                ->nullable()
                ->constrained('restaurant_tables')
                ->cascadeOnUpdate()
                ->nullOnDelete(); // NULL = takeaway; preserve order if table is deleted
            $table->foreignUuid('cashier_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('status', 20)->default('pending'); // pending|confirmed|preparing|ready|completed|cancelled
            $table->string('payment_method', 20)->nullable(); // cash|qris|transfer|card
            $table->string('payment_status', 20)->default('unpaid'); // unpaid|paid|refunded
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('discount_type', 20)->nullable(); // fixed|percentage
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(11.00);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
            $table->index(['status', 'created_at']); // composite for KDS & reports
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
