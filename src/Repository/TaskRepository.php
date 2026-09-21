<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Builds the shared filter QueryBuilder used by the list page and the API.
     */
    private function createFilteredQueryBuilder(User $owner, ?string $q, ?TaskStatus $status, ?int $categoryId): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
                ->addSelect('c')
            ->andWhere('t.owner = :owner')
                ->setParameter('owner', $owner)
            ->orderBy('t.createdAt', 'DESC');

        if (null !== $q && '' !== $q) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('t.title', ':q'),
                $qb->expr()->like('t.description', ':q')
            ))->setParameter('q', '%'.$q.'%');
        }

        if (null !== $status) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if (null !== $categoryId) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        return $qb;
    }

    /**
     * Paginated search for the task list page.
     *
     * @return array{items: Task[], total: int, totalPages: int}
     */
    public function searchForUser(User $owner, ?string $q, ?TaskStatus $status, ?int $categoryId, int $page, int $itemsPerPage): array
    {
        $qb = $this->createFilteredQueryBuilder($owner, $q, $status, $categoryId);

        $countQb = clone $qb;
        $total = (int) $countQb
            ->resetDQLPart('orderBy') // ORDER BY on a COUNT() query breaks PostgreSQL grouping
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setMaxResults($itemsPerPage)
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $itemsPerPage)),
        ];
    }

    /**
     * @return Task[]
     */
    public function findRecentForUser(User $owner, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
                ->addSelect('c')
            ->andWhere('t.owner = :owner')
                ->setParameter('owner', $owner)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Number of tasks per status value ('new' => 3, 'in_progress' => 1, ...).
     *
     * @return array<string, int>
     */
    public function countByStatusForUser(?User $owner = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.status AS status, COUNT(t.id) AS cnt')
            ->groupBy('t.status');

        if (null !== $owner) {
            $qb->andWhere('t.owner = :owner')->setParameter('owner', $owner);
        }

        $result = [];
        foreach ($qb->getQuery()->toIterable() as $row) {
            /** @var TaskStatus $status */
            $status = $row['status'];
            $result[$status->value] = (int) $row['cnt'];
        }

        return $result;
    }

    public function countOverdueForUser(?User $owner = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.status != :done')
                ->setParameter('done', TaskStatus::Done)
            ->andWhere('t.dueDate IS NOT NULL')
            ->andWhere('t.dueDate < :today')
                ->setParameter('today', new \DateTimeImmutable('today'));

        if (null !== $owner) {
            $qb->andWhere('t.owner = :owner')->setParameter('owner', $owner);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
