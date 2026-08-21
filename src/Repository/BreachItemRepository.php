<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BreachItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BreachItem>
 */
class BreachItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BreachItem::class);
    }

    public function findOneByGuid(string $guid): ?BreachItem
    {
        return $this->findOneBy(['guid' => $guid]);
    }

    /**
     * @return BreachItem[]
     */
    public function findAll(): array
    {
        return $this->findBy([], ['publishedAt' => 'DESC']);
    }
}
