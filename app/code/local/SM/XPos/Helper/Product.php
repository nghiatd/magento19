    <?php
/**
 * Created by PhpStorm.
 * User: NguyenCT
 * Date: 3/28/14
 * Time: 10:38 AM
 */
class SM_XPos_Helper_Product extends Mage_Core_Helper_Abstract {

    public function getCategoryList(){
        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToFilter('xpos_enable', true)
            ->addAttributeToSelect('*');

        return $categories;
    }

    public function getProductList($controller,$page = 1,$warehouseId){
        Mage::helper('catalog/product')->setSkipSaleableCheck(true);

        $storeId = Mage::getStoreConfig('xpos/general/storeid');
        $limit = Mage::getStoreConfig('xpos/offline/prod_per_request');

        $productInfo = array();

        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('*')
            ->setOrder('created_at', 'asc')->setCurPage($page)->setPageSize($limit);

        $productCollection = $this->queryProduct($productCollection);

        if ($productCollection->getLastPageNumber()<$page){
            return array('productInfo'=>$productInfo,'totalProduct'=>$productCollection->getSize(),'totalLoad'=>0);
        }

        $productCollection->load();

        $allowProduct = array();
        if (!empty($warehouseId)){
            $allowProduct = $this->getWarehouseProduct($warehouseId);
        }

        foreach ($productCollection as $product) {

            if (!in_array($product->getId(), $allowProduct) && !empty($warehouseId) && $product->getTypeId() == 'simple'){
                continue;
            }

            $flag = true;
            $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
            if (Mage::getStoreConfig('xpos/search/searching_instock') == 1) {
                if (!$stock->getIsInStock()) {
                    $flag = false;
                }
            }

            if (!$flag){
                continue;
            }

            $pInfo = $this->extractData($controller,$product);
            $pInfo['qty'] = $stock->getQty();
            $productInfo[$pInfo['id']] = $pInfo;
        }
        return array('productInfo'=>$productInfo,'totalProduct'=>$productCollection->getSize(),'totalLoad'=>sizeof($productInfo));
    }

    public function getWarehouseProduct($warehouseId){
        $allowProduct = array();
        if (!empty($warehouseId)) {
            $currentUserId = Mage::getSingleton('admin/session')->getUser()->getId();
            if (!empty($currentUserId)) {
                $warehouseItems = Mage::getModel('xwarehouse/warehouse_product')->getCollection();
                $warehouseItems->addFieldToFilter('warehouse_id', array('eq' => $warehouseId));
                foreach ($warehouseItems as $item) {
                    if ($item->getData('enable') == 1) {
                        $allowProduct[] = $item->getData('product_id');
                    }
                }
            }
        }
        return $allowProduct;
    }

    public function getCategoryProduct($category,$page,$limit){
        $result = array();
        $result = $this->getProductofCategory($category,$page,$limit);
        $result = array_unique($result);
        $limit -= count($result);
        //show child category's product too
        $parent_category = Mage::getModel('catalog/category')->load($category->getId());
        $subcats = $parent_category->getChildren();
        foreach(explode(',',$subcats) as $subCatid){
            $_category = Mage::getModel('catalog/category')->load($subCatid);
            if($_category->getIsActive()){
                $result_child = $this->getCategoryProduct($_category,$page,$limit);
                $limit -= count($result_child);
                $result = array_merge($result,$result_child);
                $result = array_unique($result);
            }
        }
       // $new_result = array_merge($result,$result_child);
        //$new_result = array_unique($result);
        return $result;
    }

    public function getProductofCategory($category,$page,$limit){
        $storeId = Mage::getStoreConfig('xpos/general/storeid');
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId($storeId)
            ->joinField('category_id','catalog/category_product','category_id','product_id=entity_id',null,'left')
            ->addAttributeToFilter('category_id', array('in' => $category->getId()))
            ->setOrder('created_at', 'asc')->setCurPage($page)->setPageSize($limit);

        $productCollection = $this->queryProduct($productCollection);
        $productCollection->load();

        $result = array();
        foreach ($productCollection as $product) {
            $result[] = $product->getId();
        }
        $new_result = array_unique($result);
        return $new_result;
    }

    public function searchProduct($controller, $query,$warehouseId){
        $number_result = Mage::getStoreConfig('xpos/search/number_result');
        $collection = Mage::helper('catalogsearch')->getQuery()->getSearchCollection()
            ->addAttributeToSelect('*')
            ->addTaxPercents()
            ->setCurPage(0)
            ->setPageSize($number_result);

        $searchBy = Mage::getStoreConfig('xpos/search/searching_by');
        if ($searchBy != '') {
            $result = array();
            $attributes = explode(",", $searchBy);
            foreach ($attributes as $attribute) {
                $result[] = array('attribute' => $attribute, 'like' => '%' . $query . '%');
            }
        } else {
            $result = array();
            $result[] = array('attribute' => 'entity_id', 'eq' => $query);
        }
        $collection->addAttributeToFilter($result,null,'left');
        $collection = $this->queryProduct($collection);

        $collection->load();
        $result = array();
        $allowProduct = array();
        if (!empty($warehouseId)){
            $allowProduct = $this->getWarehouseProduct($warehouseId);
        }
        foreach ($collection as $product) {
            if (!in_array($product->getId(), $allowProduct) && !empty($warehouseId) && $product->getTypeId() == 'simple'){
                continue;
            }

            $flag = true;
            $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
            if (Mage::getStoreConfig('xpos/search/searching_instock') == 1) {
                if (!$stock->getIsInStock()) {
                    $flag = false;
                }
            }

            if (!$flag){
                continue;
            }
            $pInfo = $product->getId();
            //$pInfo = $this->extractData($controller,$product);
            $result[] = $pInfo;
        }
        return $result;

    }

