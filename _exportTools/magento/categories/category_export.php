<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dir = dirname(__FILE__);
chdir($dir);
require $dir.'/app/Mage.php';
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
//Mage::app()->setCurrentStore(12);


class Category_Export
{
    public $_media_gallery_backend_model;
    public $rows = array();
    public $store_id = 0;
    public $_collection = array();

    public function run($store_id = 0)
    {
        //set store id
        $this->store_id = $store_id;

	//add header row
        $this->rows[] = array(
            'categoryId',
            'parentID',
            'description',
            'position',
            'metatitle',
            'metakeywords',
            'metadescription',
            'cmsheadline',
            'cmstext',
            'template',
            'active',
            'blog',
            'external',
            'hidefilter',
            'attribute_attribute1',
            'attribute_attribute2',
            'attribute_attribute3',
            'attribute_attribute4',
            'attribute_attribute5',
            'attribute_attribute6',
            'CustomerGroup',
            'magento_url',
            'name',
            'level',
            'path',
        );

        //prepare categories
        $this->prepareCategories();

        //proccess categories
        $magento_categories = $this->getMagentoCategories();
        Zend_Debug::dump($magento_categories->count());
        foreach($magento_categories as $magento_category)
        {
            $this->proccessCategory($magento_category);
        }

        $fp = fopen('category_export.csv', 'w');

        foreach ($this->rows as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);

	    echo ('DONE');
        //Zend_Debug::dump($this->rows);
    }

    public function proccessCategory($magento_category)
    {
        //add to row
        $this->rows[] = array(
            'categoryId' => $magento_category->getId(),
            'parentID' => $magento_category->getParentId(),
            'description' => $magento_category->getDescription(),
            'position' => $magento_category->getPosition(),
            'metatitle' => $magento_category->getMetaTitle(),
            'metakeywords' => $magento_category->getMetaKeywords(),
            'metadescription' => $magento_category->getMetaDescription(),
            'cmsheadline' => '',
            'cmstext' => '',
            'template' => '',
            'active' => $magento_category->getIsActive(),
            'blog' => '',
            'external' => '',
            'hidefilter' => 0,
            'attribute_attribute1' => '',
            'attribute_attribute2' => '',
            'attribute_attribute3' => '',
            'attribute_attribute4' => '',
            'attribute_attribute5' => '',
            'attribute_attribute6' => '',
            'CustomerGroup' => '',
            //'sku' => $magento_category->getSku(),
            'magento_url' => str_replace(basename(__FILE__).'/', '', $magento_category->getUrl()),
            'name' => $magento_category->getName(),
            'level' => $magento_category->getLevel(),
            'path' => $magento_category->getPath(),
        );

        //Zend_Debug::dump($this->rows);
        //die;
    }

    public function prepareCategories()
    {
        $magento_categories = $this->getMagentoCategories();
        foreach($magento_categories as $magento_category)
        {
            $this->prepareMagentoCategory($magento_category);
        }
    }

    public function prepareMagentoCategory($magento_product)
    {
        //Images
        //$this->getMediaGalleryBackendModel()->afterLoad($magento_product);
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

    public function getMagentoCategories()
    {
        $store_id = $this->store_id;

        if(!array_key_exists($store_id, $this->_collection) || !$this->_collection[$store_id])
        {
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($store_id);

            $this->_collection[$store_id] = Mage::getModel('catalog/category')
                ->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToSort('position', 'asc')
                //->addAttributeToFilter('entity_id', array('in' => 74))
                ->load();

            //preload url inside emulation
            foreach($this->_collection[$store_id] as $category)
            {
                $category->getUrl();
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }

        return $this->_collection[$store_id];

    }
}

$x = new Category_Export();
$x->run(1);