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

```php
$client = new ELockClient('your.elock.server.host.or.ip');

$client-lock
```
