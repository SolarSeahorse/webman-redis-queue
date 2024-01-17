<?php

namespace SolarSeahorse\WebmanRedisQueue\Exceptions;

use Exception;
use Throwable;

class LoggerConfigurationException extends Exception {

    public function __construct($message = "Logger configuration error", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}