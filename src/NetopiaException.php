<?php

namespace Gabrieldev\Netopia;

use RuntimeException;

class NetopiaException extends RuntimeException
{
    public function __construct(string $message, public readonly ?array $response = null, int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
