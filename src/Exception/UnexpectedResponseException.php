<?php

namespace Chameleon\PhpELockClient\Exception;

use Chameleon\PhpELockClient\ELockResponse;

/**
 * Class UnexpectedResponseException
 *
 * Exception class for unexpected server response for a command.
 *
 * @package Chameleon\PhpELockClient\Exception
 */
class UnexpectedResponseException extends ELockException
{
    /**
     * UnexpectedResponseException constructor.
     *
     * @param string $commandDescription What did the client try to achieve?
     * @param string|ELockResponse $response What was the server response?
     */
    public function __construct($commandDescription, $response)
    {
        parent::__construct("Unexpected response from eLock server while attempting to $commandDescription: $response");
    }
}
