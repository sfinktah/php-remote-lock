<?php

namespace Sfinktah\RemoteLock\Exceptions;

// Base exception for all package-related exceptions
abstract class PackageException extends \RuntimeException
{
    /**
     * Optionally include more functionality or custom logging specific to the package exceptions.
     */
}

// Exception for lock acquisition failure
class LockAcquireException extends PackageException
{
    public function __construct(string $message = 'Could not acquire lock', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}