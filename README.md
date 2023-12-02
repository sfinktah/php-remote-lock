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
