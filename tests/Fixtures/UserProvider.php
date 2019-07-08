<?php

namespace Railroad\EventDataSynchronizer\Tests\Fixtures;

use Doctrine\Common\Inflector\Inflector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use League\Fractal\TransformerAbstract;
use Railroad\Ecommerce\Contracts\UserProviderInterface;
use Railroad\Ecommerce\Entities\User;
use Railroad\Ecommerce\Tests\EcommerceTestCase;
use Railroad\Ecommerce\Transformers\UserTransformer;

class UserProvider implements UserProviderInterface
{
    CONST RESOURCE_TYPE = 'user';

    /**
     * @param int $id
     * @return User|null
     */
    public function getUserById(int $id): ?User
    {
        $user =
            DB::table(EcommerceTestCase::TABLES['users'])
                ->find($id);

        if ($user) {
            return new User($id, $user->email);
        }

        return null;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getUsersByIds(array $ids): array
    {
        $users =
            DB::table(EcommerceTestCase::TABLES['users'])
                ->whereIn('id', $ids)
                ->get();

        $userObjects = [];

        foreach ($users as $user) {
            $userObjects[] = new User($user->id, $user->email);
        }

        return $userObjects;
    }

    /**
     * @param User $user
     * @return int
     */
    public function getUserId(User $user): int
    {
        return $user->getId();
    }

    /**
     * @return User|null
     */
    public function getCurrentUser(): ?User
    {
        if (!auth()->id()) {
            return null;
        }

        return $this->getUserById(auth()->id());
    }

    /**
     * @return int|null
     */
    public function getCurrentUserId(): ?int
    {
        return auth()->id();
    }

    /**
     * @return TransformerAbstract
     */
    public function getUserTransformer(): TransformerAbstract
    {
        return new UserTransformer();
    }

    /**
     * @param $entity
     * @param string $relationName
     * @param array $data
     */
    public function hydrateTransDomain($entity, string $relationName, array $data): void
    {
        $setterName = Inflector::camelize('set' . ucwords($relationName));

        if (isset($data['data']['type']) &&
            $data['data']['type'] === self::RESOURCE_TYPE &&
            isset($data['data']['id']) &&
            is_object($entity) &&
            method_exists($entity, $setterName)) {

            $user = $this->getUserById($data['data']['id']);

            call_user_func([$entity, $setterName], $user);
        }

        // else some exception should be thrown
    }

    /**
     * @param string $resourceType
     * @return bool
     */
    public function isTransient(string $resourceType): bool
    {
        return $resourceType !== self::RESOURCE_TYPE;
    }

    /**
     * @param string $email
     * @param string $rawPassword
     * @return User|null
     */
    public function createUser(string $email, string $rawPassword): ?User
    {
        $userId =
            DB::table(EcommerceTestCase::TABLES['users'])
                ->insertGetId(
                    [
                        'email' => $email,
                        'password' => Hash::make($rawPassword),
                        'display_name' => $email,
                    ]
                );

        return $this->getUserById($userId);
    }
}
