<?php
/**
 * Copyright © 2018 Ekky Software Pty Ltd. All rights reserved.
 * Please visit http://www.ekkysoftware.com
 * See COPYING.txt for license details.
 */
namespace GoPeople\Shipping\Controller\Shipped;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order\Shipment\Validation\QuantityValidator;

class Index 
extends \Magento\Framework\App\Action\Action
{

    /** @var \Magento\Sales\Model\ResourceModel\Order\Collection */
    protected $_collection;

    /** @var \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader */
    protected $shipmentLoader;

    /** @var \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface */
    protected $shipmentValidator;

    /** @var \Magento\Sales\Model\Order\Email\Sender\ShipmentSender */
    protected $shipmentSender;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory
     * @param \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader
     * @param \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface $shipmentValidator
     * @param \Magento\Sales\Model\Order\Email\Sender\ShipmentSender $shipmentSender
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader,
        \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface $shipmentValidator,
        \Magento\Sales\Model\Order\Email\Sender\ShipmentSender $shipmentSender
    ) {
        parent::__construct($context);
        $this->_collection = $collectionFactory->create();
        $this->shipmentLoader = $shipmentLoader;
        $this->shipmentValidator = $shipmentValidator;
        $this->shipmentSender = $shipmentSender;
    }

    /**
     * Record shipped action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if ($this->getRequest()->isPost()) {
            try{
                $resutls = [];
                // Get initial data from request
                $cartId = (int) $this->getRequest()->getParam('jobId', false);
                $this->_collection->addFieldToFilter('shipping_method',['like' => \GoPeople\Shipping\Model\Carrier::CODE.'_%'])
                                  ->addFieldToFilter('gopeople_cart_id',$cartId);
                foreach($this->_collection as $_order){
                    $data = [];
                    foreach($this->getRequest()->getParam('barcodes', []) as $item){
                        foreach($_order->getAllItems() as $_item){
                            if($item['text'] == $item->getSku()) $data[$_item->getId()] = $item['qty'];
                        }
                    }
                    $tracking = [1=>[
                        'carrier_code' => \GoPeople\Shipping\Model\Carrier::CODE,
                        'title'        => "Go People",
                        'number'       => $this->getRequest()->getParam('trackingCode'),
                    ]];
                        
                    $this->shipmentLoader->setOrderId($_order->getId());
                    $this->shipmentLoader->setShipmentId(false);
                    $this->shipmentLoader->setShipment($data);
                    $this->shipmentLoader->setTracking($tracking);
                    $shipment = $this->shipmentLoader->load();
                    if (!$shipment) throw new \Magento\Framework\Exception\LocalizedException(__("Unable to create shipment for order id - %1",$_order->getIncrementId()));

                    $validationResult = $this->shipmentValidator->validate($shipment, [QuantityValidator::class]);
                    if ($validationResult->hasMessages())
                        throw new \Magento\Framework\Exception\LocalizedException(__("Shipment Document Validation Error(s):\n",implode("\n", $validationResult->getMessages())));

                    $shipment->register();

                    $this->_saveShipment($shipment);
                    $this->shipmentSender->send($shipment);

                    $results = ['error'=>false,'message'=>"The shipment has been created."];
                    break;
                }
                if(empty($results)) throw new \Magento\Framework\Exception\LocalizedException(__("Unable to find order with cart id - %1",$cartId));
                else $resultJson->setData($results);
            }
            catch(\Throwable $e){
                $resultJson->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR);
                $resultJson->setData(['error'=>true,'message'=>$e->getMessage(),'code'=>$e->getCode()]);
            }
        }
        else{
            $resultJson->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_METHOD_NOT_ALLOWED);
            $resultJson->setData(['error'=>true,'message'=>"Method not allowed",'code'=>405]);
        }
        return $resultJson;
    }

}
