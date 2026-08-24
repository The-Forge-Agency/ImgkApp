<?php

namespace App\Support;

use Exception;

class ImgkException extends Exception
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
