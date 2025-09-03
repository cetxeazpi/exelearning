<?php

namespace App\Repository\net\exelearning\Repository;

use App\Entity\net\exelearning\Entity\OdeFiles;
use App\Enum\Role;
use App\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OdeFiles|null find($id, $lockMode = null, $lockVersion = null)
 * @method OdeFiles|null findOneBy(array $criteria, array $orderBy = null)
 * @method OdeFiles[]    findAll()
 * @method OdeFiles[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OdeFilesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OdeFiles::class);
    }

    // /**
    //  * @return OdeFiles[] Returns an array of OdeFiles objects
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
    public function findOneBySomeField($value): ?OdeFiles
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
     * getLastFileForOde.
     *
     * @param string $odeId
     */
    public function getLastFileForOde($odeId): ?OdeFiles
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * getLastFileForOdeVersion.
     *
     * @param string $odeVersionId
     *
     * @return OdeFiles
     */
    public function getLastFileForOdeVersion($odeVersionId)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.odeVersionId = :odeVersionId')
            ->setParameter('odeVersionId', $odeVersionId)
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds OdeFiles by fileName.
     *
     * @param string $fileName
     *
     * @return OdeFiles
     */
    public function findByFileName($fileName)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.fileName = :fileName')
            ->setParameter('fileName', $fileName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds OdeFiles by username and date.
     *
     * @param DateTime $date
     *
     * @return OdeFiles
     */
    public function listOdeFilesByDate($userName, $date)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :userName')
            ->setParameter('userName', $userName)
            ->andWhere('a.createdAt <= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * List OdeFiles by user and order by most recent updated date.
     *
     * @param string $userName
     * @param bool   $onlyManualSave
     * @param bool   $onlyMine
     *
     * @return OdeFiles
     */
    public function listOdeFilesByUser($userName, $onlyManualSave, $onlyMine = true)
    {
        $queryBuilder = $this->getMainQuery($userName, $onlyMine);

        if ($onlyManualSave) {
            $queryBuilder
                ->andWhere('a.isManualSave = :isManualSave')
                ->setParameter('isManualSave', $onlyManualSave);
        }
        
        $queryBuilder
            ->orderBy('a.updatedAt', 'DESC');

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    private function getMainQuery($userName, $onlyMine) : QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('a');

        if ($onlyMine) {
            // Only show files where the user is the owner
            $queryBuilder
                    ->andWhere('a.user = :userName')
                    ->setParameter('userName', $userName);
        } else {
            // Show files where user is owner OR where user is CONTRIBUTOR/VIEWER in ode_users
            $queryBuilder
                    ->leftJoin('App\Entity\net\exelearning\Entity\OdeUsers',
                            'ou',
                            'WITH',
                            'ou.odeId = a.odeId AND ou.user = :userName')
                    ->andWhere('(a.user = :userName OR (ou.user = :userName AND ou.role IN (:contributorRole, :viewerRole)))')
                    ->setParameter('userName', $userName)
                    ->setParameter('contributorRole', Role::COLLABORATOR)
                    ->setParameter('viewerRole', Role::VIEWER);
        }
        return $queryBuilder;
    }

    /**
     * List of 3 manual save OdeFiles by user and order by most recent updated date.
     *
     * @param string $userName
     * @param bool $onlyMine
     *
     * @return OdeFiles
     */
    public function listRecentOdeFilesByUser($userName, $onlyMine = true)
    {
        $queryBuilder = $this->getMainQuery($userName, $onlyMine);

        $queryBuilder
            ->andWhere('a.isManualSave = :isManualSave')
            ->setParameter('isManualSave', true);

        $queryBuilder
            ->orderBy('a.updatedAt', 'DESC');

        $results = $queryBuilder
            ->getQuery()
            ->getResult();

        $unique = [];
        foreach ($results as $odeFile) {
            $odeId = $odeFile->getOdeId();
            if (!isset($unique[$odeId])) {
                $unique[$odeId] = $odeFile;
            }
            if (count($unique) >= Settings::USER_RECENT_ODE_FILES_AMOUNT) {
                break;
            }
        }

        return array_values($unique);
    }

    /**
     * Finds autosaved odeFiles previous to number of files to maintain.
     *
     * @param string $odeId
     *
     * @return OdeFiles[]
     */
    public function findAutosavedFilesToCleanByMaxNumberOfFiles($odeId, $maxNumberOfFiles)
    {
        if (!isset($maxNumberOfFiles)) {
            $maxNumberOfFiles = Settings::PERMANENT_SAVE_AUTOSAVE_MAX_NUMBER_OF_FILES;
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.odeId = :odeId')
            ->setParameter('odeId', $odeId)
            ->andWhere('a.isManualSave = :isManualSave')
            ->setParameter('isManualSave', false)
            ->orderBy('a.updatedAt', 'DESC')
            ->setFirstResult($maxNumberOfFiles)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds autosaved odeFiles previous by user.
     *
     * @param string $userName
     *
     * @return OdeFiles[]
     */
    public function findUserAutosavedFilesToCleanByMaxNumberOfFiles($userName, $maxNumberOfFiles)
    {
        if (!isset($maxNumberOfFiles)) {
            $maxNumberOfFiles = Settings::PERMANENT_SAVE_AUTOSAVE_MAX_NUMBER_OF_FILES;
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :userName')
            ->setParameter('userName', $userName)
            ->andWhere('a.isManualSave = :isManualSave')
            ->setParameter('isManualSave', false)
            ->orderBy('a.updatedAt', 'DESC')
            ->setFirstResult($maxNumberOfFiles)
            ->getQuery()
            ->getResult();
    }
}
