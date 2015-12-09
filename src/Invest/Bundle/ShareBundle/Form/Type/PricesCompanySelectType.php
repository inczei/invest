<?php
// src/Invest/Bundle/ShareBundle/Form/Type/PricesCompanySelectType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class PricesCompanySelectType extends AbstractType
{
	
	private $company;
	private $companies;
	
	public function __construct($company, $companies)
	{
		$this->company = $company;
		$this->companies = $companies;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
    		->add('company', 'choice', array(
        		'choices'=>$this->companies,
    			'label'=>'Company:',
        		'required'=>true,
    			'empty_value'=>' - Please select - ',
    			'data'=>((isset($this->company))?($this->company):('')),
	    		'attr'=>array(
	    			'style'=>'width: 200px'
	    		)
    		))
    		->add('search', 'submit', array(
    			'label'=>'Select',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'pricescompanyselect';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }