<?php
/**
 * #1281
 * @author Giang Thai Cuong <cuonggt@smartosc.com>
 */
class SM_XPosAPI_Model_Product
{
    /**
     * Get list of products
     * @return type
     */
    public function getProductList() {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter(array(
                array('attribute' => 'type_id', 'eq' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE),
                array('attribute' => 'type_id', 'eq' => Mage_Catalog_Model_Product_Type::TYPE_GROUPED),
                array('attribute' => 'type_id', 'eq' => Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE),
            ));

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
            
            $item = array(
                'product_id'           => $product->getId(),
                'product_image'        => Mage::helper('catalog/image')->init($product, 'image')->resize(400, 400)
                    ->__toString(),
                'product_name'         => $product->getName(),
                'sku'                  => $product->getSku(),
                // 'barcode' => ???,
                'product_price'        => $product->getPrice(),
                'product_final_price'  => Mage::getModel('catalogrule/rule')->calcProductPriceRule($product, $product->getPrice()), // #1282
                'product_category_ids' => implode(',', $product->getCategoryIds()),
                'is_active'            => $product->isSalable() ? '1' : '0'
            );
            
            if ($product->isGrouped()) {
                $associated_products = $this->_getAssociatedProducts($product);
                $item['associated_products'] = $associated_products;
            } elseif ($product->isConfigurable()) {
                $att_list = $this->_getProductAttributeList($product);
                $item['attribute_list'] = $att_list;
            }

            $data[] = $item;
        }
        // write product's list to cache file
        Mage::helper('xposapi')->writeToCacheFile('product', json_encode($data));
        
        return $data;
    }
    
    /**
     * Get associated products of grouped product
     * @param type $product
     * @return type
     */
    private function _getAssociatedProducts($product) {
        $result = array();
        $associated_products = $product->getTypeInstance(true)->getAssociatedProducts($product);
        foreach ($associated_products as $associated_product) {
            $result[] = array(
                'product_id'           => $associated_product->getId(),
                'product_image'        => Mage::helper('catalog/image')->init($associated_product, 'image')->resize(400, 400)
                    ->__toString(),
                'product_name'         => $associated_product->getName(),
                'sku'                  => $associated_product->getSku(),
                // 'barcode' => ???,
                'product_price'        => $associated_product->getPrice(),
                'product_category_ids' => implode(',', $associated_product->getCategoryIds()),
                'is_active'            => $associated_product->isSalable() ? '1' : '0'
            );
        }
        
        return $result;
    }
    
    /**
     * Get product's attribute list of configurable product
     * @param type $product
     * @return type
     */
    private function _getProductAttributeList($product) {
        $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
        $att_list = array();
        foreach ($productAttributeOptions as $v) {
            $att_list[] = array(
                'attribute_id' => $v['attribute_id'],
                'attribute_code' => $v['attribute_code'],
                'label' => $v['label'],
                'values' => $v['values'],
            );
        }
        
        return $att_list;
    }
}