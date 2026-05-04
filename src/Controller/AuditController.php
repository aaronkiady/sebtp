<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/audit')]
class AuditController extends AbstractController
{
    #[Route('/', name: 'app_audit_index', methods: ['GET'])]
    public function index(Request $request, AuditLogRepository $auditRepo): Response
    {
        $action = $request->query->get('action');
        $entityType = $request->query->get('entityType');
        $entityId = $request->query->get('entityId');
        $userId = $request->query->get('userId');
        $dateFrom = $request->query->get('dateFrom') ? new \DateTime($request->query->get('dateFrom')) : null;
        $dateTo = $request->query->get('dateTo') ? new \DateTime($request->query->get('dateTo')) : null;
        
        if ($entityId !== null && $entityId !== '' && is_numeric($entityId)) {
            $entityId = (int) $entityId;
        } else {
            $entityId = null;
        }
        
        if ($userId === '') {
            $userId = null;
        }

        $logs = $auditRepo->findByFilters($action, $entityType, $entityId, $userId, $dateFrom, $dateTo);
        $stats = $auditRepo->getActionsStats();

        $entityTypes = ['Liste', 'Cotisation', 'Evenement', 'Formation', 'Sebtp', 'Contact', 'Paiement'];

        return $this->render('audit/index.html.twig', [
            'logs' => $logs,
            'stats' => $stats,
            'actions' => ['CREATE', 'UPDATE', 'DELETE', 'EXPORT', 'PAYMENT'],
            'entityTypes' => $entityTypes,
        ]);
    }

    #[Route('/{id}', name: 'app_audit_show', methods: ['GET'])]
    public function show(AuditLog $auditLog): Response
    {
        // Décoder les données JSON en tableau PHP
        $oldData = $auditLog->getOldData() ? json_decode($auditLog->getOldData(), true) : null;
        $newData = $auditLog->getNewData() ? json_decode($auditLog->getNewData(), true) : null;
        
        return $this->render('audit/show.html.twig', [
            'log' => $auditLog,
            'oldData' => $oldData,
            'newData' => $newData,
        ]);
    }
}