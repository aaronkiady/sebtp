<?php

namespace App\Controller;

use App\Entity\Participation;
use App\Entity\ParticipationLigne;
use App\Form\ParticipationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/participation')]
class ParticipationController extends AbstractController
{
    #[Route('/{id}/edit', name: 'app_participation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Participation $participation, EntityManagerInterface $em): Response
    {
        $evenement = $participation->getEvenement();
        
        $form = $this->createForm(ParticipationType::class, $participation);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer le montant total
            $participation->recalculerMontantTotal();
            $em->flush();
            
            $this->addFlash('success', 'Quantités modifiées avec succès !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        
        return $this->render('participation/edit.html.twig', [
            'participation' => $participation,
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }
}