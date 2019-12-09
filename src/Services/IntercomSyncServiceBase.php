<?php

namespace Railroad\EventDataSynchronizer\Services;

use Railroad\EventDataSynchronizer\ValueObjects\IntercomAddRemoveTagsVO;
use Railroad\Usora\Entities\User;

abstract class IntercomSyncServiceBase
{
    /**
     * @var string
     */
    public static $userIdPrefix;

    /**
     * IntercomSyncServiceBase constructor.
     */
    public function __construct()
    {
        self::$userIdPrefix = config('event-data-synchronizer.intercom_user_id_prefix', 'musora_');
    }

    /**
     * If no brands are passed in this will get attributes for all brands in the config.
     *
     * @param  User  $user
     * @param  array  $brands
     */
    abstract public function syncUsersAttributes(User $user, $brands = []);

    /**
     * Because the intercom API is dump, we can only apply 1 tag to a user per request. In order to use the
     * minimum amount of requests possible, we must pull the intercom user first and see which tags are already applied.
     * We can use this info to only add or remove the tags that are necessary.
     *
     * When tagging in bulk we should not use this function rather use the intercomeo tag multiple users with 1 tag
     * functionality.
     *
     * @param  User  $user
     * @param  array  $brands
     */
    abstract public function syncUsersProductOwnershipTags(User $user, $brands = []);

    /**
     * @param  User  $user
     * @param  array  $brands
     * @return IntercomAddRemoveTagsVO
     */
    abstract public function getUsersProductOwnershipTags(User $user, $brands = []);

    /**
     * @param  User  $user
     * @return array
     */
    abstract public function getUsersBuiltInAttributes(User $user);

    /**
     * @param  User  $user
     * @param  array  $brands
     * @return array
     */
    abstract public function getUsersCustomAttributes(User $user, $brands = []);

    /**
     * @param  User  $user
     * @return array
     */
    abstract public function getUsersCustomProfileAttributes(User $user);
}