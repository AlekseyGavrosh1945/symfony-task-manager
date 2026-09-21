<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const DEMO_PASSWORD = 'password123';

    private const CATEGORIES = [
        ['Work', '#0d6efd'],
        ['Home', '#198754'],
        ['Learning', '#6f42c1'],
        ['Errands', '#fd7e14'],
        ['Health', '#dc3545'],
    ];

    private const TASK_TITLES = [
        'Write quarterly report',
        'Fix the login page bug',
        'Prepare demo for the client',
        'Review pull requests',
        'Update project documentation',
        'Buy groceries',
        'Clean the garage',
        'Fix the leaking tap',
        'Read chapter 4 of the PHP book',
        'Finish the Symfony course',
        'Practice SQL queries',
        'Renew car insurance',
        'Pay utility bills',
        'Book dentist appointment',
        'Go for a health check-up',
        'Plan team meeting agenda',
        'Set up monitoring alerts',
        'Refactor the export service',
        'Water the plants',
        'Sort out old photos',
        'Learn about Docker networking',
        'Answer support emails',
        'Prepare presentation slides',
        'Order new keyboard',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = $this->createUsers($manager);
        $categories = $this->createCategories($manager);
        $this->createTasks($manager, $users, $categories);

        $manager->flush();
    }

    /**
     * @return User[]
     */
    private function createUsers(ObjectManager $manager): array
    {
        $users = [];

        $userData = [
            ['email' => 'admin@demo.local', 'name' => 'Alice (admin)', 'roles' => ['ROLE_ADMIN'], 'token' => 'admin-demo-token'],
            ['email' => 'alex@demo.local', 'name' => 'Alex', 'roles' => [], 'token' => 'alex-demo-token'],
        ];

        foreach ($userData as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setName($data['name']);
            $user->setRoles($data['roles']);
            $user->setApiToken($data['token']);
            $user->setPassword($this->passwordHasher->hashPassword($user, self::DEMO_PASSWORD));

            $manager->persist($user);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * @return Category[]
     */
    private function createCategories(ObjectManager $manager): array
    {
        $categories = [];

        foreach (self::CATEGORIES as [$name, $color]) {
            $category = new Category();
            $category->setName($name);
            $category->setColor($color);

            $manager->persist($category);
            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * @param User[]      $users
     * @param Category[]  $categories
     */
    private function createTasks(ObjectManager $manager, array $users, array $categories): void
    {
        $statuses = TaskStatus::cases();
        $priorities = TaskPriority::cases();

        foreach (self::TASK_TITLES as $i => $title) {
            $task = new Task();
            $task->setTitle($title);
            $task->setDescription('Automatically generated demo task #'.($i + 1).'.');
            $task->setStatus($statuses[array_rand($statuses)]);
            $task->setPriority($priorities[array_rand($priorities)]);
            $task->setOwner($users[$i % 2]);

            if (0 === $i % 4) {
                $task->setCategory(null);
            } else {
                $task->setCategory($categories[$i % count($categories)]);
            }

            // Spread due dates from a week ago to three weeks ahead.
            $offset = random_int(-7, 21);
            $task->setDueDate(new \DateTimeImmutable("today $offset days"));

            $manager->persist($task);
        }
    }
}
