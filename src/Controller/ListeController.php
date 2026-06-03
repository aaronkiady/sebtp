<?php

namespace App\Controller;

use App\Entity\Liste;
use App\Form\ListeType;
use App\Repository\ListeRepository;
use App\Repository\ParticipationRepository;
use App\Service\AuditLogger;
use App\Service\ImportExcelService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/adherents')]
final class ListeController extends AbstractController
{
    private AuditLogger $auditLogger;
    private string $uploadDirectory;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
        $this->uploadDirectory = __DIR__ . '/../../public/uploads';
    }

    #[Route(name: 'app_liste_index', methods: ['GET'])]
    public function index(Request $request, ListeRepository $listeRepository): Response
    {
        $searchTerm = $request->query->get('q');
        $statut = $request->query->get('statut');
        $filiere = $request->query->get('filiere');
        $cotFMTP = $request->query->get('cotFMTP');
        $statutMenmbre = $request->query->get('statutMenmbre');
        $type = $request->query->get('type');
        $anneeAdhesion = $request->query->get('anneeAdhesion');

        $filieres = $listeRepository->getDistinctFilieres();
        $statutsMembres = $listeRepository->getDistinctStatutsMembres();
        $adhesionYears = $listeRepository->getAvailableAdhesionYears();

        $listes = $listeRepository->findByFilters(
            $searchTerm,
            $statut,
            $filiere,
            $cotFMTP,
            $statutMenmbre,
            $type,
            $anneeAdhesion
        );

        return $this->render('liste/index.html.twig', [
            'listes' => $listes,
            'searchTerm' => $searchTerm,
            'statut' => $statut,
            'filiere' => $filiere,
            'cotFMTP' => $cotFMTP,
            'statutMenmbre' => $statutMenmbre,
            'type' => $type,
            'anneeAdhesion' => $anneeAdhesion,
            'filieres' => $filieres,
            'statutsMembres' => $statutsMembres,
            'adhesionYears' => $adhesionYears,
        ]);
    }

    #[Route('/new', name: 'app_liste_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $liste = new Liste();
        $form = $this->createForm(ListeType::class, $liste);
        $form->handleRequest($request);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->render('liste/_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion du fichier
            $fichier = $form->get('fichiers')->getData();
            
            if ($fichier) {
                // Créer le dossier s'il n'existe pas
                if (!is_dir($this->uploadDirectory)) {
                    mkdir($this->uploadDirectory, 0777, true);
                }
                
                $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $fichier->guessExtension();

                try {
                    $fichier->move($this->uploadDirectory, $newFilename);
                    $liste->setFichiers($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors du téléchargement du fichier.');
                }
            }
            
            $entityManager->persist($liste);
            $entityManager->flush();

            $this->auditLogger->logCreate(
                'Liste',
                $liste->getId(),
                $liste->getNom(),
                [
                    'nom' => $liste->getNom(),
                    'email' => $liste->getEmail(),
                    'telephone' => $liste->getNumero(),
                    'statut' => $liste->getStatut(),
                    'activite' => $liste->getActivite(),
                    'filiere' => $liste->getFiliere(),
                    'nb_employes' => $liste->getNbEmployes(),
                    'fichier' => $fichier ? $fichier->getClientOriginalName() : null
                ]
            );

            $this->addFlash('success', 'Adhérent créé avec succès!');
            return $this->redirectToRoute('app_liste_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('liste/new.html.twig', [
            'liste' => $liste,
            'form' => $form,
        ]);
    }

    #[Route('/import', name: 'app_liste_import', methods: ['GET', 'POST'])]
    public function import(Request $request, ImportExcelService $importService): Response
    {
        if ($request->isMethod('POST')) {
            $file = $request->files->get('excel_file');
            
            if (!$file) {
                $this->addFlash('error', 'Veuillez sélectionner un fichier Excel.');
                return $this->redirectToRoute('app_liste_import');
            }

            if ($file->getClientOriginalExtension() !== 'xlsx') {
                $this->addFlash('error', 'Le fichier doit être au format .xlsx');
                return $this->redirectToRoute('app_liste_import');
            }

            try {
                $result = $importService->importAdherents($file);
                
                $this->addFlash('success', sprintf('Import terminé : %d adhérents importés, %d erreurs.', $result['success'], $result['errors']));
                
                foreach ($result['messages'] as $message) {
                    if (strpos($message, 'Erreur') !== false) {
                        $this->addFlash('error', $message);
                    } else {
                        $this->addFlash('info', $message);
                    }
                }
                
                // Audit log
                $this->auditLogger->logExport(
                    'ImportExcel',
                    sprintf('Import Excel - %d adhérents importés', $result['success'])
                );
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'import : ' . $e->getMessage());
            }
            
            return $this->redirectToRoute('app_liste_index');
        }

        return $this->render('liste/import.html.twig');
    }

    #[Route('/{id}', name: 'app_liste_show', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $em): Response
    {
        $liste = $em->getRepository(Liste::class)->find((int)$id);
        
        if (!$liste) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }
        
        return $this->render('liste/show.html.twig', [
            'liste' => $liste,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_liste_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $liste = $entityManager->getRepository(Liste::class)->find((int)$id);
        
        if (!$liste) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }
        
        $oldData = [
            'nom' => $liste->getNom(),
            'email' => $liste->getEmail(),
            'telephone' => $liste->getNumero(),
            'adresse' => $liste->getAdresse(),
            'statut' => $liste->getStatut(),
            'activite' => $liste->getActivite(),
            'filiere' => $liste->getFiliere(),
            'nb_employes' => $liste->getNbEmployes(),
            'cotFMTP' => $liste->getCotFMTP(),
            'dg' => $liste->getDg(),
            'statut_menmbre' => $liste->getStatutMenmbre(),
            'fichiers' => $liste->getFichiers(),
        ];

        $form = $this->createForm(ListeType::class, $liste);
        $form->handleRequest($request);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->render('liste/_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion du fichier
            $fichier = $form->get('fichiers')->getData();
            
            if ($fichier) {
                // Créer le dossier s'il n'existe pas
                if (!is_dir($this->uploadDirectory)) {
                    mkdir($this->uploadDirectory, 0777, true);
                }
                
                // Supprimer l'ancien fichier s'il existe
                $ancienFichier = $liste->getFichiers();
                if ($ancienFichier) {
                    $ancienChemin = $this->uploadDirectory . '/' . $ancienFichier;
                    if (file_exists($ancienChemin)) {
                        unlink($ancienChemin);
                    }
                }
                
                $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $fichier->guessExtension();

                try {
                    $fichier->move($this->uploadDirectory, $newFilename);
                    $liste->setFichiers($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors du téléchargement du fichier.');
                }
            }
            
            $entityManager->flush();

            $newData = [
                'nom' => $liste->getNom(),
                'email' => $liste->getEmail(),
                'telephone' => $liste->getNumero(),
                'adresse' => $liste->getAdresse(),
                'statut' => $liste->getStatut(),
                'activite' => $liste->getActivite(),
                'filiere' => $liste->getFiliere(),
                'nb_employes' => $liste->getNbEmployes(),
                'cotFMTP' => $liste->getCotFMTP(),
                'dg' => $liste->getDg(),
                'statut_menmbre' => $liste->getStatutMenmbre(),
                'fichiers' => $liste->getFichiers(),
            ];

            $this->auditLogger->logUpdate(
                'Liste',
                $liste->getId(),
                $liste->getNom(),
                $oldData,
                $newData
            );

            $this->addFlash('success', 'Adhérent modifié avec succès!');
            return $this->redirectToRoute('app_liste_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('liste/edit.html.twig', [
            'liste' => $liste,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/fichier/download', name: 'app_liste_fichier_download', methods: ['GET'])]
    public function downloadFichier(string $id, EntityManagerInterface $em): Response
    {
        $liste = $em->getRepository(Liste::class)->find((int)$id);
        
        if (!$liste) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }
        
        $fichier = $liste->getFichiers();
        
        if (!$fichier) {
            throw $this->createNotFoundException('Aucun fichier associé à cet adhérent.');
        }
        
        $chemin = $this->uploadDirectory . '/' . $fichier;
        
        if (!file_exists($chemin)) {
            throw $this->createNotFoundException('Le fichier n\'existe plus sur le serveur.');
        }
        
        return $this->file($chemin, $fichier);
    }

    #[Route('/{id}/fichier/delete', name: 'app_liste_fichier_delete', methods: ['POST'])]
    public function deleteFichier(string $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $liste = $entityManager->getRepository(Liste::class)->find((int)$id);
        
        if (!$liste) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }
        
        if ($this->isCsrfTokenValid('delete_fichier_' . $liste->getId(), $request->request->get('_token'))) {
            $fichier = $liste->getFichiers();
            if ($fichier) {
                $chemin = $this->uploadDirectory . '/' . $fichier;
                if (file_exists($chemin)) {
                    unlink($chemin);
                }
                $liste->setFichiers(null);
                $entityManager->flush();
                $this->addFlash('success', 'Fichier supprimé avec succès!');
            }
        }
        
        return $this->redirectToRoute('app_liste_show', ['id' => $liste->getId()]);
    }

    

    #[Route('/{id}', name: 'app_liste_delete', methods: ['POST'])]
    public function delete(string $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $liste = $entityManager->getRepository(Liste::class)->find((int)$id);
        
        if (!$liste) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }
        
        $adherentData = [
            'nom' => $liste->getNom(),
            'email' => $liste->getEmail(),
            'telephone' => $liste->getNumero(),
            'statut' => $liste->getStatut(),
        ];

        if ($this->isCsrfTokenValid('delete' . $liste->getId(), $request->getPayload()->getString('_token'))) {
            // Supprimer le fichier associé
            $fichier = $liste->getFichiers();
            if ($fichier) {
                $chemin = $this->uploadDirectory . '/' . $fichier;
                if (file_exists($chemin)) {
                    unlink($chemin);
                }
            }
            
            $entityManager->remove($liste);
            $entityManager->flush();

            $this->auditLogger->logDelete(
                'Liste',
                $liste->getId(),
                $liste->getNom(),
                $adherentData
            );
        }

        return $this->redirectToRoute('app_liste_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/stats', name: 'app_liste_stats', methods: ['GET'])]
    public function stats(
        string $id,
        ParticipationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $liste = $em->getRepository(Liste::class)->find((int)$id);

        if (!$liste) {
            throw $this->createNotFoundException('Adhérent introuvable');
        }

        $stats = $repo->getStatsByAdherent((int)$id);
        
        $this->addFlash('info', 'Statistiques calculées: Total=' . $stats['total'] . ', Payé=' . $stats['paye'] . ', Impayé=' . $stats['impaye']);

        return $this->render('liste/stats.html.twig', [
            'liste' => $liste,
            'stats' => $stats,
        ]);
    }

    #[Route('/rapport/anciennete', name: 'app_liste_rapport_anciennete', methods: ['GET'])]
    public function rapportAnciennete(ListeRepository $listeRepository): Response
    {
        $ancienneteData = $listeRepository->getAncienneteData();
        $stats = $listeRepository->getAncienneteStats();
        $ancienneteMoyenne = $listeRepository->getAncienneteMoyenne();
        $plusAncien = $listeRepository->getAdherentLePlusAncien();
        $plusRecent = $listeRepository->getAdherentLePlusRecent();

        $this->auditLogger->logExport(
            'RapportAnciennete',
            sprintf('Export rapport d\'ancienneté - %d adhérents', count($ancienneteData))
        );

        return $this->render('liste/rapport_anciennete.html.twig', [
            'ancienneteData' => $ancienneteData,
            'stats' => $stats,
            'ancienneteMoyenne' => $ancienneteMoyenne,
            'plusAncien' => $plusAncien,
            'plusRecent' => $plusRecent,
            'totalAdherents' => count($ancienneteData),
        ]);
    }
}