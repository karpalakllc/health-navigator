<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_product', function (Blueprint $table) {
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('MKD');
            $table->boolean('is_available')->default(true);
            $table->timestamp('price_updated_at');
            $table->timestamps();

            $table->primary(['facility_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_product');
    }
};
