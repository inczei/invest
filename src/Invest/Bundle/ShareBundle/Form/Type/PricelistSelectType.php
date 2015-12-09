<?php
// src/Invest/Bundle/ShareBundle/Form/Type/PricelistSelectType.php
namespace Invest\Bundle\ShareBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;


class PricelistSelectType extends AbstractType
{
	
	private $availableDates;
	private $startDate;
	private $endDate;
	private $sector;
	private $sectorList;
	private $list;
	private $ftseList;
	private $em;
	
	
	public function __construct($startDate, $endDate, $sector, $list, $sectorList, $ftseList, $availableDates, $em=null)
	{
		$this->startDate = $startDate;
		$this->endDate = $endDate;
		$this->sector = $sector;
		$this->list = $list;
		$this->availableDates = array();
		$this->sectorList = $sectorList;
		$this->ftseList = $ftseList;
		$this->availableDates = $availableDates;
		$this->em = $em;
	}
	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
	    	->add('date', 'choice', array(
	    		'choices'=>$this->availableDates,
	    		'label'=>'Available updates : ',
	    		'data'=>null,
	    		'required'=>false,
	    		'empty_value'=>'Show latest prices',
	    		'attr'=>array(
	    			'style'=>'width: 150px'
	    		)
	    	))
        	->add('startDate', 'datetime', array(
	    		'label'=>'Updated : ',
			    'data'=>$this->startDate,
		    	'widget'=>'single_text',
   				'format'=>'dd/MM/yyyy',
		    	'attr'=>array(
		    		'class'=>'dateInput',
		    		'size'=>10
		    	)
	    	))
	    	->add('endDate', 'datetime', array(
	    		'label'=>' - ',
			    'data'=>$this->endDate,
		    	'widget'=>'single_text',
		    	'format'=>'dd/MM/yyyy',
		    	'attr'=>array(
		    		'class'=>'dateInput',
		    		'size'=>10
		    	)
	    	))
	    	->add('sector', 'choice', array(
	    		'label'=>'Sector : ',
	    		'choices'=>$this->sectorList,
			    'data'=>$this->sector,
		    	'attr'=>array(
		    		'style'=>'width: 120px'
		    	)
	    	))
	    	->add('list', 'choice', array(
	    		'label'=>'List : ',
	    		'choices'=>$this->ftseList,
			    'data'=>$this->list,
		    	'attr'=>array(
		    		'style'=>'width: 120px'
		    	)
	    	))
	    	->add('search', 'submit', array(
	  			'label'=>'Select',
    			'attr'=>array('class'=>'submitButton')
    		));
	    
	    	
	    $builder->addEventListener(
    		FormEvents::PRE_SUBMIT,
    		function (FormEvent $event) {
    			$data=$event->getData();
				$this->availableDates=$this->getAvailableDates($data['startDate'], $data['endDate']);

    			$event->getForm()
    				->remove('date')
    				->add('date', 'choice', array(
    					'choices'=>$this->availableDates,
			    		'label'=>'Available updates : ',
			    		'data'=>null,
			    		'required'=>false,
			    		'empty_value'=>'Show latest prices',
			    		'attr'=>array(
			    			'style'=>'width: 150px'
			    		)
    			));

    		});

    }

    public function getName()
    {
        return 'pricelistselect';
    }
    
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
    
    private function getAvailableDates($startDate, $endDate) {
    	$availableDates=array();

    	$qb=$this->em->createQueryBuilder()
    		->select('p.date')
    		->from('InvestShareBundle:StockPrices', 'p')
    		->groupBy('p.date')
    		->orderBy('p.date', 'DESC');
    	
		if (isset($startDate)) {
    		$date1=date_create_from_format('d/m/Y H:i:s', $startDate.' 00:00:00');
   			$qb->andWhere('p.date>=:startDate')
   				->setParameter('startDate', $date1->format('Y-m-d H:i:s'));
    	}
        if (isset($endDate)) {
    		$date2=date_create_from_format('d/m/Y H:i:s', $endDate.' 00:00:00');
   			$qb->andWhere('p.date<=:endDate')
   				->setParameter('endDate', $date2->format('Y-m-d H:i:s'));
    	}
    	$results=$qb->getQuery()->getArrayResult();

    	foreach($results as $result) {
    		$availableDates[$result['date']->getTimestamp()]=$result['date']->format('d/m/Y H:i:s');
    	}
    	 
    	return $availableDates;
    }
    
 }