<?php

namespace App\Service;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

class AuditLogger
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private Security $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        Security $security
    ) {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->security = $security;
    }

    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?string $entityLabel = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $additionalInfo = null
    ): void {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $auditLog = new AuditLog();
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setEntityLabel($entityLabel);

        if ($oldData) {
            $auditLog->setOldData($oldData);
        }

        if ($newData) {
            $auditLog->setNewData($newData);
        }

        if ($user) {
            $auditLog->setUserId($user->getUserIdentifier());
            $auditLog->setUserEmail($user->getEmail() ?? $user->getUserIdentifier());
        }

        if ($request) {
            $auditLog->setUserIp($request->getClientIp());
            $auditLog->setRoute($request->get('_route'));
        }

        $auditLog->setAdditionalInfo($additionalInfo);

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }

    public function logCreate(string $entityType, int $entityId, ?string $entityLabel = null, ?array $data = null): void
    {
        $this->log('CREATE', $entityType, $entityId, $entityLabel, null, $data);
    }

    public function logUpdate(string $entityType, int $entityId, ?string $entityLabel = null, ?array $oldData = null, ?array $newData = null): void
    {
        $this->log('UPDATE', $entityType, $entityId, $entityLabel, $oldData, $newData);
    }

    public function logDelete(string $entityType, int $entityId, ?string $entityLabel = null, ?array $data = null): void
    {
        $this->log('DELETE', $entityType, $entityId, $entityLabel, $data, null);
    }

    public function logExport(string $entityType, ?string $additionalInfo = null): void
    {
        $this->log('EXPORT', $entityType, 0, null, null, null, $additionalInfo);
    }

    public function logPayment(string $entityType, int $entityId, ?string $entityLabel = null, ?array $data = null): void
    {
        $this->log('PAYMENT', $entityType, $entityId, $entityLabel, null, $data);
    }
}