<?php
// src/Invest/Bundle/ShareBundle/Form/Type/CompanySelectType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class CompanySelectType extends AbstractType
{
	
	private $companies;	
	
	public function __construct($companies)
	{
		$this->companies = $companies;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
	    	->add('submit', 'submit', array(
	  			'label'=>'Compare',
    			'attr'=>array('class'=>'submitButton')
    		));
	    	
	    if (count($this->companies)) {
	    	foreach ($this->companies as $company) {
	    		$builder->add(str_replace('.', '_', $company['code']), 'checkbox', array(
	    			'label'=>$company['code'],
	    			'value'=>1,
	    			'required'=>false
	    		));
	    	}
	    }
	    	
    }

    public function getName()
    {
        return 'companyselect';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }