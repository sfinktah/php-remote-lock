<?php

namespace Sfinktah\RemoteLock;

class SingleInstanceLock implements IInstanceLock
{
    private $lockFile;
    private $pid;

    public function __construct($scriptName = null) {
        if (!$scriptName) {
            $scriptName = preg_replace('#^.*/#', '', $argv[1]) ?? 'singleInstanceRemoteLock';
        }
        $this->lockFile = "/tmp/{$scriptName}.pid";
        $this->pid = getmypid();
    }

    public function acquire($waitSeconds = 0): bool {
        $startTime = time();
        while (file_exists($this->lockFile)) {
            $oldPid = (int) file_get_contents($this->lockFile);

            if ($this->isProcessRunning($oldPid)) {
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

    private function isProcessRunning($pid): bool {
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
