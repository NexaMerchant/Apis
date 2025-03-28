<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Admin\Reporting;

use Nicelizhi\Manage\Helpers\Reporting;

class SaleController extends ReportingController
{
    public $reportingHelper;

    public function __construct()
    {
        //parent::__construct();

        $this->reportingHelper = app(Reporting::class);
    }
    /**
     * Request param functions.
     *
     * @var array
     */
    protected $typeFunctions = [
        'total-sales'         => 'getTotalSalesStats',
        'average-sales'       => 'getAverageSalesStats',
        'total-orders'        => 'getTotalOrdersStats',
        'purchase-funnel'     => 'getPurchaseFunnelStats',
        'abandoned-carts'     => 'getAbandonedCartsStats',
        'refunds'             => 'getRefundsStats',
        'tax-collected'       => 'getTaxCollectedStats',
        'shipping-collected'  => 'getShippingCollectedStats',
        'top-payment-methods' => 'getTopPaymentMethods',
    ];
}
