<?php

namespace CanopyTax\JQL\Exceptions;

use Exception;

class JQLDecodeException extends JQLException
{
    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
        if (is_null($message)) {
            $message = 'Error decoding JSON for JQL: '.json_last_error_msg();
        }
        if (!$code) {
            $code = json_last_error();
        }
        parent::__construct($message, $code, $previous);
    }
}
