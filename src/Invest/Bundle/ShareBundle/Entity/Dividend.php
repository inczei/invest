<?php

namespace Invest\Bundle\ShareBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * Dividend
 *
 * @ORM\Table(indexes={@Index(name="dividend_idx", columns={"CompanyId","ExDivDate","PaymentDate","DeclDate","Special","PaymentRate"})})
 * @ORM\Entity
 */
class Dividend
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
     * @ORM\Column(name="CompanyId", type="integer")
     */
    private $companyId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="ExDivDate", type="datetime")
     */
    private $exDivDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="DeclDate", type="datetime")
     */
    private $declDate = null;
    
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="CreatedDate", type="datetime")
     */
    private $createdDate = null;
    
    
    /**
     * @var float
     *
     * @ORM\Column(name="Amount", type="float")
     */
    private $amount;


	/**
     * @var \DateTime
     *
     * @ORM\Column(name="PaymentDate", type="datetime", nullable=True)
     */
    private $paymentDate = null;

    /**
     * @var boolean
     *
     * @ORM\Column(name="Special", type="boolean")
     */
    private $special;
    

    /**
     * @var float
     *
     * @ORM\Column(name="PaymentRate", type="float", nullable=true)
     */
    private $paymentRate = null;
    
    
    public function __construct()
    {
    	$this->createdDate = new \DateTime();
    }
    
    /**
     * Set id
     *
     * @param integer $id
     * @return Dividend
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
     * Set companyId
     *
     * @param integer $companyId
     * @return Dividend
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
     * Set exDivDate
     *
     * @param \DateTime $exDivDate
     * @return Dividend
     */
    public function setExDivDate($exDivDate)
    {
        $this->exDivDate = $exDivDate;

        return $this;
    }

    /**
     * Get exDivDate
     *
     * @return \DateTime 
     */
    public function getExDivDate()
    {
        return $this->exDivDate;
    }
    
    /**
     * Set declDate
     *
     * @param \DateTime $declDate
     * @return Dividend
     */
    public function setDeclDate($declDate)
    {
        $this->declDate = $declDate;

        return $this;
    }

    /**
     * Get declDate
     *
     * @return \DateTime 
     */
    public function getDeclDate()
    {
        return $this->declDate;
    }

    /**
     * Set createdDate
     *
     * @param \DateTime $createdDate
     * @return Dividend
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * Get createdDate
     *
     * @return \DateTime 
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }
    
    /**
     * Set amount
     *
     * @param float $amount
     * @return Dividend
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
     * Set paymentDate
     *
     * @param \DateTime $paymentDate
     * @return Dividend
     */
    public function setPaymentDate($paymentDate)
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    /**
     * Get paymentDate
     *
     * @return \DateTime 
     */
    public function getPaymentDate()
    {
        return $this->paymentDate;
    }

    /**
     * Set special
     *
     * @param boolean $special
     * @return Dividend
     */
    public function setSpecial($special)
    {
        $this->special = $special;

        return $this;
    }

    /**
     * Get special
     *
     * @return boolean 
     */
    public function getSpecial()
    {
        return $this->special;
    }

    /**
     * Set paymentRate
     *
     * @param float $paymentRate
     * @return Dividend
     */
    public function setPaymentRate($paymentRate)
    {
        $this->paymentRate = $paymentRate;

        return $this;
    }

    /**
     * Get paymentRate
     *
     * @return float 
     */
    public function getPaymentRate()
    {
        return $this->paymentRate;
    }
}
