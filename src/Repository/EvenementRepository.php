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
}