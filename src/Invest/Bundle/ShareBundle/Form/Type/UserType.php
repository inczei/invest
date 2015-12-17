<?php
// src/Invest/Bundle/ShareBundle/Form/Type/UserType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class UserType extends AbstractType
{
	
	private $user;
	private $roles;	
	
	public function __construct($user, $roles)
	{
		$this->user = $user;
		$this->roles = $roles;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        	->add('id', 'hidden', array(
        		'data'=>((isset($this->user))?($this->user->getId()):(null))
        	))
        	->add('username', 'text', array(
        		'label'=>'Username',
        		'data'=>((isset($this->user))?($this->user->getUsername()):('')),
        		'required'=>true
        	))
        	->add('firstname', 'text', array(
        		'label'=>'First Name',
        		'data'=>((isset($this->user))?($this->user->getFirstName()):('')),
        		'required'=>true
        	))
        	->add('lastname', 'text', array(
        		'label'=>'Last Name',
        		'data'=>((isset($this->user))?($this->user->getLastName()):('')),
        		'required'=>false
        	))
        	->add('email', 'email', array(
        		'label'=>'E-mail',
        		'data'=>((isset($this->user))?($this->user->getEmail()):('')),
        		'required'=>false
        	))
        	->add('role', 'choice', array(
        		'label'=>'Role',
        		'choices'=>$this->roles,
        		'data'=>((isset($this->user))?($this->getRoles($this->user->getRoles())):('')),
        		'empty_value'=>'Please select',
        		'required'=>true
        	))
        	->add('status', 'choice', array(
        		'label'=>'Status',
        		'choices'=>array('0'=>'Inactive', '1'=>'Active'),
        		'data'=>((isset($this->user))?($this->user->isEnabled()):('')),
        		'empty_value'=>'Please select',
        		'required'=>true
        	))
    		->add('password', 'repeated', array(
		    	'type'=>'password',
		    	'required' => ((isset($this->user))?(false):(true)),
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
        return 'user';
    }
    
    
    private function getRoles($roles) {
    
//    	if (isset($roles) && is_array($roles)) {
	    	foreach ($roles as $r) {
	    		if (isset($this->roles[$r])) {
	    			return $r;
	    		}
	    	}
//    	}
	    	
    	return 'ROLE_USER';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }