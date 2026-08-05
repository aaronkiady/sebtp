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

     /**
     * Recherche des cotisations avec filtres
     */
    public function search(?string $search, ?string $statut, ?string $periode, ?string $statutAdherent = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.adherent', 'a');

        // Filtre par recherche (nom, email, téléphone)
        if ($search) {
            $qb->andWhere('a.nom LIKE :search OR a.email LIKE :search OR a.numero LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtre par statut de paiement
        if ($statut) {
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statut);
        }

        // Filtre par période
        if ($periode) {
            $qb->andWhere('c.periode = :periode')
               ->setParameter('periode', $periode);
        }

        // NOUVEAU : Filtre par statut de l'adhérent
        if ($statutAdherent) {
            $qb->andWhere('a.statut = :statutAdherent')
               ->setParameter('statutAdherent', $statutAdherent);
        }

        $qb->orderBy('c.periode', 'DESC')
           ->addOrderBy('a.nom', 'ASC');

        return $qb->getQuery()->getResult();
    }
}