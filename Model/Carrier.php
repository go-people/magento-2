<?php
/**
 * Copyright © 2018 Ekky Software Pty Ltd. All rights reserved.
 * Please visit http://www.ekkysoftware.com
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace GoPeople\Shipping\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Information;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Simplexml\Element;

/**
 * Go People shipping implementation
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Carrier extends AbstractCarrierOnline implements CarrierInterface
{
    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'gopeople';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

   /**
     * Default gateway url
     *
     * @var string
     */
    protected $_defaultGatewayUrl = 'https://api.gopeople.com.au/';

   /**
     * Default sandbox url
     *
     * @var string
     */
    protected $_defaultSandboxUrl = 'http://api-demo.gopeople.com.au/';

   /** @var \Magento\Framework\Json\Helper\Data */
    protected $_jsonHelper;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Xml\Security $xmlSecurity
     * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Helper\Data $directoryData
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Xml\Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        array $data = []
    ) {
        $this->_jsonHelper = $jsonHelper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $xmlSecurity, $xmlElFactory, $rateFactory, $rateMethodFactory, $trackFactory, $trackErrorFactory,
                            $trackStatusFactory, $regionFactory, $countryFactory, $currencyFactory, $directoryData, $stockRegistry, $data);
    }

    /**
     * Check if carrier has shipping tracking option available
     * All \Magento\Usa carriers have shipping tracking option available
     *
     * @return boolean
     */
    public function isTrackingAvailable()
    {
        return false;
    }

    /**
     * Check if carrier has shipping label option available
     *
     * @return boolean
     */
    public function isShippingLabelsAvailable()
    {
        return false;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        //\Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface')->debug("At GoPeople\Shipping\Model\Carrier::getAllowedMethods");
        return ['fasterdelivery' => $this->getConfigData('name')];
    }

    /**
     * Get Go People End Point
     *
     * @return array
     */
    public function getEndPoint()
    {
        return ((bool)$this->getConfigData('sandbox_mode') ? $this->_defaultSandboxUrl : $this->_defaultGatewayUrl);
    }

    /**
     * Get Shipping origin
     *
     * @return array
     */
    public function getShippingOrigin($storeId){
        $region = $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_REGION_ID,ScopeInterface::SCOPE_STORE,$storeId);
        if(0 < (int)$region){
            $region = $this->_regionFactory->create()->load($region);
            $region = $region->getName();
        }
        return [
            'unit'          => $this->_scopeConfig->getValue('shipping/origin/street_line2',ScopeInterface::SCOPE_STORE,$storeId),
            'address1'      => $this->_scopeConfig->getValue('shipping/origin/street_line1',ScopeInterface::SCOPE_STORE,$storeId),
            'suburb'        => $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_CITY,ScopeInterface::SCOPE_STORE,$storeId),
            'postcode'      => $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_POSTCODE,ScopeInterface::SCOPE_STORE,$storeId),
            'state'         => $region,
            'country'       => $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_COUNTRY_ID,ScopeInterface::SCOPE_STORE,$storeId), 
            'contactName'   => $this->_scopeConfig->getValue(Information::XML_PATH_STORE_INFO_NAME,ScopeInterface::SCOPE_STORE,$storeId),
            'contactNumber' => $this->_scopeConfig->getValue(Information::XML_PATH_STORE_INFO_PHONE,ScopeInterface::SCOPE_STORE,$storeId),
            'sendUpdateSMS' => false,
            'contactEmail'  => $this->_scopeConfig->getValue('trans_email/ident_'.$this->_scopeConfig->getValue(Information::XML_PATH_STORE_INFO_NAME,ScopeInterface::SCOPE_STORE,$storeId).'/email',ScopeInterface::SCOPE_STORE,$storeId),
            'isCommercial'  => true,
            'companyName'   => $this->_scopeConfig->getValue(Information::XML_PATH_STORE_INFO_NAME,ScopeInterface::SCOPE_STORE,$storeId),
        ];
    }


    /**
     * Get Http Headers
     *
     * @return \Zend\Http\Headers
     */
     public function getHttpHeaders($storeId){
        $httpHeaders = new \Zend\Http\Headers();
        $httpHeaders->addHeaders([
           'Authorization' => 'bearer ' . $this->_scopeConfig->getValue('carriers/'.static::CODE.'/rest_token',ScopeInterface::SCOPE_STORE,$storeId),
           'Accept'        => 'application/json',
           'Content-Type'  => 'application/json'
        ]);
        return $httpHeaders;
    }

    /**
     * convert weight to kilograms
     *
     * @return float
     */
     public function getWeightInKG($storeId,$weight){
        switch($this->_scopeConfig->getValue('general/locale/weight_unit',ScopeInterface::SCOPE_STORE,$storeId)){
        case 'lbs': $weight *= 2.2; break;
        }
        return $weight;
    }

    /**
     * Collect and get rates/errors
     *
     * @param RateRequest $request
     * @return  Result|Error|bool
     */
    public function collectRates(RateRequest $request)
    {
        //\Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface')->debug("At GoPeople\Shipping\Model\Carrier::collectRates");
        //\Magento\Framework\App\ObjectManager::getInstance()->get('Ekky\Extras\Helper\Logger')->logVariable($request);

        if (!$this->canCollectRates()) return $this->getErrorMessage();

        $quote = null;
        $parcels = [];
        foreach($request->getAllItems() as $item){
            if(!isset($quote)) $quote = $item->getQuote();
            $parcels[] = [
                'type'   => "custom",
                'number' => $item->getQty(),
                'width' => 0, 'height' => 0, 'length' => 0,
                'weight' => $this->getWeightInKG($request->getStoreId(),$item->getProduct()->getWeight()),
            ];
        }
        if(!isset($quote)) return $this->getErrorMessage();
        $billing = $quote->getBillingAddress();
        $region = $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_REGION_ID,ScopeInterface::SCOPE_STORE,$request->getStoreId());
        if(0 < (int)$region){
            $region = $this->_regionFactory->create()->load($region);
            $region = $region->getName();
        }
        $streets = explode("\n",$request->getStreet());
        $params = [
            'addressFrom' => $this->getShippingOrigin($request->getStoreId()),
            'addressTo'   => [
                'unit'          => isset($streets[1]) ? $streets[1] : '',
                'address1'      => isset($streets[0]) ? $streets[0] : '',
                'suburb'        => $request->getDestCity(),
                'state'         => $request->getDestRegionCode(),
                'postcode'      => $request->getDestPostcode(),
                'contactName'   => trim($billing->getPrefix().' '.$billing->getFirstname().' '.$billing->getLastname()),
                'contactNumber' => $billing->getTelephone(),
                'sendUpdateSMS' => true,
                'contactEmail'  => $quote->getCustomerEmail(),
                'isCommercial'  => false,
                'companyName'   => $billing->getCompany()
            ],
            'parcels'     => $parcels,
            'pickUpAfter' => $this->getEstimatedPickupTime(),
            'dropOffBy'   => $this->getEstimatedDropOffTime(),
            'onDemand'    => true,
            'setRun'      => true
        ];

        //\Magento\Framework\App\ObjectManager::getInstance()->get('Ekky\Extras\Helper\Logger')->logVariable($params);

        $httpRequest = new \Zend\Http\Request();
        $httpRequest->setHeaders($this->getHttpHeaders($request->getStoreId()))
                    ->setUri($this->getEndPoint().'quote')
                    ->setMethod(\Zend\Http\Request::METHOD_POST)
                    ->setContent($this->_jsonHelper->jsonEncode($params));

        $client = new \Zend\Http\Client();
        $client->setOptions([
           'adapter'      => 'Zend\Http\Client\Adapter\Curl',
           'curloptions'  => [CURLOPT_FOLLOWLOCATION => true],
           'maxredirects' => 5,
           'timeout'      => 30
        ]);

        $response = $client->send($httpRequest);
        $data = $this->_jsonHelper->jsonDecode($response->getContent());
        if(isset($data['errorCode']) && 0 < (int)$data['errorCode']){
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $errorMsg = isset($data['message']) && !empty($data['message']) ? $data['message'] : $this->getConfigData('specificerrmsg');
            $error->setErrorMessage(__($errorMsg ? $errorMsg : 'Sorry, but we can\'t deliver to the destination with this shipping module.'));
            return $error;
        }
        if(isset($data['result']) && is_array($data['result']) && 0 < count($data['result'])){
            $services = explode(',',$this->getConfigData('services'));
            $result = $this->_rateFactory->create();
            if(in_array('on_demand', $services) && isset($data['result']['onDemandPriceList']) && is_array($data['result']['onDemandPriceList'])){
                foreach ($data['result']['onDemandPriceList'] as $onDemand) {
                    /* @var $rate \Magento\Quote\Model\Quote\Address\RateResult\Method */
                    $rate = $this->_rateMethodFactory->create();
                    $rate->setCarrier(self::CODE);
                    $rate->setCarrierTitle($this->getConfigData('title'));
                    $rate->setMethod($this->_slugify($onDemand['serviceName']));
                    $rate->setMethodTitle(ucwords($onDemand['serviceName']));
                    $rate->setCost($onDemand['amount']);
                    $rate->setPrice($onDemand['amount']);
                    $result->append($rate);
                }
            }
            if(in_array('set_run', $services) && isset($data['result']['setRunPriceList']) && is_array($data['result']['setRunPriceList'])){
                foreach ($data['result']['setRunPriceList'] as $setRun) {
                    /* @var $rate \Magento\Quote\Model\Quote\Address\RateResult\Method */
                    $rate = $this->_rateMethodFactory->create();
                    $rate->setCarrier(self::CODE);
                    $rate->setCarrierTitle($this->getConfigData('title'));
                    $rate->setMethod($this->_slugify($setRun['serviceName']));
                    $rate->setMethodTitle(ucwords($setRun['serviceName']));
                    $rate->setCost($setRun['amount']);
                    $rate->setPrice($setRun['amount']);
                    $result->append($rate);
                }
            }
            if(in_array('shift', $services) && isset($data['result']['shiftList']) && is_array($data['result']['setRunPriceList'])){
                foreach ($data['result']['shiftList'] as $shift) {
                    /* @var $rate \Magento\Quote\Model\Quote\Address\RateResult\Method */
                    $rate = $this->_rateMethodFactory->create();
                    $rate->setCarrier(self::CODE);
                    $rate->setCarrierTitle($this->getConfigData('title'));
                    $rate->setMethod($this->_slugify($shift['serviceName']));
                    $rate->setMethodTitle(ucwords($shift['serviceName']));
                    $rate->setCost($shift['amount']);
                    $rate->setPrice($shift['amount']);
                    $result->append($rate);
                }
            }
            if(!empty($result->getAllRates())) return $result;
        }
 
        return false;//return no quote
    }

    /**
     * Estimate the pickup time from the current order
     *
     * @param int $storeId
     * @return string
     */
    protected function _getEstimatedPickupTime($storeId){
        $minHandlingTime = $this->getConfigData('min_handling');
        //$minHandlingTime = $this->_scopeConfig->getValue('shipping/origin/street_line2',ScopeInterface::SCOPE_STORE,$storeId);
    }

    /**
     * Estimate the pickup time from the current order
     *
     * @param int $storeId
     * @return string
     */
    protected function _getEstimatedDropOffTime($storeId){
        $minHandlingTime = $this->getConfigData('min_handling');
        //$minHandlingTime = $this->_scopeConfig->getValue('shipping/origin/street_line2',ScopeInterface::SCOPE_STORE,$storeId);
    }

    /**
     * return a safe string
     *
     * @param string $text
     * @return string
     */
    protected function _slugify($text){
        $text = strtolower(preg_replace('~-+~', '-', trim(preg_replace('~[^-\w]+~', '', iconv('utf-8', 'us-ascii//TRANSLIT', preg_replace('~[^\pL\d]+~u', '-', $text))), '-')));
        return empty($text) ? 'n-a' : $text;
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {

        \Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface')->debug("At GoPeople\Shipping\Model\Carrier::_doShipmentRequest");
    }
}
