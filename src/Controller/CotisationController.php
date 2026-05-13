<?php

namespace App\Controller;

use App\Entity\Cotisation;
use App\Entity\Liste;
use App\Entity\Paiement;
use App\Form\PaiementType;
use App\Repository\CotisationRepository;
use App\Service\AuditLogger;
use App\Service\CotisationCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cotisation')]
final class CotisationController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route('/', name: 'app_cotisation_index', methods: ['GET'])]
    public function index(Request $request, CotisationRepository $repo, CotisationCalculator $calculator): Response
    {
        $search = $request->query->get('search');
        $statut = $request->query->get('statut');
        $periode = $request->query->get('periode');
        
        $cotisations = $repo->search($search, $statut, $periode);
        
        return $this->render('cotisation/index.html.twig', [
            'cotisations' => $cotisations,
            'calculator' => $calculator,
            'search' => $search,
            'statutFilter' => $statut,
            'periodeFilter' => $periode,
        ]);
    }

    #[Route('/history/{adherent_id}', name: 'app_cotisation_history')]
    public function history(int $adherent_id, EntityManagerInterface $em, CotisationCalculator $calculator): Response
    {
        $adherent = $em->getRepository(Liste::class)->find($adherent_id);
        
        if (!$adherent) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }

        $cotisations = $adherent->getCotisations();
        $montantAttendu = $calculator->calculateMontant($adherent);
        $totalPaye = 0;
        
        foreach ($cotisations as $cotisation) {
            $totalPaye += $cotisation->getMontantPaye();
        }
        
        $statut = $totalPaye >= $montantAttendu ? 'paye' : 'impaye';

        return $this->render('cotisation/history.html.twig', [
            'adherent' => $adherent,
            'cotisations' => $cotisations,
            'montantAttendu' => $montantAttendu,
            'totalPaye' => $totalPaye,
            'statut' => $statut,
            'calculator' => $calculator,
        ]);
    }

    #[Route('/show/{id}', name: 'app_cotisation_show', methods: ['GET'])]
    public function show(Cotisation $cotisation): Response
    {
        return $this->render('cotisation/show.html.twig', [
            'cotisation' => $cotisation,
        ]);
    }

    #[Route('/paiement/new/{adherent_id}', name: 'app_paiement_new', methods: ['GET', 'POST'])]
    public function newPaiement(
        int $adherent_id,
        Request $request,
        EntityManagerInterface $em,
        CotisationCalculator $calculator
    ): Response {
        $adherent = $em->getRepository(Liste::class)->find($adherent_id);
        
        if (!$adherent) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }

        $currentYear = date('Y');
        $montantAttendu = $calculator->getMontantHistorique($adherent, $currentYear);
        
        // Vérifier si une cotisation existe pour cette année
        $cotisation = $em->getRepository(Cotisation::class)
            ->findOneBy([
                'adherent' => $adherent,
                'periode' => $currentYear
            ]);
        
        // Si aucune cotisation n'existe, la créer
        if (!$cotisation) {
            $cotisation = new Cotisation();
            $cotisation->setAdherent($adherent);
            $cotisation->setPeriode($currentYear);
            $cotisation->setMontant($montantAttendu);
            $cotisation->setMontantPaye(0);
            $em->persist($cotisation);
            $em->flush();

            // Audit log pour la création de la cotisation
            $this->auditLogger->logCreate(
                'Cotisation',
                $cotisation->getId(),
                $adherent->getNom() . ' - ' . $currentYear,
                [
                    'adherent' => $adherent->getNom(),
                    'periode' => $currentYear,
                    'montant' => $montantAttendu
                ]
            );
        }

        $paiement = new Paiement();
        $paiement->setCotisation($cotisation);
        
        $form = $this->createForm(PaiementType::class, $paiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($paiement);
            
            // Mettre à jour le montant payé de la cotisation
            $oldMontantPaye = $cotisation->getMontantPaye();
            $cotisation->setMontantPaye($cotisation->getMontantPaye() + $paiement->getMontant());
            
            $em->flush();

            // Audit log pour le paiement
            $this->auditLogger->logPayment(
                'Paiement',
                $paiement->getId(),
                $adherent->getNom() . ' - ' . $currentYear,
                [
                    'adherent' => $adherent->getNom(),
                    'montant' => $paiement->getMontant(),
                    'mode_paiement' => $paiement->getModePaiement(),
                    'ancien_montant_paye' => $oldMontantPaye,
                    'nouveau_montant_paye' => $cotisation->getMontantPaye()
                ]
            );

            return $this->redirectToRoute('app_cotisation_history', ['adherent_id' => $adherent_id]);
        }

        return $this->render('paiement/new.html.twig', [
            'form' => $form->createView(),
            'adherent' => $adherent,
            'montantAttendu' => $montantAttendu,
            'resteAPayer' => $cotisation->getResteAPayer(),
            'calculator' => $calculator,
        ]);
    }

    #[Route('/paiement/{id}/edit', name: 'app_paiement_edit', methods: ['GET', 'POST'])]
    public function editPaiement(Request $request, Paiement $paiement, EntityManagerInterface $em): Response
    {
        $cotisation = $paiement->getCotisation();
        $adherent = $cotisation->getAdherent();
        $ancienMontant = $paiement->getMontant();
        
        $oldData = [
            'montant' => $paiement->getMontant(),
            'mode_paiement' => $paiement->getModePaiement(),
            'reference' => $paiement->getReference(),
        ];
        
        $form = $this->createForm(PaiementType::class, $paiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mettre à jour le montant payé de la cotisation
            $nouveauMontant = $paiement->getMontant();
            $difference = $nouveauMontant - $ancienMontant;
            $cotisation->setMontantPaye($cotisation->getMontantPaye() + $difference);
            
            $em->flush();

            $newData = [
                'montant' => $paiement->getMontant(),
                'mode_paiement' => $paiement->getModePaiement(),
                'reference' => $paiement->getReference(),
            ];

            // Audit log
            $this->auditLogger->logUpdate(
                'Paiement',
                $paiement->getId(),
                $adherent->getNom(),
                $oldData,
                $newData
            );

            return $this->redirectToRoute('app_cotisation_history', ['adherent_id' => $adherent->getId()]);
        }

        return $this->render('paiement/edit.html.twig', [
            'form' => $form->createView(),
            'paiement' => $paiement,
            'adherent' => $adherent,
        ]);
    }

    #[Route('/paiement/{id}/delete', name: 'app_paiement_delete', methods: ['POST'])]
    public function deletePaiement(Request $request, Paiement $paiement, EntityManagerInterface $em): Response
    {
        $cotisation = $paiement->getCotisation();
        $adherentId = $cotisation->getAdherent()->getId();
        
        $paiementData = [
            'montant' => $paiement->getMontant(),
            'mode_paiement' => $paiement->getModePaiement(),
            'reference' => $paiement->getReference(),
        ];
        
        if ($this->isCsrfTokenValid('delete_paiement_' . $paiement->getId(), $request->request->get('_token'))) {
            // Mettre à jour le montant payé de la cotisation
            $cotisation->setMontantPaye($cotisation->getMontantPaye() - $paiement->getMontant());
            $em->remove($paiement);
            $em->flush();

            // Audit log
            $this->auditLogger->logDelete(
                'Paiement',
                $paiement->getId(),
                $cotisation->getAdherent()->getNom() . ' - ' . $cotisation->getPeriode(),
                $paiementData
            );
        }

        return $this->redirectToRoute('app_cotisation_history', ['adherent_id' => $adherentId]);
    }

    #[Route('/generate/{adherent_id}', name: 'app_cotisation_generate', methods: ['GET'])]
    public function generate(int $adherent_id, EntityManagerInterface $em, CotisationCalculator $calculator): Response
    {
        $adherent = $em->getRepository(Liste::class)->find($adherent_id);
        
        if (!$adherent) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }

        $currentYear = date('Y');
        
        // Vérifier si une cotisation existe déjà
        $existing = $em->getRepository(Cotisation::class)->findOneBy([
            'adherent' => $adherent,
            'periode' => $currentYear
        ]);

        if (!$existing) {
            // Calculer le montant avec le barème actuel
            $result = $calculator->calculateMontantWithBareme($adherent);
            
            $cotisation = new Cotisation();
            $cotisation->setAdherent($adherent);
            $cotisation->setPeriode($currentYear);
            $cotisation->setMontant($result['montant']);  // Montant figé
            $cotisation->setMontantPaye(0);
            $cotisation->setStatut('impaye');
            $cotisation->setBaremeId($result['baremeId']);
            $cotisation->setBaremeLibelle($result['baremeLibelle']);
            
            $em->persist($cotisation);
            $em->flush();
            
            // Audit log
            $this->auditLogger->logCreate(
                'Cotisation',
                $cotisation->getId(),
                $adherent->getNom() . ' - ' . $currentYear,
                [
                    'adherent' => $adherent->getNom(),
                    'periode' => $currentYear,
                    'montant' => $result['montant'],
                    'bareme' => $result['baremeLibelle']
                ]
            );
            
            $this->addFlash('success', 'Cotisation générée avec succès!');
        } else {
            $this->addFlash('info', 'Une cotisation existe déjà pour cette année.');
        }

        return $this->redirectToRoute('app_cotisation_history', ['adherent_id' => $adherent_id]);
    }
}