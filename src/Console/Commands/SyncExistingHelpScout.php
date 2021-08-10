<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use HelpScout\Api\Customers\Customer;
use HelpScout\Api\Customers\CustomerFilters;
use HelpScout\Api\Entity\PagedCollection;
use HelpScout\Api\Exception\RateLimitExceededException;
use Railroad\EventDataSynchronizer\Services\HelpScoutSyncService;
use Railroad\RailHelpScout\Services\RailHelpScoutService;
use Railroad\Usora\Entities\User;
use Railroad\Usora\Repositories\UserRepository;
use Throwable;

class SyncExistingHelpScout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SyncExistingHelpScout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all existing users from helpscout with matching usora users';

    const RETRY_ATTEMPTS = 3;
    const SLEEP_DELAY = 600;

    /**
     * @var DatabaseManager
     */
    private $databaseManager;

    /**
     * @var HelpScoutSyncService
     */
    protected $helpScoutSyncService;

    /**
     * @var RailHelpScoutService
     */
    protected $railHelpScoutService;

    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * SyncExistingHelpScout constructor.
     *
     * @param DatabaseManager $databaseManager
     * @param HelpScoutSyncService $helpScoutSyncService
     * @param RailHelpScoutService $railHelpScoutService
     * @param UserRepository $userRepository
     *
     */
    public function __construct(
        DatabaseManager $databaseManager,
        HelpScoutSyncService $helpScoutSyncService,
        RailHelpScoutService $railHelpScoutService,
        UserRepository $userRepository
    ) {
        parent::__construct();

        $this->databaseManager = $databaseManager;
        $this->helpScoutSyncService = $helpScoutSyncService;
        $this->railHelpScoutService = $railHelpScoutService;
        $this->userRepository = $userRepository;
    }

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle()
    {
        $currentPage = null;

        $railhelpscoutConnection = $this->databaseManager->connection(config('railhelpscout.database_connection_name'));

        while ($currentPage = $this->getNextPage($currentPage)) {

            $this->info('Started pocessing customers page #' . $currentPage->getPageNumber());

            $emailsMap = [];
            $customersMap = [];
            $customersSynced = [];

            foreach ($currentPage->toArray() as $customer) {

                $customerEmails = $customer->getEmails()->toArray();

                foreach ($customerEmails as $customerEmail) {
                    $emailsMap[$customerEmail->getValue()] = $customer->getId();
                }

                $customersMap[$customer->getId()] = $customer;
            }

            $users = $this->userRepository->findByEmails(array_keys($emailsMap));

            $existingCustomersMap =
                $railhelpscoutConnection->table('helpscout_customers')
                    ->whereIn(
                        'external_id',
                        array_keys($customersMap)
                    )
                    ->get()
                    ->pluck('internal_id')
                    ->mapWithKeys(function ($item) {
                        return [$item => true];
                    })
                    ->toArray();

            foreach ($users as $user) {

                if (!isset($existingCustomersMap[$user->getId()]) && isset($emailsMap[$user->getEmail()])) {
                    $customerId = $emailsMap[$user->getEmail()];
                    $customer = $customersMap[$customerId];

                    $this->info(
                        'Syncing user ' . $user->getEmail()
                        . ', usora id: ' . $user->getId()
                        . ', helpscout id: ' . $customer->getId()
                    );

                    $this->syncExistingCustomer($user, $customer);

                    $existingCustomersMap[$user->getId()] = true;
                }
            }

            $this->info('Finished pocessing customers page #' . $currentPage->getPageNumber());
        }
    }

    /**
     * @throws Throwable
     */
    protected function getNextPage($currentPage = null): ?PagedCollection
    {
        $attempt = 1;

        while ($attempt <= self::RETRY_ATTEMPTS) {
            try {

                $nextPage = null;

                if ($currentPage == null) {
                    $nextPage = $this->railHelpScoutService->getCustomersPage();
                } else if ($currentPage->getPageNumber() < $currentPage->getTotalPageCount()) {
                    $nextPage = $currentPage->getNextPage();
                }

                return $nextPage;

            } catch (RateLimitExceededException $rateException) {

                $this->error(
                    'RateLimitExceededException raised when fetching next helpscout customer page, sleeping for '
                    . self::SLEEP_DELAY . ' seconds'
                );

                sleep(self::SLEEP_DELAY);
                $attempt++;

            } catch (Exception $ex) {
                throw $ex;
            }
        }

        return null;
    }

    /**
     * @throws Throwable
     */
    protected function syncExistingCustomer(
        User $user,
        Customer $customer
    ) {

        $attempt = 1;

        $userAttributes = $this->helpScoutSyncService->getUsersAttributes($user);

        while ($attempt <= self::RETRY_ATTEMPTS) {
            try {

                $this->railHelpScoutService->syncExistingCustomer(
                    $user->getId(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getEmail(),
                    $userAttributes,
                    $customer
                );

                return;

            } catch (RateLimitExceededException $rateException) {

                $this->error(
                    'RateLimitExceededException raised when syncing user ' . $user->getEmail()
                    . ', sleeping for ' . self::SLEEP_DELAY . ' seconds'
                );

                sleep(self::SLEEP_DELAY);
                $attempt++;

            } catch (Exception $ex) {
                throw $ex;
            }
        }
    }
}
