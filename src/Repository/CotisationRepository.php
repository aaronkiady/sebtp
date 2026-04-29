<?php

namespace App\Repository;

use App\Entity\Cotisation;
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
}