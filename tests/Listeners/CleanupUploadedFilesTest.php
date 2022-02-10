<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Support\Str;
use Laravel\Octane\Tests\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Laravel\Octane\Listeners\CleanupUploadedFiles
 */
class CleanupUploadedFilesTest extends TestCase
{
    public function test_files_removed()
    {
        try {
            [$file1path, $file2path, $file3path] = [
                \tempnam($tmpDir = \sys_get_temp_dir(), $prefix = 'unit-'),
                \tempnam($tmpDir, $prefix),
                \tempnam($tmpDir, $prefix),
            ];

            ($request = Request::create('http://127.0.0.1:123/foo'))->files->add([
                new UploadedFile($file1path, Str::random()),
                new UploadedFile($file2path, Str::random()),
                new UploadedFile($file3path, Str::random()),
            ]);

            $this->assertTrue(\rename($file3path, $file3newPath = $file3path.Str::random()));

            $this->assertFileExists($file1path);
            $this->assertFileExists($file2path);
            $this->assertFileExists($file3newPath);

            $event = new \stdClass();
            $event->request = $request;

            (new CleanupUploadedFiles)->handle($event);

            $this->assertFileDoesNotExist($file1path);
            $this->assertFileDoesNotExist($file2path);
            $this->assertFileExists($file3newPath); // still exists
        } finally {
            foreach ([$file1path, $file2path, $file3newPath] as $fileName) {
                if (\is_file($fileName)) {
                    \unlink($fileName);
                }
            }
        }
    }
}
