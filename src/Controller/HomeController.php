<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\TaskStatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(TaskStatisticsService $statistics, TaskRepository $tasks): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('home/index.html.twig', [
            'stats' => $statistics->getStatsForUser($user),
            'recentTasks' => $tasks->findRecentForUser($user, 5),
        ]);
    }
}
