<?php
// src/Invest/Bundle/ShareBundle/Form/Type/PortfolioCreditDebitType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class PortfolioCreditDebitType extends AbstractType
{
	
	private $portfolioTransaction;
	
	public function __construct($portfolioTransaction)
	{
		$this->portfolioTransaction = $portfolioTransaction;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('id', 'hidden', array(
				'data'=>$this->portfolioTransaction->getId()
			))
			->add('portfolioid', 'hidden', array(
				'data'=>$this->portfolioTransaction->getPortfolioId()
			))
			->add('date', 'date', array(
				'label'=>'Date',
				'widget'=>'single_text',
  					'format'=>'dd/MM/yyyy',
				'data'=>$this->portfolioTransaction->getDate(),
				'attr'=>array(
    				'class'=>'dateInput',
					'size'=>10
				)
			))
			->add('amount', 'text', array(
				'label'=>'Amount',
				'data'=>$this->portfolioTransaction->getAmount()
			))
	    	->add('reference', 'text', array(
	    		'label'=>'Reference',
	    		'data'=>$this->portfolioTransaction->getReference()
	    	))
			->add('description', 'text', array(
	    		'label'=>'Description',
	    		'data'=>$this->portfolioTransaction->getDescription()
	    	))
	    	->add('save', 'submit', array(
	  			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'portfoliocreditdebit';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }