<?php
// src/Invest/Bundle/ShareBundle/Form/Type/NotesType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class NotesType extends AbstractType
{
	
	private $notes;
	
	public function __construct($notes)
	{
		$this->notes=$notes;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        	->add('save', 'submit', array(
    			'label'=>'Save',
    			'attr'=>array('class'=>'submitButton')
    		));
        if (isset($this->notes) && is_array($this->notes) && count($this->notes)) {
        	foreach ($this->notes as $k=>$note) {
        		$builder
        			->add('page_'.$k, 'textarea', array(
        				'label'=>strtoupper($k),
        				'required'=>false,
        				'data'=>$note,
        				'attr'=>array(
        					'cols'=>80,
        					'rows'=>10
        				)
        			));
        	}
        }
    }

    public function getName()
    {
        return 'notes';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
 }