<?php

namespace Bazar\Tests\Unit;

use Bazar\Database\Factories\AddressFactory;
use Bazar\Database\Factories\CartFactory;
use Bazar\Database\Factories\ProductFactory;
use Bazar\Database\Factories\ShippingFactory;
use Bazar\Models\Address;
use Bazar\Models\Cart;
use Bazar\Models\Item;
use Bazar\Models\Shipping;
use Bazar\Tests\TestCase;

class CartTest extends TestCase
{
    protected $cart, $products;

    public function setUp(): void
    {
        parent::setUp();

        $this->cart = CartFactory::new()->create();

        $this->products = ProductFactory::new()->count(3)->create()->mapWithKeys(function ($product) {
            [$quantity, $tax, $price] = [mt_rand(1, 5), 0, $product->price];

            return [$product->id => compact('price', 'tax', 'quantity')];
        });

        $this->cart->products()->attach($this->products->all());
    }

    /** @test */
    public function it_can_belong_to_a_customer()
    {
        $this->assertNull($this->cart->user);

        $this->cart->user()->associate($this->user);

        $this->cart->save();

        $this->assertSame($this->user->id, $this->cart->user->id);
    }

    /** @test */
    public function it_has_a_shipping()
    {
        $shipping = $this->cart->shipping()->save(ShippingFactory::new()->make());

        $this->assertSame($shipping->id, $this->cart->shipping->id);
    }

    /** @test */
    public function it_has_address()
    {
        $address = $this->cart->address()->save(
            AddressFactory::new()->make()
        );

        $this->assertSame($address->id, $this->cart->address->id);
    }

    /** @test */
    public function it_has_products()
    {
        $product = ProductFactory::new()->create();

        $this->cart->products()->attach($product, [
            'price' => 100, 'tax' => 0, 'quantity' => 3,
        ]);

        $this->assertTrue(
            $this->cart->products->pluck('id')->contains($product->id)
        );
    }

    /** @test */
    public function it_has_total_attribute()
    {
        $total = $this->products->sum(function ($product) {
            return ($product['price'] + $product['tax']) * $product['quantity'];
        });

        $total -= $this->cart->discount;

        $this->assertEquals($total, $this->cart->total);
    }

    /** @test */
    public function it_has_net_total_attribute()
    {
        $total = $this->products->sum(function ($product) {
            return $product['price'] * $product['quantity'];
        });

        $total -= $this->cart->discount;

        $this->assertEquals($total, $this->cart->netTotal);
    }

    /** @test */
    public function it_deletes_relations_on_deleting()
    {
        $this->cart->delete();

        $this->assertDatabaseMissing(
            'addresses', ['addressable_type' => Cart::class, 'addressable_id' => $this->cart->id]
        );
        $this->assertDatabaseMissing(
            'shippings', ['shippable_type' => Cart::class, 'shippable_id' => $this->cart->id]
        );
        $this->assertSame(
            0, Item::query()->where([['itemable_type', Cart::class], ['itemable_id', $this->cart->id]])->count()
        );
    }
}
