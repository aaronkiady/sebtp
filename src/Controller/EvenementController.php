<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Liste;
use App\Entity\Participation;
use App\Entity\PaiementEvenement;
use App\Form\EvenementType;
use App\Form\PaiementEvenementType;
use App\Repository\EvenementRepository;
use App\Service\AuditLogger;
use App\Service\DocumentGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

#[Route('/evenement')]
final class EvenementController extends AbstractController
{
    private AuditLogger $auditLogger;
    private DocumentGenerator $documentGenerator;

    public function __construct(AuditLogger $auditLogger, DocumentGenerator $documentGenerator)
    {
        $this->auditLogger = $auditLogger;
        $this->documentGenerator = $documentGenerator;
    }

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

        if ($adherent->getStatut() === 'radie') {
            $participations = [];
            $this->addFlash('info', 'Cet adhérent est radié. Ses participations aux événements ne sont plus affichées.');
        } else {
            $participations = $adherent->getParticipations();
        }

        return $this->render('evenement/history.html.twig', [
            'adherent' => $adherent,
            'participations' => $participations,
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
           // $participants = $form->get('participantsTemp')->getData();
            
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

            $this->auditLogger->logCreate(
                'Evenement',
                $evenement->getId(),
                $evenement->getNom(),
                [
                    'nom' => $evenement->getNom(),
                    'date' => $evenement->getDate()?->format('Y-m-d'),
                    'montant' => $evenement->getMontant(),
                    'nb_participants' => count($participants ?? [])
                ]
            );

            return $this->redirectToRoute('app_evenement_index');
        }

        return $this->render('evenement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        $participationsFiltrees = $evenement->getParticipations()->filter(function($participation) {
            $adherent = $participation->getAdherent();
            return $adherent && $adherent->getStatut() !== 'radie';
        });

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
            'participationsFiltrees' => $participationsFiltrees,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $oldData = [
            'nom' => $evenement->getNom(),
            'date' => $evenement->getDate()?->format('Y-m-d'),
            'montant' => $evenement->getMontant(),
            'commentaire' => $evenement->getCommentaire(),
        ];

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $newData = [
                'nom' => $evenement->getNom(),
                'date' => $evenement->getDate()?->format('Y-m-d'),
                'montant' => $evenement->getMontant(),
                'commentaire' => $evenement->getCommentaire(),
            ];

            $this->auditLogger->logUpdate(
                'Evenement',
                $evenement->getId(),
                $evenement->getNom(),
                $oldData,
                $newData
            );

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
            $adherent = $participation->getAdherent();
            if ($adherent && $adherent->getStatut() !== 'radie') {
                $participantsActuels[] = $adherent;
            }
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
            
