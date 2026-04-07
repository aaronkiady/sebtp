<?php

namespace App\Controller;

use App\Entity\Liste;
use App\Form\ListeType;
use App\Repository\ListeRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/liste')]
final class ListeController extends AbstractController
{
    #[Route(name: 'app_liste_index', methods: ['GET'])]
    public function index(Request $request, ListeRepository $listeRepository): Response
    {
        $searchTerm = $request->query->get('q');
        return $this->render('liste/index.html.twig', [
            'listes' => $listeRepository->findBySearch($searchTerm),
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
        $form = $this->createForm(ListeType::class, $liste);
        $form->handleRequest($request);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->render('liste/_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

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
        if ($this->isCsrfTokenValid('delete' . $liste->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($liste);
            $entityManager->flush();
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
        
        // Debug: afficher les stats dans les logs
        $this->addFlash('info', 'Statistiques calculées: Total=' . $stats['total'] . ', Payé=' . $stats['paye'] . ', Impayé=' . $stats['impaye']);

        return $this->render('liste/stats.html.twig', [
            'liste' => $liste,
            'stats' => $stats,
        ]);
    }
}