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

    public function getAttributes()
    {
        if(!$this->attributes)
        {
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
                ->addVisibleFilter()
                ->addFieldToFilter('is_visible', 1);

            //$this->attributes = $attributes;

            foreach($attributes as $attribute)
            {
                /*
                Zend_Debug::dump($attribute->getData());
                Zend_Debug::dump($attribute->getData('attribute_code'));
                Zend_Debug::dump($attribute->getData('frontend_input'));
                Zend_Debug::dump($attribute->getData('is_user_defined'));
                Zend_Debug::dump('XXXX');*/

                if(in_array($attribute->getData('attribute_code'), array('custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'options_container', 'page_layout', 'is_recurring')))
                {
                    continue;
                }

                if(in_array($attribute->getData('frontend_input'), array('text', 'select', 'multiselect', 'textarea')))
                {
                    $this->attributes[] = $attribute;
                }
                
            }
        }

        return $this->attributes;
    }

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

        $fp = fopen('product_export.csv', 'w');
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
	
        //add to row
        $i=0;
        $tmp = array(
            //'id' => $magento_product->getId(),
            'sku' => $magento_product->getSku(),
            'name' => $magento_product->getName(),
            'stock_qty' => $magento_product->getStockItem()->getQty(),
            'stock_min_qty' => $magento_product->getStockItem()->getMinQty(),
            'stock_min_sale_qty' => $magento_product->getStockItem()->getMinSaleQty(),
            'stock_max_sale_qty' => $magento_product->getStockItem()->getMaxSaleQty(),
            'stock_is_qty_decimal' => $magento_product->getStockItem()->getIsQtyDecimal(),
            'stock_backorders' => $magento_product->getStockItem()->getBackorders(),
            'stock_notify_stock_qty' => $magento_product->getStockItem()->getNotifyStockQty(),
            'stock_enable_qty_increments' => $magento_product->getStockItem()->getEnableQtyIncrements(),
            'stock_qty_increments' => $magento_product->getStockItem()->getQtyIncrements(),
            'stock_available' => $magento_product->getStockItem()->getIsInStock(),
            'short_description' => $magento_product->getData('short_description'),
            'description' => $magento_product->getData('description'),
            'manufacturer' => $magento_product->getAttributeText('manufacturer'),
            'news_from_date' => $magento_product->getData('news_from_date'),
            'news_to_date' => $magento_product->getData('news_to_date'),
            'active' => $magento_product->getData('status') ? 1 : 0,
            'ean' => $magento_product->getData('ean'),
            'ean_code' => $magento_product->getData('ean_code'),
            'meta_title' => $magento_product->getData('meta_title'),
            'meta_keyword' => $magento_product->getData('meta_keyword'),
            'meta_description' => $magento_product->getData('meta_description'),
            'short_description' => $magento_product->getData('short_description'),
            'categories' => implode(',', $category_ids),
            'url' => str_replace(basename(__FILE__).'/', '', $magento_product->getProductUrl()),
            'image_urls' => implode(';', $urls),
            'image_paths' => implode(';', $paths),
        );

        $all_attrs = $this->getAttributes();
        foreach($all_attrs as $all_attr) 
        {
            if($all_attr->getData('frontend_input')=='select')
            {
                $tmp[$all_attr->getAttributeCode()] = $magento_product->getAttributeText($all_attr->getAttributeCode());
            }
            else if($all_attr->getData('frontend_input')=='multiselect')
            {
                $tmp[$all_attr->getAttributeCode()] = $magento_product->getResource()->getAttribute($all_attr->getAttributeCode())->getFrontend()->getValue($magento_product);
            }else{
                $tmp[$all_attr->getAttributeCode()] = $magento_product->getData($all_attr->getAttributeCode());
            }
            
        }

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
            $attrs[] = 'meta_title';
            $attrs[] = 'herstellernummer';
            $attrs[] = 'ean_code';
            $attrs[] = 'short_description';
            $attrs[] = 'description';
            $attrs[] = 'manufacturer';
            $attrs[] = 'news_from_date';
            $attrs[] = 'news_to_date';
            $attrs[] = 'status';
            $attrs[] = 'ean';
            $attrs[] = 'ean_code';
            $attrs[] = 'meta_title';
            $attrs[] = 'meta_keyword';
            $attrs[] = 'meta_description';

            $all_attrs = $this->getAttributes();
            foreach($all_attrs as $all_attr) 
            {
                $attrs[] = $all_attr->getAttributeCode();
            }

            $asdasd = new Loewenstark_Shopware_Model_Resource_Catalog_Product_Collection();
            $collection = $asdasd 
                ->addAttributeToSelect($attrs)
                //->addAttributeToFilter('sku', '51580++') //group
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
