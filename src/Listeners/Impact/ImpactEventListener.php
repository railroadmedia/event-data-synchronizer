<?php
namespace Railroad\EventDataSynchronizer\Listeners\Impact;
use Carbon\Carbon;
use Railroad\Ecommerce\Events\OrderEvent;
use Railroad\EventDataSynchronizer\Jobs\ImpactTrackConversion;

class ImpactEventListener
{
    private $queueConnectionName;

    private $queueName;


    public function __construct() {
        $this->queueConnectionName = config('event-data-synchronizer.impact_queue_connection_name', 'database');
        $this->queueName = config('event-data-synchronizer.impact_queue_name', 'impact');
    }

    // todo: this should use the Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewed event, OrderEvent is not
    // triggered for renewal payments
    
    /**
     * @param  OrderEvent  $orderEvent
     */
    public function handleOrderPlaced(OrderEvent $orderEvent)
    {

        $order = $orderEvent->getOrder();

        dispatch(
            (new ImpactTrackConversion($order))
                ->onConnection($this->queueConnectionName)
                ->onQueue($this->queueName)
                ->delay(Carbon::now()->addSeconds(3))
        );

    }

}