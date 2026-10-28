<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name', 255);

            // Business identifier (SKU). Unique so the catalogue cannot contain
            // two products with the same code.
            $table->string('code', 64)->unique();

            // DECIMAL, never FLOAT: binary floating point cannot represent
            // values like 0.10 exactly, which corrupts money over time.
            // 12,2 handles up to 9,999,999,999.99.
            $table->decimal('price', 12, 2);

            // Percentage, e.g. 18.50 means 18.5%. 5,2 allows 0.00 - 999.99.
            $table->decimal('tax_rate', 5, 2)->default(0);

            // Unsigned so the database itself rejects negative stock, which is
            // the last line of defence behind the application checks.
            $table->unsignedInteger('stock_quantity')->default(0);

            $table->timestamps();

            // Supports the low-stock query (WHERE stock_quantity <= ?).
            $table->index('stock_quantity');
            // Supports name search / ordering in the product list screen.
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
