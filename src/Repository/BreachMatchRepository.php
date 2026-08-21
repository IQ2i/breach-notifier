<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BreachMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BreachMatch>
 */
class BreachMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BreachMatch::class);
    }

    public function existsForItemAndCompany(int $breachItemId, string $companyName): bool
    {
        return null !== $this->findOneBy(['breachItem' => $breachItemId, 'companyName' => $companyName]);
    }

    /**
     * @return BreachMatch[]
     */
    public function findNotNotified(): array
    {
        return $this->findAllWithItemQueryBuilder()
            ->andWhere('m.notifiedAt IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * All known matches, sorted by the breach's publication date.
     *
     * @return BreachMatch[]
     */
    public function findAllWithItem(): array
    {
        return $this->findAllWithItemQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    private function findAllWithItemQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->addSelect('i')
            ->join('m.breachItem', 'i')
            ->orderBy('i.publishedAt', 'DESC')
            ->addOrderBy('m.detectedAt', 'DESC');
    }
}
