<?php

namespace App\Exceptions;

use Exception;

class PriceChangedException extends Exception
{
    public string $productName;
    public float $cartPrice;
    public float $currentPrice;

    public function __construct(string $productName, float $cartPrice, float $currentPrice)
    {
        $this->productName = $productName;
        $this->cartPrice = $cartPrice;
        $this->currentPrice = $currentPrice;

        parent::__construct(
            "Price for \"{$productName}\" has changed from GH₵" . number_format($cartPrice, 2) .
            " to GH₵" . number_format($currentPrice, 2) . ". Please refresh your cart."
        );
    }
}
