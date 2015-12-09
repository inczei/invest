<?php
// src/Invest/Bundle/ShareBundle/Form/Type/TradeSearchType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class TradeSearchType extends AbstractType
{
	
	private $actionUrl;
	private $companies;
	private $searchCompany;
	private $portfolios;
	private $searchPortfolio;
	private $sectors;
	private $searchSector;
	private $searchSold;
	private $searchDateFrom;
	private $searchDateTo;
	
	public function __construct($actionUrl, $companies, $searchCompany, $portfolios, $searchPortfolio, $sectors, $searchSector, $searchSold, $searchDateFrom, $searchDateTo)
	{
		$this->actionUrl = $actionUrl;
		$this->companies = $companies;
		$this->searchCompany = $searchCompany;
		$this->portfolios = $portfolios;
		$this->searchPortfolio = $searchPortfolio;
		$this->sectors = $sectors;
		$this->searchSector = $searchSector;
		$this->searchSold = $searchSold;
		$this->searchDateFrom = $searchDateFrom;
		$this->searchDateTo = $searchDateTo;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->setAction($this->actionUrl)
	    	->add('company', 'choice', array(
	    		'choices'=>$this->companies,
	    		'label'=>'Company : ',
			   	'required'=>false,
	    		'data'=>$this->searchCompany,
	    		'empty_value'=>'All',
	    		'attr'=>array(
	    			'style'=>'width: 200px'
	    		)
			))
	    	->add('portfolio', 'choice', array(
	    		'choices'=>$this->portfolios,
	    		'label'=>'Portfolio : ',
	    		'required'=>false,
	    		'data'=>$this->searchPortfolio,
	    		'empty_value'=>'All',
	    		'attr'=>array(
	    			'style'=>'width: 200px'
	    		)
	    	))
	    	->add('sector', 'choice', array(
	    		'choices'=>$this->sectors,
	    		'label'=>'Sector : ',
	    		'required'=>false,
			   	'data'=>$this->searchSector,
	    		'empty_value'=>'All',
	    		'attr'=>array(
	    			'style'=>'width: 200px'
	    		)
	    	))
			->add('sold', 'choice', array(
	    		'choices'=>array(0=>'All', 1=>'Unsold', 2=>'Sold'),
	    		'label'=>'Status : ',
	    		'required'=>true,
			   	'data'=>$this->searchSold,
			   	'attr'=>array(
			   		'style'=>'width: 80px'
			   	)
			))
			->add('dateFrom', 'date', array(
			   	'widget'=>'single_text',
			   	'label'=>'Date:',
			   	'format'=>'dd/MM/yyyy',
			   	'required'=>false,
			   	'data'=>((isset($this->searchDateFrom))?($this->searchDateFrom):(null)),
			   	'empty_value'=>null,
			   	'attr'=>array(
			   		'class'=>'dateInput',
			   		'style'=>'width: 100px'
			   	)
			))
			->add('dateTo', 'date', array(
			   	'widget'=>'single_text',
			   	'format'=>'dd/MM/yyyy',
			   	'label'=>'To:',
			   	'required'=>false,
			   	'data'=>((isset($this->searchDateTo))?($this->searchDateTo):(null)),
			   	'empty_value'=>null,
			   	'attr'=>array(
			   		'class'=>'dateInput',
			   		'style'=>'width: 100px'
			   	)
			))
        	->add('search', 'submit', array(
    			'label'=>'Search',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'tradesearch';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }