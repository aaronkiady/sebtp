<?php

namespace App\Controller;

use App\Entity\Sebtp;
use App\Form\SebtpType;
use App\Repository\SebtpRepository;
use App\Service\AuditLogger;
use App\Service\ImportSebtpExcelService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/import', name: 'app_sebtp_import', methods: ['GET', 'POST'])]
    public function import(Request $request, ImportSebtpExcelService $importService): Response
    {
        if ($request->isMethod('POST')) {
            $file = $request->files->get('excel_file');
            
            if (!$file) {
                $this->addFlash('error', 'Veuillez sélectionner un fichier Excel.');
                return $this->redirectToRoute('app_sebtp_import');
            }

            if ($file->getClientOriginalExtension() !== 'xlsx') {
                $this->addFlash('error', 'Le fichier doit être au format .xlsx');
                return $this->redirectToRoute('app_sebtp_import');
            }

            try {
                $result = $importService->importSebtps($file);
                
                $this->addFlash('success', sprintf('Import terminé : %d organismes importés, %d erreurs.', $result['success'], $result['errors']));
                
                foreach ($result['messages'] as $message) {
                    if (strpos($message, 'Erreur') !== false) {
                        $this->addFlash('error', $message);
                    } else {
                        $this->addFlash('info', $message);
                    }
                }
                
                // Audit log
                $this->auditLogger->logExport(
                    'ImportExcelSebtp',
                    sprintf('Import Excel SEBTP - %d organismes importés', $result['success'])
                );
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'import : ' . $e->getMessage());
            }
            
            return $this->redirectToRoute('app_sebtp_index');
        }

        return $this->render('sebtp/import.html.twig');
    }

    #[Route('/new', name: 'app_sebtp_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sebtp = new Sebtp();
        $form = $this->createForm(SebtpType::class, $sebtp);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($sebtp);
            $entityManager->flush();

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
    public function edit(Request $request, Sebtp $sebtp, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SebtpType::class, $sebtp);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Organisme modifié avec succès !');
            return $this->redirectToRoute('app_sebtp_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('sebtp/edit.html.twig', [
            'sebtp' => $sebtp,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sebtp_delete', methods: ['POST'])]
    public function delete(Request $request, Sebtp $sebtp, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $sebtp->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($sebtp);
            $entityManager->flush();
            $this->addFlash('success', 'Organisme supprimé avec succès !');
        }

        return $this->redirectToRoute('app_sebtp_index', [], Response::HTTP_SEE_OTHER);
    }
}