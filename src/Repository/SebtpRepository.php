<?php

namespace App\Repository;

use App\Entity\Sebtp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sebtp>
 */
class SebtpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sebtp::class);
    }

    public function findBySearch(?string $term): array
    {
        if (empty($term)) {
            return $this->findAll();
        }

        $qb = $this->createQueryBuilder('s');
        
        return $qb
            ->where($qb->expr()->like('s.instance', ':search'))
            ->orWhere($qb->expr()->like('s.nomOrganisme', ':search'))
            ->orWhere($qb->expr()->like('s.mandat', ':search'))
            ->orWhere($qb->expr()->like('s.nomRepresentant', ':search'))
            ->orWhere($qb->expr()->like('s.observation', ':search'))
            ->setParameter('search', '%' . $term . '%')
            ->orderBy('s.mandat', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les organismes SEBTP pour export avec filtres
     */
    public function getForExport(?string $instance = null, ?string $mandat = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('s');

        if ($instance && $instance !== 'tous') {
            $qb->andWhere('s.instance = :instance')
               ->setParameter('instance', $instance);
        }

        if ($mandat && $mandat !== 'tous') {
            $qb->andWhere('s.mandat = :mandat')
               ->setParameter('mandat', $mandat);
        }

        if ($search) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('s.instance', ':search'),
                $qb->expr()->like('s.nomOrganisme', ':search'),
                $qb->expr()->like('s.nomRepresentant', ':search')
            ))
            ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('s.nomOrganisme', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère toutes les instances distinctes pour les filtres
     */
    public function getDistinctInstances(): array
    {
        $results = $this->createQueryBuilder('s')
            ->select('DISTINCT s.instance')
            ->where('s.instance IS NOT NULL')
            ->orderBy('s.instance', 'ASC')
            ->getQuery()
            ->getResult();

        $instances = [];
        foreach ($results as $result) {
            $instances[] = $result['instance'];
        }
        return $instances;
    }

    /**
     * Récupère tous les mandats distincts pour les filtres
     */
    public function getDistinctMandats(): array
    {
        $results = $this->createQueryBuilder('s')
            ->select('DISTINCT s.mandat')
            ->where('s.mandat IS NOT NULL')
            ->orderBy('s.mandat', 'DESC')
            ->getQuery()
            ->getResult();

        $mandats = [];
        foreach ($results as $result) {
            $mandats[] = $result['mandat'];
        }
        return $mandats;
    }

    /**
     * Statistiques des organismes
     */
    public function getStats(): array
    {
        $total = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $instances = $this->createQueryBuilder('s')
            ->select('s.instance, COUNT(s.id) as total')
            ->groupBy('s.instance')
            ->getQuery()
            ->getResult();

        return [
            'total' => $total,
            'instances' => $instances,
        ];
    }
}