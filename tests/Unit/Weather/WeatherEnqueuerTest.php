<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\FileQueue;
use PHPUnit\Framework\TestCase;

final class WeatherEnqueuerTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = BASE_QUEUE_DIR . '/weather_test_' . uniqid('', true);
        if (!@mkdir($this->baseDir, 0755, true) && !is_dir($this->baseDir)) {
            throw new \RuntimeException('Failed to create test queue dir: ' . $this->baseDir);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->baseDir);
    }

    public function testEnqueueWritesPayloadThatDequeuesBack(): void
    {
        $enqueuer = new WeatherEnqueuer(baseDir: $this->baseDir, addOns: [WeatherAddOn::class]);

        $enqueuer->enqueue(42, force: true);

        $message = new FileQueue('weather_42', $this->baseDir)->dequeue();
        $this->assertNotNull($message);

        $payload = WeatherQueuePayload::fromArray($message->payload);
        $this->assertSame(42, $payload->gameId);
        $this->assertTrue($payload->force);
    }

    public function testDefaultForceIsFalse(): void
    {
        $enqueuer = new WeatherEnqueuer(baseDir: $this->baseDir, addOns: [WeatherAddOn::class]);

        $enqueuer->enqueue(7);

        $message = new FileQueue('weather_7', $this->baseDir)->dequeue();
        $this->assertNotNull($message);
        $this->assertFalse(WeatherQueuePayload::fromArray($message->payload)->force);
    }

    public function testDifferentGamesWriteToSeparateQueueFiles(): void
    {
        $enqueuer = new WeatherEnqueuer(baseDir: $this->baseDir, addOns: [WeatherAddOn::class]);

        $enqueuer->enqueue(1);
        $enqueuer->enqueue(2);

        $messageForGame1 = new FileQueue('weather_1', $this->baseDir)->dequeue();
        $messageForGame2 = new FileQueue('weather_2', $this->baseDir)->dequeue();

        $this->assertNotNull($messageForGame1);
        $this->assertNotNull($messageForGame2);
        $this->assertSame(1, WeatherQueuePayload::fromArray($messageForGame1->payload)->gameId);
        $this->assertSame(2, WeatherQueuePayload::fromArray($messageForGame2->payload)->gameId);
    }

    public function testMultipleEnqueuesForSameGameAppendToSameQueue(): void
    {
        $enqueuer = new WeatherEnqueuer(baseDir: $this->baseDir, addOns: [WeatherAddOn::class]);

        $enqueuer->enqueue(5, force: false);
        $enqueuer->enqueue(5, force: true);

        $queue = new FileQueue('weather_5', $this->baseDir);
        $first = $queue->dequeue();
        $second = $queue->dequeue();

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertFalse(WeatherQueuePayload::fromArray($first->payload)->force);
        $this->assertTrue(WeatherQueuePayload::fromArray($second->payload)->force);
    }

    public function testEnqueueSilentlySkipsWhenWeatherAddOnIsNotEnabled(): void
    {
        $enqueuer = new WeatherEnqueuer(baseDir: $this->baseDir, addOns: []);

        $enqueuer->enqueue(42, force: true);

        $this->assertNull(new FileQueue('weather_42', $this->baseDir)->dequeue());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $fullPath = $path . '/' . $entry;
            is_dir($fullPath) ? $this->removeDirectory($fullPath) : @unlink($fullPath);
        }

        @rmdir($path);
    }
}
