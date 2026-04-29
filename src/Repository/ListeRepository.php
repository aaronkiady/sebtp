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
        $totalActifs = $this->createQueryBuilder('l')
            ->select('count(l.id)')
            ->where('l.statut = :s')
            ->setParameter('s', 'Actif')
            ->getQuery()
            ->getSingleScalarResult();

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

    /**
     * Récupère les cotisations pour l'export Excel
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
            ->addSelect('c.montantPaye as cotisation_montant_paye')
            ->addSelect('c.statut as cotisation_statut')
            ->addSelect('c.reference as cotisation_reference')
            ->addSelect('c.observation as cotisation_observation')
            ->addSelect('c.datePaiement as cotisation_date_paiement')
            ->addSelect('c.modePaiement as cotisation_mode_paiement')
            ->leftJoin('l.cotisations', 'c');

        if ($annee && $annee !== 'tous' && $annee !== '') {
            $qb->andWhere('c.periode = :annee')
               ->setParameter('annee', $annee);
        }

        if ($statut && $statut !== 'tous') {
            $statutValue = $statut === 'paye' ? 'paye' : 'impaye';
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statutValue);
        }

        if ($statutAdherent && $statutAdherent !== 'tous') {
            $qb->andWhere('l.statut = :statutAdherent')
               ->setParameter('statutAdherent', $statutAdherent);
        }

        return $qb->orderBy('l.nom', 'ASC')
                  ->addOrderBy('c.periode', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les adhérents pour export avec filtres
     */
    public function getForExport(
        ?string $statut = null,
        ?string $statutMenmbre = null,
        ?string $filiere = null,
        ?string $activite = null,
        ?string $search = null
    ): array {
        $qb = $this->createQueryBuilder('l');

        if ($statut && $statut !== 'tous') {
            $qb->andWhere('l.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($statutMenmbre && $statutMenmbre !== 'tous') {
            $qb->andWhere('l.statutMenmbre = :statutMenmbre')
               ->setParameter('statutMenmbre', $statutMenmbre);
        }

        if ($filiere && $filiere !== 'tous') {
            $qb->andWhere('l.filiere = :filiere')
               ->setParameter('filiere', $filiere);
        }

        if ($activite && $activite !== 'tous') {
            $qb->andWhere('l.activite = :activite')
               ->setParameter('activite', $activite);
        }

        if ($search) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('l.nom', ':search'),
                $qb->expr()->like('l.email', ':search'),
                $qb->expr()->like('l.numero', ':search'),
                $qb->expr()->like('l.activite', ':search')
            ))
            ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('l.nom', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère tous les statuts distincts pour les filtres
     */
    public function getDistinctStatuts(): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('DISTINCT l.statut')
            ->where('l.statut IS NOT NULL')
            ->orderBy('l.statut', 'ASC')
            ->getQuery()
            ->getResult();

        $statuts = [];
        foreach ($results as $result) {
            if ($result['statut']) {
                $statuts[] = $result['statut'];
            }
        }
        return $statuts;
    }

    /**
     * Récupère tous les statuts membres distincts pour les filtres
     */
    public function getDistinctStatutsMembres(): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('DISTINCT l.statutMenmbre')
            ->where('l.statutMenmbre IS NOT NULL')
            ->orderBy('l.statutMenmbre', 'ASC')
            ->getQuery()
            ->getResult();

        $statuts = [];
        foreach ($results as $result) {
            if ($result['statutMenmbre']) {
                $statuts[] = $result['statutMenmbre'];
            }
        }
        return $statuts;
    }

    /**
     * Récupère toutes les filières distinctes pour les filtres
     */
    public function getDistinctFilieres(): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('DISTINCT l.filiere')
            ->where('l.filiere IS NOT NULL')
            ->orderBy('l.filiere', 'ASC')
            ->getQuery()
            ->getResult();

        $filieres = [];
        foreach ($results as $result) {
            if ($result['filiere']) {
                $filieres[] = $result['filiere'];
            }
        }
        return $filieres;
    }

    /**
     * Récupère toutes les activités distinctes pour les filtres
     */
    public function getDistinctActivites(): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('DISTINCT l.activite')
            ->where('l.activite IS NOT NULL')
            ->orderBy('l.activite', 'ASC')
            ->getQuery()
            ->getResult();

        $activites = [];
        foreach ($results as $result) {
            if ($result['activite']) {
                $activites[] = $result['activite'];
            }
        }
        return $activites;
    }

    /**
     * Statistiques des adhérents
     */
    public function getStats(): array
    {
        $total = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $statuts = $this->createQueryBuilder('l')
            ->select('l.statut, COUNT(l.id) as total')
            ->groupBy('l.statut')
            ->getQuery()
            ->getResult();

        return [
            'total' => $total,
            'statuts' => $statuts,
        ];
    }
}