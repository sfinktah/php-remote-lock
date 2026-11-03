# Using the locking client

## With composer

`composer require php-remote-lock`

## Without composer

`git clone https://github.com/sfinktah/php-remote-lock /path/to/project-root/RemoteLock`

`/path/to/project-root/index.php`
```php
<?php
require_once "RemoteLock/RemoteLock.php";
```

## Obtaining a lock

```php
function main(...$args) {
    $lock = new \Sfinktah\RemoteLock\SingleInstanceRemoteLock('lock_name', 'https://markt14.streetfx.au');
    if ($lock->lockCall(3600 /* 1 hour */, function(...$args) {
        doWork(...$args);
    }, ...$args)) {
        printf("Work Done\n");
    }
    else {
        printf("Failed to acquire lock\n");
    }
}

```

## Redis instance locks in Laravel

`MultipleInstanceLock` uses Laravel's default Redis connection. Pass another
Laravel Redis connection as the second constructor argument if needed. Use
the same connection, key prefix, lock name, and instance limit across workers
that share a pool. Each acquisition checks its supplied limit against the
current number of holders.

```php
$lock = new \Sfinktah\RemoteLock\MultipleInstanceLock('catalog-import');

if ($lock->acquire(waitSeconds: 5, maxInstances: 3, description: 'Importing supplier A')) {
    try {
        doWork();
    } finally {
        $lock->release();
    }
}

$count = $lock->getInstanceCount();
$holders = $lock->getInstanceLocks();
```

Each holder includes an ID, hostname, PID, optional description, and an
acquisition timestamp in Unix milliseconds.
Each query is a separate snapshot.

`acquire()` and `lockCall($callback, ...$args)` default to one instance and
no wait. Locks are freed by explicit release or PHP shutdown. Pass
`leaseSeconds` to `acquire()` to opt that holder into timed expiry, even if
its process is still running. The holder's breakdown then includes
`expires_at_ms`. Redis removes expired holders during acquisition or
inspection. Omitting `leaseSeconds` gives the holder no timed expiry.

On Linux, callers also reclaim dead holders from the same boot
and PID namespace. If a holder exits without cleanup and its PID cannot be
checked locally, its entry requires manual removal from Redis after verifying
that the holder is gone. Use a separate lock object for each held slot.

# Installing the locking server


`/etc/systemd/system/php-remote-lock.service`
```ini
[Unit]
Description=PHP Remote Lock Master Service
After=network.target

[Service]
ExecStart=/usr/bin/python3 /path/to/RemoteLock/libexec/idarest_master.py
Restart=always

[Install]
WantedBy=default.target
```

```sh
sudo systemctl enable php-remote-lock.service
sudo systemctl start php-remote-lock.service
sudo systemctl show php-remote-lock.service
```

`/etc/nginx/nginx.conf`
```nginx
server {
    # ...

    location /lock {
        proxy_pass http://127.0.0.1:28612;
    }

    # ...
}
```

```sh
sudo nginx -t &&
sudo systemctl restart nginx
```
