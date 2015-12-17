<?php

namespace Invest\Bundle\ShareBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Portfolio
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Portfolio
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
     * @var integer
     *
     * @ORM\Column(name="ClientNumber", type="integer")
     */
    private $clientNumber;
    
    /**
     * @var float
     *
     * @ORM\Column(name="StartAmount", type="float")
     */
    private $startAmount;

    /**
     * @var integer
     *
     * @ORM\Column(name="Family", type="integer")
     */
    private $family = 1;
    
    /**
     * @var integer
     *
     * @ORM\Column(name="userId", type="integer")
     */
    private $userId = null;
    
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="CreatedOn", type="datetime")
     */
    private $createdOn;
    
    
    public function __construct()
    {
    	$this->createdOn = new \DateTime();
    }
    
    
    /**
     * Set id
     *
     * @param integer $id
     * @return Portfolio
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
     * @return Portfolio
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
     * Set clientNumber
     *
     * @param integer $clientNumber
     * @return Portfolio
     */
    public function setClientNumber($clientNumber)
    {
        $this->clientNumber = $clientNumber;

        return $this;
    }

    /**
     * Get clientNumber
     *
     * @return integer 
     */
    public function getClientNumber()
    {
        return $this->clientNumber;
    }
    
    /**
     * Set startAmount
     *
     * @param float $startAmount
     * @return Portfolio
     */
    public function setStartAmount($startAmount)
    {
        $this->startAmount = $startAmount;

        return $this;
    }

    /**
     * Get startAmount
     *
     * @return float 
     */
    public function getStartAmount()
    {
        return $this->startAmount;
    }

    /**
     * Set family
     *
     * @param integer $famliy
     * @return Portfolio
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
     * @return Portfolio
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
     * Set createdOn
     *
     * @param \DateTime $createdOn
     * @return Portfolio
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
}
