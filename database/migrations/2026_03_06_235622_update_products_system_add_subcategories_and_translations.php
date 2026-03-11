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
        // Update categories table - add translations and SVG icon
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_en')->after('name')->nullable();
            $table->text('svg_icon')->after('name_en')->nullable();
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });

        // Create subcategories table
        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->string('name'); // French name
            $table->string('name_en'); // English name
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Update products table - add new fields for price types and subcategories
        Schema::table('products', function (Blueprint $table) {
            // Add subcategory_id
            $table->foreignId('subcategory_id')->nullable()->after('category_id')->constrained('subcategories')->onDelete('set null');

            // Change price to nullable
            $table->decimal('price', 10, 2)->nullable()->change();

            // Add variable price fields
            $table->decimal('min_price', 10, 2)->nullable()->after('price');
            $table->decimal('max_price', 10, 2)->nullable()->after('min_price');

            // Add price type (fixed or variable)
            $table->enum('price_type', ['fixed', 'variable'])->default('fixed')->after('max_price');

            // Add product type (service or article)
            $table->enum('type', ['service', 'article'])->default('article')->after('price_type');

            // Remove single image column (will be replaced by product_images table)
            $table->dropColumn('image');
        });

        // Create product_images table for multiple images
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('image_path');
            $table->boolean('is_primary')->default(false); // Primary image for display
            $table->integer('order')->default(0); // Order of images
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop product_images table
        Schema::dropIfExists('product_images');

        // Revert products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
            $table->dropColumn(['subcategory_id', 'min_price', 'max_price', 'price_type', 'type']);
            $table->string('image')->nullable()->after('stock');
        });

        // Drop subcategories table
        Schema::dropIfExists('subcategories');

        // Revert categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'svg_icon']);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
        });
    }
};
