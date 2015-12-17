<?php
// src/Invest/Bundle/ShareBundle/Form/Type/ChangePasswordType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class ChangePasswordType extends AbstractType
{
	
	private $user;
	
	public function __construct($user)
	{
		$this->user = $user;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        	->add('id', 'hidden', array(
        		'data'=>((isset($this->user))?($this->user->getId()):(null))
        	))
        	->add('username', 'text', array(
        		'label'=>'Username',
        		'read_only'=>true,
        		'data'=>((isset($this->user))?($this->user->getUsername()):('')),
        		'required'=>true
        	))
        	->add('firstname', 'text', array(
        		'label'=>'First Name',
        		'read_only'=>true,
        		'data'=>((isset($this->user))?($this->user->getFirstName()):('')),
        		'required'=>true
        	))
        	->add('lastname', 'text', array(
        		'label'=>'Last Name',
        		'read_only'=>true,
        		'data'=>((isset($this->user))?($this->user->getLastName()):('')),
        		'required'=>false
        	))
        	->add('email', 'email', array(
        		'label'=>'E-mail',
        		'read_only'=>true,
        		'data'=>((isset($this->user))?($this->user->getEmail()):('')),
        		'required'=>false
        	))
    		->add('password', 'repeated', array(
		    	'type'=>'password',
		    	'required' => true,
			    'first_options'  => array('label' => 'Password'),
			    'second_options' => array('label' => 'Repeat Password'),
		    ))
        	->add('submit', 'submit', array(
	  			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
	    	
	    	
    }

    public function getName()
    {
        return 'changepassword';
    }
        
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }