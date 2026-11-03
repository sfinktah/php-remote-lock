<?php
/**
 * non-composer include file
 */
require_once __DIR__ . '/src/IInstanceLock.php';
require_once __DIR__ . '/src/Exceptions/LockAcquireException.php';
require_once __DIR__ . '/src/Http/HttpUri.php';
require_once __DIR__ . '/src/Http/MemoryStream.php';
require_once __DIR__ . '/src/Http/HttpResponse.php';
require_once __DIR__ . '/src/Http/HttpRequest.php';
require_once __DIR__ . '/src/Http/CurlHttpClient.php';
require_once __DIR__ . '/src/SingleInstanceLock.php';
require_once __DIR__ . '/src/MultipleInstanceLock.php';
require_once __DIR__ . '/src/SingleInstanceRemoteLock.php';
