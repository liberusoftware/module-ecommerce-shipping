<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

use RuntimeException;

/**
 * The root of every refusal this package raises.
 *
 * Callers tell the cases apart by `instanceof`, never by decoding a message:
 * two opposite instructions must never share one class.
 */
abstract class ShippingException extends RuntimeException {}
