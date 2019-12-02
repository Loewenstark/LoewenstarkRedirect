<?php

namespace LoewenstarkRedirect;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Shopware\Components\Routing\Context;
use Shopware\Components\Routing\Router;
use Shopware\Components\Translation as TranslationComponent;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Shopware-Plugin LoewenstarkRedirect.
 */
class LoewenstarkRedirect extends Plugin
{
    private $translationComponent;

    /**
     * subscribe on events
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_PostDispatch' => 'handleNoRoute',
        ];
    }

    public function handleNoRoute(\Enlight_Event_EventArgs $arguments)
    {
        /** @var $enlightController Enlight_Controller_Front */
        $enlightController = $arguments->getSubject();
     
        /** @var $request Enlight_Controller_Request_RequestHttp */
        $request = $arguments->getRequest();
     
        /** @var $response Enlight_Controller_Response_ResponseHttp */
        $response = $arguments->getResponse();

        $exceptions = $response->getException();
        if (is_array($exceptions) && $exceptions)
        {
            $last = array_pop($exceptions);
            // Make sure this is an Exception and also no minor one
            if (in_array($last->getCode(), [
                \Enlight_Controller_Exception::ActionNotFound,
                \Enlight_Controller_Exception::Controller_Dispatcher_Controller_Not_Found,
                \Enlight_Controller_Exception::Controller_Dispatcher_Controller_No_Route,
                \Enlight_Controller_Exception::NO_ROUTE,
                ], true))
            {
                $this->_handleNoRoute($request);
            }
        }else if ($response->getHttpResponseCode()=="404")
        {
            $this->_handleNoRoute($request);
        }
    }

    public function _handleNoRoute($request)
    {
        $url = $request->getRequestUri();
        $this->tryRedirectUrlByLastSlug($request, $url);
        $this->tryRedirectUrlByOldUrl($request, $url);
        $this->tryRedirectMagentoUrl($request, $url);
        $this->getCategoryUrlByOldUrl($request, $url);
        $this->getProductUrlByOldUrl($request, $url);
    }

    /*
        Try to redirect magento urls without seo url e.g.:
        .../catalog/product/view/id/4544/s/votronic-2200-12v-70a-batterietrennrelais/category/40/
    */
    public function tryRedirectMagentoUrl($request, $url)
    {
        $db = $this->container->get('dbal_connection');
        $query = $db->createQueryBuilder();

        if (strpos($url, 'catalog/product/view/id') === false)
        {
            return false;
        }

        $tmp = explode('catalog/product/view/id/', $url);
        $tmp = explode('/', $tmp[1]);
        $magento_id = $tmp[0];

        if(!$magento_id || $magento_id=='')
        {
            return false;
        }

        $query->select(['*'])
            ->from('s_loeredirect_old_urls')
            ->where("pid = :pid")
            ->setParameter(':pid', $magento_id);

        $data = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $row)
        {
            $articleId = $this->getArticleIdByNumber($row['sku']);
            if($articleId===false)
            {
                continue;
            }

            $url = $this->getProductUrlById($articleId);
            if ($url) {
                header('Location: ' . $url);
                exit;
            }
        }
        return false;
    }

    public function tryRedirectUrlByOldUrl($request, $url)
    {
        $db = $this->container->get('dbal_connection');
        $query = $db->createQueryBuilder();

        //remove first slash
        $url = ltrim($url, '/');

        //extract last path element
        $last_slug = basename($url);

        $query->select(['*'])
            ->from('s_loeredirect_old_urls')
            ->where("url LIKE :url OR url LIKE :last_url")
            ->setParameter(':url', '%' . $url)
            ->setParameter(':last_url', '%' . $last_slug);

        $data = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $row)
        {
            $articleId = $this->getArticleIdByNumber($row['sku']);
            if($articleId===false)
            {
                continue;
            }

            $url = $this->getProductUrlById($articleId);
            if ($url) {
                header('Location: ' . $url);
                exit;
            }
        }
        return false;
    }

    public function getCategoryUrlByOldUrl($request, $url)
    {
        $db = $this->container->get('dbal_connection');
        $query = $db->createQueryBuilder();

        $query->select(['*'])
            ->from('s_categories_attributes')
            ->where("magento_url LIKE :url")
            ->setParameter(':url', '%' . $url);

        $data = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $row) {
            $query = $db->createQueryBuilder();

            $query->select(['*'])
                ->from('s_core_rewrite_urls')
                ->where("org_path = :url")
                ->setParameter(':url', 'sViewport=cat&sCategory=' . $row['categoryID']);
    
            $dataa = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($dataa as $rowa) {
                header('Location: /' . $rowa['path']);
                exit;
            }
        }
        return false;
    }

    public function getProductUrlByOldUrl($request, $url)
    {
        $db = $this->container->get('dbal_connection');
        $query = $db->createQueryBuilder();

        $query->select(['*'])
            ->from('s_articles_attributes')
            ->where("magento_url LIKE :url")
            ->setParameter(':url', '%' . $url);

        $data = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $row) {
            $url = $this->getProductUrlById($row['articleID']);

            if ($url) {
                header('Location: ' . $url);
                exit;
            }
        }
        return false;
    }

    public function getArticleIdByNumber($ordernumber)
    {
        $db = $this->container->get('dbal_connection');
        $query = $db->createQueryBuilder();

        $query->select(['*'])
            ->from('s_articles_details')
            ->where("ordernumber LIKE :number")
            ->setParameter(':number', trim($ordernumber));

        $data = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $row)
        {
            return $row['articleID'];
        }
        return false;
    }

    public function getProductUrlById($id, array $options = [])
    {
        $result = array();

        //Context
        $modelManager = $this->container->get('models');
        $shop = $modelManager->getRepository(\Shopware\Models\Shop\Shop::class)->getById(1);
        $shopContext = Context::createFromShop($shop, Shopware()->Container()->get('config'));

        //get seo url
        $query = [
            'module' => 'frontend',
            'controller' => 'detail',
            'sArticle' => $id,
        ];

        $my_seourl = Shopware()->Router()->assemble($query, $shopContext);

        return $my_seourl;
    }

    /*
        Try find new product url from old category-product url e.g.
        https://www.offgridtec.com/batterien/lithium-ionen/mastervolt-mls-12-130-12v-10ah-128wh-lifepo4-akku.html
        to https://www.offgridtec.com/mastervolt-mls-12-130-12v-10ah-128wh-lifepo4-akku.html
    */
    public function tryRedirectUrlByLastSlug($request, $url)
    {
        $db = $this->container->get('dbal_connection');
        $query = $db->createQueryBuilder();

        $last_slug = basename($url); //extract "mastervolt-mls-12-130-12v-10ah-128wh-lifepo4-akku.html"

        $query->select(['*'])
            ->from('s_core_rewrite_urls')
            ->where("path LIKE :url")
            ->setParameter(':url', $last_slug);

        $dataa = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($dataa as $rowa) {
            header('Location: /' . $rowa['path']);
            exit;
        }

        return false;
    }

    public function update(UpdateContext $updateContext)
    {
        if (version_compare($updateContext->getCurrentVersion(), '1.0.2', '<=')) {
            $this->createAttributes();
        }
        if (version_compare($updateContext->getCurrentVersion(), '1.0.1', '<=')) {
            $this->createOldUrlTable();
        }
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        $this->createOldUrlTable();
        $this->createAttributes();
    }

    public function createAttributes()
    {
        $crudService = $this->container->get('shopware_attribute.crud_service');
        //$snippets = $this->container->get('snippets');

        $crudService->update('s_articles_attributes', 'magento_url', 'string', [
            'displayInBackend' => true,
            'label' => "Magento Url",
        ]);

        $crudService->update('s_categories_attributes', 'magento_url', 'string', [
            'displayInBackend' => true,
            'label' => "Magento Url",
        ]);
    }

    public function createOldUrlTable()
    {
        $em = $this->container->get('models');
        $schemaTool = new SchemaTool($em);
        $schemaTool->updateSchema(
            [ $em->getClassMetadata(\LoewenstarkRedirect\Models\OldUrls::class) ],
            true
        );
    }
}
