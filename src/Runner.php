<?php

declare(strict_types=1);

namespace Joanhey\NgxPhpRuntime;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface;

class Runner implements RunnerInterface
{
    private $kernel;
    //const SERVER_SOFTWARE = \NGX_HTTP_PHP_MODULE_NAME.'/'.\NGX_HTTP_PHP_MODULE_VERSION;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function run(): int
    {
        // Prevent worker script termination when a client connection is interrupted
        ignore_user_abort(true);

        $sfRequest = new Request(
            \ngx_query_args(),
            \ngx_post_args(),
            [],
            self::buildCookies(),
            [], // $_FILES :/
            self::buildServer(),
            \ngx_request_body(),
        );

        $sfResponse = $this->kernel->handle($sfRequest);

        //headers
        foreach ($sfResponse->headers->all() as $key => $value) {
            \ngx_header_set($key, $value);
            //\ngx_header_set(ucwords($key, '-'), $value);
        }

        echo $sfResponse->getContent();

        \ngx_exit($sfResponse->getStatusCode());

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($sfRequest, $sfResponse);
        }

        return 0;
    }

    private static function buildServer(): array
    {
        $server = [
            'REMOTE_ADDR'        => \ngx_request_remote_addr(),
            'REMOTE_PORT'        => \ngx_request_remote_port(),
            'REQUEST_URI'        => \ngx_request_uri(),
            //'QUERY_STRING'       => \ngx_request_query_string(),
            'REQUEST_METHOD'     => \ngx_request_method(),
            'SERVER_PROTOCOL'    => \ngx_request_server_protocol(),
            'SERVER_NAME'        => \ngx_request_server_name(),
            'SERVER_ADDR'        => \ngx_request_server_addr(),
            //'SERVER_SOFTWARE'    => self::SERVER_SOFTWARE,
        ];

        // content_type and length before
        foreach (\ngx_request_headers() as $key => $value) {
            $key = \strtoupper($key);
            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $server[$key] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        return array_merge($server, $_SERVER);
    }

    private static function buildCookies(): array
    {
        if (\ngx_cookie_get_all() === null) {
            return [];
        }

        \parse_str(\str_replace('; ', '&', \ngx_cookie_get_all()), $_COOKIE);

        return $_COOKIE;
    }
}
