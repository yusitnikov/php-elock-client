<?php

namespace Chameleon\PhpELockClient;

use Chameleon\PhpELockClient\Exception\UnexpectedResponseException;

/**
 * Class ELockResponse
 *
 * Structure for any server command response.
 *
 * @package Chameleon\PhpELockClient
 */
class ELockResponse
{
    /**
     * @var int $code Response code. See eLock server docs for possible response codes for each command.
     */
    public $code;

    /**
     * @var string $message Response message (not including the code).
     */
    public $message;

    /**
     * ELockResponse constructor.
     *
     * @param string $responseText The line of server response.
     * @throws UnexpectedResponseException
     */
    public function __construct($responseText)
    {
        $responseTextParts = explode(' ', $responseText, 2);
        if (count($responseTextParts) !== 2 || !is_numeric($responseTextParts[0])) {
            throw new UnexpectedResponseException("parse response text", $responseText);
        }
        $this->code = (int)$responseTextParts[0];
        $this->message = $responseTextParts[1];
    }

    public function __toString()
    {
        return $this->code . ' ' . $this->message;
    }
}
