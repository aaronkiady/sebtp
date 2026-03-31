<?php

namespace App\Controller;

use App\Entity\Cotisation;
use App\Entity\Liste;
use App\Form\CotisationType;
use App\Repository\CotisationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cotisation')]
final class CotisationController extends AbstractController
{
    #[Route(name: 'app_cotisation_index', methods: ['GET'])]
    public function index(CotisationRepository $cotisationRepository): Response
    {
        return $this->render('cotisation/index.html.twig', [
            'cotisations' => $cotisationRepository->findAll(),
        ]);
    }

    #[Route('/new/{adherent_id}', name: 'app_cotisation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, int $adherent_id, EntityManagerInterface $entityManager): Response
    {
        $adherent = $entityManager->getRepository(Liste::class)->find($adherent_id);
        
        if (!$adherent) {
            throw $this->createNotFoundException("Adhérent non trouvé");
        }

        $cotisation = new Cotisation();
        $cotisation->setAdherent($adherent);

        $form = $this->createForm(CotisationType::class, $cotisation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($cotisation);
            $entityManager->flush();

            return $this->redirectToRoute('app_liste_show', ['id' => $adherent->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('cotisation/new.html.twig', [
            'cotisation' => $cotisation,
            'adherent' => $adherent,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_cotisation_show', methods: ['GET'])]
    public function show(Cotisation $cotisation): Response
    {
        return $this->render('cotisation/show.html.twig', [
            'cotisation' => $cotisation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_cotisation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Cotisation $cotisation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CotisationType::class, $cotisation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_liste_show', ['id' => $cotisation->getAdherent()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('cotisation/edit.html.twig', [
            'cotisation' => $cotisation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_cotisation_delete', methods: ['POST'])]
    public function delete(Request $request, Cotisation $cotisation, EntityManagerInterface $entityManager): Response
    {
        $adherentId = $cotisation->getAdherent()->getId();
        if ($this->isCsrfTokenValid('delete'.$cotisation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($cotisation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_liste_show', ['id' => $adherentId], Response::HTTP_SEE_OTHER);
    }

    #[Route('/historique/{adherent_id}', name: 'app_cotisation_history', methods: ['GET'])]
    public function history(int $adherent_id, EntityManagerInterface $entityManager): Response
    {
        $adherent = $entityManager->getRepository(Liste::class)->find($adherent_id);

        if (!$adherent) {
            throw $this->createNotFoundException("Adhérent non trouvé");
        }

        // On récupère les cotisations de cet adhérent uniquement
        $cotisations = $adherent->getCotisations();

        return $this->render('cotisation/history.html.twig', [
            'adherent' => $adherent,
            'cotisations' => $cotisations,
        ]);
    }
}