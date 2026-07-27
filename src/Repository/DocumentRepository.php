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
     * Compte les documents par année - Version DQL (recommandée)
     */
    public function countByYear(string $type, int $year): int
    {
        $qb = $this->createQueryBuilder('d');
        $qb->select('COUNT(d.id)')
           ->where('d.type = :type')
           ->andWhere('YEAR(d.dateCreation) = :year')
           ->setParameter('type', $type)
           ->setParameter('year', $year);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte les documents par adhérent, type et année
     */
    public function countByAdherentTypeAndYear(int $adherentId, string $type, int $year): int
    {
        $qb = $this->createQueryBuilder('d');
        $qb->select('COUNT(d.id)')
           ->where('d.adherent = :adherentId')
           ->andWhere('d.type = :type')
           ->andWhere('YEAR(d.dateCreation) = :year')
           ->setParameter('adherentId', $adherentId)
           ->setParameter('type', $type)
           ->setParameter('year', $year);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}