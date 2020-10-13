<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$dir = dirname(__FILE__);
chdir($dir);
require $dir.'/app/Mage.php';
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
//Mage::app()->setCurrentStore(12);

class Loewenstark_Shopware_Model_Resource_Catalog_Product_Collection
extends Mage_Catalog_Model_Resource_Product_Collection
{

    /**
     * Retrieve is flat enabled flag
     * Return always false if magento run admin
     *
     * @return bool
     */
    public function isEnabledFlat()
    {
        return false;
    }

}



class Product_Export
{
    public $_media_gallery_backend_model;
    public $rows = array();
    public $attributes = array();

    public function run()
    {
        //prepare products
        $this->prepareProducts();
        //proccess products
        $magento_products = $this->getMagentoProducts();
        Zend_Debug::dump($magento_products->count());
        foreach($magento_products as $magento_product)
        {
            $this->proccessProduct($magento_product);
        }

        //header
        $header = array();
        foreach($this->rows[0] as $key=>$value)
        {
            $header[$key] = $key;
        }
        array_unshift($this->rows, $header);

        $fp = fopen('price_export.csv', 'w');
        foreach ($this->rows as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);
	    echo ('DONE');
        //Zend_Debug::dump($this->rows);
    }
    public function proccessProduct($magento_product)
    {
        //images
        $paths = array();
        $urls = array();
        foreach ($magento_product->getMediaGalleryImages() as $image)
        {
            $paths[] = $image->getPath();
            $urls[] = $image->getUrl();
        }
        //categories
	
        $category_ids = $magento_product->getCategoryIds();
        $categories = Mage::getModel('catalog/category')->getCollection()
                       ->addAttributeToSelect('name')
                       ->addAttributeToFilter('entity_id', array('in' => $category_ids));
        $category_names = array();
        foreach($categories as $category) {
            $category_names[] = $category->getName();
        }

        //tier prices
        $tier_prices = array();
        foreach($magento_product->getTierPrice() as $tier_price)
        {
            $tier_prices[] = $tier_price['price_qty'] . ':' . $tier_price['price'];
        }
	
        //add to row
        $i=0;
        $tmp = array(
            //'id' => $magento_product->getId(),
            'sku' => $magento_product->getSku(),
            'price' => $magento_product->getPrice(),
            'special_price' => $magento_product->getSpecialPrice(),
            'tier_prices' => implode('|', $tier_prices),
        );



        $this->rows[] = $tmp;

	    //Zend_Debug::dump($this->rows);
	    //die;
    }
    public function prepareProducts()
    {
        $magento_products = $this->getMagentoProducts();
        foreach($magento_products as $magento_product)
        {
            $this->prepareMagentoProduct($magento_product);
        }
    }
    public function prepareMagentoProduct($magento_product)
    {
        //Images
        $this->getMediaGalleryBackendModel()->afterLoad($magento_product);
    }
    public function getMediaGalleryBackendModel()
    {
        if(!$this->_media_gallery_backend_model)
        {
            Zend_Debug::dump('LOAD BACKEND MODEL');
            $this->_media_gallery_backend_model = Mage::getModel('catalog/product')->getResource()->getAttribute('media_gallery')->getBackend();
        }
        return $this->_media_gallery_backend_model;
    }
    public function getMagentoProducts()
    {
        if(!$this->_magento_products)
        {
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation(1);
            $attrs = Mage::getSingleton('catalog/config')->getProductAttributes();
            $attrs[] = 'sku';
            $attrs[] = 'price';
            $attrs[] = 'special_price';

            $asdasd = new Loewenstark_Shopware_Model_Resource_Catalog_Product_Collection();
            $collection = $asdasd 
                ->addAttributeToSelect($attrs)
                //->addAttributeToFilter('sku', '112482') //group
                //->addAttributeToFilter('sku', array('in' => array('40454++', '8300040460', '8300040881', '8300040459', '8300040458', '8300040457', '8300040456', '8300040454'))) //group with related and child
                //->addAttributeToFilter('sku', '8300042459') //single
                //->setPageSize(20)
                //->setCurPage(1)
                ->setFlag('require_stock_items', true)
                ->addTaxPercents()
                ->addUrlRewrite(1)
                ;
            $collection->load();
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
            $this->_magento_products = $collection;
        }
        return $this->_magento_products;
    }
}
$x = new Product_Export();
$x->run();
