<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Adminhtml sales order create newsletter block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class SM_XPos_Block_Adminhtml_Override_Load extends Mage_Adminhtml_Block_Sales_Order_Create_Load
{
    protected function _toHtml()
    {
        $result = array();
        foreach ($this->getSortedChildren() as $name) {
            if (!$block = $this->getChild($name)) {
                $result[$name] = Mage::helper('sales')->__('Invalid block: %s.', $name);
            } else {
                $result[$name] = $block->toHtml();
            }
        }
        $resultJson = Mage::helper('core')->jsonEncode($result);
        $jsVarname = $this->getRequest()->getParam('as_js_varname');
        if ($jsVarname) {
            return Mage::helper('adminhtml/js')->getScript(sprintf('var %s = %s', $jsVarname, $resultJson));
        } else {
            return $resultJson;
        }
    }
}
