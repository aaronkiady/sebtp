<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * Recherche des événements avec filtres
     */
    public function search(?string $search, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($search) {
            $qb->andWhere('e.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($statut) {
            $qb->andWhere('e.statut = :statut')
               ->setParameter('statut', $statut);
        }

        return $qb->orderBy('e.date', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    public function findBySearch(?string $term): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($term) {
            $qb->andWhere('e.nom LIKE :q OR e.commentaire LIKE :q')
               ->setParameter('q', '%' . $term . '%');
        }

        return $qb->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getForExport(
        ?string $annee = null,
        ?string $search = null
    ): array {
        $qb = $this->createQueryBuilder('e');

        if ($annee && $annee !== 'tous') {
            $qb->andWhere('e.date BETWEEN :debut AND :fin')
               ->setParameter('debut', $annee . '-01-01')
               ->setParameter('fin', $annee . '-12-31');
        }

        if ($search) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('e.nom', ':search'),
                $qb->expr()->like('e.commentaire', ':search')
            ))
            ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('e.date', 'DESC')
                  ->addOrderBy('e.nom', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    public function getAvailableYears(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT DISTINCT YEAR(date) as annee FROM evenement WHERE date IS NOT NULL ORDER BY annee DESC';
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $results = $result->fetchAllAssociative();

        $years = [];
        foreach ($results as $row) {
            if ($row['annee']) {
                $years[] = (string) $row['annee'];
            }
        }
        
        if (empty($years)) {
            $years[] = date('Y');
        }
        
        return $years;
    }

    public function getStats(): array
    {
        $total = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $participantsTotal = $this->createQueryBuilder('e')
            ->select('SUM(SIZE(e.participations))')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'participantsTotal' => $participantsTotal ?: 0,
        ];
    }

    public function findWithParticipants(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.participations', 'p')
            ->addSelect('p')
            ->leftJoin('p.adherent', 'a')
            ->addSelect('a')
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getAllForSelect(): array
    {
        $events = $this->createQueryBuilder('e')
            ->select('e.id, e.nom')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
        
        $result = [];
        foreach ($events as $event) {
            $result[$event['id']] = $event['nom'];
        }
        
        return $result;
    }
}