<?php
// src/Invest/Bundle/ShareBundle/Form/Type/TradeUploadType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class TradeUploadType extends AbstractType
{

	public function __construct()
	{
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
	    	->add('file', 'file', array(
	    		'label'=>'Select a file'
			   ))
        	->add('upload', 'submit', array(
    			'label'=>'Upload',
    			'attr'=>array('class'=>'submitButton')
    		));
    }

    public function getName()
    {
        return 'tradeupload';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }