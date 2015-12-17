<?php

namespace Invest\Bundle\ShareBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
/**
 * Summary
 *
 * @ORM\Table(indexes={@Index(name="summary_portfolioid_idx", columns={"PortfolioId"})})
 * @ORM\Entity
 */
class Summary
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
     * @var float
     *
     * @ORM\Column(name="CurrentDividend", type="float", nullable=true)
     */
    private $currentDividend;

    /**
     * @var float
     *
     * @ORM\Column(name="Investment", type="float", nullable=true)
     */
    private $investment;

    /**
     * @var float
     *
     * @ORM\Column(name="CurrentValue", type="float", nullable=true)
     */
    private $currentValue;

    /**
     * @var text
     *
     * @ORM\Column(name="CurrentValueBySector", type="text")
     */
    private $currentValueBySector = '';
    
    /**
     * @var float
     *
     * @ORM\Column(name="Profit", type="float")
     */
    private $profit;

    /**
     * @var float
     *
     * @ORM\Column(name="DividendPaid", type="float")
     */
    private $dividendPaid;
    
    /**
     * @var float
     *
     * @ORM\Column(name="RealisedProfit", type="float")
     */
    private $realisedProfit;

    /**
     * @var float
     *
     * @ORM\Column(name="DividendYield", type="float")
     */
    private $dividendYield;

    /**
     * @var float
     *
     * @ORM\Column(name="CurrentROI", type="float")
     */
    private $currentROI;

    /**
     * @var float
     *
     * @ORM\Column(name="CashIn", type="float")
     */
    private $cashIn;

    /**
     * @var float
     *
     * @ORM\Column(name="UnusedCash", type="float")
     */
    private $unusedCash;

    /**
     * @var float
     *
     * @ORM\Column(name="ActualDividendIncome", type="float", nullable=true)
     */
    private $actualDividendIncome;

    /**
     * @var float
     *
     * @ORM\Column(name="CgtProfitsRealised", type="float")
     */
    private $cgtProfitsRealised;

    /**
     * @var float
     *
     * @ORM\Column(name="UnusedCgtAllowance", type="float")
     */
    private $unusedCgtAllowance;

    /**
     * @var float
     *
     * @ORM\Column(name="UnusedBasicRateBand", type="float")
     */
    private $unusedBasicRateBand;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="UpdatedOn", type="datetime")
     */
    private $updatedOn;

    /**
     * @var integer
     *
     * @ORM\Column(name="Family", type="integer")
     */
    private $family;

    /**
     * @var integer
     *
     * @ORM\Column(name="UserId", type="integer")
     */
    private $userId;
    
    

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
     * @return Summary
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
     * Set currentDividend
     *
     * @param float $currentDividend
     * @return Summary
     */
    public function setCurrentDividend($currentDividend)
    {
        $this->currentDividend = $currentDividend;

        return $this;
    }

    /**
     * Get currentDividend
     *
     * @return float 
     */
    public function getCurrentDividend()
    {
        return $this->currentDividend;
    }

    /**
     * Set investment
     *
     * @param float $investment
     * @return Summary
     */
    public function setInvestment($investment)
    {
        $this->investment = $investment;

        return $this;
    }

    /**
     * Get investment
     *
     * @return float 
     */
    public function getInvestment()
    {
        return $this->investment;
    }

    /**
     * Set currentValue
     *
     * @param float $currentValue
     * @return Summary
     */
    public function setCurrentValue($currentValue)
    {
        $this->currentValue = $currentValue;

        return $this;
    }

    /**
     * Get currentValue
     *
     * @return float 
     */
    public function getCurrentValue()
    {
        return $this->currentValue;
    }

    /**
     * Set profit
     *
     * @param float $profit
     * @return Summary
     */
    public function setProfit($profit)
    {
        $this->profit = $profit;

        return $this;
    }

    /**
     * Get profit
     *
     * @return float 
     */
    public function getProfit()
    {
        return $this->profit;
    }

    /**
     * Set dividendPaid
     *
     * @param float $dividendPaid
     * @return Summary
     */
    public function setDividendPaid($dividendPaid)
    {
        $this->dividendPaid = $dividendPaid;

        return $this;
    }

    /**
     * Get dividendPaid
     *
     * @return float 
     */
    public function getDividendPaid()
    {
        return $this->dividendPaid;
    }
    
    /**
     * Set realisedProfit
     *
     * @param float $realisedProfit
     * @return Summary
     */
    public function setRealisedProfit($realisedProfit)
    {
        $this->realisedProfit = $realisedProfit;

        return $this;
    }

    /**
     * Get realisedProfit
     *
     * @return float 
     */
    public function getRealisedProfit()
    {
        return $this->realisedProfit;
    }

    /**
     * Set dividendYield
     *
     * @param float $dividendYield
     * @return Summary
     */
    public function setDividendYield($dividendYield)
    {
        $this->dividendYield = $dividendYield;

        return $this;
    }

    /**
     * Get dividendYield
     *
     * @return float 
     */
    public function getDividendYield()
    {
        return $this->dividendYield;
    }

    /**
     * Set currentROI
     *
     * @param float $currentROI
     * @return Summary
     */
    public function setCurrentROI($currentROI)
    {
        $this->currentROI = $currentROI;

        return $this;
    }

    /**
     * Get currentROI
     *
     * @return float 
     */
    public function getCurrentROI()
    {
        return $this->currentROI;
    }

    /**
     * Set cashIn
     *
     * @param float $cashIn
     * @return Summary
     */
    public function setCashIn($cashIn)
    {
        $this->cashIn = $cashIn;

        return $this;
    }

    /**
     * Get cashIn
     *
     * @return float 
     */
    public function getCashIn()
    {
        return $this->cashIn;
    }

    /**
     * Set unusedCash
     *
     * @param float $unusedCash
     * @return Summary
     */
    public function setUnusedCash($unusedCash)
    {
        $this->unusedCash = $unusedCash;

        return $this;
    }

    /**
     * Get unusedCash
     *
     * @return float 
     */
    public function getUnusedCash()
    {
        return $this->unusedCash;
    }

    /**
     * Set actualDividendIncome
     *
     * @param float $actualDividendIncome
     * @return Summary
     */
    public function setActualDividendIncome($actualDividendIncome)
    {
        $this->actualDividendIncome = $actualDividendIncome;

        return $this;
    }

    /**
     * Get actualDividendIncome
     *
     * @return float 
     */
    public function getActualDividendIncome()
    {
        return $this->actualDividendIncome;
    }

    /**
     * Set cgtProfitsRealised
     *
     * @param float $cgtProfitsRealised
     * @return Summary
     */
    public function setCgtProfitsRealised($cgtProfitsRealised)
    {
        $this->cgtProfitsRealised = $cgtProfitsRealised;

        return $this;
    }

    /**
     * Get cgtProfitsRealised
     *
     * @return float 
     */
    public function getCgtProfitsRealised()
    {
        return $this->cgtProfitsRealised;
    }

    /**
     * Set unusedCgtAllowance
     *
     * @param float $unusedCgtAllowance
     * @return Summary
     */
    public function setUnusedCgtAllowance($unusedCgtAllowance)
    {
        $this->unusedCgtAllowance = $unusedCgtAllowance;

        return $this;
    }

    /**
     * Get unusedCgtAllowance
     *
     * @return float 
     */
    public function getUnusedCgtAllowance()
    {
        return $this->unusedCgtAllowance;
    }

    /**
     * Set unusedBasicRateBand
     *
     * @param float $unusedBasicRateBand
     * @return Summary
     */
    public function setUnusedBasicRateBand($unusedBasicRateBand)
    {
        $this->unusedBasicRateBand = $unusedBasicRateBand;

        return $this;
    }

    /**
     * Get unusedBasicRateBand
     *
     * @return float 
     */
    public function getUnusedBasicRateBand()
    {
        return $this->unusedBasicRateBand;
    }

    /**
     * Set updatedOn
     *
     * @param \DateTime $updatedOn
     * @return Summary
     */
    public function setUpdatedOn($updatedOn)
    {
        $this->updatedOn = $updatedOn;

        return $this;
    }

    /**
     * Get updatedOn
     *
     * @return \DateTime 
     */
    public function getUpdatedOn()
    {
        return $this->updatedOn;
    }

    /**
     * Set family
     *
     * @param integer $family
     * @return Summary
     */
    public function setFamily($family)
    {
        $this->family = $family;

        return $this;
    }

    /**
     * Get family
     *
     * @return integer
     */
    public function getFamily()
    {
        return $this->family;
    }

    /**
     * Set userId
     *
     * @param integer $userId
     * @return Summary
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Get userId
     *
     * @return integer
     */
    public function getUserId()
    {
        return $this->userId;
    }
    
    /**
     * Set currentValueBySector
     *
     * @param text $currentValueBySector
     * @return Summary
     */
    public function setCurrentValueBySector($currentValueBySector)
    {
        $this->currentValueBySector = $currentValueBySector;

        return $this;
    }

    /**
     * Get currentValueBySector
     *
     * @return text 
     */
    public function getCurrentValueBySector()
    {
        return $this->currentValueBySector;
    }
}
