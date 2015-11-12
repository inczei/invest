<?php

namespace Invest\Bundle\ShareBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\GeneratedValue;

/**
 * PortfolioTransaction
 *
 * @ORM\Table(indexes={@Index(name="portfoliotransaction_idx", columns={"PortfolioId","Date","Reference"})})
 * @ORM\Entity
 */
class PortfolioTransaction
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
    private $PortfolioId;

    
    /**
     * @var float
     *
     * @ORM\Column(name="Amount", type="float")
     */
    private $amount;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="Date", type="datetime")
     */
    private $date;

    /**
     * @var string
     *
     * @ORM\Column(name="Reference", type="string", length=50)
     */
    private $reference;
    
    /**
     * @var string
     *
     * @ORM\Column(name="Description", type="string", length=255)
     */
    private $description;
    
    
    /**
     * Set id
     *
     * @param integer $id
     * @return PortfolioTransaction
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
     * Set PortfolioId
     *
     * @param integer $PortfolioId
     * @return PortfolioTransaction
     */
    public function setPortfolioId($PortfolioId)
    {
    	$this->PortfolioId = $PortfolioId;

    	return $this;
    }

    /**
     * Get PortfolioId
     *
     * @return integer 
     */
    public function getPortfolioId()
    {
        return $this->PortfolioId;
    }

    /**
     * Set amount
     *
     * @param float $amount
     * @return PortfolioTransaction
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount
     *
     * @return float 
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set date
     *
     * @param \DateTime $date
     * @return PortfolioTransaction
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     *
     * @return \DateTime 
     */
    public function getDate()
    {
        return $this->date;
    }
    
    /**
     * Set reference
     *
     * @param string $reference
     * @return PortfolioTransaction
     */
    public function setReference($reference)
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * Get reference
     *
     * @return string 
     */
    public function getReference()
    {
        return $this->reference;
    }
    
    /**
     * Set description
     *
     * @param string $description
     * @return PortfolioTransaction
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }
}
