<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Config/config.php';

use BeachVolleybot\Workers\Worker;

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if ($argc < 2) {
    fail(sprintf('Usage: php %s <WorkerName> [--param=value ...]', $argv[0]));
}

$workerName = $argv[1];
$workerClass = 'BeachVolleybot\\Workers\\' . $workerName . 'Worker';

if (!class_exists($workerClass)) {
    fail(sprintf('Worker class "%s" not found.', $workerClass));
}

$reflection = new ReflectionClass($workerClass);

if (!$reflection->isInstantiable()) {
    fail(sprintf('Worker "%s" is not instantiable.', $workerName));
}

if (!$reflection->isSubclassOf(Worker::class)) {
    fail(sprintf('Class "%s" is not a subclass of Worker.', $workerClass));
}

$params = [];
for ($i = 2; $i < $argc; $i++) {
    if (1 === preg_match('/^--([^=]+)=(.*)$/', $argv[$i], $matches)) {
        $params[$matches[1]] = $matches[2];
    } elseif (preg_match('/^--([^=]+)$/', $argv[$i], $matches)) {
        $params[$matches[1]] = true;
    }
}

$constructorArgs = [];
foreach ($reflection->getConstructor()?->getParameters() ?? [] as $param) {
    $name = $param->getName();

    if (array_key_exists($name, $params)) {
        $constructorArgs[] = $params[$name];
    } elseif ($param->isDefaultValueAvailable()) {
        $constructorArgs[] = $param->getDefaultValue();
    } else {
        fail(sprintf('Missing required parameter "--%s" for worker "%s".', $name, $workerName));
    }
}

/** @var Worker $worker */
$worker = $reflection->newInstanceArgs($constructorArgs);
$worker->run();