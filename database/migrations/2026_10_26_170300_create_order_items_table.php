<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                // Deleting an order removes its lines; a line cannot outlive
                // its parent order.
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('product_id')
                ->constrained('products')
                // Refuse to delete a product that appears in order history,
                // otherwise past orders would lose what was actually bought.
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->unsignedInteger('quantity');

            // Historic snapshot: the price and tax rate that applied at the
            // moment of ordering. If the product is repriced tomorrow, this
            // order still reflects what was charged.
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2);

            // Per-line computed money, stored for auditability so the order
            // total can always be reconciled against its lines.
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('line_tax', 12, 2);
            $table->decimal('line_total', 12, 2);

            $table->timestamps();

            // A product may appear only once per order: duplicate product ids
            // in one request are rejected during validation, and this
            // constraint enforces the same rule at the database level.
            $table->unique(['order_id', 'product_id']);

            // Supports "which orders contain this product".
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
