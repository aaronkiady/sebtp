<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findByFilters(
        ?string $action = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $userId = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($action && $action !== 'tous') {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }

        if ($entityType && $entityType !== 'tous') {
            $qb->andWhere('a.entityType = :entityType')
               ->setParameter('entityType', $entityType);
        }

        if ($entityId !== null && $entityId > 0) {
            $qb->andWhere('a.entityId = :entityId')
               ->setParameter('entityId', $entityId);
        }

        if ($userId && $userId !== '') {
            $qb->andWhere('a.userId = :userId')
               ->setParameter('userId', $userId);
        }

        if ($dateFrom) {
            $qb->andWhere('a.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo) {
            $qb->andWhere('a.createdAt <= :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        return $qb->getQuery()->getResult();
    }

    public function getActionsStats(): array
    {
        $results = $this->createQueryBuilder('a')
            ->select('a.action, COUNT(a.id) as total')
            ->groupBy('a.action')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['action']] = $result['total'];
        }
        return $stats;
    }

    public function getRecentLogs(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLogsByEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
