<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Security\ImageSanitizer;
use App\Security\PasswordPolicy;
use App\Security\ResetTokenHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests de non-régression des correctifs de sécurité du bloc 3.
 *
 * Chaque test correspond à une vulnérabilité identifiée dans le plan de
 * sécurisation : si un correctif est défait par une modification ultérieure,
 * la chaîne d'intégration le signale avant la mise en production.
 */
class SecurityHardeningTest extends AbstractControllerTest
{
    protected function getTestEmails(): array
    {
        return [...parent::getTestEmails(), 'reset_test@cesizen-test.fr'];
    }

    // -------------------------------------------------------------------------
    // V02 — limitation des tentatives de connexion
    // -------------------------------------------------------------------------

    public function testLoginThrottlingIsEnabled(): void
    {
        $container = static::getContainer();

        // Ces deux limiteurs ne sont déclarés par le composant Security que si
        // « login_throttling » est configuré sur le pare-feu principal.
        $this->assertTrue(
            $container->has('limiter._login_global_main'),
            'La limitation globale des tentatives de connexion n\'est pas configurée (V02).',
        );
        $this->assertTrue(
            $container->has('limiter._login_local_main'),
            'La limitation par identifiant n\'est pas configurée (V02).',
        );
    }

    // -------------------------------------------------------------------------
    // V03 — politique de mot de passe
    // -------------------------------------------------------------------------

    #[DataProvider('weakPasswords')]
    public function testPasswordPolicyRejectsWeakPasswords(string $password): void
    {
        $this->assertNotEmpty(
            PasswordPolicy::validate($password),
            sprintf('Le mot de passe faible "%s" devrait être refusé (V03).', $password),
        );
    }

    public static function weakPasswords(): iterable
    {
        yield 'trop court' => ['Ab1!'];
        yield 'onze caractères' => ['Abcdefgh12!'];
        yield 'minuscules seules' => ['motdepassesecret'];
        yield 'deux classes seulement' => ['motdepasse12']; // minuscules + chiffres
    }

    public function testPasswordPolicyAcceptsStrongPassword(): void
    {
        $this->assertSame([], PasswordPolicy::validate('TestPass123!', 'TestPass123!'));
    }

    public function testPasswordPolicyDetectsConfirmationMismatch(): void
    {
        $this->assertNotEmpty(PasswordPolicy::validate('TestPass123!', 'TestPass124!'));
    }

    // -------------------------------------------------------------------------
    // V04 — jeton de réinitialisation stocké sous forme d'empreinte
    // -------------------------------------------------------------------------

    public function testResetTokenIsNeverStoredInClearText(): void
    {
        $this->createTestUser('reset_test@cesizen-test.fr');

        $this->client->request('POST', '/mot-de-passe-oublie', [
            'email' => 'reset_test@cesizen-test.fr',
        ]);

        $message = $this->getMailerMessage();
        $this->assertNotNull($message, 'Aucun courriel de réinitialisation n\'a été envoyé.');

        $body = $message->getHtmlBody();
        $this->assertIsString($body);
        $this->assertSame(1, preg_match('#/reinitialiser-mot-de-passe/([a-f0-9]{64})#', $body, $matches));

        $plainToken = $matches[1];

        $storedToken = $this->reloadUser('reset_test@cesizen-test.fr')->getResetToken();

        $this->assertNotSame(
            $plainToken,
            $storedToken,
            'Le jeton envoyé par courriel ne doit jamais être stocké tel quel en base (V04).',
        );
        $this->assertSame(ResetTokenHasher::hash($plainToken), $storedToken);
    }

    public function testResetLinkWorksWithPlainTokenOnly(): void
    {
        $user = $this->createTestUser('reset_test@cesizen-test.fr');

        $plainToken = ResetTokenHasher::generate();
        $user->setResetToken(ResetTokenHasher::hash($plainToken));
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->getEntityManager()->flush();

        // Le jeton en clair ouvre le formulaire...
        $this->client->request('GET', '/reinitialiser-mot-de-passe/'.$plainToken);
        $this->assertResponseIsSuccessful();

        // ...tandis que l'empreinte lue en base ne vaut pas laissez-passer.
        $this->client->request('GET', '/reinitialiser-mot-de-passe/'.ResetTokenHasher::hash($plainToken));
        $this->assertResponseRedirects('/mot-de-passe-oublie');
    }

    // -------------------------------------------------------------------------
    // V06 — assainissement des images téléversées
    // -------------------------------------------------------------------------

    public function testUploadedImageIsReEncodedAndPayloadStripped(): void
    {
        $path = sys_get_temp_dir().'/cesizen_polyglot_'.bin2hex(random_bytes(4)).'.gif';

        // Image GIF valide à laquelle on accole une charge utile PHP : le fichier
        // reste une image acceptée par getimagesize() et par le contrôle MIME.
        $image = imagecreatetruecolor(10, 10);
        imagegif($image, $path);
        imagedestroy($image);
        file_put_contents($path, '<?php system($_GET["c"]); ?>', \FILE_APPEND);

        $this->assertStringContainsString('<?php', (string) file_get_contents($path));

        ImageSanitizer::sanitize(new UploadedFile($path, 'avatar.gif', 'image/gif', null, true));

        $sanitized = (string) file_get_contents($path);
        $this->assertStringNotContainsString(
            '<?php',
            $sanitized,
            'Le réencodage doit supprimer toute donnée non graphique (V06).',
        );
        $this->assertNotFalse(getimagesize($path), 'Le fichier réencodé doit rester une image valide.');

        unlink($path);
    }

    public function testSanitizerRejectsNonImageFile(): void
    {
        $path = sys_get_temp_dir().'/cesizen_fake_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($path, '<?php echo "pas une image"; ?>');

        $this->expectException(FileException::class);

        try {
            ImageSanitizer::sanitize(new UploadedFile($path, 'avatar.jpg', 'image/jpeg', null, true));
        } finally {
            unlink($path);
        }
    }

    // -------------------------------------------------------------------------
    // V09 — traçabilité des accès
    // -------------------------------------------------------------------------

    public function testLastLoginIsRecordedOnSuccessfulAuthentication(): void
    {
        $user = $this->createTestUser('user@cesizen-test.fr');
        $user->setLastLoginAt(null);
        $this->getEntityManager()->flush();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'user@cesizen-test.fr',
            '_password' => 'TestPass123!',
        ]);

        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $this->reloadUser('user@cesizen-test.fr')->getLastLoginAt(),
            'La date de dernière connexion doit être enregistrée à chaque authentification réussie (V09).',
        );
    }

    /** Recharge l'utilisateur depuis la base : le noyau est redémarré entre deux requêtes. */
    private function reloadUser(string $email): User
    {
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }
}
