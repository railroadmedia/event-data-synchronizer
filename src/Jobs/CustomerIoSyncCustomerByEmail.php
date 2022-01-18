<?php

namespace Railroad\EventDataSynchronizer\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;
use Throwable;

class CustomerIoSyncCustomerByEmail extends CustomerIoBaseJob
{
    /**
     * @var string
     */
    private $emailAddress;

    /**
     * @var string
     */
    private $brand;

    /**
     * @var array
     */
    private $attributesToSync;

    /**
     * CustomerIoSyncCustomerByEmail constructor.
     * @param $emailAddress
     * @param $brand
     * @param  array  $attributesToSync
     */
    public function __construct($emailAddress, $brand, array $attributesToSync = [])
    {
        $this->emailAddress = $emailAddress;
        $this->brand = $brand;
        $this->attributesToSync = $attributesToSync;
    }

    /**
     * @param  CustomerIoService  $customerIoService
     * @throws Throwable
     */
    public function handle(CustomerIoService $customerIoService)
    {
        try {
            $this->reconnectToMySQLDatabases();

            $accountNameBrandsToSync = config('event-data-synchronizer.customer_io_account_name_brands_to_sync', []);
            $accountNameToSyncAllBrand = config('event-data-synchronizer.customer_io_account_to_sync_all_brands');

            foreach ($accountNameBrandsToSync as $accountName => $brands) {
                $syncThisWorkspace = false;

                foreach ($brands as $brand) {
                    if ($brand == $this->brand || $accountNameToSyncAllBrand == $brand) {
                        $syncThisWorkspace = true;
                    }
                }

                if ($syncThisWorkspace) {
                    $customerIoService->createOrUpdateCustomerByEmail(
                        $this->emailAddress,
                        $accountName,
                        $this->attributesToSync
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
            'Error on CustomerIoSyncCustomerByEmail job trying to sync customer to customer.io. Email: '.
            $this->emailAddress.' - brand: '.$this->brand
        );

        error_log($exception);

        parent::failed($exception);
    }
}