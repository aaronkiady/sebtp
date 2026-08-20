<?php

namespace App\Controller;

use App\Entity\Partenariats;
use App\Form\PartenariatsType;
use App\Repository\PartenariatsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/partenariats')]
final class PartenariatsController extends AbstractController
{
    #[Route(name: 'app_partenariats_index', methods: ['GET'])]
    public function index(PartenariatsRepository $partenariatsRepository): Response
    {
        return $this->render('partenariats/index.html.twig', [
            'partenariats' => $partenariatsRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_partenariats_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $partenariat = new Partenariats();
        $form = $this->createForm(PartenariatsType::class, $partenariat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($partenariat);
            $entityManager->flush();

            return $this->redirectToRoute('app_partenariats_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('partenariats/new.html.twig', [
            'partenariat' => $partenariat,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_partenariats_show', methods: ['GET'])]
    public function show(Partenariats $partenariat): Response
    {
        return $this->render('partenariats/show.html.twig', [
            'partenariat' => $partenariat,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_partenariats_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Partenariats $partenariat, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PartenariatsType::class, $partenariat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_partenariats_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('partenariats/edit.html.twig', [
            'partenariat' => $partenariat,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_partenariats_delete', methods: ['POST'])]
    public function delete(Request $request, Partenariats $partenariat, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$partenariat->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($partenariat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_partenariats_index', [], Response::HTTP_SEE_OTHER);
    }
}
