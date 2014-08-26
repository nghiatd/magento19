<?php
require_once(BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . 'SM' . DS . 'XPos' . DS . 'controllers' . DS . 'Adminhtml' . DS . 'CashierController.php');

class SM_XPosAPI_Model_Observer
{
    public function generateProducts($updateFrom = false)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('type_id', 'simple');

        if ($updateFrom) {
            $collection->addFieldToFilter('updated_at', array('gt' => $updateFrom));
        }

        if (Mage::getStoreConfig('xpos/search/searching_status')){
            $collection->addFieldToFilter('is_salable', '1');
        }

        if (Mage::getStoreConfig('xpos/search/searching_product_visibility')){
            $collection->addFieldToFilter('visibility', '4');
        }

        $data = array();
        $data[] = array("update_time" => date("Y/m/d h:i:s", time()));
        foreach ($collection as $product) {

            if (Mage::getStoreConfig('xpos/search/searching_instock') && !$product->getStockItem()->getIsInStock()){
                continue;
            }

            if ($product->getStockItem()->getIsInStock()){
                $item = array(
                    'product_id'           => $product->getId(),
                    'product_image'        => Mage::helper('catalog/image')->init($product, 'image')->resize(400, 400)
                        ->__toString(),
                    'product_name'         => $product->getName(),
                    'sku'                  => $product->getSku(),
                    // 'barcode' => ???,
                    'product_price'        => $product->getPrice(),
                    'product_category_ids' => implode(',', $product->getCategoryIds()),
                    'is_active'            => $product->isSalable() ? '1' : '0'
                );

                $data[] = $item;
            }
        }

        if (!$updateFrom) {
            return Mage::helper('xposapi')->writeToCacheFile('product', json_encode($data));
        } else {
            return $data;
        }
    }

    public function generateCategories()
    {
        $data = array();
        $category_tree = $this->load_category_tree();
        $data = isset($category_tree["children"][0]["children"]) ? $category_tree["children"][0]["children"] : array();
        return Mage::helper('xposapi')->writeToCacheFile('category', json_encode($data));
    }

    public function generateCustomers()
    {
        $data = array();

        $collection = Mage::getModel("customer/customer")
            ->getCollection()
            ->AddAttributeToSelect("entity_id")
            ->AddAttributeToSelect("firstname")
            ->AddAttributeToSelect("lastname")
            ->AddAttributeToSelect("email")
            ->addFieldToFilter("lastname", array("neq"=>"Guest"));

        foreach ($collection as $customer) {
            $data[] = array("customer_id" => $customer->getEntityId(),
                            "firstname"   => $customer->getFirstname(),
                            "lastname"    => $customer->getLastname(),
                            "email"       => $customer->getEmail(),
            );
        }

        return Mage::helper('xposapi')->writeToCacheFile('customer', json_encode($data));
    }

    public function generateUsers()
    {
        $data = array();

        $collection = Mage::getModel("xpos/user")
            ->getCollection();

        foreach ($collection as $user) {
            $data[] = array("user_id" => $user->getXposUserId(),
                            "username"  => $user->getUsername(),
                            "password"  => md5($user->getPassword()),
                            "role"      => $user->getType() == SM_XPos_Adminhtml_CashierController::XPOST_CUSTOMER_ROLE_ADMIN ? "admin" : "user",
                            "firstname" => $user->getFirstname(),
                            "lastname"  => $user->getLastname(),
                            "is_active" => $user->getIsActive(),
            );
        }

        return Mage::helper('xposapi')->writeToCacheFile('user', json_encode($data));
    }

    public function generateTills()
    {
        $collection = Mage::getModel('xpos/till')->getCollection();
        $data = array();
        foreach ($collection as $till) {
            $data[] = array("till_id"   => $till->getTillId(),
                            "till_name" => $till->getTillName(),
                            "is_active" => $till->getIsActive(),
            );
        }

        return Mage::helper('xposapi')->writeToCacheFile('till', json_encode($data));
    }

    public function generateOrder($postData){
        $storeId = Mage::app()->getStore('default')->getId();
        $quote = Mage::getModel('sales/quote')
            ->setStoreId($storeId);

        $this->setSendingEmail($postData->send_email);
        $this->_getSession()->setPostData($postData);
        $customer = $this->getCustomer($postData->user_id);
        $this->addProduct($postData, $quote);
        
        $quote->getBillingAddress()
            ->addData($this->getBillingAddress($customer));

        $quote->getShippingAddress()
            ->addData($this->getShippingAddress($customer))
            ->setShippingMethod('freeshipping_freeshipping')
            ->setPaymentMethod('checkmo')
            ->setCollectShippingRates(true)
            ->collectTotals();

        $quote->setCheckoutMethod('guest')
            ->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

        $quote->getPayment()->importData( array('method' => 'checkmo'));

        $quote->save();

        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();

        $this->_getSession()->clear();
        $service->getOrder()->sendNewOrderEmail();

        //create invoice for the order
        $invoice = $service->getOrder()->prepareInvoice()
            ->setTransactionId($service->getOrder()->getId())
            ->addComment("Invoice created from cron job.")
            ->register()
            ->pay();

        $transaction_save = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transaction_save->save();
        //now create shipment
        //after creation of shipment, the order auto gets status COMPLETE
        $shipment = $service->getOrder()->prepareShipment();
        if( $shipment ) {
            $shipment->register();
            $service->getOrder()->setIsInProcess(true);

            $transaction_save = Mage::getModel('core/resource_transaction')
                ->addObject($shipment)
                ->addObject($shipment->getOrder())
                ->save();
        }
        
        // #1278
        $order = Mage::getModel('sales/order')->load($service->getOrder()->getId());
        $order->setData('xpos_app_order_id', $postData->xpos_app_order_id);
        $order->save();
        // #1278 end
        
        return array("order_id" => $service->getOrder()->getId(), "order_increment_id" => $service->getOrder()->getIncrementId());
    }

    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    private function load_category_tree()
    {

        $tree = Mage::getResourceSingleton('catalog/category_tree')
            ->load();
        $store = 1;
        $parentId = 1;

        $root = $tree->getNodeById($parentId);

        if ($root && $root->getId() == 1) {
            $root->setName(Mage::helper('catalog')->__('Root'));
        }

        $collection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($store)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('is_active');

        $tree->addCollectionData($collection, true);

        return $this->nodeToArray($root);

    }

    private function nodeToArray(Varien_Data_Tree_Node $node)
    {
        $result = array();
        $category = Mage::getModel('catalog/category')->load($node->getId());
        $result['category_id'] = $node->getId();
        $result['category_name'] = $node->getName();
        $result["category_image"] = $category->getImageUrl()
            ? $category->getImageUrl()
            : $this->getThumbnailUrl(
                $category
            );
        $result['children'] = array();

        foreach ($node->getChildren() as $child) {
            $result['children'][] = $this->nodeToArray($child);
        }

        return $result;
    }

    private function getThumbnailUrl(Mage_Catalog_Model_Category $category)
    {
        $url = "";

        if ($thumbnail = $category->getThumbnail()) {
            $url = Mage::getBaseDir("media") . "catalog/category/" . $thumbnail;
        }

        return $url;
    }

    private function setSendingEmail($is_sending_email){
        if ($is_sending_email == 0){
            Mage::app()->getStore()->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, "0");
        }else{
            Mage::app()->getStore()->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, "1");
        }
    }

    private function addProduct($postData, $quote){
        foreach($postData->products as $item){
            $product = Mage::getModel('catalog/product')->load($item->product_id);
            $buyInfo = array('qty' => $item->qty);
            $quote->addProduct($product, new Varien_Object($buyInfo));
        }
    }

    private function getCustomer($userid){
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->load($userid);

        if($customer->getId()){
            return $customer;
        }

        return null;
    }

    private function getBillingAddress(Mage_Customer_Model_Customer $customer){
        $data = $this->_getSession()->getPostData();

        if (isset($customer) && ($customer->getPrimaryBillingAddress() !=null)){
            if (isset($data->extra_customer_email) && !empty($data->extra_customer_email)){
                $customer->getPrimaryBillingAddress()->setEmail($data->extra_customer_email);
            }

            return $customer->getPrimaryBillingAddress()->getData();
        }

        return  array(
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
    }

    private function getShippingAddress(Mage_Customer_Model_Customer $customer){
        if (isset($customer) && ($customer->getPrimaryBillingAddress() !=null)){
            return $customer->getPrimaryShippingAddress()->getData();
        }

        return  array(
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
    }
}