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
        Schema::create('special_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 10, 4)->nullable(); // Precio especial base (opcional)
            $table->decimal('discount_percentage', 5, 2)->default(0.00); // Porcentaje de descuento
            $table->timestamps();

            // Evitar duplicados para la misma combinación cliente-producto
            $table->unique(['client_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_prices');
    }
};
