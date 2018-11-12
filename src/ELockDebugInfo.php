<?php

namespace Chameleon\PhpELockClient;

/**
 * Class ELockDebugInfo
 *
 * A structure of DEBUG call result.
 *
 * @package Chameleon\PhpELockClient
 */
class ELockDebugInfo
{
    public $sessions = [];
    public $locks = [];
    public $lockRequests = [];
}
