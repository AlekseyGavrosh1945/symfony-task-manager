<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authenticates API requests that carry an "X-AUTH-TOKEN" header.
 *
 * The token is matched against the api_token column of the user table,
 * so clients can call /api/* endpoints without a browser session.
 */
class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public const HEADER_NAME = 'X-AUTH-TOKEN';

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api')
            && $request->headers->has(self::HEADER_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = (string) $request->headers->get(self::HEADER_NAME);

        return new SelfValidatingPassport(
            new UserBadge(
                $apiToken,
                fn (string $token) => $this->userRepository->findOneBy(['apiToken' => $token]),
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Returning null lets the request continue to the controller.
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => 'Invalid API token.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
