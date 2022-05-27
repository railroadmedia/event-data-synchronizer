<?php

namespace Railroad\EventDataSynchronizer\Tests\Functional;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\Product;
use Railroad\Ecommerce\Entities\User;
use Railroad\Ecommerce\Entities\UserProduct;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\EventDataSynchronizer\Services\UserMembershipFieldsService;
use Railroad\EventDataSynchronizer\Tests\EventDataSynchronizerTestCase;
use Railroad\Railcontent\Factories\ContentContentFieldFactory;
use Railroad\Railcontent\Factories\ContentFactory;
use Railroad\Railcontent\Services\ConfigService;

class UserMembershipFieldsServiceTest extends EventDataSynchronizerTestCase
{
    private EcommerceEntityManager $ecommerceEntityManager;
    private UserMembershipFieldsService $userMembershipFieldsService;
    private ContentFactory $contentFactory;
    private ContentContentFieldFactory $fieldFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ecommerceEntityManager = $this->app->make(EcommerceEntityManager::class);
        $this->userMembershipFieldsService = $this->app->make(UserMembershipFieldsService::class);
        $this->contentFactory = $this->app->make(ContentFactory::class);
        $this->fieldFactory = $this->app->make(ContentContentFieldFactory::class);
    }

    public function test_sync_no_user()
    {
        $this->userMembershipFieldsService->sync(rand());

        $this->assertDatabaseMissing('usora_users', [
            'membership_expiration_date' => null,
            'is_lifetime_member' => false,
            'access_level' => '',
        ]);
    }

    public function test_sync_no_user_products()
    {
        $userId = $this->createUserAndGetId();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => null,
            'is_lifetime_member' => false,
            'access_level' => '',
        ]);
    }

    public function test_sync_basic_recurring_monthly_membership_access()
    {
        $userId = $this->createUserAndGetId();

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_SUBSCRIPTION);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_RECURRING);
        $product->setDigitalAccessTimeIntervalType(Product::DIGITAL_ACCESS_TIME_INTERVAL_TYPE_MONTH);
        $product->setDigitalAccessTimeIntervalLength(1);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(Carbon::now()->addDay());
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => $userProduct->getExpirationDate(),
            'is_lifetime_member' => false,
            'access_level' => 'member',
        ]);
    }

    public function test_sync_basic_recurring_annual_membership_access()
    {
        $userId = $this->createUserAndGetId();

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_SUBSCRIPTION);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_RECURRING);
        $product->setDigitalAccessTimeIntervalType(Product::DIGITAL_ACCESS_TIME_INTERVAL_TYPE_YEAR);
        $product->setDigitalAccessTimeIntervalLength(1);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(Carbon::now()->addYear());
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => $userProduct->getExpirationDate(),
            'is_lifetime_member' => false,
            'access_level' => 'member',
        ]);
    }

    public function test_sync_expired_recurring_annual_membership_access()
    {
        $userId = $this->createUserAndGetId();

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_SUBSCRIPTION);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_RECURRING);
        $product->setDigitalAccessTimeIntervalType(Product::DIGITAL_ACCESS_TIME_INTERVAL_TYPE_YEAR);
        $product->setDigitalAccessTimeIntervalLength(1);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(Carbon::now()->subDays(20));
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => $userProduct->getExpirationDate(),
            'is_lifetime_member' => false,
            'access_level' => 'expired',
        ]);
    }

    public function test_sync_lifetime_annual_membership_access()
    {
        $userId = $this->createUserAndGetId();

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_SUBSCRIPTION);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(null);
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => null,
            'is_lifetime_member' => true,
            'access_level' => 'lifetime',
        ]);
    }

    public function test_sync_lifetime_annual_membership_access_that_is_expired()
    {
        $userId = $this->createUserAndGetId();

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_SUBSCRIPTION);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(Carbon::now()->subDay());
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => $userProduct->getExpirationDate(),
            'is_lifetime_member' => false,
            'access_level' => 'expired',
        ]);
    }

    public function test_sync_pack_owner()
    {
        $userId = $this->createUserAndGetId();

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_ONE_TIME);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_SPECIFIC_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_ONE_TIME);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(null);
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => null,
            'is_lifetime_member' => false,
            'access_level' => 'pack',
        ]);
    }

    public function test_sync_admin_team()
    {
        $userId = $this->createUserAndGetId(null, null, 'administrator');

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_ONE_TIME);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(null);
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => null,
            'is_lifetime_member' => 1,
            'access_level' => 'team',
        ]);
    }

    public function test_sync_coach()
    {
        $userId = $this->createUserAndGetId(null, null, 'administrator');

        $user = new User($userId, $this->faker->email);

        $slug = $this->faker->word;
        $instructor = $this->contentFactory->create($slug, 'instructor', 'published');

        $this->fieldFactory->create(
            $instructor['id'],
            'associated_user_id',
            $userId,
            1,
            'integer'
        );

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_ONE_TIME);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(null);
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => null,
            'is_lifetime_member' => 1,
            'access_level' => 'coach',
        ]);
    }

    public function test_sync_house_coach()
    {
        $userId = $this->createUserAndGetId(null, null, 'administrator');

        $user = new User($userId, $this->faker->email);

        $slug = $this->faker->word;
        $instructor = $this->contentFactory->create($slug, 'instructor', 'published');

        $this->fieldFactory->create(
            $instructor['id'],
            'associated_user_id',
            $userId,
            1,
            'integer'
        );

        $this->fieldFactory->create(
            $instructor['id'],
            'is_house_coach',
            true,
            1,
            'boolean'
        );

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_ONE_TIME);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct = new UserProduct();

        $userProduct->setUser($user);
        $userProduct->setProduct($product);
        $userProduct->setQuantity(1);
        $userProduct->setExpirationDate(null);
        $userProduct->setStartDate(Carbon::now()->subDay());
        $userProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => null,
            'is_lifetime_member' => 1,
            'access_level' => 'house-coach',
        ]);
    }

    public function test_sync_recurring_monthly_membership_access_ignore_old_user_products()
    {
        $userId = $this->createUserAndGetId();

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_SUBSCRIPTION);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_RECURRING);
        $product->setDigitalAccessTimeIntervalType(Product::DIGITAL_ACCESS_TIME_INTERVAL_TYPE_MONTH);
        $product->setDigitalAccessTimeIntervalLength(1);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $userProduct1 = new UserProduct();

        $userProduct1->setUser($user);
        $userProduct1->setProduct($product);
        $userProduct1->setQuantity(1);
        $userProduct1->setExpirationDate(Carbon::now()->subDays(25));
        $userProduct1->setStartDate(Carbon::now()->subDay());
        $userProduct1->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct1);

        $userProduct2 = new UserProduct();

        $userProduct2->setUser($user);
        $userProduct2->setProduct($product);
        $userProduct2->setQuantity(1);
        $userProduct2->setExpirationDate(Carbon::now()->addDay());
        $userProduct2->setStartDate(Carbon::now()->subDay());
        $userProduct2->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct2);

        $userProduct3 = new UserProduct();

        $userProduct3->setUser($user);
        $userProduct3->setProduct($product);
        $userProduct3->setQuantity(1);
        $userProduct3->setExpirationDate(Carbon::now()->addDays(43));
        $userProduct3->setStartDate(Carbon::now()->subDay());
        $userProduct3->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct3);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => $userProduct3->getExpirationDate(),
            'is_lifetime_member' => false,
            'access_level' => 'member',
        ]);
    }

    public function test_sync_lifetime_membership_access_ignore_old_user_products()
    {
        $userId = $this->createUserAndGetId();

        $user = new User($userId, $this->faker->email);

        $product = new Product();

        $product->setBrand($this->faker->word);
        $product->setName($this->faker->word);
        $product->setSku($this->faker->word);
        $product->setPrice($this->faker->randomNumber(4));
        $product->setType(Product::TYPE_DIGITAL_SUBSCRIPTION);
        $product->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $product->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_RECURRING);
        $product->setDigitalAccessTimeIntervalType(Product::DIGITAL_ACCESS_TIME_INTERVAL_TYPE_MONTH);
        $product->setDigitalAccessTimeIntervalLength(1);
        $product->setActive(true);
        $product->setIsPhysical(false);
        $product->setAutoDecrementStock(false);
        $product->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($product);

        $lifetimeProduct = new Product();

        $lifetimeProduct->setBrand($this->faker->word);
        $lifetimeProduct->setName($this->faker->word);
        $lifetimeProduct->setSku($this->faker->word);
        $lifetimeProduct->setPrice($this->faker->randomNumber(4));
        $lifetimeProduct->setType(Product::TYPE_DIGITAL_SUBSCRIPTION);
        $lifetimeProduct->setDigitalAccessType(Product::DIGITAL_ACCESS_TYPE_ALL_CONTENT_ACCESS);
        $lifetimeProduct->setDigitalAccessTimeType(Product::DIGITAL_ACCESS_TIME_TYPE_LIFETIME);
        $lifetimeProduct->setActive(true);
        $lifetimeProduct->setIsPhysical(false);
        $lifetimeProduct->setAutoDecrementStock(false);
        $lifetimeProduct->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($lifetimeProduct);

        $userProduct1 = new UserProduct();

        $userProduct1->setUser($user);
        $userProduct1->setProduct($product);
        $userProduct1->setQuantity(1);
        $userProduct1->setExpirationDate(Carbon::now()->subDays(25));
        $userProduct1->setStartDate(Carbon::now()->subDay());
        $userProduct1->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct1);

        $userProduct2 = new UserProduct();

        $userProduct2->setUser($user);
        $userProduct2->setProduct($product);
        $userProduct2->setQuantity(1);
        $userProduct2->setExpirationDate(Carbon::now()->addDay());
        $userProduct2->setStartDate(Carbon::now()->subDay());
        $userProduct2->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct2);

        $userProduct3 = new UserProduct();

        $userProduct3->setUser($user);
        $userProduct3->setProduct($product);
        $userProduct3->setQuantity(1);
        $userProduct3->setExpirationDate(Carbon::now()->addDays(43));
        $userProduct3->setStartDate(Carbon::now()->subDay());
        $userProduct3->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct3);

        $userProduct4 = new UserProduct();

        $userProduct4->setUser($user);
        $userProduct4->setProduct($lifetimeProduct);
        $userProduct4->setQuantity(1);
        $userProduct4->setExpirationDate(null);
        $userProduct4->setStartDate(Carbon::now()->subDay());
        $userProduct4->setCreatedAt(Carbon::now());

        $this->ecommerceEntityManager->persist($userProduct4);
        $this->ecommerceEntityManager->flush();

        $this->userMembershipFieldsService->sync($userId);

        $this->assertDatabaseHas('usora_users', [
            'membership_expiration_date' => null,
            'is_lifetime_member' => true,
            'access_level' => 'lifetime',
        ]);
    }

}