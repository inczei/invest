<?php
// src/Invest/Bundle/ShareBundle/Form/Type/TradeType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class TradeType extends AbstractType
{
	
	private $trade;
	private $portfolios;
	private $companies;
	
	public function __construct($trade, $portfolios, $companies)
	{
		$this->trade = $trade;
		$this->portfolios = $portfolios;
		$this->companies = $companies;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('id', 'hidden', array(
				'data'=>$this->trade->getId()
			))
			->add('portfolioid', 'choice', array(
				'choices'=>$this->portfolios,
				'data'=>$this->trade->getPortfolioId(),
    			'attr'=>array(
    				'style'=>'width: 200px'
    			)
			))
			->add('companyId', 'choice', array(
				'choices'=>$this->companies,
				'data'=>$this->trade->getCompanyId(),
    			'attr'=>array(
    				'style'=>'width: 200px'
    			)
			))
			->add('pe_ratio', 'text', array(
				'data'=>$this->trade->getPERatio(),
				'required'=>false
			))
        	->add('save', 'submit', array(
    			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'trade';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }