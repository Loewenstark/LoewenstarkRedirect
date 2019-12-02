<?php

namespace LoewenstarkRedirect\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="s_loeredirect_old_urls")
 * @ORM\Entity
 */
class OldUrls
{
    /**
     * @var integer $id
     *
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string $sku
     *
     * @ORM\Column(type="string", length=500, nullable=false)
     */
    private $sku;


    /**
     * @var string $pid
     *
     * @ORM\Column(type="string", length=500, nullable=false)
     */
    private $pid;

    /**
     * @var string $url
     *
     * @ORM\Column(type="string", length=500, nullable=false)
     */
    private $url;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @param string $sku
     */
    public function setSku($sku)
    {
        $this->sku = $sku;
    }

    /**
     * @return string
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @param string $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }
}
