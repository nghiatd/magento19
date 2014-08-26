<?php
/**
 * #1281
 * @author Giang Thai Cuong <cuonggt@smartosc.com>
 */
class SM_XPosAPI_Model_Order
{
    /**
     * Automically generate order->invoice->shipment from data sent by an API call
     * @param type $postData
     * @return type
     */
    public function generateOrderInvoiceShipment($postData){
        $storeId = Mage::app()->getStore('default')->getId();
        $quote = Mage::getModel('sales/quote')->setStoreId($storeId);

        $this->_setSendingEmail($postData->send_email);
        $this->_getSession()->setPostData($postData);
        
        $customer = $this->_getCustomer($postData->customer_id, $postData->customer_email);
//        if ($customer) {
//            // for customer orders:
//            $quote->assignCustomer($customer);
//        } else {
//            // for guesr orders only:
//            $quote->setCustomerEmail($postData->customer_email);
//        }
        
        // add product(s)
        $this->_addProduct($postData, $quote);

        $billingAddress = $quote->getBillingAddress();
        $billingAddress->addData($this->_getBillingAddress($customer));
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($this->_getShippingAddress($customer))
                ->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingMethod('freeshipping_freeshipping')
                ->setPaymentMethod('checkmo');

        $quote->getPayment()->importData(array('method' => 'checkmo'));
        
        // for guesr orders
        if (empty($postData->customer_email)) {
            $customer_email = $quote->getBillingAddress()->getEmail();
        } else {
            $customer_email = $postData->customer_email;
        }
        $quote->setCheckoutMethod('guest')
                ->setCustomerId(null)
                ->setCustomerEmail($customer_email)
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        
        $quote->collectTotals()->save();

        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();

        try {
            $order = $service->getOrder();
            $order->setData('xpos_app_order_id', $postData->xpos_app_order_id);
            $order->save();
            $this->_getSession()->clear();
            $order->sendNewOrderEmail();
            
            //create invoice for the order
            $invoice = $order->prepareInvoice()
                ->setTransactionId($order->getId())
                ->addComment("Invoice created from cron job.")
                ->register()
                ->pay();
            $order->addStatusHistoryComment('Automatically INVOICED by SM_XPosAPI.', false);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();

            //now create shipment
            //after creation of shipment, the order auto gets status COMPLETE
            $shipment = $order->prepareShipment();
            $shipment->register();
            $order->setIsInProcess(true);
            $order->addStatusHistoryComment('Automatically SHIPPED by SM_XPosAPI.', false);
            $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
        } catch (Exception $ex) {
            $order->addStatusHistoryComment('SM_XPosAPI: Exception occurred during generateOrderInvoiceShipment action. Exception message: ' . $ex->getMessage(), false);
            $order->save();
        }
        
        return array("order_id" => $order->getId(), "order_increment_id" => $order->getIncrementId());
    }
    
    /**
     * Get customer info from id or email (id's preferred)
     * @param type $customer_id
     * @param type $customer_email
     * @return boolean
     */
    private function _getCustomer($customer_id, $customer_email) {
        $website_id = Mage::app()->getWebsite()->getId();
        $customer_model = Mage::getModel('customer/customer')->setWebsiteId($website_id);
        $customer = $customer_model->load($customer_id);
        if ($customer->getId()) {
            return $customer;
        }
        
        $customer = $customer_model->loadByEmail($customer_email);
        if ($customer->getId()) {
            return $customer;
        }
        
        return false;
    }
    
    /**
     * Set sending email or not
     * @param type $send_email
     */
    private function _setSendingEmail($send_email) {
        if ($send_email == 0) {
            Mage::app()->getStore()->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, "0");
        } else {
            Mage::app()->getStore()->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, "1");
        }
    }
    
    /**
     * Get session quote
     * @return type
     */
    protected function _getSession() {
        return Mage::getSingleton('adminhtml/session_quote');
    }
    
    /**
     * Get customer's billing address
     * @param type $customer
     * @return string
     */
    private function _getBillingAddress($customer) {
        $addressData = array(
            'firstname' => 'Billing Test Name',
            'lastname' => 'Billing Test Name',
            'company' => 'smartosc',
            'email' =>  "test@smartosc.com",
            'street' => array(
                'Sample Street Line_1',
                'Sample Street Line_2'
            ),
            'city' => 'City',
            'region_id' => '',
            'region' => 'State/Province',
            'postcode' => '12345',
            'country_id' => 'NL',
            'telephone' =>  '1234567890',
            'fax' => '123456987',
            'save_in_address_book' => '0',
        );
        if (!$customer) {
            return $addressData;
        }
        
        $primary_billing_address = $customer->getPrimaryBillingAddress();
        if (!$primary_billing_address) {
            return $addressData;
        }
        
        $data = $this->_getSession()->getPostData();
        if (!empty($data->extra_customer_email)) {
            $primary_billing_address->setEmail($data->extra_customer_email);
        }

        return $primary_billing_address->getData();
    }
    
    /**
     * Get customer's shipping address
     * @param type $customer
     * @return string
     */
    private function _getShippingAddress($customer) {
        $addressData = array(
            'firstname' => 'Shipping Test Name',
            'lastname' => 'Shipping Test Name',
            'company' => 'smartosc',
            'email' =>  'test@smartosc.com',
            'street' => array(
                'Sample Street Line_1',
                'Sample Street Line_2'
            ),
            'city' => 'City',
            'region_id' => '',
            'region' => 'State/Province',
            'postcode' => '12345',
            'country_id' => 'NL',
            'telephone' =>  '1234567890',
            'fax' => '123456987',
            'save_in_address_book' => '0',
        );
        if (!$customer) {
            return $addressData;
        }
        
        $primary_shipping_address = $customer->getPrimaryShippingAddress();
        if (!$primary_shipping_address) {
            return $addressData;
        }
        
        return $primary_shipping_address->getData();
    }

    /**
     * Add product(s)
     * @param type $postData
     * @param type $quote
     */
    private function _addProduct($postData, $quote){
        foreach($postData->products as $item){
            $product = Mage::getModel('catalog/product')->load($item->product_id);
            if ($product->isConfigurable()) {
                $buyInfo = array(
                    'qty' => $item->qty,
                    'super_attribute' => get_object_vars($item->super_attribute),
                );
            } else {
                $buyInfo = array('qty' => $item->qty);
            }
            $quote->addProduct($product, new Varien_Object($buyInfo));
        }
    }
    
    /**
     * #1284
     * Get order list based on order id, email or customer name
     * @param type $params
     * @return type
     */
    public function getOrderList($params) {
        // return no result if no search's params
        if (empty($params['id']) && empty($params['email']) && empty($params['customer_name'])) {
            return array();
        }
        
        $orders = Mage::getModel('sales/order')->getCollection();
        if (!empty($params['id'])) {
            $orders = $orders->addFieldToFilter(
                'increment_id', 
                array('like' => "%" . $params['id'] . "%")
            );
        }
        if (!empty($params['email'])) {
            $orders = $orders->addFieldToFilter(
                'customer_email', 
                array('like' => "%" . $params['mail'] . "%")
            );
        }
        if (!empty($params['customer_name'])) {
            $orders = $orders->addFieldToFilter(
                'CONCAT(IFNULL(customer_firstname,"")," ",IFNULL(customer_middlename,"")," ",IFNULL(customer_lastname,""))', 
                array('like' => '%' . $params['customer_name'] . '%')
            );
        }
        
        $data = array();
        $data[] = array("update_time" => date("Y/m/d h:i:s", time()));
        foreach ($orders as $order) {
            $data[] = $order->getData();
        }
        // write product's list to cache file
        $cache_filename = $this->generateOrderListCacheFileName($params);
        Mage::helper('xposapi')->writeToCacheFile($cache_filename, json_encode($data));
        
        return $data;
    }
    
    /**
     * Generate cache file name of order's list
     * @param type $params
     * @return type
     */
    public function generateOrderListCacheFileName($params) {
        return 'order_' . $params['id'] . '_' . $params['email'] . '_' . $params['customer_name'];
    }
    
    /**
     * #1279
     * Generate refund for order
     * @param type $postData
     * @return type
     */
    public function generateRefund($postData) {
        if (!empty($postData->comment_text)) {
            Mage::getSingleton('adminhtml/session')->setCommentText($postData->comment_text);
        }
        
        $creditmemo = $this->_initCreditmemo($postData);
        if (!$creditmemo) {
            throw new Exception('Cannot save the credit memo.');
        }
        
        if (($creditmemo->getGrandTotal() <=0) && (!$creditmemo->getAllowZeroGrandTotal())) {
            throw new Exception('Credit memo\'s total must be positive.');
        }

        $comment = '';
        if (!empty($postData->comment_text)) {
            $creditmemo->addComment(
                $postData->comment_text,
                isset($postData->comment_customer_notify),
                isset($postData->is_visible_on_front)
            );
            if (isset($postData->comment_customer_notify)) {
                $comment = $postData->comment_text;
            }
        }

        if (isset($postData->do_refund)) {
            $creditmemo->setRefundRequested(true);
        }
        if (isset($postData->do_offline)) {
            $creditmemo->setOfflineRequested((bool)(int)$postData->do_offline);
        }

        $creditmemo->register();
        if (!empty($postData->send_email)) {
            $creditmemo->setEmailSent(true);
        }

        $creditmemo->getOrder()->setCustomerNoteNotify(!empty($postData->send_email));
        $this->_saveCreditmemo($creditmemo);
        $creditmemo->sendEmail(!empty($postData->send_email), $comment);
        Mage::getSingleton('adminhtml/session')->getCommentText(true);
        
        return $creditmemo->getId();
    }
    
    /**
     * #1279
     * Initialize requested invoice instance
     * @param type $postData
     * @return type
     * @throws Exception
     */
    protected function _initCreditmemo($postData)
    {
        $items = get_object_vars($postData->items);
        foreach ($items as $k => $v) {
            $items[$k] = get_object_vars($v);
        }
        $data = array(
            'items' => $items,
            'do_offline' => $postData->do_offline,
            'comment_text' => $postData->comment_text,
            'shipping_amount' => $postData->shipping_amount,
            'adjustment_positive' => $postData->adjustment_positive,
            'adjustment_negative' => $postData->adjustment_negative,
        );
        
        $creditmemo = false;
        $invoice = false;
        $creditmemoId = null;
        $orderId = $postData->order_increment_id;
        $invoiceId = $postData->invoice_id;
        
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        if ($invoiceId) {
            $invoice = Mage::getModel('sales/order_invoice')
                ->load($invoiceId)
                ->setOrder($order);
        }
        
        if (!$order->canCreditmemo()) {
            $msg = 'Cannot refund';
            throw new Exception($msg);
        }
        
        $savedData = $data['items'];
        
        $qtys = array();
        $backToStock = array();
        foreach ($savedData as $orderItemId => $itemData) {
            if (isset($itemData['qty'])) {
                $qtys[$orderItemId] = $itemData['qty'];
            }
            if (isset($itemData['back_to_stock'])) {
                $backToStock[$orderItemId] = true;
            }
        }
        $data['qtys'] = $qtys;

        $service = Mage::getModel('sales/service_order', $order);
        if ($invoice) {
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
        } else {
            $creditmemo = $service->prepareCreditmemo($data);
        }

        /**
         * Process back to stock flags
         */
        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            $orderItem = $creditmemoItem->getOrderItem();
            $parentId = $orderItem->getParentItemId();
            if (isset($backToStock[$orderItem->getId()])) {
                $creditmemoItem->setBackToStock(true);
            } elseif ($orderItem->getParentItem() && isset($backToStock[$parentId]) && $backToStock[$parentId]) {
                $creditmemoItem->setBackToStock(true);
            } elseif (empty($savedData)) {
                $creditmemoItem->setBackToStock(Mage::helper('cataloginventory')->isAutoReturnEnabled());
            } else {
                $creditmemoItem->setBackToStock(false);
            }
        }

        return $creditmemo;
    }
    
    /**
     * #1279
     * Save creditmemo and related order, invoice in one transaction
     * @param type $creditmemo
     * @return \SM_XPosAPI_Model_Order
     */
    protected function _saveCreditmemo($creditmemo) {
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($creditmemo)
            ->addObject($creditmemo->getOrder());
        if ($creditmemo->getInvoice()) {
            $transactionSave->addObject($creditmemo->getInvoice());
        }
        $transactionSave->save();

        return $this;
    }
    
    /**
     * 
     * @param type $productId
     * @return type
     */
    public function getPriceCustomForProductId($productId)
    {
        $price_custom = 0;
        $postData = Mage::getSingleton('adminhtml/session_quote')->getPostData();

        foreach ($postData->products as $item) {
            if (isset($item->price_custom) && $item->product_id == $productId) {
                $price_custom = $item->price_custom;
                break;
            }
        }

        return $price_custom;
    }

    /**
     * 
     * @param type $productId
     * @return type
     */
    public function getPriceForProductId($productId)
    {
        $price = 0;
        $postData = Mage::getSingleton('adminhtml/session_quote')->getPostData();
        
        foreach ($postData->products as $item) {
            if (isset($item->price) && $item->product_id == $productId) {
                $price = $item->price;
                break;
            }
        }

        return $price;
    }

    /**
     * 
     * @param type $productId
     * @return type
     */
    public function getDiscountForProductId($productId)
    {
        $discount = 0;
        $postData = Mage::getSingleton('adminhtml/session_quote')->getPostData();

        foreach ($postData->products as $item) {
            if (isset($item->price) && $item->product_id == $productId) {
                $discount = $item->discount;
                break;
            }
        }

        return $discount;
    }
    
    /**
     * #1282
     * Get price rule list from coupon code
     * @param type $coupon_code
     * @return type
     */
    public function getRuleFromCouponCode($coupon_code) {
        $oCoupon = Mage::getModel('salesrule/coupon')->load($coupon_code, 'code');
        $oRule = Mage::getModel('salesrule/rule')->load($oCoupon->getRuleId());
        
        return $oRule->getData();
    }
}