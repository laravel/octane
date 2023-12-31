<?php

namespace Laravel\Octane\Swoole\Handlers;

use Swoole\Http\Request;
use Swoole\Http\Response;

class OnWebSocketHandshake
{
    /**
     * Handle the handshake.
     *
     * @see https://www.swoole.co.uk/docs/modules/swoole-websocket-server
     */
    public function handle(Request $request, Response $response): bool
    {
        $secWebSocketKey = $request->header['sec-websocket-key'];

        if (preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $secWebSocketKey) === 0 || strlen(base64_decode($secWebSocketKey)) !== 16) {
            $response->end();

            return false;
        }

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => base64_encode(sha1($request->header['sec-websocket-key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)),
            'Sec-WebSocket-Version' => '13',
        ];

        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        $response->status(101);
        $response->end();

        return true;
    }
}
