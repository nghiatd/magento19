<?php
/**
 * Created by PhpStorm.
 * User: hiephm
 * Date: 4/16/14
 * Time: 3:35 PM
 */ 
class SM_XPosAPI_Helper_Data extends Mage_Core_Helper_Abstract
{

    protected $_cacheDir = null;

    public function getCacheDir()
    {
        if(is_null($this->_cacheDir)) {
            $this->_cacheDir = Mage::getBaseDir('var') . DS . 'xposcache';
            $result = Mage::app()->getConfig()->getOptions()->createDirIfNotExists($this->_cacheDir);
            if(!$result) {
                throw new Mage_Core_Exception('Unable to create cache dir for XPOS');
            }
        }

        return $this->_cacheDir;
    }

    /**
     * @param $entity: product, category, customer
     * @param $type: all, update
     * @param $data
     */
    public function writeToCacheFile($entity, $data)
    {
        try {
            $file = fopen($this->getCacheDir() . DS . $entity, 'w');
            fwrite($file, $data);
        } catch (Exception $e) {
            Mage::logException($e);
            if(!empty($file)) {
                fclose($file);
            }
            return false;
        }

        if(!empty($file)) {
            fclose($file);
        }

        return true;
    }

}