<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function findLastNumero(string $type): ?string
    {
        $result = $this->createQueryBuilder('d')
            ->select('d.numero')
            ->where('d.type = :type')
            ->setParameter('type', $type)
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['numero'] : null;
    }

    public function findDocumentsByAdherent(int $adherentId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.adherent = :adherentId')
            ->setParameter('adherentId', $adherentId)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les documents par année (version avec SQL natif)
     */
    public function countByYear(string $type, int $year): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT COUNT(*) FROM document d WHERE d.type = :type AND YEAR(d.date_creation) = :year';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('type', $type);
        $stmt->bindValue('year', $year);
        $result = $stmt->executeQuery();
        
        return (int) $result->fetchOne();
    }
}