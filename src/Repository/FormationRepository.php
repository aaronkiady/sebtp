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
}