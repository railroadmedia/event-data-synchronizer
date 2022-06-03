<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;

class SyncCustomerIoExistingDevices extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'SyncCustomerIoExistingDevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync users devices in Customer io.';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SyncCustomerIoExistingDevices';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(
        DatabaseManager $databaseManager,
        CustomerIoSyncEventListener $customerIoSyncEventListener
    ) {
        $tStart = time();

        $usoraConnection = $databaseManager->connection(config('usora.database_connection_name'));
        $usoraConnection->disableQueryLog();

        $listener = $customerIoSyncEventListener;

        $thisSecond = time();
        $apiCallsThisSecond = 0;
        $devicesNr = 0;

        $usoraConnection->table('usora_user_firebase_tokens')
            ->where('usora_user_firebase_tokens.brand', '!=', '')
            ->orderBy('id', 'asc')
            ->chunk(
                25,
                function (Collection $tokenRows) use ($listener, &$apiCallsThisSecond, &$thisSecond, &$devicesNr) {
                    foreach ($tokenRows as $tokenRow) {
                        $listener->syncDevice(
                            $tokenRow->user_id,
                            $tokenRow->token,
                            $tokenRow->type,
                            $tokenRow->brand,
                            Carbon::parse($tokenRow->created_at)->timestamp
                        );
                        $apiCallsThisSecond++;
                        $devicesNr++;

                        if ($thisSecond == time()) {
                            if ($apiCallsThisSecond > 50) {
                                $this->info('Sleeping due to api calls in sec: ' . $apiCallsThisSecond);
                                sleep(1);
                            }
                        } else {
                            $thisSecond = time();
                            $apiCallsThisSecond = 0;
                        }
                    }
                }
            );

        $tEnd = time();

        $this->info(
            'Synced ' . $devicesNr . ' devices in ' . round((($tEnd - $tStart) / 60)) . ' minutes'
        );
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
