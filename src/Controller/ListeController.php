<?php

namespace App\Controller;

use App\Entity\Liste;
use App\Form\ListeType;
use App\Repository\ListeRepository;
use App\Repository\ParticipationRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/adherents')]
final class ListeController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

     #[Route(name: 'app_liste_index', methods: ['GET'])]
    public function index(Request $request, ListeRepository $listeRepository): Response
    {
        $searchTerm = $request->query->get('q');
        $statut = $request->query->get('statut');
        $filiere = $request->query->get('filiere');
        $cotFMTP = $request->query->get('cotFMTP');
        $statutMenmbre = $request->query->get('statutMenmbre');
        $type = $request->query->get('type');
        $anneeAdhesion = $request->query->get('anneeAdhesion');

        // Récupérer les listes pour les filtres
        $filieres = $listeRepository->getDistinctFilieres();
        $statutsMembres = $listeRepository->getDistinctStatutsMembres();
        $adhesionYears = $listeRepository->getAvailableAdhesionYears();

        $listes = $listeRepository->findByFilters(
            $searchTerm,
            $statut,
            $filiere,
            $cotFMTP,
            $statutMenmbre,
            $type,
            $anneeAdhesion
        );

        return $this->render('liste/index.html.twig', [
            'listes' => $listes,
            'searchTerm' => $searchTerm,
            'statut' => $statut,
            'filiere' => $filiere,
            'cotFMTP' => $cotFMTP,
            'statutMenmbre' => $statutMenmbre,
            'type' => $type,
            'anneeAdhesion' => $anneeAdhesion,
            'filieres' => $filieres,
            'statutsMembres' => $statutsMembres,
            'adhesionYears' => $adhesionYears,
        ]);
    }

    #[Route('/new', name: 'app_liste_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $liste = new Liste();
        $form = $this->createForm(ListeType::class, $liste);
        $form->handleRequest($request);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->render('liste/_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($liste);
            $entityManager->flush();

            // Audit log
            $this->auditLogger->logCreate(
                'Liste',
                $liste->getId(),
                $liste->getNom(),
                [
                    'nom' => $liste->getNom(),
                    'email' => $liste->getEmail(),
                    'telephone' => $liste->getNumero(),
                    'statut' => $liste->getStatut(),
                    'activite' => $liste->getActivite(),
                    'filiere' => $liste->getFiliere(),
                    'nb_employes' => $liste->getNbEmployes()
                ]
            );

            return $this->redirectToRoute('app_liste_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('liste/new.html.twig', [
            'liste' => $liste,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_liste_show', methods: ['GET'])]
    public function show(Liste $liste): Response
    {
        return $this->render('liste/show.html.twig', [
            'liste' => $liste,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_liste_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Liste $liste, EntityManagerInterface $entityManager): Response
    {
        $oldData = [
            'nom' => $liste->getNom(),
            'email' => $liste->getEmail(),
            'telephone' => $liste->getNumero(),
            'adresse' => $liste->getAdresse(),
            'statut' => $liste->getStatut(),
            'activite' => $liste->getActivite(),
            'filiere' => $liste->getFiliere(),
            'nb_employes' => $liste->getNbEmployes(),
            'cotFMTP' => $liste->getCotFMTP(),
            'dg' => $liste->getDg(),
            'statut_menmbre' => $liste->getStatutMenmbre(),
        ];

        $form = $this->createForm(ListeType::class, $liste);
        $form->handleRequest($request);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->render('liste/_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $newData = [
                'nom' => $liste->getNom(),
                'email' => $liste->getEmail(),
                'telephone' => $liste->getNumero(),
                'adresse' => $liste->getAdresse(),
                'statut' => $liste->getStatut(),
                'activite' => $liste->getActivite(),
                'filiere' => $liste->getFiliere(),
                'nb_employes' => $liste->getNbEmployes(),
                'cotFMTP' => $liste->getCotFMTP(),
                'dg' => $liste->getDg(),
                'statut_menmbre' => $liste->getStatutMenmbre(),
            ];

            // Audit log
            $this->auditLogger->logUpdate(
                'Liste',
                $liste->getId(),
                $liste->getNom(),
                $oldData,
                $newData
            );

            return $this->redirectToRoute('app_liste_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('liste/edit.html.twig', [
            'liste' => $liste,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_liste_delete', methods: ['POST'])]
    public function delete(Request $request, Liste $liste, EntityManagerInterface $entityManager): Response
    {
        $adherentData = [
            'nom' => $liste->getNom(),
            'email' => $liste->getEmail(),
            'telephone' => $liste->getNumero(),
            'statut' => $liste->getStatut(),
        ];

        if ($this->isCsrfTokenValid('delete' . $liste->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($liste);
            $entityManager->flush();

            // Audit log
            $this->auditLogger->logDelete(
                'Liste',
                $liste->getId(),
                $liste->getNom(),
                $adherentData
            );
        }

        return $this->redirectToRoute('app_liste_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/stats', name: 'app_liste_stats', methods: ['GET'])]
    public function stats(
        int $id,
        ParticipationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $liste = $em->getRepository(Liste::class)->find($id);

        if (!$liste) {
            throw $this->createNotFoundException('Adhérent introuvable');
        }

        $stats = $repo->getStatsByAdherent($id);
        
        $this->addFlash('info', 'Statistiques calculées: Total=' . $stats['total'] . ', Payé=' . $stats['paye'] . ', Impayé=' . $stats['impaye']);

        return $this->render('liste/stats.html.twig', [
            'liste' => $liste,
            'stats' => $stats,
        ]);
    }

    #[Route('/rapport/anciennete', name: 'app_liste_rapport_anciennete', methods: ['GET'])]
    public function rapportAnciennete(ListeRepository $listeRepository): Response
    {
        $ancienneteData = $listeRepository->getAncienneteData();
        $stats = $listeRepository->getAncienneteStats();
        $ancienneteMoyenne = $listeRepository->getAncienneteMoyenne();
        $plusAncien = $listeRepository->getAdherentLePlusAncien();
        $plusRecent = $listeRepository->getAdherentLePlusRecent();

        // Audit log
        $this->auditLogger->logExport(
            'RapportAnciennete',
            sprintf('Export rapport d\'ancienneté - %d adhérents', count($ancienneteData))
        );

        return $this->render('liste/rapport_anciennete.html.twig', [
            'ancienneteData' => $ancienneteData,
            'stats' => $stats,
            'ancienneteMoyenne' => $ancienneteMoyenne,
            'plusAncien' => $plusAncien,
            'plusRecent' => $plusRecent,
            'totalAdherents' => count($ancienneteData),
        ]);
    }
}