<?php

namespace Laravel\Octane\Tests;

use Exception;
use Laravel\Octane\Stream;
use php_user_filter;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class StreamTest extends TestCase
{
    public function test_throwable_with_invalid_utf8_is_still_streamed()
    {
        $output = $this->captureStderr(fn () => Stream::throwable(new Exception("foo\xB1bar")));

        $payload = json_decode($output, true);

        $this->assertIsArray($payload);
        $this->assertSame('throwable', $payload['type']);
        $this->assertSame(Exception::class, $payload['class']);
        $this->assertSame("foo\u{FFFD}bar", $payload['message']);
    }

    public function test_shutdown_with_invalid_utf8_is_still_streamed()
    {
        $output = $this->captureStderr(fn () => Stream::shutdown(new Exception("foo\xB1bar")));

        $payload = json_decode($output, true);

        $this->assertIsArray($payload);
        $this->assertSame('shutdown', $payload['type']);
        $this->assertSame(Exception::class, $payload['class']);
        $this->assertSame("foo\u{FFFD}bar", $payload['message']);
    }

    protected function captureStderr(callable $callback): string
    {
        StderrCaptureFilter::$buffer = '';

        stream_filter_register('octane.stderr_capture', StderrCaptureFilter::class);

        $filter = stream_filter_append(STDERR, 'octane.stderr_capture', STREAM_FILTER_WRITE);

        try {
            $callback();
        } finally {
            stream_filter_remove($filter);
        }

        return trim(StderrCaptureFilter::$buffer);
    }
}

class StderrCaptureFilter extends php_user_filter
{
    public static string $buffer = '';

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            static::$buffer .= $bucket->data;
            $consumed += $bucket->datalen;
        }

        return PSFS_FEED_ME;
    }
}
