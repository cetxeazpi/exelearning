<?php

namespace App\Repository\net\exelearning\Repository;

use App\Entity\net\exelearning\Entity\OdeUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OdeUsers|null find($id, $lockMode = null, $lockVersion = null)
 * @method OdeUsers|null findOneBy(array $criteria, array $orderBy = null)
 * @method OdeUsers[]    findAll()
 * @method OdeUsers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OdeUsersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OdeUsers::class);
    }

    /**
     * getCurrentUsers.
     *
     * @param string $odeId
     * @param string $odeVersionId
     * @param string $odeSessionId
     *
     * @return OdeUsers[]
     */
    public function getOdeUsers($odeId)
    {
        $queryBuilder = $this->createQueryBuilder('c');

        if (!empty($odeId)) {
            $queryBuilder
                ->andWhere('c.odeId = :odeId')
                ->setParameter('odeId', $odeId);
        }

        $queryBuilder->orderBy('c.lastAction', 'DESC');

        return $queryBuilder
            ->getQuery()
            ->getResult()
        ;
    }

    public function updateLastAction($odeId, $user) {

        $currentOdeUsers = $this->findBy(['odeId' => $odeId, 'user' => $user]);
        if (count($currentOdeUsers) > 0) {
            $currentOdeUser = $currentOdeUsers[0];
            $currentOdeUser->setLastAction(new \DateTime());

            $em = $this->getEntityManager();
            $em->persist($currentOdeUser);
            $em->flush();
    
        }
    }

}
