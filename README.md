# Using the locking client

`composer require php-remote-lock`

```php
$lock = new \Sfinktah\RemoteLock\SingleInstanceRemoteLock('lock_name', 'https://lockserver');
printf("* Acquiring lock...\n");
if (!$lock->acquire(60 * 60)) {
    printf("Failed to acquire lock within 60 minutes\n");
    return false;
}
// Do stuff
$lock->release();

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

`/etc/nginx/`
```nginx
server {
    # ...

    location /lock {
        proxy_pass http://127.0.0.1:28612;
    }

    # ...
}
```
