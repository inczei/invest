<?php
// src/Invest/Bundle/ShareBundle/Form/Type/DividendSearchType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class DividendSearchType extends AbstractType
{
	
	private $actionUrl;
	private $exDivDateSearch;
	private $paymentDateSearch;
	private $searchDateFrom;
	private $searchDateTo;
	private $searchPaymentDateFrom;
	private $searchPaymentDateTo;
	private $searchSector;
	private $searchSectors;
	private $searchPortfolio;
	private $searchPortfolios;
	private $searchIncome;
	private $searchIncomes;
	private $orderName;
	private $orderBy;
	
	public function __construct($actionUrl, $exDivDateSearch, $paymentDateSearch, $searchDateFrom, $searchDateTo, $searchPaymentDateFrom, $searchPaymentDateTo, $searchSector, $searchSectors, $searchPortfolio, $searchPortfolios, $searchIncome, $searchIncomes, $orderName, $orderBy)
	{
		$this->actionUrl = $actionUrl;
		$this->exDivDateSearch = $exDivDateSearch;
		$this->paymentDateSearch = $paymentDateSearch;
		$this->searchDateFrom =  $searchDateFrom;
		$this->searchDateTo =  $searchDateTo;
		$this->searchPaymentDateFrom =  $searchPaymentDateFrom;
		$this->searchPaymentDateTo =  $searchPaymentDateTo;
		$this->searchSector =  $searchSector;
		$this->searchSectors =  $searchSectors;
		$this->searchPortfolio =  $searchPortfolio;
		$this->searchPortfolios =  $searchPortfolios;
		$this->searchIncome =  $searchIncome;
		$this->searchIncomes =  $searchIncomes;
		$this->orderName =  $orderName;
		$this->orderBy = $orderBy;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
    		->setAction($this->actionUrl)
    		->add('exDivDateSearch', 'checkbox', array(
    			'required'=>false,
    			'data'=>($this->exDivDateSearch?true:false)
    		))
    		->add('paymentDateSearch', 'checkbox', array(
    			'required'=>false,
    			'data'=>($this->paymentDateSearch?true:false)
    		))
    		->add('exDivDateFrom', 'date', array(
    			'widget'=>'single_text',
    			'label'=>'Ex Dividend Date : ',
    			'data'=>$this->searchDateFrom,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('exDivDateTo', 'date', array(
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
    		->add('paymentDateFrom', 'date', array(
    			'widget'=>'single_text',
    			'label'=>'Payment Date : ',
    			'data'=>$this->searchPaymentDateFrom,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('paymentDateTo', 'date', array(
    			'widget'=>'single_text',
    			'label'=>' - ',
    			'data'=>$this->searchPaymentDateTo,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('sector', 'choice', array(
    			'choices'=>$this->searchSectors,
    			'label'=>'Sector : ',
    			'data'=>$this->searchSector,
    			'required'=>false,
				'empty_value'=>'All',
    			'attr'=>array(
		    		'style'=>'width: 150px'
				)
    		))
    		->add('portfolio', 'choice', array(
    			'choices'=>$this->searchPortfolios,
    			'label'=>'Portfolio : ',
    			'data'=>$this->searchPortfolio,
    			'required'=>false,
    			'empty_value'=>'All',
			    'attr'=>array(
		    		'style'=>'width: 150px'
				)
    		))
    		->add('income', 'choice', array(
    			'choices'=>$this->searchIncomes,
    			'label'=>'Income : ',
    			'data'=>$this->searchIncome,
			    'attr'=>array(
		    		'style'=>'width: 100px'
				)
    		))
    		->add('orderby', 'choice', array(
    			'choices'=>$this->orderName,
    			'label'=>'Order By : ',
    			'data'=>$this->orderBy,
			    'attr'=>array(
		    		'style'=>'width: 80px'
				)
    		))
        	->add('search', 'submit', array(
    			'label'=>'Search',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'dividendsearch';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }