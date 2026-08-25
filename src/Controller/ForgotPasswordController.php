<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use App\Security\ResetTokenHasher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForgotPasswordController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.reset_password')]
        private readonly RateLimiterFactoryInterface $resetPasswordLimiter,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            // V04 — 3 demandes par heure et par adresse IP : empêche l'énumération
            // de comptes par mesure du temps de réponse et le bombardement de courriels.
            $limiter = $this->resetPasswordLimiter->create($request->getClientIp() ?? 'unknown');

            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('warning', 'Trop de demandes de réinitialisation. Réessaie dans une heure.');

                return $this->redirectToRoute('app_forgot_password_request');
            }

            $email = trim((string) $request->request->get('email', ''));

            $user = $userRepository->findByEmail(strtolower($email));

            if (null !== $user && $user->isActive()) {
                // Le jeton en clair part uniquement par courriel ; seule son
                // empreinte est stockée en base (V04). Une fuite de la base ne
                // permet donc pas de forger un lien de réinitialisation valide.
                $token = ResetTokenHasher::generate();
                $user->setResetToken(ResetTokenHasher::hash($token));
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $em->flush();

                $resetUrl = $this->generateUrl(
                    'app_reset_password',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );

                $emailMessage = (new Email())
                    ->from($this->mailerFrom)
                    ->to((string) $user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe — CESIZen')
                    ->html($this->renderView('email/reset_password.html.twig', [
                        'username' => $user->getUsername() ?? $user->getEmail(),
                        'resetUrl' => $resetUrl,
                    ]));

                $mailer->send($emailMessage);
            }

            // Always show the same message to avoid user enumeration
            $this->addFlash('success', 'Si un compte existe avec cet email, un lien de reinitialisation a ete envoye.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        return $this->render('security/forgot_password_request.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $userRepository->findByResetToken(ResetTokenHasher::hash($token));

        if (null === $user) {
            $this->addFlash('error', 'Ce lien est invalide ou a expire. Veuillez refaire une demande.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            $confirm = (string) $request->request->get('password_confirm', '');

            $errors = PasswordPolicy::validate($password, $confirm);

            if ([] === $errors) {
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $em->flush();

                $this->addFlash('success', 'Mot de passe reinitialise avec succes. Tu peux te connecter.');

                return $this->redirectToRoute('app_login');
            }

            return $this->render('security/reset_password.html.twig', [
                'token' => $token,
                'errors' => $errors,
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
            'errors' => [],
        ]);
    }
}
