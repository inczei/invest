<?php
// src/Invest/Bundle/ShareBundle/Form/Type/CurrencySelectType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class CurrencySelectType extends AbstractType
{
	
	private $actionUrl;
	private $currencies;	
	
	public function __construct($actionUrl, $currencies)
	{
		$this->actionUrl = $actionUrl;
		$this->currencies = $currencies;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        	->setAction($this->actionUrl)        
	    	->add('submit', 'submit', array(
	  			'label'=>'Compare',
    			'attr'=>array('class'=>'submitButton')
    		));
	    	
	    if (count($this->currencies)) {
	    	foreach ($this->currencies as $currency) {
	    		$builder->add($currency, 'checkbox', array(
	    			'label'=>$currency,
	    			'value'=>1,
	    			'required'=>false
	    		));
	    	}
	    }
	    	
    }

    public function getName()
    {
        return 'currencyselect';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }