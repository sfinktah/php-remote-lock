<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace Sfinktah\RemoteLock;


class SingleInstanceRemoteLock implements IInstanceLock
{
    /**
     * @var int|false
     */
    private $pid;
    /**
     * @var string
     */
    protected $lockServer;
    /**
     * @var string|false
     */
    protected $hostName;
    /**
     * @var string|array|string[]|null
     */
    private $lockName;
    /**
     * @var string
     */
    private $key;
    /**
     * @var string
     */
    private $secret;

    public function __construct(string $lockName, string $lockServer = "https://markt14.fluffyduck.au") {
        if (!$lockName) {
            $lockName = preg_replace('#^.*/#', '', $argv[1]) ?? 'singleInstanceRemoteLock';
        }
        $this->hostName = gethostname();
        $this->pid = getmypid();
        $this->lockServer = $lockServer;
        $this->lockName = $lockName;
    }

    public static function isatty(): bool {
        if (!defined('STDOUT')) return false;
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        return posix_isatty(\STDOUT);
    }

    public static function printf($fmt, ...$args): void {
        if (self::isatty())
            printf($fmt, ...$args);
    }

    public function request($url, $params = null): ?\Psr\Http\Message\ResponseInterface {
        // Step 1: Instantiate CurlHttpClient
        $client = new \Sfinktah\RemoteLock\Http\CurlHttpClient();

        if (!empty($params)) {
            // Append parameters to the URL
            $url .= '?' . http_build_query($params);
        }

        $request = new \Sfinktah\RemoteLock\Http\HttpRequest('GET', $url);

        // Step 3: Use CurlHttpClient to send the request
        try {
            self::printf('Request         : ' . $url . PHP_EOL);
            self::printf('Parameters      : ' . json_encode($params) . PHP_EOL);
            $response = $client->sendRequest($request);

            // Step 4: Retrieve and check the HTTP status code
            $statusCode = $response->getStatusCode();

            self::printf('HTTP Status Code: ' . $statusCode . PHP_EOL);
            // it's a stream, you can only use it once!
            // echo 'Response Body   : ' . $response->getBody()->getContents() . PHP_EOL;

            return $response;
        } catch (\Exception $e) {
            // Handle exceptions, such as cURL errors or other runtime issues
            self::printf('Error           : ' . $e->getMessage() . PHP_EOL);
            return null;
        }
    }

    public function fork() {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('could not fork');
        } else if ($pid) {
            // we are the parent
            while (true) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);

                // If the process has already exited
                if ($res == -1 || $res > 0) {
                    break;
                }
                sleep(60); // Wait for 1 minute before renewing.
                self::printf("Renewing lock...\n");
                /** @noinspection PhpUnusedLocalVariableInspection */
                $response = $this->request("$this->lockServer/lock/api/renew", [
                        'key' => $this->key,
                        'secret' => $this->secret,
                    ]);
                if ($response->getStatusCode() > 299) {
                    self::printf("Lock is toast, abandoning watcher fork\n");
                    break;
                }
            }
        } else {
            return $pid; // 0
            // we are the child
        }
        return $pid;
    }

    public function acquire($waitSeconds = 0): bool {
        $startTime = time();
        self::printf("Acquiring lock...\n");
        while (true) {
            $response = $this->request("$this->lockServer/lock/api/acquire", [
                    'host' => $this->hostName,
                    'pid' => $this->pid,
                    'lock_name' => $this->lockName,
                ]);
            if (is_null($response)) {
                self::printf("Couldn't acquire lock...\n");
            }
            elseif ($response->getStatusCode() > 299) {
                self::printf("Couldn't acquire lock [%s]...\n", $response->getReasonPhrase());
            }
            else {
                $json = json_decode($response->getBody()->getContents(), true);
                self::printf(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
                if (is_array($json) && count($json)) {
                    $this->key = $json['key'];
                    $this->secret = $json['secret'];
                    register_shutdown_function([$this, 'release']);
                    $pid = $this->fork();
                    if ($pid == 0) {
                        self::printf("Lock acquired (child process)...\n");
                        // we are the child
                        // Register a shutdown function to release the lock if the script unexpectedly ends.
                        return true;
                    } else {
                        self::printf("Lock holding fork returned, terminating.\n");
                        die(0);
                        // we are the parent
                    }
                }
            }
            if ($waitSeconds === 0) {
                self::printf("(not retrying)\n");
                return false;
            }
            if ($waitSeconds > 0 && time() - $startTime > $waitSeconds) {
                self::printf("(timeout)\n");
                return false;
            }
            self::printf("Lock busy, will retry in 60 seconds...\n");
            sleep(60);
        }
    }

    public function release(): void {
        if (isset($this->key) && isset($this->secret)) {
            self::printf("Releasing lock...\n");
            /** @noinspection PhpUnusedLocalVariableInspection */
            $response = $this->request("$this->lockServer/lock/api/release", [
                'key' => $this->key,
                'secret' => $this->secret,
            ]);

            unset($this->key);
            unset($this->secret);
        }
        else {
            self::printf("Lock already released\n");
        }
    }

    public static function make(...$arguments): SingleInstanceRemoteLock {
        return new static(...$arguments);
    }
}