    function queryProduct($productCollection){
        if (Mage::getStoreConfig('xpos/search/searching_status') == 1) {
            $productCollection->addAttributeToFilter('status', array('eq' => 1));
        }

        if($product_types = Mage::getStoreConfig('xpos/search/searching_product_types')){
            $product_types = explode(',',$product_types);
            if(is_array($product_types) && count($product_types) > 0){
                $productCollection->addFieldToFilter('type_id',array('IN',$product_types));
            }
        }

        if($visibility = Mage::getStoreConfig('xpos/search/searching_product_visibility')){
            $visibility = explode(',',$visibility);
            if(is_array($visibility) && count($visibility) > 0){
                $productCollection->addFieldToFilter('visibility',array('IN',$visibility));
            }
        }

        return $productCollection;
    }

    function extractData($controller,$product){
        $image = Mage::helper('catalog/image');
        $tax = Mage::helper('tax');
        $calcTax = true;

        $smallImage = $product->getData('small_image');
        if ($smallImage!=null && $smallImage!='no_selection'){
            try {
                $smallImage = $image->init($product, 'small_image')->resize(75)->__toString();
            }catch (Exception $e){
                $smallImage = null;
            }

        }else{
            $smallImage = null;
        }

        //another search data
        $searchBy = Mage::getStoreConfig('xpos/search/searching_by');
        $anotherData = '';
        if ($searchBy != '') {
            $result = array();
            $attributes = explode(",", $searchBy);
            foreach ($attributes as $attribute) {
                $anotherData .= $product->getResource()->getAttribute($attribute)->getFrontend()->getValue($product).' ';
            }
        }

        //additional field show in search
        $additional = Mage::getStoreConfig('xpos/search/additional_field');
        $additionalData = $product->getResource()->getAttribute($additional)->getFrontend()->getValue($product).' ';

        Mage::unregister('current_product');
        Mage::unregister('product');
        Mage::register('current_product', $product);
        Mage::register('product', $product);
        $update = $controller->getLayout()->getUpdate();
        $type = 'LAYOUT_GENERAL_CACHE_TAG';
        Mage::app()->getCacheInstance()->cleanType($type); // Clean cache //Mage::app()->cleanCache();
        $productType = $product->getTypeId();
        if($productType=="configurable" ||  $productType =="bundle" || $productType =="grouped"){
            $update->resetHandles();
            $update->addHandle('ADMINHTML_XPOS_CATALOG_PRODUCT_COMPOSITE_CONFIGURE');
            $update->addHandle('XPOS_PRODUCT_TYPE_' . $productType);
            $controller->loadLayoutUpdates()->generateLayoutXml()->generateLayoutBlocks();
            $options = $controller->getLayout()->getOutput();
            if (strlen($options) < 3){
                $options = null;
            }
        }


        $finalPriceValue = $product->getFinalPrice();
        $finalPriceWithTax = $tax->getPrice($product, $finalPriceValue, $calcTax);

        $price_includes_tax = Mage::getStoreConfig('tax/calculation/price_includes_tax');
        $tax_display_type = Mage::getStoreConfig('tax/display/type');

        $price = $product->getMinimalPrice();
        $finalPrice = $product->getFinalPrice();
        if($price == null){
            $price = $finalPrice;
        }

        if($price_includes_tax == 1){
            $finalPrice = $price = round($price / (1 + $product->getData('tax_percent')/100),2);
        }

        //Can't load tax when setting Catalog Prices to Excluding and Display Product Prices In Catelog to Excluding
        //Still don't know why, that mean change to do this to get tax rate
        $taxClassId = $product->getData("tax_class_id");
        $taxClasses = Mage::helper("core")->jsonDecode($tax->getAllRatesByProductClass());
        $taxRate = $taxClasses["value_" . $taxClassId];

        $pInfo = array(
            'id' => $product->getId(),
            'type' => $product->getTypeId(),
            'options' => $options,
            'name' => $product->getData('name'),
            'price' => $price,
            'finalPrice' => $finalPrice,
            'priceInclTax' => $finalPriceWithTax,
            'small_image' => $smallImage,
            'sku' =>$additionalData,
            //'tax' =>$product->getData('tax_percent'),
            'tax' =>$taxRate,
            'searchString' => $anotherData
        );

        $pInfo = array_filter( $pInfo, 'strlen' );
        return $pInfo;
    }

    function getCategoryData($product,$catData){
        $productCategory = '';
        foreach($product->getCategoryIds() as $cid){
            if (isset($catData[$cid])){
                $productCategory.=$catData[$cid].',';
            }
        }
        return $productCategory;
    }
}