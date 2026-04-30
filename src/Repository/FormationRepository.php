<?php

namespace App\Repository;

use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /**
     * Recherche des formations par terme
     * @param string|null $term
     * @return Formation[]
     */
    public function findBySearch(?string $term): array
    {
        $qb = $this->createQueryBuilder('f');

        if ($term) {
            $qb->andWhere('f.nom LIKE :q OR f.type LIKE :q OR f.organisateur LIKE :q')
               ->setParameter('q', '%' . $term . '%');
        }

        return $qb->orderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les formations pour export avec filtres
     */
    public function getForExportWithFormationId(
        ?string $annee = null,
        ?int $formationId = null
    ): array {
        $qb = $this->createQueryBuilder('f');

        if ($annee && $annee !== 'tous') {
            $qb->andWhere('YEAR(f.dateDebut) = :annee OR YEAR(f.dateFin) = :annee')
            ->setParameter('annee', $annee);
        }

        if ($formationId !== null && $formationId > 0) {
            $qb->andWhere('f.id = :formationId')
            ->setParameter('formationId', $formationId);
        }

        return $qb->orderBy('f.dateDebut', 'DESC')
                ->addOrderBy('f.nom', 'ASC')
                ->getQuery()
                ->getResult();
    }

    /**
     * Récupère toutes les formations pour la liste déroulante
     */
    public function getAllForSelect(): array
    {
        $formations = $this->createQueryBuilder('f')
            ->select('f.id, f.nom')
            ->orderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
        
        $result = [];
        foreach ($formations as $formation) {
            $result[$formation['id']] = $formation['nom'];
        }
        
        return $result;
    }

    /**
     * Récupère les années disponibles pour les filtres
     */
    public function getAvailableYears(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT DISTINCT YEAR(date_debut) as annee FROM formation WHERE date_debut IS NOT NULL 
                UNION 
                SELECT DISTINCT YEAR(date_fin) as annee FROM formation WHERE date_fin IS NOT NULL
                ORDER BY annee DESC';
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
        
        return array_unique($years);
    }
}