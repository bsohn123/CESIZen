<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:admin-dashboard:test-data',
    description: 'Seed or cleanup test data used by the admin dashboard.',
)]
class AdminDashboardTestDataCommand extends Command
{
    private const MARKER = '[[DASH_TEST]]';
    private const DATE_BOUNDARY = '2099-01-01 00:00:00';

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'cleanup',
            null,
            InputOption::VALUE_NONE,
            'Delete test data instead of seeding it.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cleanupOnly = (bool) $input->getOption('cleanup');

        $this->connection->beginTransaction();
        try {
            $this->cleanupData();

            if (!$cleanupOnly) {
                $this->seedData();
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $counts = $this->fetchMarkerCounts();
        $io->table(
            ['marker_users', 'marker_menus', 'marker_pages', 'marker_exercises', 'marker_launches'],
            [[
                (string) $counts['marker_users'],
                (string) $counts['marker_menus'],
                (string) $counts['marker_pages'],
                (string) $counts['marker_exercises'],
                (string) $counts['marker_launches'],
            ]]
        );

        if ($cleanupOnly) {
            $io->success('Test data removed.');

            return Command::SUCCESS;
        }

        $io->success('Test data seeded.');
        $io->writeln('Expected deltas on dashboard counters:');
        $io->writeln('- Users: +3');
        $io->writeln('- Menus: +2');
        $io->writeln('- Pages: +4');
        $io->writeln('- Exercises: +2');
        $io->writeln('- Launches: +5');

        return Command::SUCCESS;
    }

    private function cleanupData(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `launch` WHERE launch_date >= :boundary',
            ['boundary' => self::DATE_BOUNDARY]
        );

        $this->connection->executeStatement(
            'DELETE FROM `page` WHERE slug LIKE :slugPattern',
            ['slugPattern' => 'dash-test-%']
        );

        $this->connection->executeStatement(
            'DELETE FROM `menu` WHERE title LIKE :marker',
            ['marker' => self::MARKER.'%']
        );

        $this->connection->executeStatement(
            'DELETE FROM breathing_exercise WHERE name LIKE :marker',
            ['marker' => self::MARKER.'%']
        );

        $this->connection->executeStatement(
            'DELETE FROM `user` WHERE email LIKE :emailPattern',
            ['emailPattern' => 'dashboard.test.%@example.com']
        );
    }

