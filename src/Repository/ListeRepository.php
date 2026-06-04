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
    // D'abord récupérer les adhérents avec les filtres simples
    $qb = $this->createQueryBuilder('l');

    if ($statut && $statut !== 'tous') {
        $qb->andWhere('l.statut = :statut')
           ->setParameter('statut', $statut);
    }

    if ($statutMenmbre && $statutMenmbre !== 'tous') {
        $qb->andWhere('l.statutMenmbre = :statutMenmbre')
           ->setParameter('statutMenmbre', $statutMenmbre);
    }

    // Ne pas filtrer par filière ici car c'est un JSON
    // On filtrera après en PHP

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

    $results = $qb->orderBy('l.nom', 'ASC')
                  ->getQuery()
                  ->getResult();
    
    // Filtrer par filière en PHP (car c'est un tableau JSON)
    if ($filiere && $filiere !== 'tous') {
        $searchFiliere = trim($filiere);
        $filteredResults = [];
        foreach ($results as $adherent) {
            $filieres = $adherent->getFiliere();
            if (is_array($filieres) && in_array($searchFiliere, $filieres)) {
                $filteredResults[] = $adherent;
            }
        }
        $results = $filteredResults;
    }
    
    return $results;
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
        $conn = $this->getEntityManager()->getConnection();
        
        // Récupérer toutes les valeurs uniques du tableau JSON
        $sql = "SELECT DISTINCT TRIM(JSON_UNQUOTE(JSON_EXTRACT(filiere, '$[0]'))) as filiere 
                FROM liste 
                WHERE filiere IS NOT NULL AND JSON_LENGTH(filiere) > 0
                
                UNION
                
                SELECT DISTINCT TRIM(JSON_UNQUOTE(JSON_EXTRACT(filiere, '$[1]'))) as filiere 
                FROM liste 
                WHERE filiere IS NOT NULL AND JSON_LENGTH(filiere) > 1
                
                UNION
                
                SELECT DISTINCT TRIM(JSON_UNQUOTE(JSON_EXTRACT(filiere, '$[2]'))) as filiere 
                FROM liste 
                WHERE filiere IS NOT NULL AND JSON_LENGTH(filiere) > 2
                
                HAVING filiere IS NOT NULL AND filiere != ''";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $results = $result->fetchAllAssociative();
        
        $filieres = [];
        foreach ($results as $row) {
            $filiere = trim($row['filiere']);
            if (!empty($filiere) && !in_array($filiere, $filieres)) {
                $filieres[] = $filiere;
            }
        }
        
        // Nettoyer : remplacer "BTP" par "BTP / Construction"
        $cleanFilieres = [];
        foreach ($filieres as $fil) {
            if ($fil === 'BTP' || $fil === 'BTP/Construction') {
                if (!in_array('BTP / Construction', $cleanFilieres)) {
                    $cleanFilieres[] = 'BTP / Construction';
                }
            } else {
                $cleanFilieres[] = $fil;
            }
        }
        
        sort($cleanFilieres);
        return $cleanFilieres;
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

    /**
     * Recherche avec filtres avancés
     */
    public function findByFilters(
        ?string $searchTerm = null,
        ?string $statut = null,
        ?string $filiere = null,
        ?string $cotFMTP = null,
        ?string $statutMenmbre = null,
        ?string $type = null,
        ?string $anneeAdhesion = null
    ): array {
        $qb = $this->createQueryBuilder('l');

        // Recherche texte
        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('l.nom', ':q'),
                    $qb->expr()->like('l.email', ':q'),
                    $qb->expr()->like('l.numero', ':q'),
                    $qb->expr()->like('l.adresse', ':q'),
                    $qb->expr()->like('l.activite', ':q')
                )
            )
            ->setParameter('q', '%' . $searchTerm . '%');
        }

        // Statut
        if ($statut && $statut !== 'tous') {
            $qb->andWhere('l.statut = :statut')
            ->setParameter('statut', $statut);
        }

        // Filière JSON
       
