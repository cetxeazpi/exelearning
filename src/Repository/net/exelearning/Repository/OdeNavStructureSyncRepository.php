<?php

namespace App\Repository\net\exelearning\Repository;

use App\Entity\net\exelearning\Entity\OdeComponentsSync;
use App\Entity\net\exelearning\Entity\OdeComponentsSyncProperties;
use App\Entity\net\exelearning\Entity\OdeNavStructureSync;
use App\Entity\net\exelearning\Entity\OdeNavStructureSyncProperties;
use App\Entity\net\exelearning\Entity\OdePagStructureSync;
use App\Entity\net\exelearning\Entity\OdePagStructureSyncProperties;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OdeNavStructureSync|null find($id, $lockMode = null, $lockVersion = null)
 * @method OdeNavStructureSync|null findOneBy(array $criteria, array $orderBy = null)
 * @method OdeNavStructureSync[]    findAll()
 * @method OdeNavStructureSync[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OdeNavStructureSyncRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OdeNavStructureSync::class);
    }

    // /**
    //  * @return OdeNavStructureSync[] Returns an array of OdeNavStructureSync objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?OdeNavStructureSync
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * getNavStructure.
     *
     * @param string $odeSessionId
     *
     * @return OdeNavStructureSync[]
     */
    public function getNavStructure($odeId)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.odeId = :odeId')
            ->andWhere('c.odeParentPageId IS NULL')
            ->setParameter('odeId', $odeId)
            ->addOrderBy('CASE WHEN c.odeNavStructureSync IS NULL THEN 1 ELSE 0 END', 'DESC')
            ->addOrderBy('c.odeNavStructureSync', 'ASC')
            ->addOrderBy('c.odeNavStructureSyncOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds odeNavStructureSyncs by odeSessionId.
     *
     * @param string $odeSessionId
     *
     * @return OdeNavStructureSync[]
     */
    public function findByOdeSessionId($odeId)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds max order of nav by parent nav.
     *
     * @return number
     */
    public function findMaxOrderByParentId($odeParentPageId)
    {
        return $this->createQueryBuilder('c')
            ->select('MAX(c.odeNavStructureSyncOrder)')
            ->andWhere('c.odeParentPageId = :odeParentPageId')
            ->setParameter('odeParentPageId', $odeParentPageId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Removes odeNavStructureSyncs by odeSessionId and its dependent data.
     *
     * @param string $odeSessionId
     *
     * @return number
     */
    public function removeByOdeSessionId($odeId)
    {
        // Get odeComponentsSync Ids
        $odeComponentsSyncIds = $this->getEntityManager()->createQueryBuilder()
            ->select('c.id as id')
            ->from(OdeComponentsSync::class, 'c')
            ->where('c.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery()
            ->getResult();

        // Delete odeComponentsSyncProperties
        $queryDeleteOdeComponentsSyncProperties = $this->getEntityManager()->createQueryBuilder()
            ->delete(OdeComponentsSyncProperties::class, 'cp')
            ->where('cp.odeComponentsSync IN (:odeComponentsSyncIds)')
            ->setParameter('odeComponentsSyncIds', $odeComponentsSyncIds)
            ->getQuery();

        $odeComponentsSyncPropertiesDeleted = $queryDeleteOdeComponentsSyncProperties->execute();

        // Delete odeComponentsSync
        $queryDeleteOdeComponentsSync = $this->getEntityManager()->createQueryBuilder()
            ->delete(OdeComponentsSync::class, 'c')
            ->where('c.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery();

        $odeComponentsSyncsDeleted = $queryDeleteOdeComponentsSync->execute();

        // Get odePagStructureSync Ids
        $odePagStructureSyncIds = $this->getEntityManager()->createQueryBuilder()
            ->select('p.id as id')
            ->from(OdePagStructureSync::class, 'p')
            ->where('p.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery()
            ->getResult();

        // Delete OdePagStructureSyncProperties
        $queryDeleteOdePagStructureSyncProperties = $this->getEntityManager()->createQueryBuilder()
            ->delete(OdePagStructureSyncProperties::class, 'pp')
            ->where('pp.odePagStructureSync IN (:odePagStructureSyncIds)')
            ->setParameter('odePagStructureSyncIds', $odePagStructureSyncIds)
            ->getQuery();

        $odePagStructureSyncPropertiesDeleted = $queryDeleteOdePagStructureSyncProperties->execute();

        // Delete OdePagStructureSync
        $queryDeleteOdePagStructureSync = $this->getEntityManager()->createQueryBuilder()
            ->delete(OdePagStructureSync::class, 'p')
            ->where('p.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery();

        $odePagStructureSyncsDeleted = $queryDeleteOdePagStructureSync->execute();

        // Get odeNavStructureSync Ids
        $odeNavStructureSyncIds = $this->createQueryBuilder('n')
            ->select('n.id as id')
            ->where('n.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery()
            ->getResult();

        // Delete OdeNavStructureSyncProperties
        $queryDeleteOdeNavStructureSyncProperties = $this->createQueryBuilder('np')
            ->delete(OdeNavStructureSyncProperties::class, 'np')
            ->where('np.odeNavStructureSync IN (:odeNavStructureSyncIds)')
            ->setParameter('odeNavStructureSyncIds', $odeNavStructureSyncIds)
            ->getQuery();

        $odeNavStructureSyncPropertiesDeleted = $queryDeleteOdeNavStructureSyncProperties->execute();

        // Remove nav hierarchy
        $queryUpdateOdeNavStructureSync = $this->createQueryBuilder('n')
            ->update()
            ->set('n.odeNavStructureSync', ':odeNavStructureSyncId')
            ->setParameter('odeNavStructureSyncId', null)
            ->where('n.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery();

        $queryUpdateOdeNavStructureSync->execute();

        // Delete OdeNavStructureSync
        $queryDeleteOdeNavStructureSync = $this->createQueryBuilder('n')
            ->delete()
            ->where('n.odeId = :odeId')
            // ->setParameter(':ids', array(array(1,2,3,4)), Connection::PARAM_INT_ARRAY);
            ->setParameter('odeId', $odeId)
            ->getQuery();

        $odeNavStructureSyncsDeleted = $queryDeleteOdeNavStructureSync->execute();

        return $odeNavStructureSyncsDeleted;
    }

    /**
     * Rename field odeSessionId from odeNavStructureSyncs by the last odeSessionId.
     *
     * @param string $odeSessionId
     * @param string $newOdeSessionId
     *
     * @return number
     */
    public function updateOdeSessionByLastOdeSessionId($odeId, $newOdeId)
    {
        // Update odeComponentsSync
        $queryUpdateOdeComponentsSync = $this->getEntityManager()->createQueryBuilder()
            ->update(OdeComponentsSync::class, 'c')
            ->set('c.odeId', ':newOdeId')
            ->setParameter('newOdeId', $newOdeId)
            ->where('c.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery();

        $odeComponentsSyncsUpdated = $queryUpdateOdeComponentsSync->execute();

        // Update OdePagStructureSync
        $queryUpdateOdePagStructureSync = $this->getEntityManager()->createQueryBuilder()
            ->update(OdePagStructureSync::class, 'p')
            ->set('p.odeId', ':newOdeId')
            ->setParameter('newOdeId', $newOdeId)
            ->where('p.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery();

        $odePagStructureSyncsUpdated = $queryUpdateOdePagStructureSync->execute();

        // Delete OdeNavStructureSync
        $queryUpdateOdeNavStructureSync = $this->createQueryBuilder('n')
            ->update()
            ->set('n.odeId', ':newOdeId')
            ->setParameter('newOdeId', $newOdeId)
            ->where('n.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->getQuery();

        $odeNavStructureSyncsUpdated = $queryUpdateOdeNavStructureSync->execute();

        return $odeNavStructureSyncsUpdated;
    }
}
