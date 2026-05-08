<?php

namespace App\Repository;

use App\Entity\Bareme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BaremeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bareme::class);
    }

    /**
     * Récupère le barème actif pour une catégorie et sous-catégorie
     */
    public function getBaremeActif(string $categorie, ?string $sousCategorie = null, ?\DateTimeInterface $date = null): ?Bareme
    {
        $date = $date ?? new \DateTime();
        
        $qb = $this->createQueryBuilder('b')
            ->where('b.categorie = :categorie')
            ->andWhere('b.actif = true')
            ->andWhere('b.dateDebut <= :date')
            ->setParameter('categorie', $categorie)
            ->setParameter('date', $date);

        if ($sousCategorie) {
            $qb->andWhere('b.sousCategorie = :sousCategorie')
               ->setParameter('sousCategorie', $sousCategorie);
        } else {
            $qb->andWhere('b.sousCategorie IS NULL');
        }

        if ($date) {
            $qb->andWhere('(b.dateFin IS NULL OR b.dateFin >= :date)')
               ->setParameter('date', $date);
        }

        return $qb->orderBy('b.dateDebut', 'DESC')
                  ->setMaxResults(1)
                  ->getQuery()
                  ->getOneOrNullResult();
    }

    /**
     * Désactive tous les barèmes actifs d'une catégorie
     */
    public function desactiverBaremes(string $categorie, ?string $sousCategorie = null): void
    {
        $qb = $this->createQueryBuilder('b')
            ->update()
            ->set('b.actif', ':actif')
            ->where('b.categorie = :categorie')
            ->setParameter('actif', false)
            ->setParameter('categorie', $categorie);

        if ($sousCategorie) {
            $qb->andWhere('b.sousCategorie = :sousCategorie')
               ->setParameter('sousCategorie', $sousCategorie);
        }

        $qb->getQuery()->execute();
    }

    /**
     * Récupère l'historique des barèmes
     */
    public function getHistorique(string $categorie, ?string $sousCategorie = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.categorie = :categorie')
            ->orderBy('b.dateDebut', 'DESC')
            ->setParameter('categorie', $categorie);

        if ($sousCategorie) {
            $qb->andWhere('b.sousCategorie = :sousCategorie')
               ->setParameter('sousCategorie', $sousCategorie);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère tous les barèmes actifs
     */
    public function getAllActifs(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.actif = true')
            ->orderBy('b.categorie', 'ASC')
            ->addOrderBy('b.sousCategorie', 'ASC')
            ->getQuery()
            ->getResult();
    }
}