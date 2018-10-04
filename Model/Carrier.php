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
 * UPS shipping implementation
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

        $httpHeaders = new \Zend\Http\Headers();
        $httpHeaders->addHeaders([
           'Authorization' => 'bearer ' . $this->getConfigData('rest_token'),
           'Accept' => 'application/json',
           'Content-Type' => 'application/json'
        ]);

        $quote = null;
        $parcels = [];
        foreach($request->getAllItems() as $item){
            if(!isset($quote)) $quote = $item->getQuote();
            $parcels[] = [
                    'type'   => "custom",
                    'number' => $item->getQty(),
                    'width'  => 0,
                    'height' => 0,
                    'length' => 0,
                    'weight' => $item->getProduct()->getWeight(),
                ];
        }
        if(!isset($quote)) return $this->getErrorMessage();
        $billing = $quote->getBillingAddress();
        //\Magento\Framework\App\ObjectManager::getInstance()->get('Ekky\Extras\Helper\Logger')->logVariable($quote);
        //\Magento\Framework\App\ObjectManager::getInstance()->get('Ekky\Extras\Helper\Logger')->logVariable($quote->getBillingAddress());

        $params = [
           'addressFrom' => [
                'unit'          => $this->_scopeConfig->getValue('shipping/origin/street_line2',ScopeInterface::SCOPE_STORE,$request->getStoreId()),
                'address1'      => $this->_scopeConfig->getValue('shipping/origin/street_line1',ScopeInterface::SCOPE_STORE,$request->getStoreId()),
                'suburb'        => $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_CITY,ScopeInterface::SCOPE_STORE,$request->getStoreId()),
                'postcode'      => $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_POSTCODE,ScopeInterface::SCOPE_STORE,$request->getStoreId()),
                'state'         => $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_REGION_ID,ScopeInterface::SCOPE_STORE,$request->getStoreId()),
                'country'       => $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_COUNTRY_ID,ScopeInterface::SCOPE_STORE,$request->getStoreId()), 
                'contactName'   => $this->_scopeConfig->getValue(Information::XML_PATH_STORE_INFO_NAME,ScopeInterface::SCOPE_STORE,$request->getStoreId()),
                'contactNumber' => $this->_scopeConfig->getValue(Information::XML_PATH_STORE_INFO_PHONE,ScopeInterface::SCOPE_STORE,$request->getStoreId()),
                'sendUpdateSMS' => false,
                'contactEmail'  => $this->_scopeConfig->getValue('trans_email/ident_'.$this->_scopeConfig->getValue(Information::XML_PATH_STORE_INFO_NAME,ScopeInterface::SCOPE_STORE,$request->getStoreId()).'/email',
                                                                 ScopeInterface::SCOPE_STORE,$request->getStoreId()),
                'isCommercial'  => true,
                'companyName'   => $this->_scopeConfig->getValue(Information::XML_PATH_STORE_INFO_NAME,ScopeInterface::SCOPE_STORE,$request->getStoreId()),
            ],
            'addressTo' => [
                'unit'          => $request->getDestStreet(),
                'address1'      => $request->getDestStreet(),
                'suburb'        => $request->getDestCity(),
                'state'         => $request->getDestRegionCode(),
                'postcode'      => $request->getDestPostcode(),
                'contactName'   => trim($billing->getPrefix().' '.$billing->getFirstname().' '.$billing->getFirstname()),
                'contactNumber' => $billing->getTelephone(),
                'sendUpdateSMS' => true,
                'contactEmail'  => $quote->getCustomerEmail(),
                'isCommercial'  => false,
                'companyName'   => $billing->getCompany()
            ],
            'parcels' => $parcels,
            'pickUpAfter' => $this->getEstimatedPickupTime(),
            'dropOffBy'   => "2018-10-08 17:00:00+1000",
            'onDemand'    => true,
            'setRun'      => true
        ];

        //\Magento\Framework\App\ObjectManager::getInstance()->get('Ekky\Extras\Helper\Logger')->logVariable($params);

        $request = new \Zend\Http\Request();
        $request->setHeaders($httpHeaders)
                ->setUri(((bool)$this->getConfigData('sandbox_mode') ? $this->_defaultSandboxUrl : $this->_defaultGatewayUrl).'quote')
                ->setMethod(\Zend\Http\Request::METHOD_POST)
                ->setContent($this->_jsonHelper->jsonEncode($params));

        $client = new \Zend\Http\Client();
        $client->setOptions([
           'adapter'   => 'Zend\Http\Client\Adapter\Curl',
           'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
           'maxredirects' => 5,
           'timeout' => 30
        ]);

        $response = $client->send($request);
        //\Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface')->debug(var_export($response->getContent(),true));
        $data = $this->_jsonHelper->jsonDecode($response->getContent());
        //\Magento\Framework\App\ObjectManager::getInstance()->get('Ekky\Extras\Helper\Logger')->logVariable($data);
        if(isset($data['errorCode']) && 0 < (int)$data['errorCode']){
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $errorMsg = isset($data['message']) && !empty($data['message']) ? $data['message'] : $this->getConfigData('specificerrmsg');
            $error->setErrorMessage(__($errorMsg ? $errorMsg : 'Sorry, but we can\'t deliver to the destination country with this shipping module.'));
            return $error;
        }
        if(isset($data['result']) && is_array($data['result']) && 0 < count($data['result'])){
            $result = $this->_rateFactory->create();
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
            return $result;
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
     * return a safe string
     *
     * @param string $text
     * @return string
     */
    protected function _slugify($text){
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
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
