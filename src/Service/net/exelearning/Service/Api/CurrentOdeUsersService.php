<?php

namespace App\Service\net\exelearning\Service\Api;

use App\Constants;
use App\Entity\net\exelearning\Entity\CurrentOdeUsers;
use App\Entity\net\exelearning\Entity\OdeFiles;
use App\Entity\net\exelearning\Entity\OdeNavStructureSync;
use App\Entity\net\exelearning\Entity\OdeUsers;
use App\Entity\net\exelearning\Entity\User;
use App\Enum\Role;
use App\Util\net\exelearning\Util\Util;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CurrentOdeUsersService implements CurrentOdeUsersServiceInterface
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Inserts CurrentOdeUsers from its data.
     *
     * @param string $odeId
     * @param string $odeVersionId
     * @param string $odeSessionId
     * @param User   $user
     * @param string $clientIp
     *
     * @return CurrentOdeUsers
     */
    public function createCurrentOdeUsers($odeId, $odeVersionId, $odeSessionId, $user, $clientIp)
    {
        $currentOdeUsers = new CurrentOdeUsers();
        $currentOdeUsers->setOdeId($odeId);
        $currentOdeUsers->setOdeVersionId($odeVersionId);
        $currentOdeUsers->setOdeSessionId($odeSessionId);
        $currentOdeUsers->setUser($user->getUserIdentifier());
        $currentOdeUsers->setLastAction(new \DateTime());
        $currentOdeUsers->setLastSync(new \DateTime());
        $currentOdeUsers->setSyncSaveFlag(false);
        $currentOdeUsers->setSyncNavStructureFlag(false);
        $currentOdeUsers->setSyncPagStructureFlag(false);
        $currentOdeUsers->setSyncComponentsFlag(false);
        $currentOdeUsers->setSyncUpdateFlag(false);
        $currentOdeUsers->setNodeIp($clientIp);

        $this->entityManager->persist($currentOdeUsers);
        $this->entityManager->flush();

        return $currentOdeUsers;
    }

    /**
     * Creates CurrentOdeUsers with secure ode_id generation.
     *
     * @param string $odeVersionId
     * @param string $odeSessionId
     * @param User   $user
     * @param string $clientIp
     *
     * @return CurrentOdeUsers
     */
    public function createCurrentOdeUsersWithSecureId($odeVersionId, $odeSessionId, $user, $clientIp)
    {
        // Use secure ID generation for ode_id
        $odeId = Util::generateSecureId();
        
        return $this->createCurrentOdeUsers($odeId, $odeVersionId, $odeSessionId, $user, $clientIp);
    }

    /**
     * Inserts or updates CurrentOdeUsers from OdeNavStructureSync data.
     *
     * @param OdeNavStructureSync $odeNavStructureSync
     * @param User                $user
     * @param string              $clientIp
     *
     * @return CurrentOdeUsers
     */
    public function insertOrUpdateFromOdeNavStructureSync(OdeNavStructureSync $odeNavStructureSync, $user, $clientIp)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentOdeSessionForUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUserIdentifier());

        if (!empty($currentOdeSessionForUser)) {
            $currentOdeSessionForUser->setCurrentPageId($odeNavStructureSync->getOdePageId());
            $currentOdeSessionForUser->setLastSync(new \DateTime());
        } else {
            $odeId = Util::generateId();

            $odeVersionId = Util::generateId();
            // TODO check if this is correct
            $odeSessionId = $odeNavStructureSync->getOdeSessionId();

            // Insert into current_ode_users
            $currentOdeSessionForUser = new CurrentOdeUsers();
            $currentOdeSessionForUser->setOdeId($odeId);
            $currentOdeSessionForUser->setOdeVersionId($odeVersionId);
            $currentOdeSessionForUser->setOdeSessionId($odeSessionId);
            $currentOdeSessionForUser->setUser($user->getUserIdentifier());
            $currentOdeSessionForUser->setLastAction(new \DateTime());
            $currentOdeSessionForUser->setLastSync(new \DateTime());
            $currentOdeSessionForUser->setSyncSaveFlag(false);
            $currentOdeSessionForUser->setSyncNavStructureFlag(false);
            $currentOdeSessionForUser->setSyncPagStructureFlag(false);
            $currentOdeSessionForUser->setSyncComponentsFlag(false);
            $currentOdeSessionForUser->setNodeIp($clientIp);

            $currentOdeSessionForUser->setCurrentPageId($odeNavStructureSync->getOdePageId());
            $currentOdeSessionForUser->setCurrentBlockId(null);
            $currentOdeSessionForUser->setCurrentComponentId(null);
        }

        $this->entityManager->persist($currentOdeSessionForUser);
        $this->entityManager->flush();

        return $currentOdeSessionForUser;
    }

    /**
     * Updates current idevice CurrentOdeUser.
     *
     * @param OdeNavStructureSync $odeNavStructureSync
     * @param string              $blockId
     * @param string              $odeIdeviceId
     * @param User                $user
     * @param array               $odeCurrentUsersFlags
     *
     * @return CurrentOdeUsers
     */
    public function updateCurrentIdevice($odeNavStructureSync, $blockId, $odeIdeviceId, $user, $odeCurrentUsersFlags)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentOdeSessionForUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUserIdentifier());

        // Transform flags to boolean number
        $odeCurrentUsersFlags = $this->currentOdeUsersFlagsToBoolean($odeCurrentUsersFlags);

        // Update current user
        $currentOdeSessionForUser->setLastSync(new \DateTime());

        if (!empty($odeCurrentUsersFlags['odeComponentFlag'])) {
            $currentOdeSessionForUser->setSyncComponentsFlag($odeCurrentUsersFlags['odeComponentFlag']);
            $currentOdeSessionForUser->setCurrentComponentId($odeIdeviceId);
            $currentOdeSessionForUser->setCurrentBlockId($blockId);
            $currentOdeSessionForUser->setCurrentPageId($odeNavStructureSync->getOdePageId());
        } elseif (!empty($odeCurrentUsersFlags['odePagStructureFlag'])) {
            $currentOdeSessionForUser->setSyncPagStructureFlag($odeCurrentUsersFlags['odePagStructureFlag']);
            $currentOdeSessionForUser->setCurrentBlockId($blockId);
            $currentOdeSessionForUser->setCurrentPageId($odeNavStructureSync->getOdePageId());
        } elseif (!empty($odeCurrentUsersFlags['odeNavStructureFlag'])) {
            $currentOdeSessionForUser->setSyncNavStructureFlag($odeCurrentUsersFlags['odeNavStructureFlag']);
        } else {
            $currentOdeSessionForUser->setSyncComponentsFlag($odeCurrentUsersFlags['odeComponentFlag']);
            $currentOdeSessionForUser->setCurrentComponentId(null);
            $currentOdeSessionForUser->setCurrentBlockId(null);
            $currentOdeSessionForUser->setCurrentPageId($odeNavStructureSync->getOdePageId());
        }

        $this->entityManager->persist($currentOdeSessionForUser);
        $this->entityManager->flush();

        return $currentOdeSessionForUser;
    }

    /**
     * Convert the values ​​to booleans.
     *
     * @param array $odeCurrentUsersFlags
     *
     * @return array
     */
    private function currentOdeUsersFlagsToBoolean($odeCurrentUsersFlags)
    {
        foreach ($odeCurrentUsersFlags as $key => $odeCurrentUsersFlag) {
            if ('true' == $odeCurrentUsersFlag) {
                $odeCurrentUsersFlags[$key] = 1;
            } else {
                $odeCurrentUsersFlags[$key] = 0;
            }
        }

        return $odeCurrentUsersFlags;
    }

    /**
     * Inserts or updates CurrentOdeUsers from root node data.
     *
     * @param User   $user
     * @param string $clientIp
     *
     * @return CurrentOdeUsers
     */
    public function insertOrUpdateFromRootNode($user, $clientIp)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentOdeSessionForUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUserIdentifier());

        if (!empty($currentOdeSessionForUser)) {
            $currentOdeSessionForUser->setCurrentPageId(Constants::ROOT_NODE_IDENTIFIER);
            $currentOdeSessionForUser->setCurrentBlockId(null);
            $currentOdeSessionForUser->setCurrentComponentId(null);
            $currentOdeSessionForUser->setLastSync(new \DateTime());
        }

        if (!empty($currentOdeSessionForUser)) {
            $this->entityManager->persist($currentOdeSessionForUser);

            $this->entityManager->flush();
        }

        return $currentOdeSessionForUser;
    }

    /**
     * Checks if the user passed as param is the only one who is editing the content and updates CurrentOdeUser.
     *
     * @param User   $user
     * @param string $odeId
     * @param string $odeVersionId
     * @param string $odeSessionId
     * @param string $newOdeSessionId
     *
     * @return bool
     */
    public function updateLastUserOdesId($user, $odeId, $odeVersionId, $odeSessionId, $newOdeSessionId)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);

        $currentOdeUsers = $currentOdeUsersRepository->getCurrentUsers($odeId, null, $odeSessionId);

        $userIsEditing = false;
        foreach ($currentOdeUsers as $currentOdeUser) {
            if ($currentOdeUser->getUser() == $user->getUserName()) {
                $userIsEditing = true;
                if ($userIsEditing && (1 == count($currentOdeUsers))) {
                    $isLastUser = true;
                    $currentOdeUser->setOdeId($odeId);
                    $currentOdeUser->setOdeVersionId($odeVersionId);
                    $currentOdeUser->setOdeSessionId($newOdeSessionId);
                } else {
                    $isLastUser = false;
                }
            } else {
                $this->logger->error('User is not editing', ['user' => $user->getUsername(), 'odeId' => $odeId, 'file:' => $this, 'line' => __LINE__]);
            }
        }

        return $isLastUser;
    }

    /**
     * Update current user odeId, only for users who join (shared session).
     *
     * @param string $odeSessionId
     * @param User   $user
     */
    public function updateSyncCurrentUserOdeId($odeSessionId, $user)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);

        // Get current user
        $currentUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUserName());

        // Users with the same sessionId
        $currentOdeUsers = $currentOdeUsersRepository->getCurrentUsers(null, null, $odeSessionId);

        foreach ($currentOdeUsers as $currentOdeUser) {
            // Case session is the same and other user
            if ($currentOdeUser->getUser() !== $user->getUserName() && $currentOdeUser->getOdeSessionId() == $currentUser->getOdeSessionId()) {
                $currentUser->setOdeId($currentOdeUser->getOdeId());
                break;
            }
        }

        $this->entityManager->persist($currentOdeUser);
        $this->entityManager->flush();
    }

    /**
     * Checks if the user passed as param is the only one who is editing the content.
     *
     * @param User   $user
     * @param string $odeId
     * @param string $odeVersionId
     * @param string $odeSessionId
     *
     * @return bool
     */
    public function isLastUser($user, $odeId, $odeVersionId, $odeSessionId)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);

        $currentOdeUsers = $currentOdeUsersRepository->getCurrentUsers($odeId, null, $odeSessionId);

        $userIsEditing = false;
        foreach ($currentOdeUsers as $currentOdeUser) {
            if ($currentOdeUser->getUser() == $user->getUserName()) {
                $userIsEditing = true;
                break;
            }
        }

        if (!$userIsEditing) {
            $this->logger->error('User is not editing', ['user' => $user->getUsername(), 'odeId' => $odeId, 'file:' => $this, 'line' => __LINE__]);
        }

        if ($userIsEditing && (1 == count($currentOdeUsers))) {
            $isLastUser = true;
        } else {
            $isLastUser = false;
        }

        return $isLastUser;
    }

    /**
     * Returns OdeId from CurrentOdeUsers for user and odeSessionId.
     *
     * @param User   $user
     * @param string $odeSessionId
     *
     * @return string
     */
    public function getOdeIdByOdeSessionId($user, $odeSessionId)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);

        $odeId = null;

        $currentSessionForUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUsername());

        if ((!empty($currentSessionForUser)) && ($currentSessionForUser->getOdeSessionId() == $odeSessionId)) {
            $odeId = $currentSessionForUser->getOdeId();
        }

        return $odeId;
    }

    /**
     * Returns OdeVersionId from CurrentOdeUsers for user and odeSessionId.
     *
     * @param User   $user
     * @param string $odeSessionId
     *
     * @return string
     */
    public function getOdeVersionIdByOdeSessionId($user, $odeSessionId)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);

        $odeVersionId = null;

        $currentSessionForUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUsername());

        if ((!empty($currentSessionForUser)) && ($currentSessionForUser->getOdeSessionId() == $odeSessionId)) {
            $odeVersionId = $currentSessionForUser->getOdeVersionId();
        }

        return $odeVersionId;
    }

    /**
     * Checks SyncSaveFlag state on CurrentOdeUsers.
     *
     * @return bool
     */
    public function checkSyncSaveFlag(?string $odeId, string $odeSessionId)
    {
        // If no odeId is available, we assume there is no concurrent saving.
        if (empty($odeId)) {
            return false;
        }

        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentOdeUsers = $currentOdeUsersRepository->getCurrentUsers($odeId, null, $odeSessionId);

        foreach ($currentOdeUsers as $currentOdeUser) {
            $syncSaveFlag = $currentOdeUser->getSyncSaveFlag();
            if (true == $syncSaveFlag) {
                return true;
            }
        }

        // Case syncSaveFlag isn't true
        return false;
    }

    /**
     * Checks if another user in the session has the idevice open.
     *
     * @param string $odeSessionId
     * @param string $odeIdeviceId
     * @param string $odeBlockId
     * @param User   $user
     *
     * @return bool
     */
    public function checkIdeviceCurrentOdeUsers($odeId, $odeIdeviceId, $odeBlockId, $user)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentOdeUsers = $currentOdeUsersRepository->getCurrentUsers($odeId, null, null);
        $user = $user->getUsername();
        foreach ($currentOdeUsers as $currentOdeUser) {
            $concurrentUser = $currentOdeUser->getUser();
            $currentComponentId = $currentOdeUser->getCurrentComponentId();
            $currentBlockId = $currentOdeUser->getCurrentBlockId();
            if (!empty($odeIdeviceId)) {
                if ($concurrentUser !== $user && $currentComponentId == $odeIdeviceId) {
                    return false;
                }
            } else {
                if ($concurrentUser !== $user && $currentBlockId == $odeBlockId) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check and join session using either odeId or odeSessionId as primary identifier.
     *
     * @param string $odeId
     * @param string $odeSessionId
     * @param User   $user
     *
     * @return array|false Returns session data on success, false on failure
     */
    public function checkAndJoinSession($odeId, $odeSessionId, $user)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        
        // Use the new repository method for better performance
        $currentUsers = $currentOdeUsersRepository->findByOdeIdAndSessionId($odeId, $odeSessionId);

        if (empty($currentUsers)) {
            return false;
        }

        // Get the first user's session data to establish the session
        $firstUser = $currentUsers[0];
        $sessionOdeId = $firstUser->getOdeId();
        $sessionOdeSessionId = $firstUser->getOdeSessionId();

        // Get current user's session
        $currentUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUsername());
        
        if (!$currentUser) {
            return false;
        }

        // Update current user to join the session
        $currentUser->setOdeId($sessionOdeId);
        $currentUser->setOdeSessionId($sessionOdeSessionId);
        $currentUser->setOdeVersionId($firstUser->getOdeVersionId());

        $this->entityManager->persist($currentUser);
        $this->entityManager->flush();

        return [
            'odeId' => $sessionOdeId,
            'odeSessionId' => $sessionOdeSessionId,
            'odeVersionId' => $firstUser->getOdeVersionId()
        ];
    }

    /**
     * Removes the user syncSaveFlag activated value.
     *
     * @param User $user
     */
    public function removeActiveSyncSaveFlag($user)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentOdeUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUsername());

        // Set 0 to syncSaveFlag
        $currentOdeUser->setSyncSaveFlag(0);

        $this->entityManager->persist($currentOdeUser);
        $this->entityManager->flush();
    }

    /**
     * Activate the user syncSaveFlag.
     *
     * @param User $user
     */
    public function activateSyncSaveFlag($user)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentOdeUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUsername());

        // Set 1 to syncSaveFlag
        $currentOdeUser->setSyncSaveFlag(1);

        $this->entityManager->persist($currentOdeUser);
        $this->entityManager->flush();
    }

    /**
     * Removes the user syncSaveFlag activated value.
     *
     * @param User $user
     */
    public function removeActiveSyncComponentsFlag($user)
    {
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentOdeUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUsername());

        // Set 0 to syncSaveFlag and remove block/idevice
        $currentOdeUser->setSyncComponentsFlag(0);
        $currentOdeUser->setCurrentComponentId(null);
        $currentOdeUser->setCurrentBlockId(null);

        $this->entityManager->persist($currentOdeUser);
        $this->entityManager->flush();
    }

    /**
     * Examines number of current users on page.
     *
     * @param string $odeSessionId
     * @param User   $user
     */
    public function checkCurrentUsersOnSamePage($odeId, $user)
    {
        $response = [];
        // Get currentOdeUser
        $currentOdeUsersRepository = $this->entityManager->getRepository(CurrentOdeUsers::class);
        $currentSessionForUser = $currentOdeUsersRepository->getCurrentSessionForUser($user->getUsername());
        // Get currentPageId
        $currentPageId = $currentSessionForUser->getCurrentPageId();

        // Check if any user is on the same page
        $currentOdeUsers = $currentOdeUsersRepository->getCurrentUsers($odeId, null, null);

        foreach ($currentOdeUsers as $currentOdeUser) {
            $concurrentUser = $currentOdeUser->getUser();
            if ($concurrentUser !== $user->getUsername()) {
                // Current user on same page
                if ($currentOdeUser->getCurrentPageId() == $currentPageId) {
                    $response['responseMessage'] = 'There are more users on the page';
                    $response['isAvailable'] = false;

                    return $response;
                }
            }
        }

        $response['responseMessage'] = 'Page without users';
        $response['isAvailable'] = true;

        return $response;
    }

    /**
     * Add user as the user first created of the session in order to apply theme to the rest of users.
     *
     * @param User   $user
     * @param string $odeId
     * @param role $role
     * @param string $nodeIp
     */
    public function addUserToOdeIfNotExit($user, $odeId, Role $role, $nodeIp)
    {
        $odeUserRepository = $this->entityManager->getRepository(OdeUsers::class);
        $currentOdeUsers = $odeUserRepository->getOdeUsers($odeId);
        $userRepository = $this->entityManager->getRepository(User::class);
        $username = $user->getUsername();

        foreach ($currentOdeUsers as $currentOdeUser) {
            $concurrentUser = $currentOdeUser->getUser();
            if ($concurrentUser === $username) {
                if ($role->value != $currentOdeUser->getRole()->value) {
                    if ($currentOdeUser->getRole() === Role::OWNER) {
                        $this->logger->error("Can't change user " . $concurrentUser . ' to role ' . $role->value . ' because is the ' . Role::OWNER->value . ' of the ode_id ' . $odeId );
                        return false;
                    } else {
                        $currentOdeUser->setRole($role);
                        $this->entityManager->persist($currentOdeUser);
                        $this->entityManager->flush();
                    }
                }
                // Get user form database user
                $user = $userRepository->findBy(['email' => $concurrentUser]);
                if (!empty($user)) {
                    return $user[0];
                }
            }
        }

        // Create new OdeUsers entry for this user
        $newOdeUser = new OdeUsers();
        $newOdeUser->setOdeId($odeId);
        $newOdeUser->setUser($username);
        $newOdeUser->setRole($role);
        $newOdeUser->setLastAction(new \DateTime());
        $newOdeUser->setNodeIp($nodeIp);

        $this->entityManager->persist($newOdeUser);
        $this->entityManager->flush();

        $sameUser = $userRepository->findBy(['email' => $username]);
        if (!empty($sameUser)) {

            return $sameUser[0];
        } else {
            return false;
        }
    }

    /**
     * @param $user
     * @param $odeId
     * @param $nodeIp
     * @return void
     */
    public function addOwnerToOdeIfNotExit($user, $odeId, $nodeIp) {
        $odeUserRepository = $this->entityManager->getRepository(OdeUsers::class);
        $currentOdeUsers = $odeUserRepository->getOdeUsers($odeId);

        if (count($currentOdeUsers) == 0) {


            $odeFilesRepository = $this->entityManager->getRepository(OdeFiles::class);
            $lastOdeFileByOdeId = $odeFilesRepository->getLastFileForOde($odeId);
            if (!empty($lastOdeFileByOdeId)) {
                $userPropietary = $lastOdeFileByOdeId->getUser();
                // Check that last ode file is from another user
                if ($userPropietary == $user) {
                    $userPropietary = null;
                }
            }

            $newOdeUser = new OdeUsers();
            $newOdeUser->setOdeId($odeId);
            if (isset($userPropietary)) {
                $newOdeUser->setUser($userPropietary->getUsername());
            } else {
                $newOdeUser->setUser($user->getUsername());
            }
            $newOdeUser->setRole(Role::OWNER);
            $newOdeUser->setLastAction(new \DateTime());
            $newOdeUser->setNodeIp($nodeIp);

            $this->entityManager->persist($newOdeUser);
            $this->entityManager->flush();

        }
    }
}
