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
        $totalPaye = 0;
        
        foreach ($cotisations as $cotisation) {
            $totalPaye += $cotisation->getMontantPaye();
        }
        
        $montantAttendu = $calculator->calculateMontantByPeriode($adherent, date('Y'));

        return $this->render('cotisation/history.html.twig', [
            'adherent' => $adherent,
            'cotisations' => $cotisations,
            'montantAttendu' => $montantAttendu,
            'totalPaye' => $totalPaye,
            'statut' => $totalPaye >= $montantAttendu ? 'paye' : 'impaye',
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

        $periode = $request->request->get('periode', date('Y'));
        
        $montantAttendu = $calculator->calculateMontant($adherent, $periode);
        
        $cotisation = $em->getRepository(Cotisation::class)
            ->findOneBy([
                'adherent' => $adherent,
                'periode' => $periode
            ]);
        
        if (!$cotisation) {
            $result = $calculator->calculateMontantWithBareme($adherent, $calculator->createDateFromPeriode($periode));
            
            $cotisation = new Cotisation();
            $cotisation->setAdherent($adherent);
            $cotisation->setPeriode($periode);
            $cotisation->setMontant($result['montant']);
            $cotisation->setMontantPaye(0);
            $cotisation->setStatut('impaye');
            $cotisation->setBaremeId($result['baremeId']);
            $cotisation->setBaremeLibelle($result['baremeLibelle']);
            
            $em->persist($cotisation);
            $em->flush();

            $this->auditLogger->logCreate(
                'Cotisation',
                $cotisation->getId(),
                $adherent->getNom() . ' - ' . $periode,
                [
                    'adherent' => $adherent->getNom(),
                    'periode' => $periode,
                    'montant' => $result['montant'],
                    'bareme' => $result['baremeLibelle']
                ]
            );
        }

        $paiement = new Paiement();
        $paiement->setCotisation($cotisation);
        $paiement->setPeriode($periode);
        
        $form = $this->createForm(PaiementType::class, $paiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($paiement);
            
            $oldMontantPaye = $cotisation->getMontantPaye();
            $cotisation->setMontantPaye($cotisation->getMontantPaye() + $paiement->getMontant());
            
            $em->flush();

            $this->auditLogger->logPayment(
                'Paiement',
                $paiement->getId(),
                $adherent->getNom() . ' - ' . $periode,
                [
                    'adherent' => $adherent->getNom(),
                    'periode' => $periode,
                    'montant' => $paiement->getMontant(),
                    'mode_paiement' => $paiement->getModePaiement(),
                    'ancien_montant_paye' => $oldMontantPaye,
                    'nouveau_montant_paye' => $cotisation->getMontantPaye()
                ]
            );

            $this->addFlash('success', 'Paiement enregistré avec succès!');
            return $this->redirectToRoute('app_cotisation_history', ['adherent_id' => $adherent_id]);
        }

        return $this->render('paiement/new.html.twig', [
            'form' => $form->createView(),
            'adherent' => $adherent,
            'montantAttendu' => $montantAttendu,
            'resteAPayer' => $cotisation->getResteAPayer(),
            'periode' => $periode,
            'calculator' => $calculator,
        ]);
    }

    #[Route('/paiement/{id}/edit', name: 'app_paiement_edit', methods: ['GET', 'POST'])]
    public function editPaiement(Request $request, Paiement $paiement, EntityManagerInterface $em): Response
    {
        $cotisation = $paiement->getCotisation();
        $adherent = $cotisation->getAdherent();
        $ancienMontant = $paiement->getMontant();
        $anciennePeriode = $paiement->getPeriode();
        
        $oldData = [
            'montant' => $paiement->getMontant(),
            'mode_paiement' => $paiement->getModePaiement(),
            'reference' => $paiement->getReference(),
            'commentaire' => $paiement->getCommentaire(),
            'observation' => $paiement->getObservation(),
            'periode' => $paiement->getPeriode(),
        ];
        
        $form = $this->createForm(PaiementType::class, $paiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nouveauMontant = $paiement->getMontant();
            $difference = $nouveauMontant - $ancienMontant;
            $cotisation->setMontantPaye($cotisation->getMontantPaye() + $difference);
            
            $em->flush();

            $newData = [
                'montant' => $paiement->getMontant(),
                'mode_paiement' => $paiement->getModePaiement(),
                'reference' => $paiement->getReference(),
                'commentaire' => $paiement->getCommentaire(),
                'observation' => $paiement->getObservation(),
                'periode' => $paiement->getPeriode(),
            ];

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
            'commentaire' => $paiement->getCommentaire(),
            'observation' => $paiement->getObservation(),
            'periode' => $paiement->getPeriode(),
        ];
        
        if ($this->isCsrfTokenValid('delete_paiement_' . $paiement->getId(), $request->request->get('_token'))) {
            $cotisation->setMontantPaye($cotisation->getMontantPaye() - $paiement->getMontant());
            $em->remove($paiement);
            $em->flush();

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
        
        $existing = $em->getRepository(Cotisation::class)->findOneBy([
            'adherent' => $adherent,
            'periode' => $currentYear
        ]);

        if (!$existing) {
            $result = $calculator->calculateMontantWithBareme($adherent);
            
            $cotisation = new Cotisation();
            $cotisation->setAdherent($adherent);
            $cotisation->setPeriode($currentYear);
            $cotisation->setMontant($result['montant']);
            $cotisation->setMontantPaye(0);
            $cotisation->setStatut('impaye');
            $cotisation->setBaremeId($result['baremeId']);
            $cotisation->setBaremeLibelle($result['baremeLibelle']);
            
            $em->persist($cotisation);
            $em->flush();
            
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