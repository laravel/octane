<?php

declare(strict_types=1);

namespace Laravel\Octane\RoadRunner;

use Illuminate\Contracts\Cache\Store;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class RoadRunnerFactory
{
    public static function ensureCacheSetup(): bool
    {
        return class_exists(Factory::class);
    }

    public static function createRPC(): RPCInterface
    {
        return RPC::create(sprintf('tcp://%s:%s', config('octane.roadrunner.rpc.host'), config('octane.roadrunner.rpc.port')));
    }

    public static function createCacheStorage(RPCInterface $rpc): StorageInterface
    {
        return (new Factory($rpc))->select(config('octane.roadrunner.cache.key'));
    }

    public static function createCacheStore(RPCInterface $rpc): Store
    {
        return new Cache(self::createCacheStorage($rpc), config('cache.prefix', ''));
    }
}
