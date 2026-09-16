<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\FileQueue;
use DateTimeImmutable;
use DateTimeZone;
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
        $this->enqueuer()->enqueue($this->keyAt($this->bogatell()));

        $message = new FileQueue($this->bogatellQueueName(), $this->baseDir)->dequeue();
        $this->assertNotNull($message);

        $payload = WeatherQueuePayload::fromArray($message->payload);
        $this->assertNotNull($payload);
        $this->assertSame(41.394, $payload->coordinates->latitude);
        $this->assertSame('2030-04-25 18:00:00', $payload->forecastTs->format('Y-m-d H:i:s'));
    }

    public function testCoordinatesWrittenTwoWaysShareOneQueue(): void
    {
        $enqueuer = $this->enqueuer();

        $enqueuer->enqueue($this->keyAt(new LocationCoordinates(41.4, 2.2)));
        $enqueuer->enqueue($this->keyAt(new LocationCoordinates(41.400, 2.200)));

        $queue = new FileQueue($this->queueName('41.4', '2.2'), $this->baseDir);
        $this->assertNotNull($queue->dequeue());
        $this->assertNotNull($queue->dequeue());
    }

    public function testDifferentKeysWriteToSeparateQueueFiles(): void
    {
        $enqueuer = $this->enqueuer();

        $enqueuer->enqueue($this->keyAt($this->bogatell()));
        $enqueuer->enqueue($this->keyAt(new LocationCoordinates(41.415, 2.205)));

        $this->assertNotNull(new FileQueue($this->bogatellQueueName(), $this->baseDir)->dequeue());
        $this->assertNotNull(new FileQueue($this->queueName('41.415', '2.205'), $this->baseDir)->dequeue());
    }

    public function testTheSameHourAtTheSameVenueSharesOneQueue(): void
    {
        $enqueuer = $this->enqueuer();

        $enqueuer->enqueue($this->keyAt($this->bogatell()));
        $enqueuer->enqueue($this->keyAt($this->bogatell()));

        $queue = new FileQueue($this->bogatellQueueName(), $this->baseDir);

        $this->assertNotNull($queue->dequeue());
        $this->assertNotNull($queue->dequeue());
    }

    public function testEnqueueSilentlySkipsWhenWeatherAddOnIsNotEnabled(): void
    {
        new WeatherEnqueuer(baseDir: $this->baseDir, addOns: [])
            ->enqueue($this->keyAt($this->bogatell()));

        $this->assertNull(new FileQueue($this->bogatellQueueName(), $this->baseDir)->dequeue());
    }

    private function enqueuer(): WeatherEnqueuer
    {
        return new WeatherEnqueuer(baseDir: $this->baseDir, addOns: [WeatherAddOn::class]);
    }

    private function keyAt(LocationCoordinates $coordinates): WeatherQueuePayload
    {
        return WeatherQueuePayload::createRounded($coordinates, $this->forecastHour());
    }

    private function bogatell(): LocationCoordinates
    {
        return new LocationCoordinates(41.394, 2.208);
    }

    private function forecastHour(): DateTimeImmutable
    {
        return new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('UTC'));
    }

    private function bogatellQueueName(): string
    {
        return $this->queueName('41.394', '2.208');
    }

    private function queueName(string $latitude, string $longitude): string
    {
        return 'weather_' . $latitude . '_' . $longitude . '_' . $this->forecastHour()->getTimestamp();
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
