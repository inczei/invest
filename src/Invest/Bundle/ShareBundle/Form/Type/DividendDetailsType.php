<?php
// src/Invest/Bundle/ShareBundle/Form/Type/DividendDetailsType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class DividendDetailsType extends AbstractType
{
	private $id;
	private $company;
	private $dividend;
	
	public function __construct($id, $company, $dividend)
	{
		$this->id = $id;
		$this->company = $company;
		$this->dividend = $dividend;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('id', 'hidden', array(
				'data'=>$this->dividend->getId()
			))
			->add('CompanyId', 'hidden', array(
				'data'=>$this->id
			))
		    ->add('EPIC', 'text', array(
		    	'label'=>'EPIC',
		    	'read_only'=>true,
		    	'mapped'=>false,
		    	'data'=>$this->company->getCode()
		    ))
		    ->add('Company', 'text', array(
		    	'label'=>'Company',
		    	'read_only'=>true,
		    	'mapped'=>false,
		    	'data'=>$this->company->getName()
		    ))
		    ->add('DeclDate', 'date', array(
		    	'widget'=>'single_text',
		    	'label'=>'Declaration Date',
		    	'data'=>$this->dividend->getDeclDate(),
		    	'format'=>'dd/MM/yyyy',
		    	'attr'=>array(
		    		'class'=>'dateInput',
		    		'size'=>10
		    	)
		    ))
		    ->add('ExDivDate', 'date', array(
		    	'widget'=>'single_text',
		    	'label'=>'ExDiv Date',
		    	'data'=>$this->dividend->getExDivDate(),
		    	'format'=>'dd/MM/yyyy',
		    	'attr'=>array(
		    		'class'=>'dateInput',
		    		'size'=>10
		    	)
		    ))
		    ->add('Amount', 'text', array(
		    	'label'=>'Amount'.(($this->company->getCurrency()=='GBP')?(''):(' ('.$this->company->getCurrency().')')),
		    	'data'=>$this->dividend->getAmount()
		    ))
		    ->add('PaymentDate', 'date', array(
		    	'widget'=>'single_text',
		    	'label'=>'Payment Date',
		    	'data'=>$this->dividend->getPaymentDate(),
		    	'format'=>'dd/MM/yyyy',
		    	'empty_value'=>null,
		    	'required'=>false,
		    	'attr'=>array(
		    		'class'=>'dateInput',
		    		'size'=>10
		    	)
		    ))
		    ->add('PaymentRate', 'text', array(
		    	'label'=>'Payment Exchange Rate',
		    	'required'=>false,
		    	'read_only'=>($this->company->getCurrency()=='GBP'),
		    	'data'=>$this->dividend->getPaymentRate()
		    ))
		    ->add('Special', 'choice', array(
		    	'choices'=>array(0=>'No', 1=>'Yes'),
		    	'label'=>'Special Dividend',
		    	'expanded'=>false,
		    	'multiple'=>false,
		    	'data'=>$this->dividend->getSpecial()
		    ))
        	->add('save', 'submit', array(
    			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'dividenddetails';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }