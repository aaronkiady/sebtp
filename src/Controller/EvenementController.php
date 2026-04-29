<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Liste;
use App\Entity\Participation;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenement')]
final class EvenementController extends AbstractController
{
    #[Route(name: 'app_evenement_index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $repo): Response
    {
        $searchTerm = $request->query->get('q');

        return $this->render('evenement/index.html.twig', [
            'evenements' => $repo->findBySearch($searchTerm)
        ]);
    }

    #[Route('/historique/{adherent_id}', name: 'app_evenement_history')]
    public function history(int $adherent_id, EntityManagerInterface $em): Response
    {
        $adherent = $em->getRepository(Liste::class)->find($adherent_id);
        
        if (!$adherent) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }

        return $this->render('evenement/history.html.twig', [
            'adherent' => $adherent,
            'participations' => $adherent->getParticipations(),
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Les participants sont optionnels, on peut en créer sans
            $participants = $form->get('participantsTemp')->getData();
            
            if (!empty($participants)) {
                foreach ($participants as $liste) {
                    $participation = new Participation();
                    $participation->setAdherent($liste);
                    $participation->setEvenement($evenement);
                    $participation->setStatutPaiement('impaye');
                    $em->persist($participation);
                }
            }

            $em->persist($evenement);
            $em->flush();

            return $this->redirectToRoute('app_evenement_index');
        }

        return $this->render('evenement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/gerer-participants', name: 'app_evenement_gerer_participants', methods: ['GET', 'POST'])]
    public function gererParticipants(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $participantsActuels = [];
        foreach ($evenement->getParticipations() as $participation) {
            $participantsActuels[] = $participation->getAdherent();
        }

        $form = $this->createFormBuilder()
            ->add('participants', EntityType::class, [
                'class' => Liste::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'data' => $participantsActuels,
                'label' => 'Sélectionnez les participants',
                'attr' => ['class' => 'participants-checkbox-list']
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nouveauxParticipants = $form->get('participants')->getData();
            
            // Supprimer les participants qui ne sont plus sélectionnés
            foreach ($evenement->getParticipations() as $participation) {
                if (!in_array($participation->getAdherent(), $nouveauxParticipants)) {
                    $em->remove($participation);
                }
            }
            
            // Ajouter les nouveaux participants
            foreach ($nouveauxParticipants as $participant) {
                $existe = false;
                foreach ($evenement->getParticipations() as $participation) {
                    if ($participation->getAdherent() === $participant) {
                        $existe = true;
                        break;
                    }
                }
                
                if (!$existe) {
                    $participation = new Participation();
                    $participation->setAdherent($participant);
                    $participation->setEvenement($evenement);
                    $participation->setStatutPaiement('impaye');
                    $em->persist($participation);
                }
            }
            
            $em->flush();
            
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        return $this->render('evenement/gerer_participants.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/ajouter-participant', name: 'app_evenement_ajouter_participant', methods: ['GET', 'POST'])]
    public function ajouterParticipant(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $form = $this->createFormBuilder()
            ->add('participant', EntityType::class, [
                'class' => Liste::class,
                'choice_label' => 'nom',
                'multiple' => false,
                'expanded' => false,
                'required' => true,
                'label' => 'Ajouter un participant',
                'attr' => ['class' => 'form-control custom-input']
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participant = $form->get('participant')->getData();
            
            // Vérifier si le participant existe déjà
            $existe = false;
            foreach ($evenement->getParticipations() as $participation) {
                if ($participation->getAdherent() === $participant) {
                    $existe = true;
                    break;
                }
            }
            
            if (!$existe) {
                $participation = new Participation();
                $participation->setAdherent($participant);
                $participation->setEvenement($evenement);
                $participation->setStatutPaiement('impaye');
                $em->persist($participation);
                $em->flush();
                $this->addFlash('success', 'Participant ajouté avec succès!');
            } else {
                $this->addFlash('warning', 'Ce participant est déjà inscrit à cet événement.');
            }
            
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        return $this->render('evenement/ajouter_participant.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/supprimer-participant/{participation_id}', name: 'app_evenement_supprimer_participant', methods: ['POST'])]
    public function supprimerParticipant(
        Evenement $evenement, 
        int $participation_id, 
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $participation = $em->getRepository(Participation::class)->find($participation_id);
        
        if ($participation && $participation->getEvenement() === $evenement) {
            $token = $request->request->get('_token');
            if ($this->isCsrfTokenValid('delete' . $participation_id, $token)) {
                $em->remove($participation);
                $em->flush();
                $this->addFlash('success', 'Participant retiré avec succès!');
            } else {
                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } else {
            $this->addFlash('error', 'Participant non trouvé.');
        }
        
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $token = $request->getPayload()->getString('_token');
        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $token)) {
            $entityManager->remove($evenement);
            $entityManager->flush();
            $this->addFlash('success', 'Événement supprimé avec succès!');
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/participation/{id}/toggle', name: 'app_participation_toggle', methods: ['GET', 'POST'])]
    public function toggle(Participation $participation, EntityManagerInterface $em): Response
    {
        $newStatus = $participation->getStatutPaiement() === 'paye' ? 'impayé' : 'payé';
        $participation->setStatutPaiement($newStatus);
        $em->flush();

        $this->addFlash('success', 'Statut de paiement mis à jour avec succès!');

        return $this->redirectToRoute('app_evenement_history', [
            'adherent_id' => $participation->getAdherent()->getId()
        ]);
    }
}