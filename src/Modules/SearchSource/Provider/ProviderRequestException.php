<?php

namespace App\Modules\SearchSource\Provider;

class ProviderRequestException extends \RuntimeException
{
    public const TYPE_AUTHENTICATION = 'authentication';
    public const TYPE_RATE_LIMIT = 'rate_limit';
    public const TYPE_SERVER = 'server';
    public const TYPE_TRANSPORT = 'transport';
    public const TYPE_MALFORMED_RESPONSE = 'malformed_response';
    public const TYPE_REQUEST = 'request';

    public function __construct(
        private readonly string $type,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getType(): string
    {
        return $this->type;
    }
}
