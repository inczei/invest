<?php
// src/Invest/Bundle/ShareBundle/Form/Type/TradeDetailsType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class TradeDetailsType extends AbstractType
{
	
	private $trade;
	private $tradeTransaction;
	private $portfolios;
	private $companies;
	
	public function __construct($trade, $tradeTransaction, $portfolios, $companies)
	{
		$this->trade = $trade;
		$this->tradeTransaction = $tradeTransaction;
		$this->portfolios = $portfolios;
		$this->companies = $companies;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('id', 'hidden', array(
				'data'=>$this->tradeTransaction->getId()
			))
			->add('portfolioId', 'choice', array(
				'label'=>'Portfolio',
				'choices'=>(($this->trade->getPortfolioId())?(array($this->portfolios[$this->trade->getPortfolioId()])):($this->portfolios)),
				'data'=>$this->trade->getPortfolioId(),
				'mapped'=>false,
    			'attr'=>array(
    				'style'=>'width: 200px'
    			),
				'read_only'=>(($this->trade->getPortfolioId())?true:false)
			))
			->add('companyId', 'choice', array(
				'label'=>'Company',
				'choices'=>(($this->trade->getCompanyId())?(array($this->companies[$this->trade->getCompanyId()])):($this->companies)),
				'data'=>$this->trade->getCompanyId(),
				'mapped'=>false,
    			'attr'=>array(
    				'style'=>'width: 200px'
    			),
				'read_only'=>(($this->trade->getCompanyId())?true:false)
			))
			->add('type', 'choice', array(
				'choices'=>(($this->tradeTransaction->getId())?($this->tradeTransaction->getType()?(array(1=>'Sell')):(array(0=>'Buy'))):(array(0=>'Buy', 1=>'Sell'))),
				'data'=>$this->tradeTransaction->getType(),
				'read_only'=>true
			))
			->add('tradeDate', 'date', array(
				'label'=>'Trade date',
				'widget'=>'single_text',
				'data'=>$this->tradeTransaction->getTradeDate(),
				'format'=>'dd/MM/yyyy',
				'attr'=>array(
		    		'class'=>'dateInput',
					'size'=>10
				)
			))
			->add('settleDate', 'date', array(
				'label'=>'Settle date',
				'widget'=>'single_text',
				'data'=>$this->tradeTransaction->getSettleDate(),
				'format'=>'dd/MM/yyyy',
				'attr'=>array(
		    		'class'=>'dateInput',
					'size'=>10
				)
			))
			->add('reference', 'text', array(
				'data'=>$this->tradeTransaction->getReference()
			))
			->add('description', 'text', array(
				'required'=>false,
				'data'=>$this->tradeTransaction->getDescription()
			))
			->add('quantity', 'text', array(
				'data'=>$this->tradeTransaction->getQuantity()
			))
			->add('unitPrice', 'text', array(
				'label'=>'Unit cost (p)',
				'data'=>$this->tradeTransaction->getUnitPrice()
			))
			->add('cost', 'text', array(
				'label'=>'Cost (£)',
				'data'=>$this->tradeTransaction->getCost()
			))
        	->add('save', 'submit', array(
    			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'tradedetails';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }