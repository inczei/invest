<?php

namespace Invest\Bundle\ShareBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * TradeTransactions
 *
 * @ORM\Table(indexes={@Index(name="tradetransactions_idx", columns={"tradeId","type","settleDate","tradeDate","reference"})})
 * @ORM\Entity
 */
class TradeTransactions
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
     * @ORM\Column(name="tradeId", type="integer")
     */
    private $tradeId;

    /**
     * @var integer
     *
     * @ORM\Column(name="type", type="integer")
     */
    private $type;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="settleDate", type="datetime")
     */
    private $settleDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="tradeDate", type="datetime")
     */
    private $tradeDate;

    /**
     * @var string
     *
     * @ORM\Column(name="reference", type="string", length=20, unique=true)
     */
    private $reference;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    private $description = '';

    /**
     * @var float
     *
     * @ORM\Column(name="unitPrice", type="float")
     */
    private $unitPrice;

    /**
     * @var float
     *
     * @ORM\Column(name="quantity", type="float")
     */
    private $quantity;

    /**
     * @var float
     *
     * @ORM\Column(name="cost", type="float")
     */
    private $cost;


    /**
     * Set id
     *
     * @param integer $id
     * @return TradeTransactions
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
     * Set tradeId
     *
     * @param integer $tradeId
     * @return TradeTransactions
     */
    public function setTradeId($tradeId)
    {
        $this->tradeId = $tradeId;

        return $this;
    }

    /**
     * Get tradeId
     *
     * @return integer 
     */
    public function getTradeId()
    {
        return $this->tradeId;
    }

    /**
     * Set type
     *
     * @param integer $type
     * @return TradeTransactions
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return integer 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set settleDate
     *
     * @param \DateTime $settleDate
     * @return TradeTransactions
     */
    public function setSettleDate($settleDate)
    {
        $this->settleDate = $settleDate;

        return $this;
    }

    /**
     * Get settleDate
     *
     * @return \DateTime 
     */
    public function getSettleDate()
    {
        return $this->settleDate;
    }

    /**
     * Set tradeDate
     *
     * @param \DateTime $tradeDate
     * @return TradeTransactions
     */
    public function setTradeDate($tradeDate)
    {
        $this->tradeDate = $tradeDate;

        return $this;
    }

    /**
     * Get tradeDate
     *
     * @return \DateTime 
     */
    public function getTradeDate()
    {
        return $this->tradeDate;
    }

    /**
     * Set reference
     *
     * @param string $reference
     * @return TradeTransactions
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
     * @return TradeTransactions
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

    /**
     * Set unitPrice
     *
     * @param float $unitPrice
     * @return TradeTransactions
     */
    public function setUnitPrice($unitPrice)
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /**
     * Get unitPrice
     *
     * @return float 
     */
    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    /**
     * Set quantity
     *
     * @param float $quantity
     * @return TradeTransactions
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Get quantity
     *
     * @return float 
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set cost
     *
     * @param float $cost
     * @return TradeTransactions
     */
    public function setCost($cost)
    {
        $this->cost = $cost;

        return $this;
    }

    /**
     * Get cost
     *
     * @return float 
     */
    public function getCost()
    {
        return $this->cost;
    }
}