if (!empty($filiere) && $filiere !== 'tous') {

    $allResults = $qb->getQuery()->getResult();

    $filteredIds = [];

    foreach ($allResults as $adherent) {

        $filieres = $adherent->getFiliere();

        if (!is_array($filieres)) {
            continue;
        }

        foreach ($filieres as $fil) {

            if (trim($fil) === trim($filiere)) {
                $filteredIds[] = $adherent->getId();
                break;
            }
        }
    }

    if (empty($filteredIds)) {
        return [];
    }

    $qb = $this->createQueryBuilder('l');
    $qb->andWhere('l.id IN (:ids)')
       ->setParameter('ids', $filteredIds);
}
        // Cotisation FMTP
        if ($cotFMTP && $cotFMTP !== 'tous') {
            $qb->andWhere('l.cotFMTP = :cotFMTP')
            ->setParameter('cotFMTP', $cotFMTP);
        }

        // Statut membre
        if ($statutMenmbre && $statutMenmbre !== 'tous') {
            $qb->andWhere('l.statutMenmbre = :statutMenmbre')
            ->setParameter('statutMenmbre', $statutMenmbre);
        }

        // Type
        if ($type && $type !== 'tous') {
            $qb->andWhere('l.type = :type')
            ->setParameter('type', $type);
        }

        // Année adhésion
        if ($anneeAdhesion && $anneeAdhesion !== 'tous') {
            $qb->andWhere(
                '(YEAR(l.validationBureau) = :annee OR YEAR(l.validationAG) = :annee)'
            )
            ->setParameter('annee', (int) $anneeAdhesion);
        }

        return $qb->orderBy('l.nom', 'ASC')
                ->getQuery()
                ->getResult();
    }

     /**
     * Calcule l'ancienneté de chaque adhérent
     */
    public function getAncienneteData(): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l.id, l.nom, l.validationBureau, l.validationAG, l.dateCreation')
            ->where('l.statut = :statut')
            ->setParameter('statut', 'actif')
            ->getQuery();

        $adherents = $qb->getResult();
        $result = [];

        foreach ($adherents as $adherent) {
            $dateAdhesion = $this->getDateAdhesion($adherent);
            if ($dateAdhesion) {
                $anciennete = $this->calculateAnciennete($dateAdhesion);
                $result[] = [
                    'id' => $adherent['id'],
                    'nom' => $adherent['nom'],
                    'date_adhesion' => $dateAdhesion->format('d/m/Y'),
                    'anciennete_annees' => $anciennete['annees'],
                    'anciennete_mois' => $anciennete['mois'],
                    'anciennete_jours' => $anciennete['jours'],
                    'tranche' => $this->getTrancheAnciennete($anciennete['annees']),
                ];
            }
        }

        return $result;
    }

    /**
     * Récupère la date d'adhésion (priorité: validationBureau, validationAG, dateCreation)
     */
    private function getDateAdhesion(array $adherent): ?\DateTimeInterface
    {
        if (!empty($adherent['validationBureau'])) {
            return $adherent['validationBureau'];
        }
        if (!empty($adherent['validationAG'])) {
            return $adherent['validationAG'];
        }
        if (!empty($adherent['dateCreation'])) {
            return $adherent['dateCreation'];
        }
        return null;
    }

    /**
     * Calcule l'ancienneté en années, mois et jours
     */
    private function calculateAnciennete(\DateTimeInterface $dateAdhesion): array
    {
        $now = new \DateTime();
        $diff = $now->diff($dateAdhesion);

        return [
            'annees' => $diff->y,
            'mois' => $diff->m,
            'jours' => $diff->d,
        ];
    }

    /**
     * Détermine la tranche d'ancienneté
     */
    private function getTrancheAnciennete(int $annees): string
    {
        if ($annees < 1) {
            return 'Moins d\'1 an';
        } elseif ($annees >= 1 && $annees < 3) {
            return '1 à 3 ans';
        } elseif ($annees >= 3 && $annees < 5) {
            return '3 à 5 ans';
        } elseif ($annees >= 5 && $annees < 10) {
            return '5 à 10 ans';
        } else {
            return 'Plus de 10 ans';
        }
    }

    /**
     * Récupère les statistiques d'ancienneté par tranche
     */
    public function getAncienneteStats(): array
    {
        $adherents = $this->getAncienneteData();
        $stats = [
            'Moins d\'1 an' => 0,
            '1 à 3 ans' => 0,
            '3 à 5 ans' => 0,
            '5 à 10 ans' => 0,
            'Plus de 10 ans' => 0,
        ];

        foreach ($adherents as $adherent) {
            $tranche = $adherent['tranche'];
            if (isset($stats[$tranche])) {
                $stats[$tranche]++;
            }
        }

        return $stats;
    }

    /**
     * Récupère l'ancienneté moyenne en années
     */
    public function getAncienneteMoyenne(): float
    {
        $adherents = $this->getAncienneteData();
        if (empty($adherents)) {
            return 0;
        }

        $totalAnnees = 0;
        foreach ($adherents as $adherent) {
            $totalAnnees += $adherent['anciennete_annees'];
        }

        return round($totalAnnees / count($adherents), 2);
    }

    /**
     * Récupère l'adhérent le plus ancien
     */
    public function getAdherentLePlusAncien(): ?array
    {
        $adherents = $this->getAncienneteData();
        if (empty($adherents)) {
            return null;
        }

        usort($adherents, function ($a, $b) {
            return $b['anciennete_annees'] <=> $a['anciennete_annees'];
        });

        return $adherents[0];
    }

    /**
     * Récupère l'adhérent le plus récent
     */
    public function getAdherentLePlusRecent(): ?array
    {
        $adherents = $this->getAncienneteData();
        if (empty($adherents)) {
            return null;
        }

        usort($adherents, function ($a, $b) {
            return $a['anciennete_annees'] <=> $b['anciennete_annees'];
        });

        return $adherents[0];
    }

    public function getAvailableAdhesionYears(): array
{
    $conn = $this->getEntityManager()->getConnection();
    $sql = 'SELECT DISTINCT YEAR(validation_bureau) as annee FROM liste WHERE validation_bureau IS NOT NULL
            UNION
            SELECT DISTINCT YEAR(validation_ag) as annee FROM liste WHERE validation_ag IS NOT NULL
            ORDER BY annee DESC';

    $stmt = $conn->prepare($sql);
    $result = $stmt->executeQuery();
    $results = $result->fetchAllAssociative();

    $years = [];
    foreach ($results as $row) {
        if ($row['annee']) {
            $years[] = (string) $row['annee'];
        }
    }

    if (empty($years)) {
        $years[] = date('Y');
    }

    return $years;
}
}