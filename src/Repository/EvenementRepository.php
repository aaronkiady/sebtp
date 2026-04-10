<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * Recherche des événements par terme
     * @param string|null $term
     * @return Evenement[]
     */
    public function findBySearch(?string $term): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($term) {
            $qb->andWhere('e.nom LIKE :q OR e.montant LIKE :q OR e.commentaire LIKE :q')
               ->setParameter('q', '%' . $term . '%');
        }

        return $qb->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les événements pour export avec filtres
     */
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

    /**
     * Récupère les années disponibles pour les filtres
     * Utilisation de SQL natif car DQL ne supporte pas YEAR()
     */
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
        
        // Si aucune année trouvée, ajouter l'année en cours
        if (empty($years)) {
            $years[] = date('Y');
        }
        
        return $years;
    }

    /**
     * Statistiques des événements
     */
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

    /**
     * Récupère les événements avec leurs participants
     */
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
}