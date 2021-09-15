<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Railroad\Ecommerce\Repositories\OrderRepository;
use Railroad\Ecommerce\Repositories\PaymentRepository;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;

class SyncCustomerIoOldEvents extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'SyncCustomerIoOldEvents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all old orders, payments and membership renewed and create proper events in Customer io.';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SyncCustomerIoOldEvents {--brand=}';

    /**
     * @var DatabaseManager
     */
    private $databaseManager;
    /**
     * @var CustomerIoSyncEventListener
     */
    private $customerIoSyncEventListener;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var PaymentRepository
     */
    private $paymentRepository;

    /**
     * @param DatabaseManager $databaseManager
     * @param CustomerIoSyncEventListener $customerIoSyncEventListener
     * @param OrderRepository $orderRepository
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        DatabaseManager $databaseManager,
        CustomerIoSyncEventListener $customerIoSyncEventListener,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository
    ) {
        parent::__construct();

        $this->databaseManager = $databaseManager;
        $this->customerIoSyncEventListener = $customerIoSyncEventListener;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $brand = $this->option('brand');

        $ecommerceConnection = $this->databaseManager->connection(config('ecommerce.database_connection_name'));

        $query =
            $ecommerceConnection->table('ecommerce_orders')
                ->join('ecommerce_order_payments', 'ecommerce_orders.id', '=', 'ecommerce_order_payments.order_id')
                ->where('ecommerce_orders.user_id', '!=', null)
                ->orderBy('ecommerce_orders.id', 'desc');

        if (!empty($brand)) {
            $query->where('brand', $brand);
        }

        $done = 0;

        $query->chunk(1, function (Collection $orderRows) use (&$done) {
            foreach ($orderRows as $orderRow) {
                $order = $this->orderRepository->find($orderRow->order_id);
                $payment = $this->paymentRepository->find($orderRow->payment_id);

                // $this->customerIoSyncEventListener->syncOrder($order, $payment);
dd('testing');
                $done++;
            }

            $this->info('Done ' . $done);
        });
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
