<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Human-facing reference shown in the UI and order history, kept
            // separate from the auto-increment id so the id is not leaked as a
            // guessable business identifier.
            $table->string('reference', 32)->unique();

            $table->foreignId('customer_id')
                ->constrained('customers')
                // An order must never be silently detached from its customer.
                // Deleting a customer with orders is refused by the database.
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Snapshot of the computed money values. Stored (rather than
            // recomputed on read) so a historic order always shows exactly what
            // the customer was charged, even if prices or tax rates change.
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_total', 12, 2);
            $table->decimal('grand_total', 12, 2);

            $table->timestamps();

            // Supports "this customer's orders, newest first" without a filesort.
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
