<?php
// src/Invest/Bundle/ShareBundle/Form/Type/PortfolioType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class PortfolioType extends AbstractType
{
	
	private $portfolio;
	
	public function __construct($portfolio)
	{
		$this->portfolio = $portfolio;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('id', 'hidden', array(
				'data'=>$this->portfolio->getId()
			))
			->add('name', 'text', array(
				'label'=>'Name',
				'data'=>$this->portfolio->getName()
			))
			->add('clientNumber', 'text', array(
				'label'=>'Client Number',
				'data'=>$this->portfolio->getClientNumber()
			))
			->add('family', 'text', array(
				'label'=>'Number of family member',
				'data'=>$this->portfolio->getFamily()
			))
        	->add('save', 'submit', array(
    			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'portfolio';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }