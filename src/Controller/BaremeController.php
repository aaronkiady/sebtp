<?php

namespace App\Controller;

use App\Entity\Bareme;
use App\Form\BaremeType;
use App\Repository\BaremeRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/baremes')]
class BaremeController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route('/', name: 'app_bareme_index', methods: ['GET'])]
    public function index(BaremeRepository $baremeRepo): Response
    {
        $baremesActifs = $baremeRepo->getAllActifs();
        $categories = [
            'entreprise' => ['1-10', '11-50', '51+'],
            'ong' => [null],
            'sponsor' => [null],
        ];

        return $this->render('bareme/index.html.twig', [
            'baremes' => $baremesActifs,
            'categories' => $categories,
        ]);
    }

    #[Route('/history', name: 'app_bareme_history', methods: ['GET'])]
    public function history(BaremeRepository $baremeRepo): Response
    {
        $historique = $baremeRepo->findBy([], ['dateDebut' => 'DESC']);
        
        return $this->render('bareme/history.html.twig', [
            'historique' => $historique,
        ]);
    }

    #[Route('/new', name: 'app_bareme_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, BaremeRepository $baremeRepo): Response
    {
        $bareme = new Bareme();
        $form = $this->createForm(BaremeType::class, $bareme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Désactiver l'ancien barème de la même catégorie
            $baremeRepo->desactiverBaremes($bareme->getCategorie(), $bareme->getSousCategorie());
            
            $bareme->setActif(true);
            $bareme->setCreatedBy($this->getUser()->getUserIdentifier());
            
            $em->persist($bareme);
            $em->flush();

            $this->auditLogger->logCreate(
                'Bareme',
                $bareme->getId(),
                $bareme->getCategorie() . ' - ' . ($bareme->getSousCategorie() ?: 'standard'),
                [
                    'categorie' => $bareme->getCategorie(),
                    'sous_categorie' => $bareme->getSousCategorie(),
                    'montant' => $bareme->getMontant(),
                    'date_debut' => $bareme->getDateDebut()->format('Y-m-d'),
                ]
            );

            $this->addFlash('success', 'Barème créé avec succès !');
            return $this->redirectToRoute('app_bareme_index');
        }

        return $this->render('bareme/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_bareme_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Bareme $bareme, EntityManagerInterface $em): Response
    {
        $oldData = [
            'categorie' => $bareme->getCategorie(),
            'sous_categorie' => $bareme->getSousCategorie(),
            'montant' => $bareme->getMontant(),
        ];

        $form = $this->createForm(BaremeType::class, $bareme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $bareme->setUpdatedAt(new \DateTime());
            $em->flush();

            $newData = [
                'categorie' => $bareme->getCategorie(),
                'sous_categorie' => $bareme->getSousCategorie(),
                'montant' => $bareme->getMontant(),
            ];

            $this->auditLogger->logUpdate(
                'Bareme',
                $bareme->getId(),
                $bareme->getCategorie() . ' - ' . ($bareme->getSousCategorie() ?: 'standard'),
                $oldData,
                $newData
            );

            $this->addFlash('success', 'Barème modifié avec succès !');
            return $this->redirectToRoute('app_bareme_index');
        }

        return $this->render('bareme/edit.html.twig', [
            'form' => $form->createView(),
            'bareme' => $bareme,
        ]);
    }

    #[Route('/{id}', name: 'app_bareme_delete', methods: ['POST'])]
    public function delete(Request $request, Bareme $bareme, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $bareme->getId(), $request->request->get('_token'))) {
            $data = [
                'categorie' => $bareme->getCategorie(),
                'sous_categorie' => $bareme->getSousCategorie(),
                'montant' => $bareme->getMontant(),
            ];
            
            $em->remove($bareme);
            $em->flush();

            $this->auditLogger->logDelete(
                'Bareme',
                $bareme->getId(),
                $bareme->getCategorie() . ' - ' . ($bareme->getSousCategorie() ?: 'standard'),
                $data
            );

            $this->addFlash('success', 'Barème supprimé avec succès !');
        }

        return $this->redirectToRoute('app_bareme_index');
    }
}