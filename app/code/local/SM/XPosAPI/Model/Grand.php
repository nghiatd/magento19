<?php
class SM_XPosAPI_Model_Grand extends Mage_Sales_Model_Quote_Address_Total_Grand
{
    public function collect(Mage_Sales_Model_Quote_Address $address) {
        // Fix bug price is always 0 when user add product to cart
        $postData = Mage::getSingleton('adminhtml/session_quote')->getPostData();
        if (empty($postData)) {
            return parent::collect($address);
        }
        
        $this->_setAddress($address);
        /**
         * Reset amounts
         */
        $this->_setAmount(0);
        $this->_setBaseAmount(0);
        
        $model = Mage::getModel('xposapi/order');
        foreach ($address->getQuote()->getItemsCollection() as $item) {
            $discount_amount = $model->getDiscountForProductId($item->getProductId());
            $price = $model->getPriceForProductId($item->getProductId());
            $item->setDiscountAmount($discount_amount*$item->getQty());
            $item->setDiscountPercent($discount_amount * 100 / $price);
            $item->setOriginalCustomPrice($price);
            $item->setTaxPercent($postData->tax_percent);


            $this->_addAmount(-$discount_amount);
            $this->_addBaseAmount(-$discount_amount);
            $this->_getAddress()->setSubtotal($postData->subtotal);
            $this->_getAddress()->setBaseSubtotal($postData->subtotal);
            $this->_getAddress()->setTaxAmount($postData->tax_amount);
            $this->_getAddress()->setDiscountAmount($postData->discount_amount);

            $price_custom = $model->getPriceCustomForProductId($item->getProductId());
            if ($price_custom > 0) {
                $item->setOriginalCustomPrice($price_custom);
            }
            $tax_amount = ($postData->tax_percent*($item->getOriginalCustomPrice()-$discount_amount)*$item->getQty())/100;
            $item->setTaxAmount($tax_amount);
//            $this->_getAddress()->setGrandTotal($postData->grand_total);
//            $this->_getAddress()->setBaseGrandTotal($postData->grand_total);
            $address->setGrandTotal($postData->grand_total);
            $address->setBaseGrandTotal($postData->grand_total);
        }
        
        return $this;
    }
}