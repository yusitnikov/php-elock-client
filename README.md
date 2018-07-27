# PHP eLock Client
Simple eLock client on PHP.

## What is eLock?

eLock is a simple distributed lock server on erlang.

Advantages:
- fault-tolerant
- simple to install and to use
- safe from race conditions - all operations are atomic
- safe from deadlocks - all locks required by a client are being unlocked automatically when the client disconnects

See [source repository](https://github.com/dustin/elock).

## Install eLock server

1. [Install erlang OTP](https://hostpresto.com/community/tutorials/how-to-install-erlang-on-ubuntu-16-04/)
2. [Install and run eLock server](http://dustin.sallings.org/elock/admin.html)

It would be listening on port 11400.

## Install eLock client

The client is available as a composer dependency:

```cmd
composer install yusitnikov/php-elock-client
```

## Use eLock client

### Basic usage

```php
$key1 = 'unique-resource-key1';
$key1 = 'unique-resource-key2';
$lockTimeout = 5;

// Create a client
$client = new ELockClient('your.elock.server.host.or.ip');

// Tell to release all locks after the disconnection
$client->setTimeout(0);

// Lock keys
$lockedKey1 = $client->lock($key1, $lockTimeout);
$lockedKey2 = $client->lock($key2, $lockTimeout);

// Unlock key
$unlockedKey1 = $client->unlock($key1);

// Unlock all keys you own
$client->unlockAll();

// Disconnect from the server
$client->quit();
$client->close();
```

### Common usage for atomic operations

```php
// Lock the resource with a timeout that's big enough to wait for other clients to finish their job
if (!$client->lock($key, $timeout)) {
    throw new Exception('Failed to lock the resource XXX during YYY seconds');
}

try {
    // Do something with the locked resource
} finally {
    // Ensure that the resource will be unlocked in the case of unexpected error
    $client->unlock($key);
}
```
