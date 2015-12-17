<?php
// src/Invest/Bundle/ShareBundle/Form/Type/LoginType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class LoginType extends AbstractType
{
	
	public function __construct()
	{
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
    		->add('uname', 'text', array(
    			'label'=>'Username:',
    			'constraints'=>array(
    				new NotBlank(),
    				new Length(array('min'=>4))
    			)
    		))
    		->add('upass', 'password', array(
    			'label'=>'Password:',
    			'constraints'=>array(
    				new NotBlank(),
    				new Length(array('min'=>4))
    			)
    		))
    		->add('submit', 'submit');

    }


    public function getName()
    {
        return 'login';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }

}