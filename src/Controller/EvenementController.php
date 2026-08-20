<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\LigneEvenement;
use App\Entity\Liste;
use App\Entity\Participation;
use App\Entity\ParticipationLigne;
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

    #[Route('/', name: 'app_evenement_index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $repo): Response
    {
        $search = $request->query->get('search');
        $statut = $request->query->get('statut');
        
        $evenements = $repo->search($search, $statut);
        
        $totalAttendu = 0;
        $totalPaye = 0;
        $totalReste = 0;
        
        foreach ($evenements as $evenement) {
            $totalAttendu += $evenement->getMontantTotal();
            $totalPaye += $evenement->getMontantTotalPaye();
            $totalReste += ($evenement->getMontantTotal() - $evenement->getMontantTotalPaye());
        }

        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenements,
            'search' => $search,
            'statutFilter' => $statut,
            'totalAttendu' => $totalAttendu,
            'totalPaye' => $totalPaye,
            'totalReste' => $totalReste,
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
    
    if ($evenement->getLignes()->count() === 0) {
        $ligne = new LigneEvenement();
        $ligne->setDesignation('');
        $ligne->setMontantUnitaire(0);
        $ligne->setEvenement($evenement);
        $evenement->addLigne($ligne);
    }
    
    $form = $this->createForm(EvenementType::class, $evenement);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $participants = $form->get('participants')->getData();
        $montantFixe = $form->get('montantFixe')->getData();
        
        $nbParticipants = $request->request->all('nb_participants') ?? [];
        $quantitesDesignations = $request->request->all('quantite_designation') ?? [];
        
        if ($participants instanceof \Doctrine\Common\Collections\Collection) {
            $participantsArray = $participants->toArray();
        } else {
            $participantsArray = (array) $participants;
        }
        
        $participantsActifs = array_filter($participantsArray, function($adherent) {
            return $adherent && $adherent->getStatut() === 'actif';
        });
        
        $lignes = $evenement->getLignes();
        $hasLignes = $lignes->count() > 0 && $lignes->first()->getDesignation() !== '';
        
        foreach ($participantsActifs as $adherent) {
            $adherentId = $adherent->getId();
            
            $participation = new Participation();
            $participation->setAdherent($adherent);
            $participation->setEvenement($evenement);
            $participation->setStatutPaiement('impaye');
            
            $montantTotal = 0;
            
            if ($montantFixe && $montantFixe > 0 && !$hasLignes) {
                // Montant fixe : récupérer le nombre de participants
                $nbParticipant = isset($nbParticipants[$adherentId]) ? (int) $nbParticipants[$adherentId] : 1;
                if ($nbParticipant < 1) $nbParticipant = 1;
                $participation->setNbParticipants($nbParticipant);
                $participation->setMontantTotal($montantFixe * $nbParticipant);
            } elseif ($hasLignes) {
                // Désignations
                $ligneIndex = 0;
                foreach ($lignes as $ligne) {
                    if ($ligne->getDesignation() === '') continue;
                    $quantite = isset($quantitesDesignations[$adherentId][$ligneIndex]) ? (int) $quantitesDesignations[$adherentId][$ligneIndex] : 0;
                    if ($quantite > 0) {
                        $participationLigne = new ParticipationLigne();
                        $participationLigne->setLigne($ligne);
                        $participationLigne->setQuantite($quantite);
                        $participationLigne->calculerMontantLigne();
                        $participation->addParticipationLigne($participationLigne);
                        $montantTotal += $participationLigne->getMontantLigne();
                    }
                    $ligneIndex++;
                }
                $participation->setMontantTotal($montantTotal);
                $participation->setNbParticipants(1);
            } else {
                $participation->setMontantTotal(0);
                $participation->setNbParticipants(1);
            }
            
            $em->persist($participation);
        }

        $em->persist($evenement);
        $em->flush();

        $this->addFlash('success', 'Événement créé avec succès !');
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    return $this->render('evenement/new.html.twig', [
        'form' => $form->createView(),
    ]);
}

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $em): Response
    {
        $evenement = $em->getRepository(Evenement::class)->find((int) $id);
        
        if (!$evenement) {
            $this->addFlash('error', 'Événement non trouvé.');
            return $this->redirectToRoute('app_evenement_index');
        }
        
        $participationsFiltrees = $evenement->getParticipations()->filter(function($participation) {
            $adherent = $participation->getAdherent();
            return $adherent && $adherent->getStatut() !== 'radie';
        });

        $totalParticipants = $participationsFiltrees->count();
        $totalPaye = 0;
        $montantGlobal = 0;
        $payeCount = 0;
        
        foreach ($participationsFiltrees as $p) {
            $montantGlobal += $p->getMontantTotal() ?? 0;
            if ($p->isPaye()) {
                $payeCount++;
                $totalPaye += $p->getMontantTotal() ?? 0;
            }
        }
        
        $tauxPaiement = $totalParticipants > 0 ? round(($payeCount / $totalParticipants) * 100, 2) : 0;

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
            'participationsFiltrees' => $participationsFiltrees,
            'totalParticipants' => $totalParticipants,
            'montantGlobal' => $montantGlobal,
            'totalPaye' => $totalPaye,
            'payeCount' => $payeCount,
            'tauxPaiement' => $tauxPaiement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
public function edit(string $id, Request $request, EntityManagerInterface $entityManager): Response
{
    $evenement = $entityManager->getRepository(Evenement::class)->find((int) $id);
    
    if (!$evenement) {
        $this->addFlash('error', 'Événement non trouvé.');
        return $this->redirectToRoute('app_evenement_index');
    }
    
    $oldData = [
        'nom' => $evenement->getNom(),
        'periode' => $evenement->getPeriode(),
        'date' => $evenement->getDate()?->format('Y-m-d'),
        'type_document' => $evenement->getTypeDocument(),
        'commentaire' => $evenement->getCommentaire(),
        'statut' => $evenement->getStatut(),
    ];

    $participantsActuels = [];
    $quantitesDesignations = [];
    $nbParticipants = [];
    
    foreach ($evenement->getParticipations() as $participation) {
        $adherent = $participation->getAdherent();
        if ($adherent && $adherent->getStatut() !== 'radie') {
            $participantsActuels[] = $adherent;
            
            // Récupérer le nombre de participants pour les événements à montant fixe
            $nbParticipants[$adherent->getId()] = $participation->getNbParticipants() ?? 1;
            
            // Récupérer les quantités par ligne
            $lignesQuantites = [];
            foreach ($participation->getParticipationLignes() as $pl) {
                $ligneId = $pl->getLigne() ? $pl->getLigne()->getId() : 0;
                $lignesQuantites[$ligneId] = $pl->getQuantite();
            }
            $quantitesDesignations[$adherent->getId()] = $lignesQuantites;
        }
    }

    $form = $this->createForm(EvenementType::class, $evenement);
    $form->get('participants')->setData($participantsActuels);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $nouveauxParticipants = $form->get('participants')->getData();
        $montantFixe = $form->get('montantFixe')->getData();
        
        $nbParticipantsPost = $request->request->all('nb_participants') ?? [];
        $quantitesDesignationsPost = $request->request->all('quantite_designation') ?? [];
        
        if ($nouveauxParticipants instanceof \Doctrine\Common\Collections\Collection) {
            $nouveauxParticipantsArray = $nouveauxParticipants->toArray();
        } else {
            $nouveauxParticipantsArray = (array) $nouveauxParticipants;
        }
        
        // Supprimer les participants retirés
        foreach ($evenement->getParticipations() as $participation) {
            $adherent = $participation->getAdherent();
            if ($adherent && $adherent->getStatut() !== 'radie' && !in_array($adherent, $nouveauxParticipantsArray)) {
                $entityManager->remove($participation);
            }
        }
        
        $lignes = $evenement->getLignes();
        $hasLignes = $lignes->count() > 0 && $lignes->first()->getDesignation() !== '';
        
        foreach ($nouveauxParticipantsArray as $participant) {
            $existe = false;
            $existingParticipation = null;
            foreach ($evenement->getParticipations() as $participation) {
                if ($participation->getAdherent() === $participant) {
                    $existe = true;
                    $existingParticipation = $participation;
                    break;
                }
            }
            
            $adherentId = $participant->getId();
            $nbParticipant = isset($nbParticipantsPost[$adherentId]) ? (int) $nbParticipantsPost[$adherentId] : 1;
            if ($nbParticipant < 1) $nbParticipant = 1;
            $montantTotal = 0;
            
            if ($existe && $existingParticipation) {
                // 🔥 FIX: Mettre à jour le nombre de participants
                $existingParticipation->setNbParticipants($nbParticipant);
                
                // Supprimer les anciennes lignes
                foreach ($existingParticipation->getParticipationLignes() as $pl) {
                    $entityManager->remove($pl);
                }
                $existingParticipation->getParticipationLignes()->clear();
                
                if ($montantFixe && $montantFixe > 0 && !$hasLignes) {
                    $existingParticipation->setMontantTotal($montantFixe * $nbParticipant);
                } elseif ($hasLignes) {
                    $ligneIndex = 0;
                    foreach ($lignes as $ligne) {
                        if ($ligne->getDesignation() === '') continue;
                        $quantite = isset($quantitesDesignationsPost[$adherentId][$ligneIndex]) ? (int) $quantitesDesignationsPost[$adherentId][$ligneIndex] : 0;
                        if ($quantite > 0) {
                            $participationLigne = new ParticipationLigne();
                            $participationLigne->setLigne($ligne);
                            $participationLigne->setQuantite($quantite);
                            $participationLigne->calculerMontantLigne();
                            $existingParticipation->addParticipationLigne($participationLigne);
                            $montantTotal += $participationLigne->getMontantLigne();
                        }
                        $ligneIndex++;
                    }
                    $existingParticipation->setMontantTotal($montantTotal);
                } else {
                    $existingParticipation->setMontantTotal(0);
                }
            } else {
                // Créer une nouvelle participation
                $participation = new Participation();
                $participation->setAdherent($participant);
                $participation->setEvenement($evenement);
                $participation->setStatutPaiement('impaye');
                
                // 🔥 FIX: Définir nbParticipants pour les nouvelles participations
                $participation->setNbParticipants($nbParticipant);
                
                if ($montantFixe && $montantFixe > 0 && !$hasLignes) {
                    $participation->setMontantTotal($montantFixe * $nbParticipant);
                } elseif ($hasLignes) {
                    $ligneIndex = 0;
                    foreach ($lignes as $ligne) {
                        if ($ligne->getDesignation() === '') continue;
                        $quantite = isset($quantitesDesignationsPost[$adherentId][$ligneIndex]) ? (int) $quantitesDesignationsPost[$adherentId][$ligneIndex] : 0;
                        if ($quantite > 0) {
                            $participationLigne = new ParticipationLigne();
                            $participationLigne->setLigne($ligne);
                            $participationLigne->setQuantite($quantite);
                            $participationLigne->calculerMontantLigne();
                            $participation->addParticipationLigne($participationLigne);
                            $montantTotal += $participationLigne->getMontantLigne();
                        }
                        $ligneIndex++;
                    }
                    $participation->setMontantTotal($montantTotal);
                } else {
                    $participation->setMontantTotal(0);
                }
                $entityManager->persist($participation);
            }
        }
        
        $entityManager->flush();

        $this->addFlash('success', 'Événement modifié avec succès !');
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    return $this->render('evenement/edit.html.twig', [
        'evenement' => $evenement,
        'form' => $form->createView(),
        'quantitesDesignations' => $quantitesDesignations,
        'nbParticipants' => $nbParticipants,
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
            'attr' => ['class' => 'participants-checkbox-list'],
            'query_builder' => function($repository) {
                return $repository->createQueryBuilder('l')
                    ->where('l.statut != :statutRadie')
                    ->setParameter('statutRadie', 'radie')
                    ->orderBy('l.nom', 'ASC');
            },
        ])
        ->getForm();

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $nouveauxParticipants = $form->get('participants')->getData();
        
        // Supprimer les participants retirés
        foreach ($evenement->getParticipations() as $participation) {
            $adherent = $participation->getAdherent();
            if ($adherent && $adherent->getStatut() !== 'radie' && !in_array($adherent, $nouveauxParticipants)) {
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
                
                // Initialiser avec 1 participant par défaut
                $lignes = $evenement->getLignes();
                $hasValidLignes = false;
                foreach ($lignes as $ligne) {
                    if ($ligne->getDesignation() !== '') {
                        $hasValidLignes = true;
                        break;
                    }
                }
                
                if ($evenement->getMontantFixe() && $evenement->getMontantFixe() > 0 && !$hasValidLignes) {
                    $participation->setMontantTotal($evenement->getMontantFixe());
                } elseif ($hasValidLignes) {
                    foreach ($lignes as $ligne) {
                        if ($ligne->getDesignation() !== '') {
                            $participationLigne = new ParticipationLigne();
                            $participationLigne->setLigne($ligne);
                            $participationLigne->setQuantite(1);
                            $participationLigne->calculerMontantLigne();
                            $participation->addParticipationLigne($participationLigne);
                        }
                    }
                    $participation->recalculerMontantTotal();
                } else {
                    $participation->setMontantTotal(0);
                }
                
                $em->persist($participation);
            }
        }
        
        $em->flush();
        
        $this->addFlash('success', 'La liste des participants a été mise à jour avec succès !');
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
            'label' => 'Adhérent',
            'attr' => ['class' => 'form-control custom-input'],
            'choices' => $adherentsActifs
        ])
        ->add('quantite', IntegerType::class, [
            'label' => 'Nombre de participants',
            'required' => false,
            'attr' => ['class' => 'form-control custom-input', 'min' => 1, 'value' => 1]
        ])
        ->getForm();

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $participant = $form->get('participant')->getData();
        $quantite = $form->get('quantite')->getData() ?? 1;
        
        // Récupérer les quantités temporaires par désignation
        $quantitesDesignationsTemp = $request->request->all('quantite_designation_temp') ?? [];
        
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
            
            // 🔥 FIX: Définir le nombre de participants
            $participation->setNbParticipants($quantite);
            
            $montantTotal = 0;
            $lignes = $evenement->getLignes();
            $hasValidLignes = false;
            
            // Vérifier s'il y a des lignes valides
            foreach ($lignes as $ligne) {
                if ($ligne->getDesignation() !== '') {
                    $hasValidLignes = true;
                    break;
                }
            }
            
            // Si montant fixe est défini ET pas de désignations
            if ($evenement->getMontantFixe() && $evenement->getMontantFixe() > 0 && !$hasValidLignes) {
                $participation->setMontantTotal($evenement->getMontantFixe() * $quantite);
            } elseif ($hasValidLignes) {
                // Utiliser les quantités temporaires
                $ligneIndex = 0;
                foreach ($lignes as $ligne) {
                    if ($ligne->getDesignation() === '') {
                        $ligneIndex++;
                        continue;
                    }
                    
                    $quantiteLigne = isset($quantitesDesignationsTemp[$ligneIndex]) ? (int) $quantitesDesignationsTemp[$ligneIndex] : 0;
                    if ($quantiteLigne > 0) {
                        $participationLigne = new ParticipationLigne();
                        $participationLigne->setLigne($ligne);
                        $participationLigne->setQuantite($quantiteLigne);
                        $participationLigne->calculerMontantLigne();
                        $participation->addParticipationLigne($participationLigne);
                        $montantTotal += $participationLigne->getMontantLigne();
                    }
                    $ligneIndex++;
                }
                $participation->setMontantTotal($montantTotal);
            } else {
                $participation->setMontantTotal(0);
            }
            
            $em->persist($participation);
            $em->flush();
            
            $this->addFlash('success', sprintf('Participant "%s" ajouté avec succès !', $participant->getNom()));
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
                'periode' => $evenement->getPeriode(),
                'date' => $evenement->getDate()?->format('Y-m-d'),
                'type_document' => $evenement->getTypeDocument(),
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
        
        $montantTotal = $participation->getMontantTotal() ?? 0;
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

    #[Route('/generer-note-debit-evenement/{participation_id}', name: 'app_document_generer_note_debit_evenement', methods: ['GET'])]
    public function genererNoteDebitEvenement(int $participation_id, EntityManagerInterface $em): Response
    {
        $participation = $em->getRepository(Participation::class)->find($participation_id);
        
        if (!$participation) {
            $this->addFlash('error', 'Participation non trouvée');
            return $this->redirectToRoute('app_home');
        }
        
        $adherent = $participation->getAdherent();
        $evenement = $participation->getEvenement();
        
        if ($adherent->getStatut() === 'radie') {
            $this->addFlash('error', 'Impossible : cet adhérent est radié.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        
        try {
            $document = $this->documentGenerator->generateNoteDebitFromParticipation($participation);
            $this->addFlash('success', 'Note de débit générée avec succès !');
            return $this->redirectToRoute('app_document_adherent', ['id' => $adherent->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération : ' . $e->getMessage());
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
    }
}