<?php

namespace App\Core\Subscriber;

use App\Infrastructure\Repository\AccessTokenRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AccessTokenRepository $repository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $response = $event->getResponse();

        $this->repository->terminateTokenValidityByUserIdentifier(
            $token->getUser()->getUserIdentifier()
        );

        $event->setResponse($response);
    }
}
