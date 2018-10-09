<?php
/**
 * Copyright © 2018 Ekky Software Pty Ltd. All rights reserved.
 * Please visit http://www.ekkysoftware.com
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace GoPeople\Shipping\Model\Config\Source;

class Services implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * Return options array
     *
     * @param boolean $isMultiselect
     * @return array
     */
    public function toOptionArray($isMultiselect = false)
    {
        $options = [
            ['value' => 'on_demand', 'label'=> __('GoNOW')],
            ['value' => 'set_run',   'label'=> __('GoSAMEDAY')],
            ['value' => 'shift',     'label'=> __('GoSHIFT')],
        ];

        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => __('--Please Select--')]);
        }

        return $options;
    }
}
