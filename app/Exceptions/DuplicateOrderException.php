<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a user tries to place a new order for a link that already has
 * an order in progress for that same user.
 */
class DuplicateOrderException extends RuntimeException {}
