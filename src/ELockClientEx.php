<?php

namespace Chameleon\PhpELockClient;

use Chameleon\PhpELockClient\Exception\UnexpectedResponseException;

/**
 * Class ELockClientEx
 *
 * A service for managing extended eLock servers.
 *
 * @package Chameleon\PhpELockClient
 */
class ELockClientEx extends ELockClient
{
    /**
     * Locks the key to be equal to the specified value.
     *
     * Few client can share the lock of the key with the same value,
     * but the key could be locked only to one value at the same time.
     *
     * If the lock is acquired by other client, waits $timeout seconds for it to be released.
     * Returns true if the key was locked successfully or false if the lock is acquired by other client.
     * Any acquired lock will be held until it's specifically unlocked, unlocked with unlockAll, or the client disconnects.
     *
     * @param string $key The unique key to lock.
     * @param string $value Key value to lock on.
     * @param int $timeout Timeout in seconds.
     * @return bool
     * @throws UnexpectedResponseException
     */
    public function lockValue($key, $value, $timeout = 0)
    {
        $this->_log("Attempting to lock '$key' to '$value' with timeout of $timeout seconds");
        $normalizedKey = $this->_normalizeKey($key);
        $normalizedValue = $this->_normalizeKey($value);
        $this->_setConnectionTimeout($timeout + 60);
        $response = $this->sendCommand("lock_value $normalizedKey $normalizedValue $timeout");
        switch ($response->code) {
            case 200:
                return true;
            case 409:
                return false;
            default:
                throw new UnexpectedResponseException("lock_value '$key' '$value'", $response);
        }
    }

    /**
     * Returns statistics of the eLock server and current connection.
     *
     * @return ELockDebugInfo
     * @throws UnexpectedResponseException
     */
    public function getDebug()
    {
        $this->_log("Getting debug info");
        $response = $this->sendCommand("debug");
        if ($response->code !== 200) {
            throw new UnexpectedResponseException("get debug info", $response);
        }
        $info = new ELockDebugInfo();
        while (true) {
            $line = $this->readResponseLine();
            if ($line === 'END') {
                break;
            }

            $lineParts = explode(' ', $line, 2);
            if (count($lineParts) !== 2) {
                throw new UnexpectedResponseException("fetch debug info result", $line);
            }
            list($command, $args) = $lineParts;
            switch ($command) {
                case 'SESSION':
                    $info->sessions[] = @json_decode($args);
                    break;
                case 'LOCK':
                    $info->locks[] = $args;
                    break;
                case 'REQUEST':
                    $info->lockRequests[] = $args;
                    break;
                default:
                    throw new UnexpectedResponseException("fetch debug info result", $line);
                    break;
            }
        }
        return $info;
    }
}
