<?php

namespace Invest\Bundle\ShareBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * Company
 *
 * @ORM\Table(indexes={@Index(name="company_idx", columns={"Name","List","Sector"})})
 * @ORM\Entity
 */
class Company
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
     * @var string
     *
     * @ORM\Column(name="Name", type="string", length=100)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="altName", type="string", length=100)
     */
    private $altName = '';
    
    /**
     * @var string
     *
     * @ORM\Column(name="List", type="string", length=20)
     */
    private $list = 'FTSE100';
    
    /**
     * @var string
     *
     * @ORM\Column(name="Code", type="string", length=4, unique=true)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="Sector", type="string", length=100, nullable=true)
     */
    private $sector = '';
    
    /**
     * @var float
     *
     * @ORM\Column(name="LastPrice", type="float", nullable=true)
     */
    private $lastPrice = null;

    /**
     * @var float
     *
     * @ORM\Column(name="LatestAveragePrice", type="float", nullable=true)
     */
    private $latestAveragePrice = null;
    
    /**
     * @var float
     *
     * @ORM\Column(name="LastDayAveragePrice", type="float", nullable=true)
     */
    private $lastDayAveragePrice = null;
    
    /**
     * @var float
     *
     * @ORM\Column(name="LastWeekAveragePrice", type="float", nullable=true)
     */
    private $lastWeekAveragePrice = null;
    
    /**
     * @var float
     *
     * @ORM\Column(name="LastMonthAveragePrice", type="float", nullable=true)
     */
    private $lastMonthAveragePrice = null;
    
    /**
     * @var float
     *
     * @ORM\Column(name="LastChange", type="float", nullable=true)
     */
    private $lastChange = null;
    
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="LastPriceDate", type="datetime", nullable=true)
     */
    private $lastPriceDate = null;

    /**
     * @var integer
     *
     * @ORM\Column(name="Frequency", type="integer")
     */
    private $frequency = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="Currency", type="string", length=4)
     */
    private $currency = 'GBP';
    
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="CreatedOn", type="datetime", nullable=true)
     */
    private $createdOn = null;
    
    
    public function __construct()
    {
    	$this->createdOn = new \DateTime();
    }
    
    /**
     * Set id
     *
     * @param integer $id
     * @return Company
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
     * Set name
     *
     * @param string $name
     * @return Company
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
     * Set altName
     *
     * @param string $altName
     * @return Company
     */
    public function setAltName($altName)
    {
        $this->altName = $altName;

        return $this;
    }

    /**
     * Get altName
     *
     * @return string 
     */
    public function getAltName()
    {
        return $this->altName;
    }
    
    /**
     * Set sector
     *
     * @param string $sector
     * @return Company
     */
    public function setSector($sector)
    {
        $this->sector = $sector;

        return $this;
    }

    /**
     * Get sector
     *
     * @return string 
     */
    public function getSector()
    {
        return $this->sector;
    }
    
    /**
     * Set code
     *
     * @param string $code
     * @return Company
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }
    
    /**
     * Set lastPrice
     *
     * @param float $lastPrice
     * @return Company
     */
    public function setLastPrice($lastPrice)
    {
        $this->lastPrice = $lastPrice;

        return $this;
    }

    /**
     * Get lastPrice
     *
     * @return float 
     */
    public function getLastPrice()
    {
        return $this->lastPrice;
    }
    
    /**
     * Set lastChange
     *
     * @param float $lastChange
     * @return Company
     */
    public function setLastChange($lastChange)
    {
        $this->lastChange = $lastChange;

        return $this;
    }

    /**
     * Get lastChange
     *
     * @return float 
     */
    public function getLastChange()
    {
        return $this->lastChange;
    }

    /**
     * Set lastPriceDate
     *
     * @param \DateTime $lastPriceDate
     * @return Company
     */
    public function setLastPriceDate($lastPriceDate)
    {
        $this->lastPriceDate = $lastPriceDate;

        return $this;
    }

    /**
     * Get lastPriceDate
     *
     * @return \DateTime
     */
    public function getLastPriceDate()
    {
        return $this->lastPriceDate;
    }
    
    /**
     * Set frequency
     *
     * @param integer $frequency
     * @return Company
     */
    public function setFrequency($frequency)
    {
        $this->frequency = $frequency;

        return $this;
    }

    /**
     * Get frequency
     *
     * @return integer
     */
    public function getFrequency()
    {
        return $this->frequency;
    }

    /**
     * Set list
     *
     * @param string $list
     * @return Company
     */
    public function setList($list)
    {
        $this->list = $list;

        return $this;
    }

    /**
     * Get list
     *
     * @return string 
     */
    public function getList()
    {
        return $this->list;
    }
    
    /**
     * Set currency
     *
     * @param string $currency
     * @return Company
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Get currency
     *
     * @return string 
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set createdOn
     *
     * @param \DateTime $createdOn
     * @return Company
     */
    public function setCreatedOn($createdOn)
    {
        $this->createdOn = $createdOn;

        return $this;
    }

    /**
     * Get createdOn
     *
     * @return \DateTime
     */
    public function getCreatedOn()
    {
        return $this->createdOn;
    }

    /**
     * Set latestAveragetPrice
     *
     * @param float $latestAveragePrice
     * @return Company
     */
    public function setLatestAveragePrice($latestAveragePrice)
    {
    	$this->latestAveragePrice = $latestAveragePrice;
    
    	return $this;
    }
    
    /**
     * Get latestAveragePrice
     *
     * @return float
     */
    public function getLatestAveragePrice()
    {
    	return $this->latestAveragePrice;
    }
    
    /**
     * Set lastDayAveragetPrice
     *
     * @param float $lastDayAveragePrice
     * @return Company
     */
    public function setLastDayAveragePrice($lastDayAveragePrice)
    {
    	$this->lastDayAveragePrice = $lastDayAveragePrice;
    
    	return $this;
    }
    
    /**
     * Get lastDayAveragePrice
     *
     * @return float
     */
    public function getLastDayAveragePrice()
    {
    	return $this->lastDayAveragePrice;
    }
    
    /**
     * Set lastWeekAveragetPrice
     *
     * @param float $lastWeekAveragePrice
     * @return Company
     */
    public function setLastWeekAveragePrice($lastWeekAveragePrice)
    {
    	$this->lastWeekAveragePrice = $lastWeekAveragePrice;
    
    	return $this;
    }
    
    /**
     * Get lastWeekAveragePrice
     *
     * @return float
     */
    public function getLastWeekAveragePrice()
    {
    	return $this->lastWeekAveragePrice;
    }
    
    /**
     * Set lastMonthAveragetPrice
     *
     * @param float $lastMonthAveragePrice
     * @return Company
     */
    public function setLastMonthAveragePrice($lastMonthAveragePrice)
    {
    	$this->lastMonthAveragePrice = $lastMonthAveragePrice;
    
    	return $this;
    }
    
    /**
     * Get lastMonthAveragePrice
     *
     * @return float
     */
    public function getLastMonthAveragePrice()
    {
    	return $this->lastMonthAveragePrice;
    }
    
}
