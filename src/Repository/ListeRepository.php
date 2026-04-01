<?php

namespace App\Repository;

use App\Entity\Liste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Liste>
 */
class ListeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Liste::class);
    }

    //    /**
    //     * @return Liste[] Returns an array of Liste objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('l.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Liste
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

     public function findBySearch(?string $term): array
    {
        $qb = $this->createQueryBuilder('l');
        if ($term) {
            $qb->andWhere('l.nom LIKE :q OR l.email LIKE :q OR l.activite LIKE :q OR l.numero LIKE :q OR l.adresse LIKE :q OR l.statut LIKE :q')
                ->setParameter('q', '%'.$term.'%');
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
    // On ne compte que pour les membres 'actifs'
    $totalActifs = $this->createQueryBuilder('l')
        ->select('count(l.id)')
        ->where('l.statut = :s')
        ->setParameter('s', 'actif')
        ->getQuery()
        ->getSingleScalarResult();

    $paye = $this->createQueryBuilder('l')
        ->select('count(l.id)')
        ->innerJoin('l.cotisations', 'c')
        ->where('l.statut = :statutAdherent')
        ->andWhere('c.periode = :year')
        ->andWhere('c.statut = :statutCotis')
        ->setParameter('statutAdherent', 'actif')
        ->setParameter('year', $year)
        ->setParameter('statutCotis', 'payé')
        ->getQuery()
        ->getSingleScalarResult();

    $impaye = $totalActifs - $paye;

    return [
        'paye' => $paye,
        'impaye' => $impaye > 0 ? $impaye : 0,
        'total' => $totalActifs
    ];
}

}
