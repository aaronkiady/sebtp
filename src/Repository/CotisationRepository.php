<?php

namespace App\Repository;

use App\Entity\Cotisation;
use App\Entity\Liste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CotisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cotisation::class);
    }

    public function getTotalPayeByAdherent(int $adherentId): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.montantPaye) as total')
            ->where('c.adherent = :adherentId')
            ->setParameter('adherentId', $adherentId)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (float) ($result ?? 0);
    }

    public function getCotisationByAdherentAndPeriode(int $adherentId, string $periode): ?Cotisation
    {
        return $this->createQueryBuilder('c')
            ->where('c.adherent = :adherentId')
            ->andWhere('c.periode = :periode')
            ->setParameter('adherentId', $adherentId)
            ->setParameter('periode', $periode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function search(?string $term = null, ?string $statut = null, ?string $periode = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.adherent', 'a')
            ->addSelect('a');

        // Recherche par terme
        if ($term) {
            $qb->andWhere('a.nom LIKE :term')
               ->setParameter('term', '%' . $term . '%');
        }

        // Filtre par statut
        if ($statut && $statut !== '') {
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statut);
        }

        // Filtre par période
        if ($periode && $periode !== '') {
            $qb->andWhere('c.periode = :periode')
               ->setParameter('periode', $periode);
        }

        return $qb->orderBy('c.periode', 'DESC')
                  ->addOrderBy('a.nom', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}