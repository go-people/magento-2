<?php
/**
 * Copyright © 2018 Ekky Software Pty Ltd. All rights reserved.
 * Please visit http://www.ekkysoftware.com
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace GoPeople\Shipping\Block\Email\Shipment;

/**
 * Go People tracking email implementation
 */
class Track
extends \Magento\Framework\View\Element\Template
{

    public function getTrackingLink($_item){
        if($_item->getCarrierCode() == \GoPeople\Shipping\Model\Carrier::CODE){
            if($this->_scopeConfig->isSetFlag('carrier/'.\GoPeople\Shipping\Model\Carrier::CODE.'/sandbox_mode'))
                return '<a href="https://www.gopeople.com.au/tracking/?code='.$_item->getNumber().'">'.$this->escapeHtml($_item->getNumber()).'</a>';
            return '<a href="https://www.gopeople.com.au/tracking/?code='.$_item->getNumber().'">'.$this->escapeHtml($_item->getNumber()).'</a>';
        }
        return $this->escapeHtml($_item->getNumber());
    }

}