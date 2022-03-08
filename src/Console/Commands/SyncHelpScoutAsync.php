<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Railroad\EventDataSynchronizer\Jobs\SynchUsoraHelpscout;

class SyncHelpScoutAsync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SyncHelpScoutAsync {user=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all database users with helpscout using async jobs';

    private $queueConnectionName = 'database';
    private $queueName = 'usorahelpscout';

    public function __construct(
    ) {
        parent::__construct();

        $this->queueConnectionName = config('event-data-synchronizer.usora_helpscout_queue_connection_name', 'database');
        $this->queueName = config('event-data-synchronizer.usora_helpscout_queue_name', 'usorahelpscout');
    }

    public function handle()
    {
        $userId = $this->argument('user');

        dispatch(
            (new SynchUsoraHelpscout((int) $userId))
                ->onConnection($this->queueConnectionName)
                ->onQueue($this->queueName)
        );

        $this->info('Dispatched SynchUsoraHelpscout to start syncing usora users starting with id: ' . $userId);
    }
}
