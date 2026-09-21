<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Categories ordered alphabetically.
     *
     * @return Category[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Categories together with the number of tasks in each.
     *
     * @return array<int, array{0: Category, taskCount: int}>
     */
    public function findAllWithTaskCounts(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(t.id) AS taskCount')
            ->leftJoin('c.tasks', 't')
            ->orderBy('c.name', 'ASC')
            ->groupBy('c')
            ->getQuery()
            ->getResult();
    }
}
