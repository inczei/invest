<?php
// src/Invest/Bundle/ShareBundle/Form/Type/DiarySearchType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class DiarySearchType extends AbstractType
{
	private $searchType;
	private $searchCompany;
	private $searchDateFrom;
	private $searchDateTo;
	private $searchFilter;
	private $types;
	private $companies;
	
	public function __construct($searchType, $types, $searchCompany, $companies, $searchDateFrom, $searchDateTo, $searchFilter)
	{
		$this->searchType = $searchType;
		$this->types = $types;
		$this->searchCompany = $searchCompany;
		$this->companies = $companies;
		$this->searchDateFrom = $searchDateFrom;
		$this->searchDateTo = $searchDateTo;
		$this->searchFilter = $searchFilter;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('type', 'choice', array(
				'label'=>'Type : ',
				'choices'=>$this->types,
				'data'=>$this->searchType,
				'required'=>false,
				'empty_value'=>'All',
			    'attr'=>array(
		    		'style'=>'width: 150px'
			    )
			))
			->add('filter', 'choice', array(
				'label'=>'Filter : ',
				'choices'=>array('1'=>'Only hold'),
				'data'=>$this->searchFilter,
				'required'=>false,
				'empty_value'=>'All',
			    'attr'=>array(
		    		'style'=>'width: 80px'
			    )
			))
			->add('company', 'choice', array(
				'label'=>'Company : ',
				'choices'=>$this->companies,
				'data'=>$this->searchCompany,
				'required'=>false,
				'empty_value'=>'All',
			    'attr'=>array(
		    		'style'=>'width: 150px'
			    )
			))
			->add('dateFrom', 'date', array(
    			'widget'=>'single_text',
    			'label'=>'Date : ',
    			'data'=>$this->searchDateFrom,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('dateTo', 'date', array(
    			'widget'=>'single_text',
    			'label'=>' - ',
    			'data'=>$this->searchDateTo,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
        	->add('search', 'submit', array(
    			'label'=>'Search',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'diarysearch';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }