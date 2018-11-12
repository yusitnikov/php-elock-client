<?php

namespace Chameleon\PhpELockClient;

use Chameleon\PhpELockClient\Exception\DeadlockException;
use Chameleon\PhpELockClient\Exception\ELockException;
use Chameleon\PhpELockClient\Exception\UnexpectedResponseException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class ELockClient
 *
 * A service for managing eLock servers.
 *
 * @package Chameleon\PhpELockClient
 */
class ELockClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var resource $_connection A socket of connection to eLock server.
     */
    private $_connection;

    /**
     * ELockService constructor.
     *
     * Initializes the service by eLock server host.
     * Default port 11400 is always being used.
     *
     * @param string $host
     * @param LoggerInterface|null $logger
     * @throws ELockException
     */
    public function __construct($host, LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        // Suppress php errors, cause we've got $errno and $errstr
        $this->_connection = @fsockopen($host, 11400, $errno, $errstr);
        if (!$this->_connection) {
            throw new ELockException("Failed to open connection to eLock server: ($errno) $errstr", $errno);
        }
    }

    /**
     * Logs a debug message to the configured logger.
     *
     * @param string $message
     */
    protected function _log($message)
    {
        if ($this->logger) {
            $this->logger->debug("[elock] $message");
        }
    }

    /**
     * Reads one line of server response.
     *
     * @return string
     */
    public function readResponseLine()
    {
        return trim(fgets($this->_connection));
    }

    /**
     * Executes a command on the server and returns a response object for the first line of the response.
     * If more that one response line is expected, then it should be handled manually (see getStats method as an example).
     *
     * @param string $command
     * @return ELockResponse
     * @throws UnexpectedResponseException
     */
    public function sendCommand($command)
    {
        $this->_log("REQUEST $command");
        fwrite($this->_connection, "$command\n");
        $responseText = $this->readResponseLine();
        $this->_log("RESPONSE $responseText");
        return new ELockResponse($responseText);
    }

    /**
     * Set the amount of time after disconnect before your locks are all automatically freed.
     * Default is 30,000 (30 seconds).
     *
     * @param int $timeout Timeout in milliseconds.
     * @throws UnexpectedResponseException
     */
    public function setTimeout($timeout)
    {
        $this->_log("Setting unlock timeout to $timeout milliseconds");
        $response = $this->sendCommand("set_timeout $timeout");
        if ($response->code !== 200) {
            throw new UnexpectedResponseException("set unlock timeout to $timeout milliseconds", $response);
        }
    }

    /**
     * Converts a key to a format that is safe for eLock protocol (doesn't contain spaces and other special characters)
     * with keeping key uniqueness.
     *
     * @param string $key
     * @return string
     */
    protected function _normalizeKey($key)
    {
        return sha1($key);
    }

    /**
     * Sets timeout for the connection.
     *
     * @param int $timeout Timeout in seconds
     */
    protected function _setConnectionTimeout($timeout)
    {
        stream_set_timeout($this->_connection, $timeout);
    }

    /**
     * Executes a lock command and parses results.
     *
     * @param string $command
     * @param string $commandDescription
     * @param int $timeout Timeout in seconds.
     * @return bool
     * @throws UnexpectedResponseException
     * @throws DeadlockException
     */
    protected function _executeLockCommand($command, $commandDescription, $timeout)
    {
        $this->_log("Attempting to $commandDescription with timeout of $timeout seconds");
        $this->_setConnectionTimeout($timeout + 60);
        $response = $this->sendCommand($command);
        switch ($response->code) {
            case 200:
                return true;
            case 409:
                return false;
            case 423:
                throw new DeadlockException($response->message);
            default:
                throw new UnexpectedResponseException($commandDescription, $response);
        }
    }

    /**
     * Locks the key with an exclusive lock.
     *
     * Only one client can lock the key at the same time.
     *
     * If the lock is acquired by other client, waits $timeout seconds for it to be released.
     * Returns true if the key was locked successfully or false if the lock is acquired by other client.
     * Any acquired lock will be held until it's specifically unlocked, unlocked with unlockAll, or the client disconnects.
     *
     * @param string $key The unique key to lock.
     * @param int $timeout Timeout in seconds.
     * @return bool
     * @throws UnexpectedResponseException
     * @throws DeadlockException
     */
    public function lock($key, $timeout = 0)
    {
        $normalizedKey = $this->_normalizeKey($key);
        return $this->_executeLockCommand("lock $normalizedKey $timeout", "lock '$key'", $timeout);
    }

    /**
     * Releases the lock by the key.
     * Returns true if the lock was released successfully or false if the lock wasn't released cause it was acquired by other client.
     *
     * @param string $key The unique key to unlock.
     * @return bool
     * @throws UnexpectedResponseException
     */
    public function unlock($key)
    {
        $this->_log("Unlocking '$key'");
        $normalizedKey = $this->_normalizeKey($key);
        $response = $this->sendCommand("unlock $normalizedKey");
        switch ($response->code) {
            case 200:
                return true;
            case 403:
                return false;
            default:
                throw new UnexpectedResponseException("unlock '$key'", $response);
        }
    }

    /**
     * Releases any locks this client may be holding.
     *
     * @throws UnexpectedResponseException
     */
    public function unlockAll()
    {
        $this->_log("Unlocking all keys");
        $response = $this->sendCommand("unlock_all");
        if ($response->code !== 200) {
            throw new UnexpectedResponseException('unlock all keys', $response);
        }
    }

    /**
     * Returns statistics of the eLock server and current connection.
     *
     * @return ELockStats
     * @throws UnexpectedResponseException
     */
    public function getStats()
    {
        $this->_log("Getting stats");
        $response = $this->sendCommand("stats");
        if ($response->code !== 200) {
            throw new UnexpectedResponseException("get stats", $response);
        }
        $stats = new ELockStats();
        while (true) {
            $line = $this->readResponseLine();
            if ($line === 'END') {
                break;
            }

            $lineParts = explode(' ', $line);
            if (count($lineParts) === 3 && $lineParts[0] === 'STAT' && is_numeric($lineParts[2])) {
                $stats->{$lineParts[1]} = (int)$lineParts[2];
            } else {
                throw new UnexpectedResponseException("fetch stats result", $line);
            }
        }
        return $stats;
    }

    /**
     * Returns current session ID.
     *
     * @return string
     * @throws UnexpectedResponseException
     */
    public function getSessionId()
    {
        $this->_log("Getting session ID");
        $response = $this->sendCommand("conn_id");
        if ($response->code !== 200) {
            throw new UnexpectedResponseException("get current session ID", $response);
        }
        return $response->message;
    }

    /**
     * Sets current session ID.
     *
     * @param string $sessionId New session ID.
     * @throws UnexpectedResponseException
     */
    public function setSessionId($sessionId)
    {
        $this->_log("Setting session ID to '$sessionId'");
        $response = $this->sendCommand("conn_id $sessionId");
        if ($response->code !== 200) {
            throw new UnexpectedResponseException("set current session ID to '$sessionId'", $response);
        }
    }

    /**
     * Sends a command to disconnect.
     *
     * @throws UnexpectedResponseException
     */
    public function quit()
    {
        $this->_log("Sending quit command");
        $response = $this->sendCommand("quit");
        if ($response->code !== 200) {
            throw new UnexpectedResponseException("quit", $response);
        }
    }

    public function close()
    {
        if ($this->_connection) {
            $this->_log("Closing the connection");
            fclose($this->_connection);
            $this->_connection = null;
        }
    }
}
