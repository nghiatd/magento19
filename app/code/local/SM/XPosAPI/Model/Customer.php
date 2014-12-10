<?php
/**
 * #1275
 * Customer
 *
 * @category   SM
 * @package    SM_XPosAPI
 * @author     xD Team <cuonggt@smartosc.com>
 */
class SM_XPosAPI_Model_Customer extends SM_XPosAPI_Model_Resource
{
    /**
     * Get array type of customer data from post data object
     * @param type $data
     * @return type
     */
    protected function _prepareData($data) {
        $data = $this->_parseObjectToArray($data);
        
        return $data;
    }
    
    /**
     * Convert object to array
     * @param type $data
     * @return type
     */
    private function _parseObjectToArray($data) {
        if (!is_object($data) && !is_array($data)) {
            return $data;
        }
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        foreach ($data as $k => $v) {
            $data[$k] = $this->_parseObjectToArray($v);
        }
        
        return $data;
    }
    
    /**
     * Create a new customer
     * @param type $customerData
     * @return type
     * @throws Exception
     */
    public function create($customerData) {
        $customer = Mage::getModel('customer/customer');
        
        $customerData = $this->_prepareData($customerData);
        
        foreach ($this->getAllowedAttributes($customer) as $attributeCode => $attribute) {
            if (isset($customerData[$attributeCode])) {
                $customer->setData($attributeCode, $customerData[$attributeCode]);
            }
        }
        
        if (!empty($customerData['password'])) {
            $customer->setPassword($customerData['password']);
            $customer->setForceConfirmed(true);
        }
        
        $valid = $this->validateCustomerData($customer);
        if (is_array($valid)) {
            throw new Exception(implode("\n", $valid));
        }
        
        try {
            $customer->save();
        } catch (Mage_Core_Exception $e) {
            throw new Exception($e->getMessage());
        }
        
        if (!empty($customerData['address'])) {
            foreach ($customerData['address'] as $addressId => $addressData) {
                $this->updateCustomerAddress($customer, $addressId, $addressData);
            }
        }
        
        return $customer->getId();
    }
    
    /**
     * Update an existing customer
     * @param type $customerId
     * @param type $customerData
     * @return boolean
     * @throws Exception
     */
    public function update($customerId, $customerData) {
        $customer = Mage::getModel('customer/customer')->load($customerId);
        
        if (!$customer->getId()) {
            throw new Exception("Customer does not exist");
        }
        
        $customerData = $this->_prepareData($customerData);

        foreach ($this->getAllowedAttributes($customer) as $attributeCode => $attribute) {
            if (isset($customerData[$attributeCode])) {
                $customer->setData($attributeCode, $customerData[$attributeCode]);
            }
        }
        
        if (!empty($customerData['password'])) {
            $customer->changePassword($customerData['password']);
        }
        
        $valid = $this->validateCustomerData($customer);
        if (is_array($valid)) {
            throw new Exception(implode("\n", $valid));
        }

        try {
            $customer->save();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        
        if (!empty($customerData['address'])) {
            foreach ($customerData['address'] as $addressId => $addressData) {
                $this->updateCustomerAddress($customer, $addressId, $addressData);
            }
        }
        
        return true;
    }
    
    /**
     * Create or update customer's address
     * @param type $customer
     * @param type $addressId
     * @param type $addressData
     * @return type
     * @throws Exception
     */
    public function updateCustomerAddress($customer, $addressId, $addressData) {
        $isNew = false;
        $address = $customer->getAddressItemById($addressId);
        if (!$address) {
            $isNew = true;
            $address = Mage::getModel('customer/address');
        }
        
        foreach ($this->getAllowedAttributes($address) as $attributeCode => $attribute) {
            if (isset($addressData[$attributeCode])) {
                $address->setData($attributeCode, $addressData[$attributeCode]);
            }
        }
        if (isset($addressData['is_default_billing'])) {
            $address->setIsDefaultBilling($addressData['is_default_billing']);
        }

        if (isset($addressData['is_default_shipping'])) {
            $address->setIsDefaultShipping($addressData['is_default_shipping']);
        }

        if ($isNew) {
            $address->setCustomerId($customer->getId());
        }

        $valid = $address->validate();
        if (is_array($valid)) {
            throw new Exception(implode("\n", $valid));
        }

        try {
            $address->save();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        
        return $address->getId();
    }
    
    /**
     * Validate customer data
     * @param type $customer
     * @return boolean
     */
    public function validateCustomerData($customer)
    {
        $errors = array();
        if (!Zend_Validate::is( trim($customer->getFirstname()) , 'NotEmpty')) {
            $errors[] = Mage::helper('customer')->__('The first name cannot be empty.');
        }

        if (!Zend_Validate::is( trim($customer->getLastname()) , 'NotEmpty')) {
            $errors[] = Mage::helper('customer')->__('The last name cannot be empty.');
        }

        if (!Zend_Validate::is($customer->getEmail(), 'EmailAddress')) {
            $errors[] = Mage::helper('customer')->__('Invalid email address "%s".', $customer->getEmail());
        }

        $password = $customer->getPassword();
        if (!empty($password)) {
            if (!$customer->getId() && !Zend_Validate::is($password , 'NotEmpty')) {
                $errors[] = Mage::helper('customer')->__('The password cannot be empty.');
            }
            if (strlen($password) && !Zend_Validate::is($password, 'StringLength', array(6))) {
                $errors[] = Mage::helper('customer')->__('The minimum password length is %s', 6);
            }
        }
        
        $entityType = Mage::getSingleton('eav/config')->getEntityType('customer');
        $attribute = Mage::getModel('customer/attribute')->loadByCode($entityType, 'dob');
        if ($attribute->getIsRequired() && '' == trim($customer->getDob())) {
            $errors[] = Mage::helper('customer')->__('The Date of Birth is required.');
        }
        $attribute = Mage::getModel('customer/attribute')->loadByCode($entityType, 'taxvat');
        if ($attribute->getIsRequired() && '' == trim($customer->getTaxvat())) {
            $errors[] = Mage::helper('customer')->__('The TAX/VAT number is required.');
        }
        $attribute = Mage::getModel('customer/attribute')->loadByCode($entityType, 'gender');
        if ($attribute->getIsRequired() && '' == trim($customer->getGender())) {
            $errors[] = Mage::helper('customer')->__('Gender is required.');
        }

        if (empty($errors)) {
            return true;
        }
        return $errors;
    }

} // Class SM_XPosAPI_Model_Customer End
