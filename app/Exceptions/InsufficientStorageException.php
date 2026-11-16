<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a team cannot move to a plan because its current storage usage
 * does not fit in the target plan.
 */
class InsufficientStorageException extends RuntimeException {}
