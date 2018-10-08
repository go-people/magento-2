<?php
/**
 * Copyright © 2018 Ekky Software Pty Ltd. All rights reserved.
 * Please visit http://www.ekkysoftware.com
 * See COPYING.txt for license details.
 */

namespace GoPeople\Shipping\Cron;

class SynchroniseOrders 
{

    /** @var \Magento\Framework\Stdlib\DateTime\DateTime */
    protected $_date;

    /** @var \Magento\Framework\DataObjectFactory */
    protected $_dataFactory;

    /** @var \Magento\Framework\Event\ObserverFactory */
    protected $_eventFactory;

    /** @var \Magento\Sales\Model\OrderFactory */
    protected $_orderFactory;

    /** @var \Magento\Sales\Model\ResourceModel\Order\Collection */
    protected $_collection;

    /** @var \GoPeople\Shipping\Model\Carrier $carrier */
    protected $_carrier;

    /**
     * @param \Magento\Framework\DataObjectFactory $dataFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param \Magento\Framework\Event\ObserverFactory $eventFactory
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory
     * @param \GoPeople\Shipping\Model\CarrierFactory $carrierFactory
     * @param \GoPeople\Shipping\Observer\SalesOrderPaymentPayFactory $observerFactory
     */
    public function __construct(
        \Magento\Framework\DataObjectFactory $dataFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\Event\ObserverFactory $eventFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \GoPeople\Shipping\Model\CarrierFactory $carrierFactory,
        \GoPeople\Shipping\Observer\SalesOrderPaymentPayFactory $observerFactory
    )
    {
        $this->_dataFactory = $dataFactory;
        $this->_date = $date;
        $this->_eventFactory = $eventFactory;
        $this->_orderFactory = $orderFactory;
        $this->_collection = $collectionFactory->create();
        $this->_carrier = $carrierFactory->create();
        $this->_observer = $observerFactory->create();
    }

    /**
     * Scans database for any orders that failed to be transferred and attempts to transfer them
     *
     */
    public function execute()
    {
        $this->_collection->addFieldToFilter('shipping_method',['like' => $this->_carrier::CODE.'_%'])
                   ->addFieldToFilter('gopeople_cart_id',['null' => true])
                   ->addFieldToFilter('state',\Magento\Sales\Model\Order::STATE_PROCESSING)
                   ->addFieldToFilter('created_at',['to' => date('Y-m-d H:i:s', $this->_date->timestamp()-(5*60))]);//test whether local or UTC time
        foreach($this->_collection as $_order){
            $order = $this->_orderFactory->create()->load($_order->getId());
            foreach($order->getInvoiceCollection() as $invoice){
                $parameters = $this->_dataFactory->create()->setInvoice($invoice);
                $event = $this->_eventFactory->create('Magento\Framework\Event\Observer')->setEvent($parameters);
                $this->_observer->execute($event);
                break;//only the first one is enough
            }
        }
    }

}