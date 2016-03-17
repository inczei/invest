<?php
// src/Invest/Bundle/ShareBundle/Form/Type/DealsSearchType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class DealsSearchType extends AbstractType
{
	private $searchType;
	private $searchPosition;
	private $searchCompany;
	private $searchDateFrom;
	private $searchDateTo;
	private $searchLimit;
	private $searchFilter;
	private $searchListType;
	private $searchSector;
	private $types;
	private $positions;
	private $companies;
	private $sectors;
	
	public function __construct($searchType, $types, $searchPosition, $positions, $searchCompany, $companies, $searchDateFrom, $searchDateTo, $searchLimit, $searchFilter, $searchListType, $searchSector, $sectors)
	{
		$this->searchType = $searchType;
		$this->types = $types;
		$this->searchPosition = $searchPosition;
		$this->positions = $positions;
		$this->companies = $companies;
		$this->sectors = $sectors;
		$this->searchCompany = $searchCompany;
		$this->searchDateFrom = $searchDateFrom;
		$this->searchDateTo = $searchDateTo;
		$this->searchLimit = $searchLimit;
		$this->searchFilter = $searchFilter;
		$this->searchListType = $searchListType;
		$this->searchSector = $searchSector;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('type', 'choice', array(
				'label'=>'Type : ',
				'choices'=>$this->types,
				'required'=>false,
				'empty_value'=>'All',
				'data'=>$this->searchType
			))
			->add('position', 'choice', array(
				'label'=>'Position : ',
				'choices'=>$this->positions,
				'required'=>false,
				'empty_value'=>'All',
				'data'=>$this->searchPosition,
			    'attr'=>array(
		    		'style'=>'width: 60px'
			    )
			))
			->add('company', 'choice', array(
				'label'=>'Company : ',
				'choices'=>$this->companies,
				'data'=>$this->searchCompany,
				'required'=>false,
				'empty_value'=>'All',
				'attr'=>array(
		    		'style'=>'width: 120px'
			    )
			))
			->add('sector', 'choice', array(
				'label'=>'Sector : ',
				'choices'=>$this->sectors,
				'data'=>$this->searchSector,
				'required'=>false,
				'empty_value'=>'All',
				'attr'=>array(
		    		'style'=>'width: 120px'
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
			->add('limit', 'text', array(
				'label'=>'Limit : ',
				'data'=>$this->searchLimit,
				'required'=>false,
				'attr'=>array(
					'size'=>10,
					'style'=>'width: 80px'
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
			->add('listType', 'choice', array(
				'label'=>'List : ',
				'choices'=>array('1'=>'Sales Volume'),
				'data'=>$this->searchListType,
				'required'=>false,
				'empty_value'=>'Normal',
			    'attr'=>array(
		    		'style'=>'width: 120px'
			    )
			))
			->add('search', 'submit', array(
    			'label'=>'Search',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'dealssearch';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }