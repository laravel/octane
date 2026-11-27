<?php

namespace Laravel\Octane\Swoole;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Carbon;

class TaskPayload
{
    /**
     * The number of seconds a sealed payload remains valid.
     */
    public const TTL = 60;

    /**
     * Generate a new secret for sealing task payloads.
     */
    public static function generateSecret(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Serialize and encrypt the given value for the given purpose.
     */
    public static function seal(string $secret, string $purpose, mixed $value): string
    {
        return static::encrypter($secret)->encryptString(
            $purpose.'|'.(Carbon::now()->getTimestamp() + static::TTL).'|'.serialize($value)
        );
    }

    /**
     * Decrypt and unserialize a payload sealed for the given purpose.
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public static function open(string $secret, string $purpose, mixed $payload): mixed
    {
        if (! is_string($payload)) {
            throw new DecryptException('The payload is invalid.');
        }

        [$sealedPurpose, $expiresAt, $value] = explode('|', static::encrypter($secret)->decryptString($payload), 3) + [null, null, null];

        if ($sealedPurpose !== $purpose || (int) $expiresAt < Carbon::now()->getTimestamp()) {
            throw new DecryptException('The payload is invalid.');
        }

        return unserialize($value);
    }

    /**
     * Get an encrypter for the given secret.
     */
    protected static function encrypter(string $secret): Encrypter
    {
        return new Encrypter(base64_decode($secret), 'aes-256-gcm');
    }
}
