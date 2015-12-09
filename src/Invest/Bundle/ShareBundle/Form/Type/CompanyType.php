<?php
// src/Invest/Bundle/ShareBundle/Form/Type/CompanyType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class CompanyType extends AbstractType
{
	
	private $company;
	private $lists;
	
	public function __construct($company, $lists)
	{
		$this->company = $company;
		$this->lists = $lists;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
    		->add('id', 'hidden', array(
    			'data'=>$this->company->getId()
    		))
    		->add('name', 'text', array(
    			'label'=>'Name',
    			'max_length'=>500,
    			'data'=>$this->company->getName()
    		))
    		->add('code', 'text', array(
    			'label'=>'EPIC',
    			'max_length'=>4,
    			'data'=>$this->company->getCode()
    		))
    		->add('sector', 'text', array(
    			'label'=>'Sector',
   				'required'=>false,
    			'max_length'=>100,
    			'data'=>$this->company->getSector()
    		))
    		->add('currency', 'text', array(
    			'label'=>'Currency',
   				'required'=>true,
    			'max_length'=>3,
    			'data'=>$this->company->getCurrency()
    		))
    		->add('frequency', 'number', array(
    			'label'=>'Dividend Payments per Year',
   				'required'=>false,
    			'max_length'=>1,
    			'data'=>$this->company->getFrequency()
    		))
    		->add('altName', 'text', array(
    			'label'=>'Alternative name',
   				'required'=>false,
    			'max_length'=>100,
    			'data'=>$this->company->getAltName()
    		))
    		->add('list', 'choice', array(
    			'choices'=>$this->lists,
    			'label'=>'List',
   				'required'=>false,
    			'max_length'=>20,
    			'data'=>$this->company->getList()
    		))
    		->add('save', 'submit', array(
    			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'company';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }