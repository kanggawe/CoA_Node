<?php

/**
 * FreeRADIUS CoA Proxy API
 * Lightweight CLI Test Runner
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Autoloader setup
spl_autoload_register(function (string $class): void {
    $prefixMap = [
        'CoaProxy\\' => BASE_PATH . '/src/',
        'CoaProxy\\Tests\\' => BASE_PATH . '/tests/',
    ];

    foreach ($prefixMap as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

echo "==================================================\n";
echo " FreeRADIUS CoA Proxy API - Test Suite\n";
echo "==================================================\n\n";

$passCount = 0;
$failCount = 0;

$runAssertion = function(string $testName, callable $fn) use (&$passCount, &$failCount) {
    try {
        $fn();
        echo " [PASS] {$testName}\n";
        $passCount++;
    } catch (\Throwable $e) {
        echo " [FAIL] {$testName}: {$e->getMessage()}\n";
        $failCount++;
    }
};

// 1. Test Env loader
$runAssertion("Env loader parses values", function() {
    \CoaProxy\Env::load(BASE_PATH . '/.env.example');
    $val = \CoaProxy\Env::get('RADIUS_COA_PORT');
    if ($val != '3799') throw new \Exception("Expected 3799, got {$val}");
});

// 2. Test Validator username
$runAssertion("Validator username check", function() {
    $val = new \CoaProxy\Validator(['default_nas' => '10.10.10.1', 'allowed_nas' => ['10.10.10.1'], 'allowed_attributes' => []]);
    $res = $val->validateUsername('user001');
    if ($res !== 'user001') throw new \Exception("Username validation failed");
});

// 3. Test Validator invalid username injection
$runAssertion("Validator username injection prevention", function() {
    $val = new \CoaProxy\Validator(['default_nas' => '10.10.10.1', 'allowed_nas' => ['10.10.10.1'], 'allowed_attributes' => []]);
    try {
        $val->validateUsername('user001; drop table users;');
        throw new \Exception("Should have thrown exception");
    } catch (\InvalidArgumentException $e) {
        // Expected
    }
});

// 4. Test Validator rate limit regex
$runAssertion("Validator rate limit parsing", function() {
    $val = new \CoaProxy\Validator(['default_nas' => '10.10.10.1', 'allowed_nas' => ['10.10.10.1'], 'allowed_attributes' => []]);
    $res = $val->validateRateLimit('20M/20M');
    if ($res !== '20M/20M') throw new \Exception("Rate limit parsing failed");
});

// 5. Test Auth IP allowlist
$runAssertion("Auth IP allowlist matching", function() {
    $auth = new \CoaProxy\Auth(['allowed_ips' => ['127.0.0.1', '10.10.10.20']]);
    if (!$auth->checkIpAllowlist('127.0.0.1')) throw new \Exception("127.0.0.1 should be allowed");
    if ($auth->checkIpAllowlist('192.168.1.1')) throw new \Exception("192.168.1.1 should be blocked");
});

// 6. Test Auth token matching
$runAssertion("Auth Bearer Token hash_equals matching", function() {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer super_secret_token_123';
    $auth = new \CoaProxy\Auth(['api_token' => 'super_secret_token_123', 'allowed_ips' => ['127.0.0.1']]);
    if (!$auth->authenticate()) throw new \Exception("Token should match");

    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong_token';
    if ($auth->authenticate()) throw new \Exception("Wrong token should fail");
    unset($_SERVER['HTTP_AUTHORIZATION']);
});

echo "\n==================================================\n";
echo " Test Results: {$passCount} Passed, {$failCount} Failed\n";
echo "==================================================\n";

exit($failCount > 0 ? 1 : 0);
