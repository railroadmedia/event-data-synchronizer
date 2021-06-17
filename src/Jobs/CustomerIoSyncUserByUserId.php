<?php

namespace Railroad\EventDataSynchronizer\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\Ecommerce\Entities\User as EcommerceUser;
use Railroad\Ecommerce\Services\UserProductService;
use Railroad\EventDataSynchronizer\Services\CustomerIoSyncService;
use Railroad\Usora\Entities\User;
use Railroad\Usora\Repositories\UserRepository;

class CustomerIoSyncUserByUserId extends CustomerIoBaseJob
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
        UserRepository $userRepository,
        UserProductService $userProductService
    ) {
        try {
            $this->user = $userRepository->find($this->user->getId());
            $accountNameBrandsToSync = config('event-data-synchronizer.customer_io_account_name_brands_to_sync', []);
            $accountNameToSyncAllBrand = config('event-data-synchronizer.customer_io_account_to_sync_all_brands');

            foreach ($accountNameBrandsToSync as $accountName => $brands) {
                // in order for this user to be synced to a specific workspace, they must have at least 1 product
                // from any of the brands which are configured to be synced to the workspace.
                $syncThisWorkspace = false;

                foreach ($brands as $brand) {
                    if ($userProductService->userHadOrHasAnyDigitalProductsForBrand(
                            new EcommerceUser($this->user->getId(), $this->user->getEmail()),
                            $brand
                        ) || $accountNameToSyncAllBrand == $brand) {
                        $syncThisWorkspace = true;
                    }
                }

                if ($syncThisWorkspace) {
                    $customerAttributes = $customerIoSyncService->getUsersCustomAttributes($this->user, $brands);

                    $customerIoService->createOrUpdateCustomerByUserId(
                        $this->user->getId(),
                        $accountName,
                        $this->user->getEmail(),
                        $customerAttributes,
                        $this->user->getCreatedAt()->timestamp
                    );
                }
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
            'Error on CustomerIoSyncUserById job trying to sync user to customer.io. User ID: '.
            $this->user->getId().' - lookupEmail: '.$this->user->getEmail()
        );

        parent::failed($exception);
    }
}