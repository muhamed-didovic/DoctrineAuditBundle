<?php

namespace DH\DoctrineAuditBundle;

use Doctrine\ORM\EntityManagerInterface;

class AuditReader
{
    /**
     * @var AuditConfiguration
     */
    private $configuration;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(AuditConfiguration $configuration, EntityManagerInterface $entityManager)
    {
        $this->configuration = $configuration;
        $this->entityManager = $entityManager;
    }

    /**
     * @return AuditConfiguration
     */
    public function getConfiguration(): AuditConfiguration
    {
        return $this->configuration;
    }

    /**
     * Returns an array of audit table names indexed by entity FQN.
     *
     * @return array
     */
    public function getEntities(): array
    {
        $entities = $this->entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        $audited = [];
        foreach ($entities as $entity) {
            if ($this->configuration->isAudited($entity)) {
                $audited[$entity] = $this->getEntityTableName($entity);
            }
        }
        ksort($audited);

        return $audited;
    }

    /**
     * Returns an array of audited entries/operations.
     *
     * @param object|string $entity
     * @param null|int      $id
     * @param int           $page
     * @param int           $pageSize
     *
     * @return array
     */
    public function getAudits($entity, $id = null, int $page = 1, int $pageSize = 50): array
    {
        $connection = $this->entityManager->getConnection();
        $auditTable = implode('', [
            $this->configuration->getTablePrefix(),
            $this->getEntityTableName(\is_string($entity) ? $entity : \get_class($entity)),
            $this->configuration->getTableSuffix(),
        ]);

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($auditTable)
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
        ;

        if (null !== $id) {
            $queryBuilder
                ->where('object_id = :object_id')
                ->setParameter('object_id', $id)
            ;
        }

        return $queryBuilder
            ->execute()
            ->fetchAll(\PDO::FETCH_CLASS, AuditEntry::class)
        ;
    }

    /**
     * @param object|string $entity
     * @param int           $id
     *
     * @return mixed
     */
    public function getAudit($entity, int $id)
    {
        $connection = $this->entityManager->getConnection();
        $auditTable = implode('', [
            $this->configuration->getTablePrefix(),
            $this->getEntityTableName(\is_string($entity) ? $entity : \get_class($entity)),
            $this->configuration->getTableSuffix(),
        ]);

        /**
         * @var \Doctrine\DBAL\Query\QueryBuilder
         */
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($auditTable)
            ->where('id = :id')
            ->setParameter('id', $id);

        return $queryBuilder
            ->execute()
            ->fetchAll(\PDO::FETCH_CLASS, AuditEntry::class)
        ;
    }

    /**
     * Returns the table name of $entity.
     *
     * @param object|string $entity
     *
     * @return string
     */
    public function getEntityTableName($entity): string
    {
        return $this
            ->entityManager
            ->getClassMetadata($entity)
            ->table['name']
        ;
    }
}
