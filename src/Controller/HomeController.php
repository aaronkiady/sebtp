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
    return $this->render('home/index.html.twig', [
        // Adhérents
        'nb_actifs'   => $listeRepo->countByStatut('Actif'),
        'nb_inactifs' => $listeRepo->countByStatut('Inactif'),
        'nb_radies'   => $listeRepo->countByStatut('Radié'),
        
        // Totaux simples
        'total_formations' => $formationRepo->count([]),
        'total_evenements' => $evenementRepo->count([]),
    ]);
}
}
