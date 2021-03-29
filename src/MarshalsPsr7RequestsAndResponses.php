<?php

namespace Laravel\Octane;

use Illuminate\Http\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

trait MarshalsPsr7RequestsAndResponses
{
    /**
     * The Symfony PSR-7 factory.
     *
     * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface|null
     */
    protected $psrHttpFactory;

    /**
     * The Symfony HttpFoundation factory.
     *
     * @var \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory|null
     */
    protected $httpFoundationFactory;

    /**
     * Convert the given PSR-7 request to an HttpFoundation request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Illuminate\Http\Request
     */
    protected function toHttpFoundationRequest(ServerRequestInterface $request): Request
    {
        return Request::createFromBase($this->httpFoundationRequestFactory()->createRequest($request));
    }

    /**
     * Convert the given HttpFoundation response into a PSR-7 response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return  \Psr\Http\Message\ResponseInterface
     */
    protected function toPsr7Response(Response $response): ResponseInterface
    {
        return $this->psr7ResponseFactory()->createResponse($response);
    }

    /**
     * Create the Symfony HttpFoundation factory.
     *
     * This instance can turn a PSR-7 request into an HttpFoundation request.
     *
     * @return \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory
     */
    protected function httpFoundationRequestFactory(): HttpFoundationFactoryInterface
    {
        return $this->httpFoundationFactory ?: (
            $this->httpFoundationFactory = new HttpFoundationFactory
        );
    }

    /**
     * Create the Symfony PSR-7 factory.
     *
     * This instance can turn an HTTP Foundation response into a PSR-7 response.
     *
     * @return \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
     */
    protected function psr7ResponseFactory(): HttpMessageFactoryInterface
    {
        return $this->psrHttpFactory ?: ($this->psrHttpFactory = new PsrHttpFactory(
            new \Spiral\RoadRunner\Diactoros\ServerRequestFactory,
            new \Spiral\RoadRunner\Diactoros\StreamFactory,
            new \Spiral\RoadRunner\Diactoros\UploadedFileFactory,
            new \Laminas\Diactoros\ResponseFactory
        ));
    }
}
