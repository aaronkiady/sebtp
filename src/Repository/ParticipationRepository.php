<?php

namespace App\Repository;

use App\Entity\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    public function getStatsByAdherent(int $adherentId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id) as total')
            ->addSelect('SUM(CASE WHEN p.statutPaiement = :paye THEN 1 ELSE 0 END) as paye')
            ->addSelect('SUM(CASE WHEN p.statutPaiement = :impaye THEN 1 ELSE 0 END) as impaye')
            ->where('p.adherent = :adherentId')
            ->setParameter('adherentId', $adherentId)
            ->setParameter('paye', 'paye')
            ->setParameter('impaye', 'impaye')
            ->getQuery();

        $result = $qb->getSingleResult();

        $total = (int) $result['total'];
        $paye = (int) $result['paye'];
        $impaye = (int) $result['impaye'];

        return [
            'total' => $total,
            'paye' => $paye,
            'impaye' => $impaye,
            'pourcentagePaye' => $total > 0 ? round(($paye / $total) * 100, 2) : 0,
            'pourcentageImpaye' => $total > 0 ? round(($impaye / $total) * 100, 2) : 0,
        ];
    }

    public function getGlobalStats(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id) as total')
            ->addSelect('SUM(CASE WHEN p.statutPaiement = :paye THEN 1 ELSE 0 END) as paye')
            ->addSelect('SUM(CASE WHEN p.statutPaiement = :impaye THEN 1 ELSE 0 END) as impaye')
            ->setParameter('paye', 'paye')
            ->setParameter('impaye', 'impaye')
            ->getQuery();

        $result = $qb->getSingleResult();

        $total = (int) $result['total'];
        $paye = (int) $result['paye'];
        $impaye = (int) $result['impaye'];

        return [
            'total' => $total,
            'paye' => $paye,
            'impaye' => $impaye,
            'pourcentagePaye' => $total > 0 ? round(($paye / $total) * 100, 2) : 0,
            'pourcentageImpaye' => $total > 0 ? round(($impaye / $total) * 100, 2) : 0,
        ];
    }

     public function findParticipationsByAdherent(int $adherentId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.adherent', 'a')
            ->where('p.adherent = :adherentId')
            ->andWhere('a.statut != :statutRadie')
            ->setParameter('adherentId', $adherentId)
            ->setParameter('statutRadie', 'radie')
            ->orderBy('p.id', 'DESC');

        return $qb->getQuery()->getResult();
    }
}