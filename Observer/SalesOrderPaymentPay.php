<?php
/**
 * Copyright © 2018 Ekky Software Pty Ltd. All rights reserved.
 * Please visit http://www.ekkysoftware.com
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace GoPeople\Shipping\Observer;

use \Magento\Framework\Event\Observer;
use \Magento\Framework\Event\ObserverInterface;
use \Magento\Sales\Model\Order;
use \Magento\Store\Model\ScopeInterface;

class SalesOrderPaymentPay implements ObserverInterface 
{

     /** @var Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;

     /** @var Magento\Framework\Json\Helper\Data */
    protected $_jsonHelper;

     /** @var GoPeople\Shipping\Model\Carrier */
    protected $_carrier;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \GoPeople\Shipping\Model\CarrierFactory $carrierFactory
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \GoPeople\Shipping\Model\CarrierFactory $carrierFactory
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_jsonHelper = $jsonHelper;
        $this->_carrier = $carrierFactory->create();
    }

    /*
     * Send Order to GoPeople after payment is successful
     */
    public function execute(Observer $observer) {

        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        $shipping = $order->getShippingAddress();
        $method = $order->getShippingMethod();
        $code_l = strlen($this->_carrier::CODE);
        if(substr($method,0,$code_l) == $this->_carrier::CODE){
            $method = str_replace('-',' ',substr($method,$code_l+1));

            $parcels = [];
            foreach($order->getAllItems() as $item){
                if (0 < $item->getQtyToShip()) $parcels[] = [
                                                        'type'   => "custom",
                                                        'number' => $item->getQtyOrdered(),
                                                        'width'  => 0, 'height'=>0, 'length'=>0,
                                                        'weight' => $this->_carrier->getWeightInKG($order->getStoreId(),$item->getWeight())
                                                ];
            }
            $params = [
                'source'       => "magento2",
                'orderNumber'  => $order->getIncrementId(),
                'addressFrom'  => $this->_carrier->getShippingOrigin($order->getStoreId()),
                'addressTo'    => [
                    'unit'          => isset($shipping->getStreet()[1]) ? $shipping->getStreet()[1] : '',
                    'address1'      => isset($shipping->getStreet()[0]) ? $shipping->getStreet()[0] : '',
                    'suburb'        => $shipping->getCity(),
                    'state'         => $shipping->getRegion(),
                    'postcode'      => $shipping->getPostcode(),
                    'contactName'   => trim($shipping->getPrefix().' '.$shipping->getFirstname().' '.$shipping->getLastname()),
                    'contactNumber' => $shipping->getTelephone(),
                    'sendUpdateSMS' => true,
                    'contactEmail'  => $shipping->getEmail(),
                    'isCommercial'  => false,
                    'companyName'   => $shipping->getCompany()
                ],
                'parcels'      => $parcels,
                'shippingName' => $method,
            ];

            $request = new \Zend\Http\Request();
            $request->setHeaders($this->_carrier->getHttpHeaders($order->getStoreId()))
                    ->setUri($this->_carrier->getEndPoint().'shoppingcart')
                    ->setMethod(\Zend\Http\Request::METHOD_POST)
                    ->setContent($this->_jsonHelper->jsonEncode($params));

            $client = new \Zend\Http\Client();
            $client->setOptions([
               'adapter'      => 'Zend\Http\Client\Adapter\Curl',
               'curloptions'  => [CURLOPT_FOLLOWLOCATION => true],
               'maxredirects' => 5,
               'timeout'      => 30
            ]);

            $response = $client->send($request);
            $data = $this->_jsonHelper->jsonDecode($response->getContent());
            if(isset($data['result']) && is_array($data['result'])){
                if(isset($data['result']['guid'])) $order->setGopeopleCartId($data['result']['guid']);//update from response once available
                else $order->setGopeopleCartId('none');//prevent repeated export
                $order->save();
            }
            //ignore errors, accept the order and rely on the cron to synchronise the order
            //if(isset($data['errorCode']) && 0 < (int)$data['errorCode']){
            //    throw new \Magento\Framework\Exception\LocalizedException(__(
            //        isset($data['message']) && !empty($data['message']) ? $data['message'] : 'Sorry, but we can\'t deliver to the destination with this shipping module.'
            //    ));
            //}
        }
    }

}