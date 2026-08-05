<?php

namespace App\Controller;

use App\Entity\Dirigeant;
use App\Form\DirigeantType;
use App\Repository\DirigeantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dirigeant')]
final class DirigeantController extends AbstractController
{
    #[Route(name: 'app_dirigeant_index', methods: ['GET'])]
    public function index(DirigeantRepository $dirigeantRepository): Response
    {
        return $this->render('dirigeant/index.html.twig', [
            'dirigeants' => $dirigeantRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_dirigeant_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dirigeant = new Dirigeant();
        $form = $this->createForm(DirigeantType::class, $dirigeant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($dirigeant);
            $entityManager->flush();

            return $this->redirectToRoute('app_dirigeant_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dirigeant/new.html.twig', [
            'dirigeant' => $dirigeant,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dirigeant_show', methods: ['GET'])]
    public function show(Dirigeant $dirigeant): Response
    {
        return $this->render('dirigeant/show.html.twig', [
            'dirigeant' => $dirigeant,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dirigeant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Dirigeant $dirigeant, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DirigeantType::class, $dirigeant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de la signature
            $signatureFile = $form->get('signatureFile')->getData();
            if ($signatureFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/images/';
                $originalFilename = pathinfo($signatureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = 'signature_' . uniqid() . '.' . $signatureFile->guessExtension();
                $signatureFile->move($uploadsDir, $newFilename);
                
                $dirigeant->setSignature($newFilename);
            }

            $em->flush();

            $this->addFlash('success', 'Dirigeant modifié avec succès !');
            return $this->redirectToRoute('app_dirigeant_index');
        }

        return $this->render('dirigeant/edit.html.twig', [
            'form' => $form->createView(),
            'dirigeant' => $dirigeant,
        ]);
    }

    #[Route('/{id}', name: 'app_dirigeant_delete', methods: ['POST'])]
    public function delete(Request $request, Dirigeant $dirigeant, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$dirigeant->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($dirigeant);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_dirigeant_index', [], Response::HTTP_SEE_OTHER);
    }
}