            foreach ($evenement->getParticipations() as $participation) {
                $adherent = $participation->getAdherent();
                if ($adherent && $adherent->getStatut() !== 'radie' && !in_array($adherent, $nouveauxParticipants)) {
                    $em->remove($participation);
                }
            }
            
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
                    $participation->setQuantite(1);
                    $em->persist($participation);
                }
            }
            
            $em->flush();
            
            $this->addFlash('success', 'La liste des participants a été mise à jour avec succès!');
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
        $adherentsActifs = $em->getRepository(Liste::class)->createQueryBuilder('l')
            ->where('l.statut != :statutRadie')
            ->setParameter('statutRadie', 'radie')
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
        
        $form = $this->createFormBuilder()
            ->add('participant', EntityType::class, [
                'class' => Liste::class,
                'choice_label' => 'nom',
                'multiple' => false,
                'expanded' => false,
                'required' => true,
                'label' => 'Ajouter un participant',
                'attr' => ['class' => 'form-control custom-input'],
                'choices' => $adherentsActifs
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Nombre de représentants',
                'required' => true,
                'attr' => ['class' => 'form-control custom-input', 'min' => 1, 'value' => 1]
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence du paiement',
                'required' => false,
                'attr' => ['class' => 'form-control custom-input', 'placeholder' => 'N° chèque, ref virement...']
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participant = $form->get('participant')->getData();
            $quantite = $form->get('quantite')->getData();
            $reference = $form->get('reference')->getData();
            
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
                $participation->setQuantite($quantite);
                $participation->setReference($reference);
                $participation->calculerMontantTotal();
                
                $em->persist($participation);
                $em->flush();
                
                $this->addFlash('success', sprintf('Participant ajouté avec succès! (%d représentant(s))', $quantite));
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
                $adherentNom = $participation->getAdherent()?->getNom();
                $em->remove($participation);
                $em->flush();
                
                $this->auditLogger->logUpdate(
                    'Evenement',
                    $evenement->getId(),
                    $evenement->getNom(),
                    ['participant_supprime' => $adherentNom],
                    null,
                    'Suppression d\'un participant'
                );
                
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
            $evenementData = [
                'nom' => $evenement->getNom(),
                'date' => $evenement->getDate()?->format('Y-m-d'),
                'montant' => $evenement->getMontant(),
            ];
            
            $entityManager->remove($evenement);
            $entityManager->flush();
            
            $this->auditLogger->logDelete(
                'Evenement',
                $evenement->getId(),
                $evenement->getNom(),
                $evenementData
            );
            
            $this->addFlash('success', 'Événement supprimé avec succès!');
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/participation/{id}/toggle', name: 'app_participation_toggle', methods: ['GET', 'POST'])]
    public function toggle(Participation $participation, EntityManagerInterface $em): Response
    {
        $adherent = $participation->getAdherent();

        if ($adherent->getStatut() === 'radie') {
            $this->addFlash('error', 'Impossible de modifier le statut : cet adhérent est radié.');
            return $this->redirectToRoute('app_evenement_history', [
                'adherent_id' => $adherent->getId()
            ]);
        }

        $oldStatut = $participation->getStatutPaiement();
        $newStatus = $oldStatut === 'paye' ? 'impaye' : 'paye';
        $participation->setStatutPaiement($newStatus);
        $em->flush();

        $this->auditLogger->logUpdate(
            'Participation',
            $participation->getId(),
            $participation->getAdherent()?->getNom(),
            ['statut_paiement' => $oldStatut],
            ['statut_paiement' => $newStatus],
            'Changement de statut de paiement'
        );

        $this->addFlash('success', 'Statut de paiement mis à jour avec succès!');

        return $this->redirectToRoute('app_evenement_history', [
            'adherent_id' => $participation->getAdherent()->getId()
        ]);
    }

    #[Route('/participation/paiement/new/{participation_id}', name: 'app_participation_paiement_new', methods: ['GET', 'POST'])]
    public function newPaiementParticipation(
        int $participation_id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $participation = $em->getRepository(Participation::class)->find($participation_id);
        
        if (!$participation) {
            throw $this->createNotFoundException('Participation non trouvée');
        }
        
        $adherent = $participation->getAdherent();
        $evenement = $participation->getEvenement();
        
        if ($adherent->getStatut() === 'radie') {
            $this->addFlash('error', 'Impossible : cet adhérent est radié.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        
        $montantTotal = $evenement->getMontant() * $participation->getQuantite();
        $resteAPayer = $participation->getResteAPayer();
        
        $paiement = new PaiementEvenement();
        $paiement->setParticipation($participation);
        $paiement->setMontant($resteAPayer);
        $paiement->setDatePaiement(new \DateTime());
        
        $form = $this->createForm(PaiementEvenementType::class, $paiement);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($paiement);
            
            $participation->addPaiement($paiement);
            $participation->setStatutPaiement('paye');
            
            $em->flush();
            
            $this->auditLogger->logPayment(
                'PaiementEvenement',
                $paiement->getId(),
                $adherent->getNom() . ' - ' . $evenement->getNom(),
                [
                    'adherent' => $adherent->getNom(),
                    'evenement' => $evenement->getNom(),
                    'montant' => $paiement->getMontant(),
                    'mode_paiement' => $paiement->getModePaiement(),
                ]
            );
            
            $this->addFlash('success', 'Paiement enregistré avec succès !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        
        return $this->render('evenement/paiement_new.html.twig', [
            'participation' => $participation,
            'adherent' => $adherent,
            'evenement' => $evenement,
            'montantTotal' => $montantTotal,
            'resteAPayer' => $resteAPayer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/participation/paiement/{id}/delete', name: 'app_participation_paiement_delete', methods: ['POST'])]
    public function deletePaiementParticipation(
        Request $request,
        PaiementEvenement $paiement,
        EntityManagerInterface $em
    ): Response {
        $participation = $paiement->getParticipation();
        $evenement = $participation->getEvenement();
        
        if ($this->isCsrfTokenValid('delete_paiement_evenement_' . $paiement->getId(), $request->request->get('_token'))) {
            $participation->removePaiement($paiement);
            $em->remove($paiement);
            $em->flush();
            
            $this->addFlash('success', 'Paiement supprimé avec succès.');
        }
        
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/generer-recu-evenement/{participation_id}', name: 'app_document_generer_recu_evenement', methods: ['GET'])]
    public function genererRecuEvenement(int $participation_id, EntityManagerInterface $em): Response
    {
        $participation = $em->getRepository(Participation::class)->find($participation_id);
        
        if (!$participation) {
            $this->addFlash('error', 'Participation non trouvée');
            return $this->redirectToRoute('app_home');
        }
        
        $paiements = $participation->getPaiements();
        
        try {
            if (!$paiements->isEmpty()) {
                $dernierPaiement = $paiements->last();
                $document = $this->documentGenerator->generateRecuFromPaiementEvenement($dernierPaiement);
            } else {
                $document = $this->documentGenerator->generateRecuFromParticipation($participation);
            }
            
            $this->addFlash('success', 'Reçu généré avec succès !');
            
            return $this->redirectToRoute('app_document_adherent', ['id' => $participation->getAdherent()->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du reçu : ' . $e->getMessage());
            return $this->redirectToRoute('app_evenement_history', ['adherent_id' => $participation->getAdherent()->getId()]);
        }
    }
}