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
	private $type;
	
	public function __construct($trade, $tradeTransaction, $portfolios, $companies, $type)
	{
		$this->trade = $trade;
		$this->tradeTransaction = $tradeTransaction;
		$this->portfolios = $portfolios;
		$this->companies = $companies;
		$this->type = $type;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('id', 'hidden', array(
				'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getId()):(null))
			))
			->add('tradeId', 'hidden', array(
				'data'=>((isset($this->trade))?($this->trade->getId()):(null))
			))
			->add('tradeDate', 'date', array(
				'label'=>'Trade date',
				'widget'=>'single_text',
				'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getTradeDate()):(null)),
				'format'=>'dd/MM/yyyy',
				'attr'=>array(
		    		'class'=>'dateInput',
					'size'=>10
				)
			))
			->add('settleDate', 'date', array(
				'label'=>'Settle date',
				'widget'=>'single_text',
				'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getSettleDate()):(null)),
				'format'=>'dd/MM/yyyy',
				'attr'=>array(
		    		'class'=>'dateInput',
					'size'=>10
				)
			))
			->add('reference', 'text', array(
				'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getReference()):(null))
			))
			->add('description', 'text', array(
				'required'=>false,
				'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getDescription()):(null))
			))
			->add('quantity', 'text', array(
				'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getQuantity()):(null))
			))
			->add('unitPrice', 'text', array(
				'label'=>'Unit cost (p)',
				'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getUnitPrice()):(null))
			))
			->add('cost', 'text', array(
				'label'=>'Cost (£)',
				'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getCost()):(null))
			))
        	->add('save', 'submit', array(
    			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
        	
        switch ($this->type) {
        	case 'buy' : {
				$builder
					->add('portfolioId', 'choice', array(
						'label'=>'Portfolio',
						'choices'=>(($this->trade->getPortfolioId())?(array($this->portfolios[$this->trade->getPortfolioId()])):($this->portfolios)),
						'data'=>((isset($this->trade))?($this->trade->getPortfolioId()):(null)),
						'mapped'=>false,
						'attr'=>array(
								'style'=>'width: 200px'
						),
						'read_only'=>((isset($this->trade) && $this->trade->getPortfolioId())?true:false)
					))
					->add('companyId', 'choice', array(
						'label'=>'Company',
						'choices'=>(($this->trade->getCompanyId())?(array($this->companies[$this->trade->getCompanyId()])):($this->companies)),
						'data'=>((isset($this->trade))?($this->trade->getCompanyId()):(null)),
						'mapped'=>false,
						'attr'=>array(
							'style'=>'width: 200px'
						),
						'read_only'=>(($this->trade->getCompanyId())?true:false)
					))
					->add('type', 'choice', array(
						'choices'=>(($this->tradeTransaction->getId())?($this->tradeTransaction->getType()?(array(1=>'Sell')):(array(0=>'Buy'))):(array(0=>'Buy', 1=>'Sell'))),
						'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getType()):(null)),
						'read_only'=>true
					));
						
        		break;
        	}
        	case 'sell' : {
				$builder
					->add('portfolioName', 'text', array(
						'label'=>'Portfolio',
						'data'=>((isset($this->trade))?($this->portfolios[$this->trade->getPortfolioId()]):(null)),
						'mapped'=>false,
						'attr'=>array(
								'style'=>'width: 200px'
						),
						'read_only'=>true
					))
					->add('companyName', 'text', array(
						'label'=>'Company',
						'data'=>((isset($this->trade))?($this->companies[$this->trade->getCompanyId()]):(null)),
						'mapped'=>false,
						'attr'=>array(
							'style'=>'width: 200px'
						),
						'read_only'=>true
					))
					->add('type', 'choice', array(
						'choices'=>array(1=>'Sell'),
						'data'=>((isset($this->tradeTransaction))?($this->tradeTransaction->getType()):(null)),
						'read_only'=>true
					));
					break;
        	}
        }
    }

    public function getName()
    {
        return 'tradedetails';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }