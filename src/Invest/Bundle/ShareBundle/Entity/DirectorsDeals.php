<?php

namespace Invest\Bundle\ShareBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * DirectorsDeals
 *
 * @ORM\Table(indexes={@Index(name="directorsdeals_idx", columns={"Code","Name","DeclDate","DealDate","Type","Position"})})
 * @ORM\Entity
 */
class DirectorsDeals
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
     * @ORM\Column(name="Code", type="string", length=4)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="Name", type="string", length=100)
     */
    private $name;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="CreatedOn", type="datetime")
     */
    private $createdOn;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="DeclDate", type="datetime")
     */
    private $declDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="DealDate", type="datetime")
     */
    private $dealDate;

    /**
     * @var string
     *
     * @ORM\Column(name="Type", type="string", length=4)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="Position", type="string", length=4)
     */
    private $position;

    /**
     * @var float
     *
     * @ORM\Column(name="Shares", type="float")
     */
    private $shares;

    /**
     * @var float
     *
     * @ORM\Column(name="Price", type="float")
     */
    private $price;

    /**
     * @var float
     *
     * @ORM\Column(name="Value", type="float")
     */
    private $value;


    public function __construct()
    {
    	$this->createdOn = new \DateTime();
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
     * Set code
     *
     * @param string $code
     * @return DirectorsDeals
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
     * Set name
     *
     * @param string $name
     * @return DirectorsDeals
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
     * Set createdOn
     *
     * @param \DateTime $createdOn
     * @return DirectorsDeals
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
     * Set declDate
     *
     * @param \DateTime $declDate
     * @return DirectorsDeals
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
     * Set dealDate
     *
     * @param \DateTime $dealDate
     * @return DirectorsDeals
     */
    public function setDealDate($dealDate)
    {
        $this->dealDate = $dealDate;

        return $this;
    }

    /**
     * Get dealDate
     *
     * @return \DateTime 
     */
    public function getDealDate()
    {
        return $this->dealDate;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return DirectorsDeals
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set position
     *
     * @param string $position
     * @return DirectorsDeals
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position
     *
     * @return string 
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set shares
     *
     * @param float $shares
     * @return DirectorsDeals
     */
    public function setShares($shares)
    {
        $this->shares = $shares;

        return $this;
    }

    /**
     * Get shares
     *
     * @return float 
     */
    public function getShares()
    {
        return $this->shares;
    }

    /**
     * Set price
     *
     * @param float $price
     * @return DirectorsDeals
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get price
     *
     * @return float 
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set value
     *
     * @param float $value
     * @return DirectorsDeals
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return float 
     */
    public function getValue()
    {
        return $this->value;
    }
}