    private function seedData(): void
    {
        $users = [
            ['dashboard.test.admin@example.com', 'dash_admin', ['ROLE_ADMIN'], 1],
            ['dashboard.test.user1@example.com', 'dash_user_1', ['ROLE_USER'], 1],
            ['dashboard.test.user2@example.com', 'dash_user_2', ['ROLE_USER'], 0],
        ];

        foreach ($users as [$email, $pseudo, $roles, $active]) {
            $this->connection->executeStatement(
                'INSERT INTO `user` (email, pseudo, password, roles, created_at, last_login_at, active)
                 VALUES (:email, :pseudo, :password, :roles, NOW(), NOW(), :active)',
                [
                    'email' => $email,
                    'pseudo' => $pseudo,
                    'password' => password_hash('test1234', PASSWORD_BCRYPT),
                    'roles' => json_encode($roles, JSON_THROW_ON_ERROR),
                    'active' => $active,
                ]
            );
        }

        $menus = [
            [self::MARKER.' Bien-etre', 1, 1],
            [self::MARKER.' Respiration', 2, 1],
        ];

        foreach ($menus as [$title, $displayOrder, $active]) {
            $this->connection->executeStatement(
                'INSERT INTO `menu` (title, display_order, active)
                 VALUES (:title, :displayOrder, :active)',
                [
                    'title' => $title,
                    'displayOrder' => $displayOrder,
                    'active' => $active,
                ]
            );
        }

        $adminId = $this->fetchUserId('dashboard.test.admin@example.com');
        $user1Id = $this->fetchUserId('dashboard.test.user1@example.com');
        $wellBeingMenuId = $this->fetchMenuId(self::MARKER.' Bien-etre');
        $breathingMenuId = $this->fetchMenuId(self::MARKER.' Respiration');

        $pages = [
            [self::MARKER.' Accueil Zen', 'dash-test-accueil-zen', 'Contenu de test - accueil zen', 'Publiee', $adminId, $wellBeingMenuId],
            [self::MARKER.' Routine Matin', 'dash-test-routine-matin', 'Contenu de test - routine matin', 'Publiee', $adminId, $wellBeingMenuId],
            [self::MARKER.' Respiration 4-4-6', 'dash-test-respiration-446', 'Contenu de test - respiration 4-4-6', 'Brouillon', $user1Id, $breathingMenuId],
            [self::MARKER.' Pause Anti-stress', 'dash-test-pause-anti-stress', 'Contenu de test - pause anti-stress', 'Archivee', $user1Id, $breathingMenuId],
        ];

        foreach ($pages as [$title, $slug, $content, $status, $authorId, $menuId]) {
            $this->connection->executeStatement(
                'INSERT INTO `page` (title, slug, content, status, created_at, updated_at, author_id, menu_id)
                 VALUES (:title, :slug, :content, :status, NOW(), NOW(), :authorId, :menuId)',
                [
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content,
                    'status' => $status,
                    'authorId' => $authorId,
                    'menuId' => $menuId,
                ]
            );
        }

        $exercises = [
            [self::MARKER.' Cohérence cardiaque', 5, 5, 5, 1],
            [self::MARKER.' Relaxation profonde', 4, 7, 8, 1],
        ];

        foreach ($exercises as [$name, $inhale, $hold, $exhale, $active]) {
            $this->connection->executeStatement(
                'INSERT INTO breathing_exercise (name, inhale_duration, hold_duration, exhale_duration, active)
                 VALUES (:name, :inhale, :hold, :exhale, :active)',
                [
                    'name' => $name,
                    'inhale' => $inhale,
                    'hold' => $hold,
                    'exhale' => $exhale,
                    'active' => $active,
                ]
            );
        }

        $exerciseAId = $this->fetchExerciseId(self::MARKER.' Cohérence cardiaque');
        $exerciseBId = $this->fetchExerciseId(self::MARKER.' Relaxation profonde');
        $user2Id = $this->fetchUserId('dashboard.test.user2@example.com');

        $launches = [
            [$adminId, $exerciseAId, '2099-01-01 10:00:00', 6, '00:03:00'],
            [$adminId, $exerciseBId, '2099-01-02 11:30:00', 4, '00:04:00'],
            [$user1Id, $exerciseAId, '2099-01-03 09:15:00', 8, '00:06:00'],
            [$user1Id, $exerciseBId, '2099-01-04 18:45:00', 5, '00:05:00'],
            [$user2Id, $exerciseAId, '2099-01-05 07:50:00', 3, '00:02:15'],
        ];

        foreach ($launches as [$userId, $exerciseId, $launchDate, $cycleCount, $totalDuration]) {
            $this->connection->executeStatement(
                'INSERT INTO `launch` (user_id, exercise_id, launch_date, cycle_count, total_duration)
                 VALUES (:userId, :exerciseId, :launchDate, :cycleCount, :totalDuration)',
                [
                    'userId' => $userId,
                    'exerciseId' => $exerciseId,
                    'launchDate' => $launchDate,
                    'cycleCount' => $cycleCount,
                    'totalDuration' => $totalDuration,
                ]
            );
        }
    }

    private function fetchUserId(string $email): int
    {
        $id = $this->connection->fetchOne(
            'SELECT id_users FROM `user` WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        if ($id === false) {
            throw new \RuntimeException(sprintf('User not found for email "%s".', $email));
        }

        return (int) $id;
    }

    private function fetchMenuId(string $title): int
    {
        $id = $this->connection->fetchOne(
            'SELECT id_menu FROM `menu` WHERE title = :title LIMIT 1',
            ['title' => $title]
        );

        if ($id === false) {
            throw new \RuntimeException(sprintf('Menu not found for title "%s".', $title));
        }

        return (int) $id;
    }

    private function fetchExerciseId(string $name): int
    {
        $id = $this->connection->fetchOne(
            'SELECT id_exercise FROM breathing_exercise WHERE name = :name LIMIT 1',
            ['name' => $name]
        );

        if ($id === false) {
            throw new \RuntimeException(sprintf('Exercise not found for name "%s".', $name));
        }

        return (int) $id;
    }

    /**
     * @return array{
     *     marker_users:int|string,
     *     marker_menus:int|string,
     *     marker_pages:int|string,
     *     marker_exercises:int|string,
     *     marker_launches:int|string
     * }
     */
    private function fetchMarkerCounts(): array
    {
        $counts = $this->connection->fetchAssociative(
            'SELECT
                (SELECT COUNT(*) FROM `user` WHERE email LIKE :emailPattern) AS marker_users,
                (SELECT COUNT(*) FROM `menu` WHERE title LIKE :marker) AS marker_menus,
                (SELECT COUNT(*) FROM `page` WHERE slug LIKE :slugPattern) AS marker_pages,
                (SELECT COUNT(*) FROM breathing_exercise WHERE name LIKE :marker) AS marker_exercises,
                (SELECT COUNT(*) FROM `launch` WHERE launch_date >= :boundary) AS marker_launches',
            [
                'marker' => self::MARKER.'%',
                'slugPattern' => 'dash-test-%',
                'emailPattern' => 'dashboard.test.%@example.com',
                'boundary' => self::DATE_BOUNDARY,
            ]
        );

        if ($counts === false) {
            throw new \RuntimeException('Unable to fetch marker counts.');
        }

        return $counts;
    }
}
