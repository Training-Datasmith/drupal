<?php

declare (strict_types=1);
namespace Drupal\Core\Controller\Argument_Resolver;

use Psr\Http\Message\Server_Request_Interface;
use Symfony\Bridge\Psr_Http_Message\Http_Message_Factory_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
/**
 * Yields a PSR7 request object based on the request object passed along.
 */
final class Psr7request_Value_Resolver implements Value_Resolver_Interface
{
    /**
     * The PSR-7 converter.
     *
     * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
     */
    protected $http_message_factory;
    /**
     * Constructs a new ControllerResolver.
     *
     * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
     *   The PSR-7 converter.
     */
    public function __construct(Http_Message_Factory_Interface $http_message_factory)
    {
        $this->http_message_factory = $http_message_factory;
    }
    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        return $argument->get_type() === Server_Request_Interface::class ? [$this->http_message_factory->create_request($request)] : [];
    }
}