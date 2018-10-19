<?php
/**
 * Copyright © 2018 Ekky Software Pty Ltd. All rights reserved.
 * Please visit http://www.ekkysoftware.com
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace GoPeople\Shipping\Model\Config\Comment;

class Callback implements \Magento\Config\Model\Config\CommentInterface
{

    /** @var \Magento\Store\Model\StoreManagerInterface */
    protected $_storeManager;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_storeManager = $storeManager;
    }

    /**
     * Return options array
     *
     * @param boolean $isMultiselect
     * @return array
     */
   public function getCommentText($elementValue)  //the method has to be named getCommentText
    {
        $url = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB,true).'gopeople/shipped';
        return "Please copy this link into your Go Poeple's Members Area:-<br/><a href='".$url."'>".$url."</a>";
    }
}
