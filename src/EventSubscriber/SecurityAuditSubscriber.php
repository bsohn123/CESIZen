<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * V09 — Journalisation des événements d'authentification.
 *
 * Sans trace, une attaque par bourrage d'identifiants est invisible : on ne
 * peut ni la détecter, ni l'analyser après coup, ni prouver son ampleur lors
 * d'une notification à la CNIL. Les événements partent dans le canal dédié
 * « security » (voir config/packages/monolog.yaml).
 *
 * Aucune donnée sensible n'est journalisée : jamais de mot de passe, et
 * l'identifiant saisi est tronqué pour rester exploitable sans constituer un
 * fichier d'adresses en clair dans les journaux.
 */
final class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $securityLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        $this->securityLogger->info('Authentification réussie', [
            'event' => 'login_success',
            'user' => self::mask($user->getUserIdentifier()),
            'ip' => $event->getRequest()->getClientIp(),
        ]);

        if ($user instanceof User) {
            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $this->securityLogger->warning('Échec d\'authentification', [
            'event' => 'login_failure',
            'user' => self::mask((string) $event->getRequest()->request->get('_username', '')),
            'ip' => $event->getRequest()->getClientIp(),
            'reason' => $event->getException()::class,
        ]);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();

        $this->securityLogger->info('Déconnexion', [
            'event' => 'logout',
            'user' => self::mask($token?->getUserIdentifier() ?? 'anonyme'),
            'ip' => $event->getRequest()?->getClientIp(),
        ]);
    }

    /** j***@domaine.fr — suffisant pour corréler, insuffisant pour constituer un fichier. */
    private static function mask(string $identifier): string
    {
        if ('' === $identifier) {
            return '(vide)';
        }

        $at = strpos($identifier, '@');

        if (false === $at || $at < 1) {
            return substr($identifier, 0, 1).'***';
        }

        return substr($identifier, 0, 1).'***'.substr($identifier, $at);
    }
}
