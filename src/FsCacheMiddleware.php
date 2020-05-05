<?php

namespace ReinVanOyen\Middlewares\FsCache;

use Oak\Contracts\Config\RepositoryInterface;
use Oak\Contracts\Filesystem\FilesystemInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FsCacheMiddleware implements MiddlewareInterface
{
    /**
     * @var FilesystemInterface $filesystem
     */
    private $filesystem;

    /**
     * @var RepositoryInterface $config
     */
    private $config;

    /**
     * @var ResponseFactoryInterface $responseFactory
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface $streamFactory
     */
    private $streamFactory;

    /**
     * FsCacheMiddleware constructor.
     * @param FilesystemInterface $filesystem
     * @param RepositoryInterface $config
     * @param ResponseFactoryInterface $responseFactory
     * @param StreamFactoryInterface $streamFactory
     */
    public function __construct(FilesystemInterface $filesystem, RepositoryInterface $config, ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->filesystem = $filesystem;
        $this->config = $config;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestedPath = $request->getUri()->getPath();

        $bodyCacheFile = $this->getCacheFilename($requestedPath, 'html');
        $headersCacheFile = $this->getCacheFilename($requestedPath, 'json');

        // Check if we have body and headers of this request in our cache
        if ($this->filesystem->exists($bodyCacheFile) && $this->filesystem->exists($headersCacheFile)) {

            $headers = json_decode($this->filesystem->get($headersCacheFile));

            // Create the response with the body
            $response = $this->responseFactory->createResponse(200)
                ->withBody(
                    $this->streamFactory->createStream(
                        $this->filesystem->get($bodyCacheFile)
                    )
                );

            // Add the headers to the response
            foreach ($headers as $name => $contents) {
                $response = $response->withHeader($name, implode(',', $contents));
            }

            return $response;
        }

        // Handle next handler
        $response = $handler->handle($request);

        // Write the response to our cache
        $this->filesystem->put($bodyCacheFile, (string) $response->getBody());
        $this->filesystem->put($headersCacheFile, json_encode($response->getHeaders(), true));

        // Give back the original response
        return $response;
    }

    /**
     * @param $path
     * @param $ext
     * @return string
     */
    private function getCacheFilename($path, $ext)
    {
        return $this->config->get('app.cache_path').'fscache/'.trim(str_replace('/', '_', $path), '_').'.'.$ext;
    }
}