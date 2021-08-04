<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use HelpScout\Api\Exception\AuthenticationException;
use Railroad\RailHelpScout\Services\RailHelpScoutService;

class HelpScoutIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'HelpScoutIndex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull the first customers page from helpscout API and show page info';

    /**
     * @var RailHelpScoutService
     */
    protected $railHelpScoutService;

    /**
     * SyncExistingHelpScout constructor.
     *
     * @param RailHelpScoutService $railHelpScoutService
     *
     */
    public function __construct(
        RailHelpScoutService $railHelpScoutService
    ) {
        parent::__construct();

        $this->railHelpScoutService = $railHelpScoutService;
    }

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle()
    {
        try {
            $page = $this->railHelpScoutService->getCustomersPage();

            $this->info('Pulled page #' . $page->getPageNumber());
            $this->info('total pages count: ' . $page->getTotalPageCount());
            $this->info('page size setting: ' . $page->getPageSize());
            $this->info('actual customers count on current page: ' . $page->getPageElementCount());
            $this->info('total customers count: ' . $page->getTotalElementCount());

        } catch (AuthenticationException $authenticationException) {
            $this->error(
                'AuthenticationException raised, please setup correct ENV values for keys HELPSCOUT_APP_ID and HELPSCOUT_APP_SECRET'
            );
        }
    }
}
