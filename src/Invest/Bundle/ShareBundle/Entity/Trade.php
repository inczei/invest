<?php

namespace Invest\Bundle\ShareBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * Trade
 *
 * @ORM\Table(indexes={@Index(name="trade_idx", columns={"PortfolioId","CompanyId","sold"})})
 * @ORM\Entity
 */
class Trade
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="PortfolioId", type="integer")
     */
    private $portfolioId;
    
    /**
     * @var integer
     *
     * @ORM\Column(name="CompanyId", type="integer")
     */
    private $companyId;

    /**
     * @var boolean
     *
     * @ORM\Column(name="Sold", type="boolean")
     */
    private $sold = FALSE;

    /**
     * @var string
     *
     * @ORM\Column(name="Name", type="string", length=100)
     */
    private $name;

	/**
     * @var float
     *
     * @ORM\Column(name="PE_Ratio", type="float", nullable=true)
     */
    private $pERatio = NULL;


    
    /**
     * Set Id
     *
     * @param integer $Id
     * @return Trade
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }
    
    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set portfolioId
     *
     * @param integer $portfolioId
     * @return Trade
     */
    public function setPortfolioId($portfolioId)
    {
        $this->portfolioId = $portfolioId;

        return $this;
    }

    /**
     * Get portfolioId
     *
     * @return integer
     */
    public function getPortfolioId()
    {
        return $this->portfolioId;
    }
    
    /**
     * Set companyId
     *
     * @param integer $companyId
     * @return Trade
     */
    public function setCompanyId($companyId)
    {
        $this->companyId = $companyId;

        return $this;
    }

    /**
     * Get companyId
     *
     * @return integer 
     */
    public function getCompanyId()
    {
        return $this->companyId;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Trade
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set pERatio
     *
     * @param float $pERatio
     * @return Trade
     */
    public function setPERatio($pERatio)
    {
        $this->pERatio = $pERatio;

        return $this;
    }

    /**
     * Get pERatio
     *
     * @return float 
     */
    public function getPERatio()
    {
        return $this->pERatio;
    }


    /**
     * Set sold
     *
     * @param boolean $sold
     * @return Trade
     */
    public function setSold($sold)
    {
        $this->sold = $sold;

        return $this;
    }

    /**
     * Get sold
     *
     * @return boolean 
     */
    public function getSold()
    {
        return $this->sold;
    }
}
