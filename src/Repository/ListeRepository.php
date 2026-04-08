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

    /**
     * Récupère les cotisations avec les informations adhérent pour export
     */
    public function getCotisationsForExport(?string $annee = null, ?string $statut = null, ?string $statutAdherent = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l.nom as adherent_nom')
            ->addSelect('l.email as adherent_email')
            ->addSelect('l.numero as adherent_telephone')
            ->addSelect('l.adresse as adherent_adresse')
            ->addSelect('c.id as cotisation_id')
            ->addSelect('c.periode as cotisation_periode')
            ->addSelect('c.montant as cotisation_montant')
            ->addSelect('c.statut as cotisation_statut')
            ->addSelect('c.reference as cotisation_reference')
            ->addSelect('c.observation as cotisation_observation')
            ->addSelect('c.datePaiement as cotisation_date_paiement')
            ->addSelect('c.modePaiement as cotisation_mode_paiement')
            ->leftJoin('l.cotisations', 'c');

        // Filtre par année
        if ($annee && $annee !== 'tous') {
            $qb->andWhere('c.periode = :annee')
               ->setParameter('annee', $annee);
        }

        // Filtre par statut de cotisation
        if ($statut && $statut !== 'tous') {
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statut === 'paye' ? 'payé' : 'impayé');
        }

        // Filtre par statut d'adhérent
        if ($statutAdherent && $statutAdherent !== 'tous') {
            $qb->andWhere('l.statut = :statutAdherent')
               ->setParameter('statutAdherent', $statutAdherent);
        }

        // Inclure aussi les adhérents sans cotisation si demande
        if ($statut === 'impaye' || ($statut === 'tous' && $annee)) {
            // Garder les adhérents sans cotisation pour l'année concernée
        }

        return $qb->orderBy('l.nom', 'ASC')
                  ->addOrderBy('c.periode', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les années disponibles des cotisations
     */
    public function getAvailableCotisationYears(): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('DISTINCT c.periode')
            ->innerJoin('l.cotisations', 'c')
            ->where('c.periode IS NOT NULL')
            ->orderBy('c.periode', 'DESC')
            ->getQuery();

        $results = $qb->getResult();
        $years = [];
        foreach ($results as $result) {
            $years[] = $result['periode'];
        }
        return $years;
    }
}