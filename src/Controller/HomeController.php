<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\FormationRepository;
use App\Repository\ListeRepository;
use App\Repository\EvenementRepository;


final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
public function index(
    ListeRepository $listeRepo, 
    FormationRepository $formationRepo, 
    EvenementRepository $evenementRepo
): Response {

    $anneeN = date('Y');
    $anneeN1 = date('Y') - 1;

    $statsN = $listeRepo->getCotisationStats($anneeN);
    $statsN1 = $listeRepo->getCotisationStats($anneeN1);

    // Calcul pour les événements (Projets payants)
    // Ici on compte le nombre d'événements ayant un montant > 0
    $totalEvents = $evenementRepo->count([]);
    // Note: Si tu n'as pas de champ "statut" sur l'événement lui-même, 
    // on peut simuler ou compter ceux qui ont un commentaire "terminé" par exemple.
    return $this->render('home/index.html.twig', [
        // Adhérents
        'nb_actifs'   => $listeRepo->countByStatut('Actif'),
        'nb_inactifs' => $listeRepo->countByStatut('Inactif'),
        'nb_radies'   => $listeRepo->countByStatut('Radié'),
        'nb_demande'   => $listeRepo->countByStatut('Demande'),
        
        // Totaux simples
        'total_formations' => $formationRepo->count([]),
        'total_evenements' => $evenementRepo->count([]),

        'nb_demandes' => $listeRepo->countByStatut('demande'),
        'statsN' => $statsN,
        'statsN1' => $statsN1,
    ]);
}
}
