<?php
// src/Invest/Bundle/ShareBundle/Form/Type/CompanySearchType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class CompanySearchType extends AbstractType
{
	private $actionUrl;
	private $searchCompany;
	private $searchCompanies;
	private $searchSector;
	private $searchSectors;
	private $searchList;
	private $searchLists;
	
	public function __construct($actionUrl, $searchCompany, $searchCompanies, $searchSector, $searchSectors, $searchList, $searchLists)
	{
		$this->actionUrl = $actionUrl;
		$this->searchCompany = $searchCompany;
		$this->searchCompanies = $searchCompanies;
		$this->searchSector = $searchSector;
		$this->searchSectors = $searchSectors;
		$this->searchList = $searchList;
		$this->searchLists = $searchLists;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->setAction($this->actionUrl)
    		->add('company', 'choice', array(
    			'choices'=>$this->searchCompanies,
    			'label'=>'Company : ',
		    	'data'=>$this->searchCompany,
    			'required'=>false,
    			'empty_value'=>'All',
    			'attr'=>array(
    				'style'=>'width: 200px'
    			)
		    ))
    		->add('sector', 'choice', array(
    			'choices'=>$this->searchSectors,
    			'label'=>'Sector : ',
		    	'data'=>$this->searchSector,
    			'required'=>false,
    			'empty_value'=>'All',
    			'attr'=>array(
    				'style'=>'width: 200px'
    			)
    		))
    		->add('list', 'choice', array(
    			'choices'=>$this->searchLists,
    			'label'=>'List : ',
		    	'data'=>$this->searchList,
    			'required'=>false,
    			'empty_value'=>'All',
    			'attr'=>array(
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
        return 'companysearch';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }