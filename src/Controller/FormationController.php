<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Liste;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/formation')]
final class FormationController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route('/', name: 'app_formation_index', methods: ['GET'])]
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

        if ($adherent->getStatut() === 'radie') {
            $formations = [];
        } else {
            $searchTerm = $request->query->get('q');
            $formations = $adherent->getFormations();

            if ($searchTerm) {
                $formations = $formations->filter(function(Formation $f) use ($searchTerm) {
                    return stripos($f->getNom(), $searchTerm) !== false || 
                        stripos($f->getFormateurs(), $searchTerm) !== false;
                });
            }
        }

        return $this->render('formation/history.html.twig', [
            'adherent' => $adherent,
            'formations' => $formations,
        ]);
    }

    #[Route('/{id}/participants', name: 'app_formation_show_participants', methods: ['GET', 'POST'])]
    public function showParticipants(Request $request, Formation $formation, EntityManagerInterface $em): Response
    {
        $form = $this->createFormBuilder()
            ->getForm();
        
        $participants = $formation->getParticipants();
        $nombresFormes = $formation->getNombresFormes() ?? [];
        
        foreach ($participants as $participant) {
            $defaultValue = $nombresFormes[$participant->getId()] ?? 0;
            $form->add('nombre_' . $participant->getId(), IntegerType::class, [
                'label' => false,
                'data' => $defaultValue,
                'attr' => ['class' => 'form-control text-center', 'min' => 0],
                'required' => false,
            ]);
        }
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $newNombres = [];
            
            foreach ($participants as $participant) {
                $key = 'nombre_' . $participant->getId();
                if (isset($data[$key]) && $data[$key] !== null) {
                    $newNombres[$participant->getId()] = (int) $data[$key];
                }
            }
            
            $formation->setNombresFormes($newNombres);
            $em->flush();
            
            $this->addFlash('success', 'Les nombres de bénéficiaires ont été mis à jour !');
            return $this->redirectToRoute('app_formation_show_participants', ['id' => $formation->getId()]);
        }
        
        return $this->render('formation/show_participants.html.twig', [
            'formation' => $formation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/new', name: 'app_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participants = $formation->getParticipants();
            
            $entityManager->persist($formation);
            $entityManager->flush();

            $this->auditLogger->logCreate(
                'Formation',
                $formation->getId(),
                $formation->getNom(),
                [
                    'nom' => $formation->getNom(),
                    'formateurs' => $formation->getFormateurs(),
                    'date_debut' => $formation->getDateDebut()?->format('Y-m-d'),
                    'date_fin' => $formation->getDateFin()?->format('Y-m-d'),
                    'organisateur' => $formation->getOrganisateur(),
                    'nb_participants' => count($participants)
                ]
            );

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

    #[Route('/{id}/edit', name: 'app_formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $oldData = [
            'nom' => $formation->getNom(),
            'formateurs' => $formation->getFormateurs(),
            'date_debut' => $formation->getDateDebut()?->format('Y-m-d'),
            'date_fin' => $formation->getDateFin()?->format('Y-m-d'),
            'organisateur' => $formation->getOrganisateur(),
            'nombres_formes' => $formation->getNombresFormes(),
        ];

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $newData = [
                'nom' => $formation->getNom(),
                'formateurs' => $formation->getFormateurs(),
                'date_debut' => $formation->getDateDebut()?->format('Y-m-d'),
                'date_fin' => $formation->getDateFin()?->format('Y-m-d'),
                'organisateur' => $formation->getOrganisateur(),
                'nombres_formes' => $formation->getNombresFormes(),
            ];

            $this->auditLogger->logUpdate(
                'Formation',
                $formation->getId(),
                $formation->getNom(),
                $oldData,
                $newData
            );

            $this->addFlash('success', 'Formation modifiée avec succès!');
            return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/ajouter-participant', name: 'app_formation_ajouter_participant', methods: ['GET', 'POST'])]
public function ajouterParticipant(Request $request, Formation $formation, EntityManagerInterface $em): Response
{
    // Récupérer les adhérents actifs (non radiés)
    $adherentsActifs = $em->getRepository(Liste::class)->createQueryBuilder('l')
        ->where('l.statut != :statutRadie')
        ->setParameter('statutRadie', 'radie')
        ->orderBy('l.nom', 'ASC')
        ->getQuery()
        ->getResult();

    // Créer un formulaire pour sélectionner un adhérent
    $form = $this->createFormBuilder()
        ->add('participant', EntityType::class, [
            'class' => Liste::class,
            'choice_label' => 'nom',
            'multiple' => false,
            'expanded' => false,
            'required' => true,
            'label' => 'Ajouter un participant',
            'attr' => ['class' => 'form-control custom-input'],
            'choices' => $adherentsActifs,
            'query_builder' => function($repository) {
                return $repository->createQueryBuilder('l')
                    ->where('l.statut != :statutRadie')
                    ->setParameter('statutRadie', 'radie')
                    ->orderBy('l.nom', 'ASC');
            },
        ])
        ->add('nombreBeneficiaires', IntegerType::class, [
            'label' => 'Nombre de bénéficiaires (formés)',
            'required' => false,
            'data' => 1,
            'attr' => ['class' => 'form-control', 'min' => 1],
        ])
        ->getForm();

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $participant = $form->get('participant')->getData();
        $nombreBeneficiaires = $form->get('nombreBeneficiaires')->getData() ?? 1;

        // Vérifier si le participant existe déjà
        $existe = false;
        foreach ($formation->getParticipants() as $p) {
            if ($p->getId() === $participant->getId()) {
                $existe = true;
                break;
            }
        }

        if (!$existe) {
            // Ajouter le participant à la formation
            $formation->addParticipant($participant);
            
            // Initialiser le nombre de bénéficiaires pour ce participant
            $nombresFormes = $formation->getNombresFormes() ?? [];
            $nombresFormes[$participant->getId()] = $nombreBeneficiaires;
            $formation->setNombresFormes($nombresFormes);
            
            $em->flush();

            $this->auditLogger->logUpdate(
                'Formation',
                $formation->getId(),
                $formation->getNom(),
                ['action' => 'Ajout participant'],
                ['participant' => $participant->getNom(), 'nombre_beneficiaires' => $nombreBeneficiaires]
            );

            $this->addFlash('success', sprintf('Participant "%s" ajouté avec succès !', $participant->getNom()));
        } else {
            $this->addFlash('warning', 'Ce participant est déjà inscrit à cette formation.');
        }

        return $this->redirectToRoute('app_formation_show_participants', ['id' => $formation->getId()]);
    }

    return $this->render('formation/ajouter_participant.html.twig', [
        'formation' => $formation,
        'form' => $form->createView(),
    ]);
}

    #[Route('/{id}/retirer-participant/{participant_id}', name: 'app_formation_retirer_participant', methods: ['POST'])]
public function retirerParticipant(
    Request $request,
    Formation $formation,
    int $participant_id,
    EntityManagerInterface $em
): Response {
    $participant = $em->getRepository(Liste::class)->find($participant_id);
    
    if (!$participant) {
        $this->addFlash('error', 'Participant non trouvé.');
        return $this->redirectToRoute('app_formation_show_participants', ['id' => $formation->getId()]);
    }

    if ($this->isCsrfTokenValid('retirer_participant_' . $participant_id, $request->request->get('_token'))) {
        $formation->removeParticipant($participant);
        
        // Supprimer le nombre de bénéficiaires pour ce participant
        $nombresFormes = $formation->getNombresFormes() ?? [];
        if (isset($nombresFormes[$participant_id])) {
            unset($nombresFormes[$participant_id]);
            $formation->setNombresFormes($nombresFormes);
        }
        
        $em->flush();

        $this->auditLogger->logUpdate(
            'Formation',
            $formation->getId(),
            $formation->getNom(),
            ['action' => 'Retrait participant'],
            ['participant' => $participant->getNom()]
        );

        $this->addFlash('success', sprintf('Participant "%s" retiré avec succès !', $participant->getNom()));
    }

    return $this->redirectToRoute('app_formation_show_participants', ['id' => $formation->getId()]);
}

    #[Route('/{id}', name: 'app_formation_delete', methods: ['POST'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $formationData = [
            'nom' => $formation->getNom(),
            'formateurs' => $formation->getFormateurs(),
            'date_debut' => $formation->getDateDebut()?->format('Y-m-d'),
            'date_fin' => $formation->getDateFin()?->format('Y-m-d'),
        ];

        if ($this->isCsrfTokenValid('delete' . $formation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($formation);
            $entityManager->flush();

            $this->auditLogger->logDelete(
                'Formation',
                $formation->getId(),
                $formation->getNom(),
                $formationData
            );
        }

        return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
    }
}