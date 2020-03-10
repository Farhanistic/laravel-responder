<?php

namespace Flugg\Responder\Http\Builders;

use Exception;
use Flugg\Responder\Contracts\AdapterFactory;
use Flugg\Responder\Contracts\ErrorMessageRegistry;
use Flugg\Responder\Contracts\Http\ResponseFactory;
use Flugg\Responder\Contracts\Validation\Validator;
use Flugg\Responder\Exceptions\InvalidStatusCodeException;
use Flugg\Responder\Exceptions\MissingAdapterException;
use Flugg\Responder\Http\ErrorResponse;
use Illuminate\Support\Str;

/**
 * A builder class for building error responses.
 *
 * @package flugger/laravel-responder
 * @author Alexander Tømmerås <flugged@gmail.com>
 * @license The MIT License
 */
class ErrorResponseBuilder extends ResponseBuilder
{
    /**
     * A response object.
     *
     * @var ErrorResponse
     */
    protected $response;

    /**
     * A resolver used for resolving error messages from error codes.
     *
     * @var ErrorMessageRegistry
     */
    protected $messageRegistry;

    /**
     * A validator attached with the error response.
     *
     * @var Validator
     */
    protected $validator;

    /**
     * A constant defining the status code used if nothing is set.
     *
     * @var int
     */
    protected const DEFAULT_STATUS = 500;

    /**
     * Create a new response builder instance.
     *
     * @param ResponseFactory $responseFactory
     * @param AdapterFactory $adapterFactory
     * @param ErrorMessageRegistry $messageRegistry
     */
    public function __construct(ResponseFactory $responseFactory, AdapterFactory $adapterFactory, ErrorMessageRegistry $messageRegistry)
    {
        $this->messageRegistry = $messageRegistry;

        parent::__construct($responseFactory, $adapterFactory);
    }

    /**
     * Make an error response from an error code and message.
     *
     * @param Exception|int|string|null $errorCode
     * @param Exception|string|null $message
     * @return $this
     * @throws InvalidStatusCodeException
     */
    public function error($errorCode = null, $message = null)
    {
        if (($exception = $errorCode) instanceof Exception) {
            $this->response = $this->makeResponseFromException($exception);
        } elseif (($exception = $message) instanceof Exception) {
            $this->response = $this->makeResponseFromException($exception, $errorCode);
        } else {
            $this->response = $this->makeResponse($errorCode, $message ?: $this->messageRegistry->resolve($errorCode), self::DEFAULT_STATUS);
        }

        return $this;
    }

    /**
     * Add a validator to the error response.
     *
     * @param mixed $validator
     * @return $this
     * @throws MissingAdapterException
     */
    public function validator($validator)
    {
        if (!$this->validator = $this->adapterFactory->makeValidator($validator)) {
            throw new MissingAdapterException;
        }

        return $this;
    }

    /**
     * Make an error response from the exception.
     *
     * @param Exception $exception
     * @param int|string|null $errorCode
     * @return ErrorResponse
     * @throws InvalidStatusCodeException
     */
    protected function makeResponseFromException(Exception $exception, $errorCode = null): ErrorResponse
    {
        $errorCode = $errorCode ?: $this->resolveCodeFromException($exception);
        $message = $this->messageRegistry->resolve($errorCode) ?: $exception->getMessage();
        $status = $this->resolveStatusFromException($exception);

        return $this->makeResponse($errorCode, $message, $status);
    }

    /**
     * Resolve an error code from an exception.
     *
     * @param Exception $exception
     * @return string
     */
    protected function resolveCodeFromException(Exception $exception): string
    {
        if (['code' => $errorCode] = $this->resolveErrorFromException($exception)) {
            return $errorCode;
        }

        return Str::snake(Str::replaceLast('Exception', '', class_basename($exception)));
    }

    /**
     * Resolve a status code from an exception.
     *
     * @param Exception $exception
     * @return int
     */
    protected function resolveStatusFromException(Exception $exception): int
    {
        if (['status' => $status] = $this->resolveErrorFromException($exception)) {
            return $status;
        }

        return self::DEFAULT_STATUS;
    }

    /**
     * Resolve a configured error object from an exception.
     *
     * @param Exception $exception
     * @return array|null
     */
    protected function resolveErrorFromException(Exception $exception): ?array
    {
        return config('responder.exceptions')[get_class($exception)] ?? null;
    }

    /**
     * Make an error response.
     *
     * @param int|string $errorCode
     * @param string $message
     * @param int $status
     * @param array $headers
     * @return ErrorResponse
     * @throws InvalidStatusCodeException
     */
    protected function makeResponse($errorCode, string $message, int $status = 500, array $headers = []): ErrorResponse
    {
        return (new ErrorResponse)->setStatus($status)->setHeaders($headers)->setErrorCode($errorCode)->setMessage($message);
    }

    /**
     * Get the response content.
     *
     * @return array
     */
    protected function content(): array
    {
        if (!$this->formatter) {
            return ['message' => $this->response->message()];
        }

        return $this->format($this->response);
    }

    /**
     * Format the response data.
     *
     * @param ErrorResponse $response
     * @return array
     */
    protected function format(ErrorResponse $response): array
    {
        $data = $this->formatter->error($response);

        if ($this->validator) {
            $data = $this->formatter->validator($data, $this->validator);
        }

        return $data;
    }
}