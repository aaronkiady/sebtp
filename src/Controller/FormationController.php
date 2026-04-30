<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Liste;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/formation')]
final class FormationController extends AbstractController
{
    #[Route(name: 'app_formation_index', methods: ['GET'])]
    public function index(Request $request, FormationRepository $formationRepository): Response
    {
        $searchTerm = $request->query->get('q');

        return $this->render('formation/index.html.twig', [
            'formations' => $formationRepository->findBySearch($searchTerm),
        ]);
    }

    #[Route('/historique/{adherent_id}', name: 'app_formation_history', methods: ['GET'])]
    public function history(int $adherent_id, EntityManagerInterface $entityManager, Request $request): Response
    {
        $adherent = $entityManager->getRepository(Liste::class)->find($adherent_id);

        if (!$adherent) {
            throw $this->createNotFoundException("Adhérent non trouvé");
        }

        $searchTerm = $request->query->get('q');
        $formations = $adherent->getFormations();

        if ($searchTerm) {
            $formations = $formations->filter(function(Formation $f) use ($searchTerm) {
                return stripos($f->getNom(), $searchTerm) !== false || 
                       stripos($f->getType(), $searchTerm) !== false;
            });
        }

        return $this->render('formation/history.html.twig', [
            'adherent' => $adherent,
            'formations' => $formations,
        ]);
    }

    #[Route('/new', name: 'app_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participantsDetails = [];
            $participants = $formation->getParticipants();
            
            foreach ($participants as $participant) {
                $detailKey = 'participant_detail_' . $participant->getId();
                $detail = $request->request->get($detailKey);
                if ($detail) {
                    $participantsDetails[$participant->getId()] = $detail;
                }
            }
            
            $formation->setParticipantsDetails($participantsDetails);
            
            $entityManager->persist($formation);
            $entityManager->flush();

            $this->addFlash('success', 'Formation créée avec succès!');
            return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('formation/new.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_formation_show', methods: ['GET'])]
    public function show(Formation $formation): Response
    {
        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
        ]);
    }

    #[Route('/{id}/participants', name: 'app_formation_show_participants', methods: ['GET'])]
    public function showParticipants(Formation $formation): Response
    {
        return $this->render('liste/show_formation.html.twig', [
            'formation' => $formation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participantsDetails = [];
            $participants = $formation->getParticipants();
            
            foreach ($participants as $participant) {
                $detailKey = 'participant_detail_' . $participant->getId();
                $detail = $request->request->get($detailKey);
                if ($detail) {
                    $participantsDetails[$participant->getId()] = $detail;
                }
            }
            
            $formation->setParticipantsDetails($participantsDetails);
            
            $entityManager->flush();

            $this->addFlash('success', 'Formation modifiée avec succès!');
            return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_formation_delete', methods: ['POST'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $formation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($formation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
    }
}