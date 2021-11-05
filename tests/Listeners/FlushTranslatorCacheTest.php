<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Translation\Translator;
use Laravel\Octane\Tests\TestCase;
use Mockery;
use Illuminate\Http\Request;

class FlushTranslatorCacheTest extends TestCase
{
    /** @doesNotPerformAssertions */
    public function test_parsed_keys_cache_is_flushed()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/test-translator', 'GET'),
            Request::create('/test-translator', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/test-cache', function () {
            Validator::make($data, [
                'name' => 'string|max:50',
            ])->validate();
        });

        $translator = $app['translator'];

        $app['translator'] = tap(Mockery::mock($translator), function ($translator) {
            $translator->shouldReceive('flushParsedKeys')->twice();
        });

        $worker->run();
    }
}
