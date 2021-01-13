<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\Rest\Middleware\ShortUrl;

use Fig\Http\Message\RequestMethodInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shlinkio\Shlink\Core\Domain\DomainServiceInterface;
use Shlinkio\Shlink\Core\Validation\ShortUrlMetaInputFilter;
use Shlinkio\Shlink\Rest\ApiKey\Role;
use Shlinkio\Shlink\Rest\Middleware\AuthenticationMiddleware;

class OverrideDomainMiddleware implements MiddlewareInterface
{
    private DomainServiceInterface $domainService;

    public function __construct(DomainServiceInterface $domainService)
    {
        $this->domainService = $domainService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $apiKey = AuthenticationMiddleware::apiKeyFromRequest($request);
        if (! $apiKey->hasRole(Role::DOMAIN_SPECIFIC)) {
            return $handler->handle($request);
        }

        $requestMethod = $request->getMethod();
        $domainId = Role::domainIdFromMeta($apiKey->getRoleMeta(Role::DOMAIN_SPECIFIC));
        $domain = $this->domainService->getDomain($domainId);

        if ($requestMethod === RequestMethodInterface::METHOD_POST) {
            $payload = $request->getParsedBody();
            $payload[ShortUrlMetaInputFilter::DOMAIN] = $domain->getAuthority();

            return $handler->handle($request->withParsedBody($payload));
        }

        return $handler->handle($request->withAttribute(ShortUrlMetaInputFilter::DOMAIN, $domain->getAuthority()));
    }
}