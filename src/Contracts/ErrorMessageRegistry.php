<?php

namespace Flugg\Responder\Contracts;

/**
 * A contract for registering and resolving error messages from error codes.
 *
 * @package flugger/laravel-responder
 * @author Alexander Tømmerås <flugged@gmail.com>
 * @license The MIT License
 */
interface ErrorMessageRegistry
{
    /**
     * Register error messages mapped to error codes.
     *
     * @param int|string|array $code
     * @param string|null $message
     * @return void
     */
    public function register($code, string $message = null): void;

    /**
     * Resolve an error message from an error code.
     *
     * @param int|string $code
     * @return string|null
     */
    public function resolve($code): ?string;
}