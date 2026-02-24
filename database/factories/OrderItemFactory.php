<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->randomFloat(2, 20, 500);

        return [
            'order_id' => Order::factory(),
            'orderable_type' => Product::class,
            'orderable_id' => Product::factory(),
            'variant_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $quantity,
            'currency' => 'GHS',
            'snapshot' => [],
        ];
    }

    /**
     * Configure the order item for a product.
     */
    public function forProduct(?Product $product = null): static
    {
        return $this->state(function (array $attributes) use ($product) {
            $product ??= Product::factory()->create();
            $unitPrice = $product->discount_price ?? $product->price;

            return [
                'orderable_type' => Product::class,
                'orderable_id' => $product->id,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $attributes['quantity'],
                'snapshot' => $product->toArray(),
            ];
        });
    }

    /**
     * Configure the order item for a service.
     */
    public function forService(?Service $service = null): static
    {
        return $this->state(function (array $attributes) use ($service) {
            $service ??= Service::factory()->create();
            $unitPrice = $service->charge_start;

            return [
                'orderable_type' => Service::class,
                'orderable_id' => $service->id,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $attributes['quantity'],
                'snapshot' => $service->toArray(),
            ];
        });
    }
}
