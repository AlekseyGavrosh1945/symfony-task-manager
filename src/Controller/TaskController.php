<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Event\TaskCreatedEvent;
use App\Form\TaskType;
use App\Repository\CategoryRepository;
use App\Repository\TaskRepository;
use App\Enum\TaskStatus;
use App\Security\Voter\TaskVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tasks', name: 'app_task_')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        // $itemsPerPage is bound to the "app.items_per_page" parameter in config/services.yaml
        private readonly int $itemsPerPage,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, TaskRepository $taskRepository, CategoryRepository $categoryRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $q = $request->query->getString('q');
        $status = TaskStatus::tryFrom($request->query->getString('status'));
        $categoryId = $request->query->getInt('category') ?: null;
        $page = max(1, $request->query->getInt('page', 1));

        $result = $taskRepository->searchForUser($user, $q, $status, $categoryId, $page, $this->itemsPerPage);

        return $this->render('task/index.html.twig', [
            'tasks' => $result['items'],
            'total' => $result['total'],
            'totalPages' => $result['totalPages'],
            'currentPage' => $page,
            'q' => $q,
            'status' => $status,
            'categoryId' => $categoryId,
            'categories' => $categoryRepository->findAllOrdered(),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        $this->denyAccessUnlessGranted(TaskVoter::VIEW, $task);

        return $this->render('task/show.html.twig', ['task' => $task]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'], priority: 1)]
    public function new(Request $request): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $task->setOwner($user);

            $this->entityManager->persist($task);
            $this->entityManager->flush();

            // Any listener interested in new tasks will react to this event.
            $this->eventDispatcher->dispatch(new TaskCreatedEvent($task));

            $this->addFlash('success', 'Task created.');

            return $this->redirectToRoute('app_task_index');
        }

        return $this->render('task/form.html.twig', [
            'form' => $form,
            'task' => $task,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task): Response
    {
        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Task updated.');

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }

        return $this->render('task/form.html.twig', [
            'form' => $form,
            'task' => $task,
        ]);
    }

    #[Route('/{id<\d+>}/complete', name: 'complete', methods: ['POST'])]
    public function complete(Request $request, Task $task): Response
    {
        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);

        $this->validateCsrf($request, 'complete'.$task->getId());

        $task->setStatus(TaskStatus::Done);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Task "%s" marked as done.', $task->getTitle()));

        return $this->redirectToRoute('app_task_index');
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Task $task): Response
    {
        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);

        $this->validateCsrf($request, 'delete'.$task->getId());

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        $this->addFlash('success', 'Task deleted.');

        return $this->redirectToRoute('app_task_index');
    }

    private function validateCsrf(Request $request, string $tokenId): void
    {
        $submittedToken = (string) $request->request->getString('_token');
        if (!$this->isCsrfTokenValid($tokenId, $submittedToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
