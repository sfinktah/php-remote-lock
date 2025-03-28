<?php

namespace Sfinktah\RemoteLock;

use Sfinktah\RemoteLock\Exceptions;


class SingleInstanceLock implements IInstanceLock
{
    private static string $LOCK_DIRECTORY;

    public string $lockFile;
    private int $pid;

    public function __construct(?string $scriptName)
    {
        self::$LOCK_DIRECTORY = storage_path('single-instance-locks');
        $defaultScriptName = basename($GLOBALS['argv'][1] ?? '') ?: 'singleInstanceRemoteLock';
        $scriptName = $scriptName ?? $defaultScriptName;

        $this->createLockDirectory();

        $this->lockFile = sprintf('%s/%s.pid', self::$LOCK_DIRECTORY, $scriptName);
        $this->pid = getmypid();
    }

    private function createLockDirectory(): void
    {
        if (!is_dir(self::$LOCK_DIRECTORY)) {
            mkdir(self::$LOCK_DIRECTORY, 0777, true);
        }
    }

    public function lockCall(callable $callback, ...$args): mixed
    {
        if (!$this->acquire()) {
            throw new Exceptions\LockAcquireException('Could not acquire lock');
        }

        try {
            // Execute the callback, passing any additional arguments.
            return $callback(...$args);
        } finally {
            $this->release();
        }
    }

    public function acquire($waitSeconds = 0): bool {
        $startTime = time();
        while (file_exists($this->lockFile)) {
            $oldPid = (int) file_get_contents($this->lockFile);

            if (static::isProcessRunning($oldPid)) {
                if (time() - $startTime >= $waitSeconds) {
                    return false;
                }

                sleep(1); // Wait for 1 second before checking again.
            } else {
                unlink($this->lockFile);
                break;
            }
        }

        file_put_contents($this->lockFile, $this->pid);
        chmod($this->lockFile, 0666);

        // Register a shutdown function to release the lock if the script unexpectedly ends.
        register_shutdown_function([$this, 'release']);

        return true;
    }

    public function release() {
        if (file_exists($this->lockFile) && (int) file_get_contents($this->lockFile) === $this->pid) {
            unlink($this->lockFile);
        }
    }

    public static function isProcessRunning($pid): bool {
        if (empty($pid) || !is_numeric($pid)) {
            return false;
        }

        if (strncasecmp(PHP_OS, 'win', 3) === 0) {
            // Windows does not support this method, you can use other methods to check process existence on Windows.
            return true; // For demonstration purposes, always assume it's running.
        } else {
            // On Unix-like systems, check if the /proc directory exists for the given PID.
            return file_exists("/proc/{$pid}");
        }
    }
}
