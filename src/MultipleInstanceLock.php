<?php

namespace Sfinktah\RemoteLock;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use RuntimeException;
use Sfinktah\RemoteLock\Exceptions\LockAcquireException;

class MultipleInstanceLock implements IInstanceLock
{
    private const PRUNE_EXPIRED = <<<'LUA'
local key = KEYS[1]
local time = redis.call('TIME')
local now = time[1] * 1000 + math.floor(time[2] / 1000)
for _, entry in ipairs(redis.call('HVALS', key)) do
    local holder = cjson.decode(entry)
    if type(holder.expires_at_ms) == 'number' and holder.expires_at_ms <= now then
        redis.call('HDEL', key, holder.id)
    end
end
LUA;

    private string $key;
    private ?string $member = null;
    private ?int $ownerPid = null;
    private bool $shutdownRegistered = false;

    public function __construct(?string $scriptName = null, private ?Connection $connection = null)
    {
        $scriptName ??= basename($GLOBALS['argv'][1] ?? '') ?: 'singleInstanceRemoteLock';
        $this->key = 'remote-lock:holders:' . hash('sha256', $scriptName);
    }

    public function lockCall(callable $callback, ...$args): mixed
    {
        if (!$this->acquire()) {
            throw new LockAcquireException('Could not acquire lock');
        }

        try {
            return $callback(...$args);
        } finally {
            $this->release();
        }
    }

    public function acquire(
        $waitSeconds = 0,
        int $maxInstances = 1,
        ?string $description = null,
        ?int $leaseSeconds = null,
    ): bool
    {
        if (!is_numeric($waitSeconds) || !is_finite((float) $waitSeconds) || $waitSeconds < 0 || $maxInstances < 1) {
            throw new InvalidArgumentException('Expected a nonnegative wait and a positive instance limit.');
        }
        if ($leaseSeconds !== null && ($leaseSeconds < 1 || $leaseSeconds > 2147483647)) {
            throw new InvalidArgumentException('Expected a lease duration of 1 to 2147483647 seconds.');
        }

        $pid = getmypid();
        $ownedMember = $this->ownerPid === $pid ? ($this->member ?? '') : '';

        $id = bin2hex(random_bytes(16));
        $processScope = $this->processScope();
        $metadata = json_encode([
            'id' => $id,
            'hostname' => gethostname() ?: null,
            'pid' => $pid,
            'process_scope' => $processScope,
            'description' => $description,
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        $script = self::PRUNE_EXPIRED . "\n" . <<<'LUA'
if ARGV[5] ~= '' and redis.call('HEXISTS', key, ARGV[5]) == 1 then
    return -1
end
if redis.call('HLEN', key) >= tonumber(ARGV[1]) then
    return 0
end
local holder = cjson.decode(ARGV[3])
holder.acquired_at_ms = now
if ARGV[4] ~= '' then
    holder.expires_at_ms = now + tonumber(ARGV[4]) * 1000
end
return redis.call('HSET', key, ARGV[2], cjson.encode(holder))
LUA;

        $deadline = hrtime(true) / 1e9 + (float) $waitSeconds;
        while (true) {
            $this->reapDeadLocalHolders($processScope);
            $result = (int) $this->evaluate($script, $maxInstances, $id, $metadata, $leaseSeconds ?? '', $ownedMember);
            if ($result === 1) {
                $this->member = $id;
                $this->ownerPid = $pid;
                if (!$this->shutdownRegistered) {
                    register_shutdown_function([$this, 'release']);
                    $this->shutdownRegistered = true;
                }

                return true;
            }

            $remaining = $deadline - hrtime(true) / 1e9;
            if ($result === -1 || $remaining <= 0) {
                return false;
            }

            usleep((int) (min(0.25, $remaining) * 1e6));
        }
    }

    public function release(): void
    {
        if ($this->member !== null && $this->ownerPid === getmypid()) {
            $this->evaluate("return redis.call('HDEL', KEYS[1], ARGV[1])", $this->member);
            $this->member = null;
        }
    }

    public function getInstanceCount(): int
    {
        $this->reapDeadLocalHolders($this->processScope());

        return (int) $this->evaluate(self::PRUNE_EXPIRED . "\nreturn redis.call('HLEN', key)");
    }

    /**
     * @return list<array{id: string, hostname: ?string, pid: int, process_scope: ?string, description: ?string, acquired_at_ms: int, expires_at_ms?: int}>
     */
    public function getInstanceLocks(): array
    {
        $this->reapDeadLocalHolders($this->processScope());

        return $this->readHolders(self::PRUNE_EXPIRED . "\nreturn redis.call('HVALS', key)");
    }

    private function readHolders(string $script = "return redis.call('HVALS', KEYS[1])"): array
    {
        $entries = $this->evaluate($script);

        return array_map(
            static fn (string $entry): array => json_decode($entry, true, flags: JSON_THROW_ON_ERROR),
            $entries,
        );
    }

    public static function isProcessRunning($pid): bool
    {
        return SingleInstanceLock::isProcessRunning($pid);
    }

    private function processScope(): ?string
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return null;
        }

        $bootId = @file_get_contents('/proc/sys/kernel/random/boot_id');
        $namespace = @readlink('/proc/self/ns/pid');

        return $bootId && $namespace ? trim($bootId) . '/' . $namespace : null;
    }

    private function reapDeadLocalHolders(?string $processScope): void
    {
        if ($processScope === null) {
            return;
        }

        foreach ($this->readHolders() as $holder) {
            if ($holder['process_scope'] === $processScope && !static::isProcessRunning($holder['pid'])) {
                $this->evaluate("return redis.call('HDEL', KEYS[1], ARGV[1])", $holder['id']);
            }
        }
    }

    private function evaluate(string $script, ...$arguments): mixed
    {
        $connection = $this->connection ??= Redis::connection();
        $result = $connection->eval($script, 1, $this->key, ...$arguments);
        if ($result === false || $result === null) {
            throw new RuntimeException('Redis lock script failed.');
        }

        return $result;
    }
}
