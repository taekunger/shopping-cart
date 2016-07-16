<?php

namespace App\Basket;

use App\Exceptions\QuantityExceededException;
use App\Product;
use App\Support\Storage\Contracts\StorageInterface;

class Basket
{
	/**
	 * Instance of StorageInterface.
	 *
	 * @var StorageInterface
	 */
	protected $storage;

	/**
	 * Instance of Product.
	 *
	 * @var Product
	 */
	protected $product;

	/**
	 * Create a new Basket instance.
	 *
	 * @param StorageInterface $storage
	 * @param Product          $product
	 */
	public function __construct(StorageInterface $storage, Product $product)
	{
		$this->storage = $storage;
		$this->product = $product;
	}

	/**
	 * Add the product with its quantity to the basket.
	 * The quantity will be updated if it exists.
	 *
	 * @param Product  $product
	 * @param Integer  $quantity
	 */
	public function add(Product $product, $quantity)
	{
		if ($this->has($product)) {
			$quantity = $this->get($product)['quantity'] + $quantity;
		}

		$this->update($product, $quantity);
	}

    /**
     * Update the basket.
     *
     * @param Product $product
     * @param         $quantity
     *
     * @throws QuantityExceededException
     */
	public function update(Product $product, $quantity)
	{
		if (! $this->product->find($product->id)->hasStock($quantity)) {
			throw new QuantityExceededException;
		}

		if ($quantity == 0) {
			$this->remove($product);

			return;
		}

		$this->storage->set($product->id, [
			'product_id' => (int) $product->id,
			'quantity' => (int) $quantity,
		]);
	}

	/**
	 * Remove a Product from the storage.
	 *
	 * @param  Product $product
	 */
	public function remove(Product $product)
	{
		$this->storage->remove($product->id);
	}

	/**
	 * Check if the basket has a certain product.
	 *
	 * @param  Product $product
	 */
	public function has(Product $product)
	{
		return $this->storage->exists($product->id);
	}

	/**
	 * Get a product that is inside the basket.
	 *
	 * @param  Product $product
	 */
	public function get(Product $product)
	{
		return $this->storage->get($product->id);
	}

	/**
	 * Clear the basket.
	 */
	public function clear()
	{
		return $this->storage->clear();
	}

	/**
	 * Get all products inside the basket.
	 */
	public function all()
	{
		$ids = [];
		$items = [];

		foreach ($this->storage->all() as $product) {
			$ids[] = $product['product_id'];
		}

		$products = $this->product->find($ids);

		foreach ($products as $product) {
			$product->quantity = $this->get($product)['quantity'];
			$items[] = $product;
		}

		return $items;
	}

	/**
	 * Get the amount of products inside the basket.
	 */
	public function itemCount()
	{
		return count($this->storage->all());
	}

	/**
	 * Get the subtotal price of all products inside the basket.
	 */
	public function subTotal()
	{
		$total = 0;

		foreach ($this->all() as $item) {
			if ($item->outOfStock()) {
				continue;
			}

			$total += ($item->price * $item->quantity);
		}

		return $total;
	}

	/**
	 * Check if the items in the basket are still in stock.
	 */
	public function refresh()
	{
		foreach ($this->all() as $item) {
			if (! $item->hasStock($item->quantity)) {
				$this->update($item, $item->stock);
			}
		}
	}
}
