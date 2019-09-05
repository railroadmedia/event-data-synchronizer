<?php

namespace Railroad\EventDataSynchronizer\Listeners\Intercom;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\PaymentMethod;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\Ecommerce\Repositories\UserPaymentMethodsRepository;
use Railroad\Ecommerce\Repositories\UserProductRepository;
use Railroad\Intercomeo\Jobs\IntercomSyncUser;
use Railroad\Intercomeo\Services\IntercomeoService;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Repositories\UserRepository;

class IntercomSyncEventListener
{
    /**
     * @var IntercomeoService
     */
    protected $intercomeoService;

    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @var SubscriptionRepository
     */
    protected $subscriptionRepository;

    /**
     * @var UserProductRepository
     */
    protected $userProductRepository;

    /**
     * @var UserPaymentMethodsRepository
     */
    private $userPaymentMethodsRepository;

    protected const USER_ID_PREFIX = 'musora_';

    public function __construct(
        IntercomeoService $intercomeoService,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        UserProductRepository $userProductRepository,
        UserPaymentMethodsRepository $userPaymentMethodsRepository
    )
    {
        $this->intercomeoService = $intercomeoService;
        $this->userRepository = $userRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->userProductRepository = $userProductRepository;
        $this->userPaymentMethodsRepository = $userPaymentMethodsRepository;
    }

    /**
     * @param UserCreated $userCreated
     */
    public function handleUserCreated(UserCreated $userCreated)
    {
        $this->handleUserUpdated(new UserUpdated($userCreated->getUser(), $userCreated->getUser()));
    }

    /**
     * @param UserUpdated $userUpdated
     */
    public function handleUserUpdated(UserUpdated $userUpdated)
    {
        $user = $userUpdated->getNewUser();

        if (!empty($user)) {

            dispatch(
                new IntercomSyncUser(
                    self::USER_ID_PREFIX . $user->getId(), [
                        'email' => $user->getEmail(),
                        'created_at' => Carbon::parse($user->getCreatedAt())->timestamp,
                        'name' => $user->getFirstName() .
                            (!empty($user->getLastName()) ? ' ' . $user->getLastName() : ''),
                        'avatar' => ['type' => 'avatar', 'image_url' => $user->getProfilePictureUrl()],
                        'custom_attributes' => [
                            'musora_profile_display_name' => $user->getDisplayName(),
                            'musora_profile_gender' => $user->getGender(),
                            'musora_profile_country' => $user->getCountry(),
                            'musora_profile_region' => $user->getRegion(),
                            'musora_profile_city' => $user->getCity(),
                            'musora_profile_birthday' => !empty($user->getBirthday()) ?
                                $user->getBirthday()->timestamp : null,
                            'musora_phone_number' => $user->getPhoneNumber(),
                            'musora_timezone' => $user->getTimezone(),
                        ],
                    ]
                )
            );

        }
    }

    /**
     * @param PaymentMethodCreated $paymentMethodCreated
     */
    public function handleUserPaymentMethodCreated(PaymentMethodCreated $paymentMethodCreated)
    {
        $this->handleUserPaymentMethodUpdated(
            new PaymentMethodUpdated(
                $paymentMethodCreated->getPaymentMethod(),
                $paymentMethodCreated->getPaymentMethod(),
                $paymentMethodCreated->getUser()
            )
        );
    }

    /**
     * @param PaymentMethodUpdated $paymentMethodUpdated
     */
    public function handleUserPaymentMethodUpdated(PaymentMethodUpdated $paymentMethodUpdated)
    {
        $paymentMethod = $paymentMethodUpdated->getNewPaymentMethod();
        $userPaymentMethod = $this->userPaymentMethodsRepository->getByMethodId($paymentMethod->getId());

        if (!empty($paymentMethod) && !empty($paymentMethodUpdated->getUser())) {

            $brand = null;

            if ($paymentMethod->getMethodType() == PaymentMethod::TYPE_CREDIT_CARD) {

                $brand =
                    $paymentMethod->getCreditCard()
                        ->getPaymentGatewayName();

                $expirationDate = Carbon::parse(
                    $paymentMethod->getMethod()
                        ->getExpirationDate()
                )->timestamp;
            }
            elseif ($paymentMethod->getMethodType() == PaymentMethod::TYPE_PAYPAL) {
                $brand =
                    $paymentMethod->getPaypalBillingAgreement()
                        ->getPaymentGatewayName();

                $expirationDate = null;
            }

            // we only want to sync their primary payment method
            if (empty($brand) || !$userPaymentMethod->getIsPrimary() || empty($expirationDate)) {
                return;
            }

            dispatch(
                new IntercomSyncUser(
                    self::USER_ID_PREFIX .
                    $paymentMethodUpdated->getUser()
                        ->getId(), [
                        'custom_attributes' => [
                            $brand . '_primary_payment_method_expiration_date' => $expirationDate,
                        ],
                    ]
                )
            );
        }
    }

    /**
     * @param UserProductCreated $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        $this->syncUserMembershipAndProductData(
            $userProductCreated->getUserProduct()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param UserProductUpdated $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        $this->syncUserMembershipAndProductData(
            $userProductUpdated->getNewUserProduct()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param UserProductDeleted $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        $this->syncUserMembershipAndProductData(
            $userProductDeleted->getUserProduct()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param SubscriptionCreated $subscriptionCreated
     */
    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        $this->syncUserMembershipAndProductData(
            $subscriptionCreated->getSubscription()
                ->getUser()
                ->getId()
        );
    }

    /**
     * @param SubscriptionUpdated $subscriptionUpdated
     */
    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        $this->syncUserMembershipAndProductData(
            $subscriptionUpdated->getNewSubscription()
                ->getUser()
                ->getId()
        );
    }

    public function syncUserMembershipAndProductData($userId)
    {
        $userSubscriptions = $this->subscriptionRepository->getAllUsersSubscriptions($userId);
        $userProducts = $this->userProductRepository->getAllUsersProducts($userId);
    }
}