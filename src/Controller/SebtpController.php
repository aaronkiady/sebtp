<?php

namespace App\Controller;

use App\Entity\Sebtp;
use App\Form\SebtpType;
use App\Repository\SebtpRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/sebtp')]
final class SebtpController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route(name: 'app_sebtp_index', methods: ['GET'])]
    public function index(Request $request, SebtpRepository $sebtpRepository): Response
    {
        $searchTerm = $request->query->get('q');
        $sebtps = $sebtpRepository->findBySearch($searchTerm);
        
        return $this->render('sebtp/index.html.twig', [
            'sebtps' => $sebtps,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/new', name: 'app_sebtp_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $sebtp = new Sebtp();
        $form = $this->createForm(SebtpType::class, $sebtp);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

        // Gestion du fichier
            $fichier = $form->get('fichiers')->getData();
            
            if ($fichier) {
                $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $fichier->guessExtension();

                try {
                    $fichier->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $sebtp->setFichiers($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors du téléchargement du fichier.');
                }
            }
            $entityManager->persist($sebtp);
            $entityManager->flush();

            // Audit log
            $this->auditLogger->logCreate(
                'Sebtp',
                $sebtp->getId(),
                $sebtp->getNomOrganisme(),
                [
                    'instance' => $sebtp->getInstance(),
                    'nom_organisme' => $sebtp->getNomOrganisme(),
                    'mandat' => $sebtp->getMandat(),
                    'nom_representant' => $sebtp->getNomRepresentant(),
                    'fichier' => $fichier ? $fichier->getClientOriginalName() : null
                ]
            );

            $this->addFlash('success', 'Organisme créé avec succès !');
            return $this->redirectToRoute('app_sebtp_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sebtp/new.html.twig', [
            'sebtp' => $sebtp,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sebtp_show', methods: ['GET'])]
    public function show(Sebtp $sebtp): Response
    {
        return $this->render('sebtp/show.html.twig', [
            'sebtp' => $sebtp,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sebtp_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sebtp $sebtp, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $oldData = [
            'instance' => $sebtp->getInstance(),
            'nom_organisme' => $sebtp->getNomOrganisme(),
            'mandat' => $sebtp->getMandat(),
            'nom_representant' => $sebtp->getNomRepresentant(),
            'observation' => $sebtp->getObservation(),
            'fichiers' => $sebtp->getFichiers(),

        ];

        $form = $this->createForm(SebtpType::class, $sebtp);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

        // Gestion du fichier
            $fichier = $form->get('fichiers')->getData();
            
            if ($fichier) {
                // Supprimer l'ancien fichier s'il existe
                $ancienFichier = $sebtp->getFichiers();
                if ($ancienFichier) {
                    $ancienChemin = $this->getParameter('uploads_directory') . '/' . $ancienFichier;
                    if (file_exists($ancienChemin)) {
                        unlink($ancienChemin);
                    }
                }
                
                $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $fichier->guessExtension();

                try {
                    $fichier->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $sebtp->setFichiers($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors du téléchargement du fichier.');
                }
            }
            $entityManager->flush();

            $newData = [
                'instance' => $sebtp->getInstance(),
                'nom_organisme' => $sebtp->getNomOrganisme(),
                'mandat' => $sebtp->getMandat(),
                'nom_representant' => $sebtp->getNomRepresentant(),
                'observation' => $sebtp->getObservation(),
            ];

            // Audit log
            $this->auditLogger->logUpdate(
                'Sebtp',
                $sebtp->getId(),
                $sebtp->getNomOrganisme(),
                $oldData,
                $newData
            );

            $this->addFlash('success', 'Organisme modifié avec succès !');
            return $this->redirectToRoute('app_sebtp_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sebtp/edit.html.twig', [
            'sebtp' => $sebtp,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/fichier/download', name: 'app_liste_fichier_download', methods: ['GET'])]
    public function downloadFichier(Sebtp $sebtp): Response
    {
        $fichier = $sebtp->getFichiers();
        
        if (!$fichier) {
            throw $this->createNotFoundException('Aucun fichier associé à cet adhérent.');
        }
        
        $chemin = $this->getParameter('uploads_directory') . '/' . $fichier;
        
        if (!file_exists($chemin)) {
            throw $this->createNotFoundException('Le fichier n\'existe plus sur le serveur.');
        }
        
        return $this->file($chemin, $fichier);
    }

    #[Route('/{id}/fichier/delete', name: 'app_liste_fichier_delete', methods: ['POST'])]
    public function deleteFichier(Request $request, Sebtp $sebtp, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_fichier_' . $sebtp->getId(), $request->request->get('_token'))) {
            $fichier = $sebtp->getFichiers();
            if ($fichier) {
                $chemin = $this->getParameter('uploads_directory') . '/' . $fichier;
                if (file_exists($chemin)) {
                    unlink($chemin);
                }
                $sebtp->setFichiers(null);
                $entityManager->flush();
                $this->addFlash('success', 'Fichier supprimé avec succès!');
            }
        }
        
        return $this->redirectToRoute('app_liste_show', ['id' => $sebtp->getId()]);
    }

    #[Route('/{id}', name: 'app_sebtp_delete', methods: ['POST'])]
    public function delete(Request $request, Sebtp $sebtp, EntityManagerInterface $entityManager): Response
    {
        $sebtpData = [
            'instance' => $sebtp->getInstance(),
            'nom_organisme' => $sebtp->getNomOrganisme(),
            'mandat' => $sebtp->getMandat(),
            'nom_representant' => $sebtp->getNomRepresentant(),
        ];

        if ($this->isCsrfTokenValid('delete' . $sebtp->getId(), $request->getPayload()->getString('_token'))) {

        // Supprimer le fichier associé
            $fichier = $sebtp->getFichiers();
            if ($fichier) {
                $chemin = $this->getParameter('uploads_directory') . '/' . $fichier;
                if (file_exists($chemin)) {
                    unlink($chemin);
                }
            }
            $entityManager->remove($sebtp);
            $entityManager->flush();

            // Audit log
            $this->auditLogger->logDelete(
                'Sebtp',
                $sebtp->getId(),
                $sebtp->getNomOrganisme(),
                $sebtpData
            );

            $this->addFlash('success', 'Organisme supprimé avec succès !');
        }

        return $this->redirectToRoute('app_sebtp_index', [], Response::HTTP_SEE_OTHER);
    }
}