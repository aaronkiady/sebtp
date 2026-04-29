<?php

namespace App\Repository;

use App\Entity\Liste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ListeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Liste::class);
    }

    public function findBySearch(?string $term): array
    {
        $qb = $this->createQueryBuilder('l');
        if ($term) {
            $qb->andWhere('l.nom LIKE :q OR l.email LIKE :q OR l.numero LIKE :q OR l.adresse LIKE :q OR l.statut LIKE :q OR l.activite LIKE :q')
                ->setParameter('q', '%' . $term . '%');
        }
        return $qb->orderBy('l.nom', 'ASC')->getQuery()->getResult();
    }

    public function countByStatut(string $statut): int
    {
        return $this->createQueryBuilder('l')
            ->select('count(l.id)')
            ->where('l.statut = :s')
            ->setParameter('s', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCotisationStats(string $year): array
    {
        // Compter tous les adhérents actifs
        $totalActifs = $this->createQueryBuilder('l')
            ->select('count(l.id)')
            ->where('l.statut = :s')
            ->setParameter('s', 'Actif')
            ->getQuery()
            ->getSingleScalarResult();

        // Compter ceux qui ont payé pour cette année
        $paye = $this->createQueryBuilder('l')
            ->select('count(l.id)')
            ->innerJoin('l.cotisations', 'c')
            ->where('l.statut = :statutAdherent')
            ->andWhere('c.periode = :year')
            ->andWhere('c.statut = :statutCotis')
            ->setParameter('statutAdherent', 'Actif')
            ->setParameter('year', $year)
            ->setParameter('statutCotis', 'paye')
            ->getQuery()
            ->getSingleScalarResult();

        $impaye = $totalActifs - $paye;
        $pourcentagePaye = $totalActifs > 0 ? round(($paye / $totalActifs) * 100, 2) : 0;
        $pourcentageImpaye = $totalActifs > 0 ? round(($impaye / $totalActifs) * 100, 2) : 0;

        return [
            'paye' => $paye,
            'impaye' => $impaye > 0 ? $impaye : 0,
            'total' => $totalActifs,
            'pourcentagePaye' => $pourcentagePaye,
            'pourcentageImpaye' => $pourcentageImpaye,
        ];
    }
}