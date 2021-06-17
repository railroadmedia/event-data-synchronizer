<?php

namespace Railroad\EventDataSynchronizer\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\EventDataSynchronizer\Services\CustomerIoSyncService;
use Railroad\Usora\Entities\User;
use Railroad\Usora\Repositories\UserRepository;

class CustomerIoSyncNewUserByEmail extends CustomerIoBaseJob
{
    /**
     * @var User
     */
    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @param  CustomerIoService  $customerIoService
     * @throws \Throwable
     */
    public function handle(
        CustomerIoService $customerIoService,
        CustomerIoSyncService $customerIoSyncService,
        UserRepository $userRepository
    ) {
        try {
            $this->user = $userRepository->find($this->user->getId());
            $customerAttributes = $customerIoSyncService->getUsersCustomAttributes($this->user);

            foreach (config('event-data-synchronizer.customer_io_brands_to_sync', []) as $brand) {
                $customerIoService->createOrUpdateCustomerByEmail(
                    $this->user->getEmail(),
                    $brand,
                    $customerAttributes,
                    $this->user->getId(),
                    $this->user->getCreatedAt()->timestamp
                );
            }
        } catch (Exception $exception) {
            $this->failed($exception);
        }
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     */
    public function failed(Exception $exception)
    {
        error_log(
            'Error on CustomerIoSyncNewUserByEmail job trying to sync user to customer.io. User ID: '.
            $this->user->getId().' - lookupEmail: '.$this->user->getEmail()
        );

        parent::failed($exception);
    }
}