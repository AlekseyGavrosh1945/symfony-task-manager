<?php

namespace App\Controller\Api;

use App\Dto\TaskInput;
use App\Entity\Task;
use App\Entity\User;
use App\Event\TaskCreatedEvent;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\CategoryRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * JSON API for tasks.
 *
 * Authentication works through ApiTokenAuthenticator: send the
 * "X-AUTH-TOKEN" header with one of the tokens created by the fixtures.
 */
#[Route('/api/tasks', name: 'api_tasks_')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, TaskRepository $taskRepository): JsonResponse
    {
        if (null === $user = $this->getAuthenticatedUser()) {
            return $this->unauthorized();
        }

        $result = $taskRepository->searchForUser(
            owner: $user,
            q: $request->query->getString('q'),
            status: TaskStatus::tryFrom($request->query->getString('status')),
            categoryId: $request->query->getInt('category') ?: null,
            page: max(1, $request->query->getInt('page', 1)),
            itemsPerPage: min(100, max(1, $request->query->getInt('perPage', 20))),
        );

        return $this->json(
            [
                'data' => $result['items'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $request->query->getInt('page', 1),
                    'totalPages' => $result['totalPages'],
                ],
            ],
            context: ['groups' => ['task:read']],
        );
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(Task $task): JsonResponse
    {
        if (null === $this->getAuthenticatedUser()) {
            return $this->unauthorized();
        }

        return $this->json($task, context: ['groups' => ['task:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CategoryRepository $categoryRepository,
    ): JsonResponse {
        if (null === $user = $this->getAuthenticatedUser()) {
            return $this->unauthorized();
        }

        try {
            $input = $serializer->deserialize($request->getContent(), TaskInput::class, 'json');
        } catch (UnexpectedValueException) {
            return $this->json(['error' => 'Malformed JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($input);
        if (\count($errors) > 0) {
            return $this->json(
                ['errors' => array_map(
                    fn ($e) => ['field' => $e->getPropertyPath(), 'message' => $e->getMessage()],
                    iterator_to_array($errors)
                )],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $category = null;
        if (null !== $input->categoryId) {
            $category = $categoryRepository->find($input->categoryId);
            if (null === $category) {
                throw new NotFoundHttpException(sprintf('Category %d does not exist.', $input->categoryId));
            }
        }

        $task = new Task();
        $task->setTitle($input->title);
        $task->setDescription($input->description);
        $task->setStatus(null !== $input->status ? TaskStatus::from($input->status) : TaskStatus::New);
        $task->setPriority(null !== $input->priority ? TaskPriority::from($input->priority) : TaskPriority::Medium);
        $task->setDueDate(null !== $input->dueDate ? new \DateTimeImmutable($input->dueDate) : null);
        $task->setCategory($category);
        $task->setOwner($user);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new TaskCreatedEvent($task));

        return $this->json($task, Response::HTTP_CREATED, context: ['groups' => ['task:read']]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function unauthorized(): JsonResponse
    {
        return $this->json(
            ['error' => sprintf('Authentication required. Send the "%s" header.', 'X-AUTH-TOKEN')],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
