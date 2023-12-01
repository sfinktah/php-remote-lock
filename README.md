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
