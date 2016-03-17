<?php

/*
 * Author: Imre Incze
 * Todo:
 * - Trade : Fix yield calculation if the currency is not GBP 
 * - Dividend : Fix yield calculation if the currency is not GBP
 * 
 *  13/11/2014 - can we have the option to include predicted dates?
 */

namespace Invest\Bundle\ShareBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Invest\Bundle\ShareBundle\Entity\Config;
use Invest\Bundle\ShareBundle\Entity\Company;
use Invest\Bundle\ShareBundle\Entity\Diary;
use Invest\Bundle\ShareBundle\Entity\Trade;
use Invest\Bundle\ShareBundle\Entity\TradeTransactions;
use Invest\Bundle\ShareBundle\Entity\DirectorsDeals;
use Invest\Bundle\ShareBundle\Entity\Dividend;
use Invest\Bundle\ShareBundle\Entity\Portfolio;
use Invest\Bundle\ShareBundle\Entity\PortfolioTransaction;
use Invest\Bundle\ShareBundle\Entity\Transaction;
use Invest\Bundle\ShareBundle\Entity\StockPrices;
use Invest\Bundle\ShareBundle\Entity\StockPricesWrong;
use Invest\Bundle\ShareBundle\Entity\Summary;
use Invest\Bundle\ShareBundle\Entity\Currency;
use Symfony\Component\Validator\Validator;
use Invest\Bundle\ShareBundle\InvestShareBundle;
use Ps\PdfBundle\Annotation\Pdf;
use Invest\Bundle\ShareBundle\Form\Type\LoginType;
use Invest\Bundle\ShareBundle\Form\Type\PricesCompanySelectType;
use Invest\Bundle\ShareBundle\Form\Type\DividendSearchType;
use Invest\Bundle\ShareBundle\Form\Type\CompanyType;
use Invest\Bundle\ShareBundle\Form\Type\DividendDetailsType;
use Invest\Bundle\ShareBundle\Form\Type\CompanySearchType;
use Invest\Bundle\ShareBundle\Form\Type\DealsSearchType;
use Invest\Bundle\ShareBundle\Form\Type\DiarySearchType;
use Invest\Bundle\ShareBundle\Form\Type\TradeUploadType;
use Invest\Bundle\ShareBundle\Form\Type\PortfolioType;
use Invest\Bundle\ShareBundle\Form\Type\PortfolioCreditDebitType;
use Invest\Bundle\ShareBundle\Form\Type\TradeType;
use Invest\Bundle\ShareBundle\Form\Type\TradeDetailsType;
use Invest\Bundle\ShareBundle\Form\Type\PricelistSelectType;
use Invest\Bundle\ShareBundle\Form\Type\CompanySelectType;
use Invest\Bundle\ShareBundle\Form\Type\CurrencySelectType;
use Invest\Bundle\ShareBundle\Form\Type\TradeSearchType;
use Invest\Bundle\ShareBundle\Form\Type\NotesType;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use FOS\UserBundle\Propel\User;
use Invest\Bundle\ShareBundle\Form\Type\UserType;
use Invest\Bundle\ShareBundle\Form\Type\ChangePasswordType;

class DefaultController extends Controller
{
/*
 * Update interval by default 5 minutes
 */	
	protected $refresh_interval = 300;
	
	protected $maxChanges = 50;
	
	protected $searchTime = 1800; // 1800 = 30 min, 3600 = 1 hour
	
	protected $startTaxYear = '04-06';
	
	protected $startTaxYearMonth = 4;
	
	protected $startTaxYearDay = 6;
	
	protected $dealsLimit = 60000;
	
	protected $dividendWarningDays = 7;
	
	protected $currencyNeeded=array();
	
	protected $defaultCurrencies=array('EUR', 'USD', 'AUD', 'HUF', 'PHP');

	protected $pager = 20;
	
	protected $updateRetry = 3;
	
	
    public function indexAction() {

    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}
/*
 * on the 1st page can see the summary of all investment
 */
		$currentUser=$this->getUser();
    	$em=$this->getDoctrine()->getManager();
		$functions=$this->get('invest.share.functions');
		$message='';
		$graphs=array();
		
		if ($functions->updateSummary($currentUser->getId())) {
/*
 * Summary updated, at the moment nothing to do with this
 */
		}
		
		$qb=$em->createQueryBuilder()
			->select('s')
			->from('InvestShareBundle:Summary', 's')
			->where('s.userId=:uId')
			->setParameter('uId', $currentUser->getId());
		
		$summary=$qb->getQuery()->getArrayResult();
		
		$overall=array(
			'CurrentDividend'=>0,
			'Investment'=>0,
			'CurrentValue'=>0,
			'Profit'=>0,
			'DividendPaid'=>0,
			'RealisedProfit'=>0,
			'DividendYield'=>0,
			'CurrentROI'=>0,
			'CashIn'=>0,
			'UnusedCash'=>0,
			'ActualDividendIncome'=>0,
			'CgtProfitsRealised'=>0,
			'UnusedBasicRateBand'=>0
		);
/*
 * calculate the overall line for the summary
 */
		if ($summary && count($summary)) {
			foreach ($summary as $k=>$s) {
/*
 * add values
 */
				$overall['CurrentDividend']+=$s['currentDividend'];
				$overall['Investment']+=$s['investment'];
				$overall['CurrentValue']+=$s['currentValue'];
				$overall['Profit']+=$s['profit'];
				$overall['DividendPaid']+=$s['dividendPaid'];
				$overall['RealisedProfit']+=$s['realisedProfit'];
				$overall['CashIn']+=$s['cashIn'];
				$overall['UnusedCash']+=$s['unusedCash'];
				$overall['ActualDividendIncome']+=$s['actualDividendIncome'];
				$overall['CgtProfitsRealised']+=$s['cgtProfitsRealised'];
				$overall['UnusedBasicRateBand']+=$s['unusedBasicRateBand'];
				$js=json_decode($s['currentValueBySector']);
				foreach ($js as $pName=>$v1) {
					foreach ($v1 as $k2=>$v2) {
						$js1=array('name'=>$k2, 'value'=>$v2);

						$graphs[$k][$pName][]=$js1;
						if (!isset($graphs[100]['Total'][$k2]['value'])) {
							$graphs[100]['Total'][$k2]['value']=0;
						}
						$graphs[100]['Total'][$k2]['name']=$k2;
						$graphs[100]['Total'][$k2]['value']+=$v2;
					}
				}
				ksort($graphs);
			}
/*
 * calculate percentage for Dividend Yield and Currenct ROI
 */
			$overall['DividendYield']=($overall['Investment'] != 0)?($overall['CurrentDividend']/$overall['Investment']):(0);
			$overall['CurrentROI']=($overall['Investment'] != 0)?($overall['RealisedProfit']/$overall['Investment']):(0);
		}

		$portfolios=array();
		$qb=$em->createQueryBuilder()
			->select('p.id')
			->addSelect('p.name')
			->from('InvestShareBundle:Portfolio', 'p');
			
		$results=$qb->getQuery()->getArrayResult();
		if (count($results)) {
			foreach ($results as $result) {
				$portfolios[$result['id']]=$result['name'];
			}
		}
	
        return $this->render('InvestShareBundle:Default:index.html.twig', array(
        	'summary' 		=> $summary,
        	'overall' 		=> $overall,
	      	'portfolios'	=> $portfolios,
        	'graphs'		=> $graphs,
        	'message'		=> $message,
        	'notes'			=> $functions->getConfig('page_summary')
        ));
    }


    public function loginAction() {
    	 
        if ($this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_homepage'));
    	}
    	$message='';
    	$session = $this->get('session');
    	$request=$this->getRequest();
    	 
    	$form=$this->createForm(new LoginType());
    	$form->handleRequest($request);
    
    	if ($form->isSubmitted() && $form->isValid()) {
    		$data=$form->getData();
    
    		$userManager = $this->container->get('fos_user.user_manager');
    		$user=$userManager->findUserBy(array('username'=>$data['uname']));
    
    		if ($user) {
    			if ($user->isEnabled()) {
	    			$encoder_service = $this->get('security.encoder_factory');
	    			$encoder = $encoder_service->getEncoder($user);
	    
	    			if ($encoder->isPasswordValid($user->getPassword(), trim($data['upass']), $user->getSalt())) {
	    
	    				$providerKey = $this->container->getParameter('fos_user.firewall_name');
	    				$token = new UsernamePasswordToken($user, $data['upass'], $providerKey, $user->getRoles());
	    				$this->get("security.context")->setToken($token);
	    
	    				// Fire the login event
	    				$event = new InteractiveLoginEvent($this->getRequest(), $token);
	    				$this->get("event_dispatcher")->dispatch("security.interactive_login", $event);
	    
	    				$message.='Password accepted';
	    
	    				$session->getFlashBag()->set('login', $message);
	    
	    				error_log($message);
	    				return $this->redirect($this->generateUrl('invest_share_homepage'));
	    
	    			} else {
	    				$message.='Wrong password for '.$data['uname'];
	    			}
    			} else {
    				$message.='Inactive user:'.$data['uname'];
    			}
    		} else {
    			$message.='Wrong username:'.$data['uname'];
    		}
    		error_log($message);
    	}
    	 
    	return $this->render('InvestShareBundle:Default:login.html.twig', array(
   			'form'	=> $form->createView(),
   			'message'=> $message
    	));
    
    }
    

    public function changepasswordAction() {
	
		if (!$this->get("security.context")->isGranted('ROLE_USER')) {
			return $this->redirect($this->generateUrl('invest_share_login'));
		}
		
		$message='';
		$request=$this->getRequest();
		$currentUser=$this->getUser();
		$userManager = $this->container->get('fos_user.user_manager');
		
		$form=$this->createForm(new ChangePasswordType($currentUser));
		$form->handleRequest($request);
		
		if ($form->isValid()) {
			$data=$form->getData();
			if ($data['password']) {
				$currentUser->setPlainPassword($data['password']);
			}
			try {
				$userManager->updateUser($currentUser);
			} catch (\Exception $e) {
				if (strpos($e->getMessage(), '1062') === false) {
					error_log('Database error:'.$e->getMessage());
				} else {
					$message='Username already exists, please try another username';
				}
			}
				
			return $this->redirect($this->generateUrl('invest_share_homepage'));
		}
		
		return $this->render('InvestShareBundle:Default:changepassword.html.twig', array(
			'showmenu'	=> true,
			'form'		=> ((isset($form))?($form->createView()):(null)),
			'message'	=> $message
		));
		
    }

    
    public function usersAction($action, $id) {
	
		if (!$this->get("security.context")->isGranted('ROLE_ADMIN')) {
			return $this->redirect($this->generateUrl('invest_share_login'));
		}
		
		$request=$this->getRequest();
		$users=array();
		
    	$em=$this->getDoctrine()->getManager();
    	$userManager = $this->container->get('fos_user.user_manager');
		
		$message='';
		$roles=$this->getRoles();
		
		if ($action) {
			switch ($action) {
				case 'add' : {
					$user=$userManager->createUser();
					break;
				}
				case 'edit' : {
					$user=$this->getDoctrine()
						->getRepository('InvestShareBundle:User')
						->findOneBy(array('id'=>$id));
					break;
				}
			}
			if (isset($user)) {
				
				$form=$this->createForm(new UserType($user, $roles));
				$form->handleRequest($request);
				
				if ($form->isValid()) {
					$data=$form->getData();
					if ($data['password']) {
						$user->setPlainPassword($data['password']);
					}
					$user->setUsername($data['username']);
					$user->setFirstName($data['firstname']);
					$user->setLastName($data['lastname']);
					$user->setEmail($data['email']);
					$user->setEnabled($data['status']);
					$user->setRoles(array($data['role']));
					try {
						$userManager->updateUser($user);
					} catch (\Exception $e) {
						if (strpos($e->getMessage(), '1062') === false) {
							error_log('Database error:'.$e->getMessage());
						} else {
							$message='Username already exists, please try another username';
						}
					}

					if ($user->getId()) {
						return $this->redirect($this->generateUrl('invest_share_users'));
					}
						
				}
			}
		} else {
			$qb=$em->createQueryBuilder()
				->select('u.id')
				->addSelect('u.username')
				->addSelect('u.firstName')
				->addSelect('u.lastName')
				->addSelect('u.email')
				->addSelect('u.enabled')
				->addSelect('u.lastLogin')
				->addSelect('u.roles')
				->from('InvestShareBundle:User', 'u')
				->orderBy('u.username');
			
			if ($id) {
				$qb->andWhere('u.id=:uId')
					->setParameter('uId', $id);
			}
			
			$users=$qb->getQuery()->getArrayResult();
		}

		return $this->render('InvestShareBundle:Default:users.html.twig', array(
			'showmenu'	=> true,
			'form'		=> ((isset($form))?($form->createView()):(null)),
			'users'		=> $users,
			'roles'		=> $roles,
			'message'	=> $message
		));
		
    }
    
    
    public function dividendAction() {

    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}
    	 
    	$currentUser=$this->getUser();
    	$request=$this->getRequest();
    	
    	$message='';
    	if (date('m-d') >= $this->startTaxYear) {
/*
 * from start of this tax year
 * until end of this tax year 
 */
/*
    		$searchDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay, date('Y'))));
    		$searchDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay-1, date('Y')+1)));

    		$searchPaymentDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay, date('Y'))));
    		$searchPaymentDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay-1, date('Y')+1)));
*/
/*
 * from today
 * until today + 1 year
 */
    		$searchDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), date('Y'))));
    		$searchDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-1, date('Y')+1)));
    		
    		$searchPaymentDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), date('Y'))));
    		$searchPaymentDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-1, date('Y')+1)));
    	} else {
/*
 * from start of this tax year
 * until end of this tax year 
 */
/*
    		$searchDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay, date('Y')-1)));
    		$searchDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay-1, date('Y'))));

    		$searchPaymentDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay, date('Y')-1)));
    		$searchPaymentDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay-1, date('Y'))));
*/
/*
 * from today
 * until today + 1 year
 */
    		$searchDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), date('Y'))));
    		$searchDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-1, date('Y')+1)));
    		$searchPaymentDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), date('Y'))));
    		$searchPaymentDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-1, date('Y')+1)));
    	}
    	$searchPortfolio=null;
    	$searchSector=null;
    	$searchIncome=1;
    	$orderBy=0;
    	$exDivDateSearch=false;
    	$paymentDateSearch=false;

		$em=$this->getDoctrine()->getManager();
		$functions=$this->get('invest.share.functions');

		if (!$request->isMethod('POST') && null !== ($request->getSession()->get('is_div'))) {
			$data=$request->getSession()->get('is_div');
			$ok=true;
			if (isset($data['updated'])) {
				if ($data['updated'] < date('Y-m-d H:i:s', time()-$this->searchTime)) {
					$ok=false;
				}
			}
			if ($ok) {
				if (isset($data['f']) && is_object($data['f'])) {
					$searchDateFrom=$data['f'];
				}
				if (isset($data['t']) && is_object($data['t'])) {
					$searchDateTo=$data['t'];
				}
				if (isset($data['pf']) && is_object($data['pf'])) {
					$searchPaymentDateFrom=$data['pf'];
				}
				if (isset($data['pt']) && is_object($data['pt'])) {
					$searchPaymentDateTo=$data['pt'];
				}
				if (isset($data['p'])) {
					$searchPortfolio=$data['p'];
				}
				if (isset($data['s'])) {
					$searchSector=$data['s'];
				}
				if (isset($data['i'])) {
					$searchIncome=$data['i'];
				}
				if (isset($data['o'])) {
					$orderBy=$data['o'];
				}
				if (isset($data['d1'])) {
					$exDivDateSearch=$data['d1'];
				}
				if (isset($data['d2'])) {
					$paymentDateSearch=$data['d2'];
				}
			} else {
				$request->getSession()->remove('is_div');
			}
		}
		
    	$companies=array();
    	$tradeData=$functions->getTradesData(null, null, null, 0, null, null, $currentUser->getId());

    	if (count($tradeData)) {
   			foreach ($tradeData as $td) {
   				if (!$searchPortfolio || $searchPortfolio==$td['portfolioId']) {
   					if (!isset($companies[$td['companyCode']])) {
		    			$companies[$td['companyCode']][0]=array(
		    				'Quantity'=>0,
		    				'Total'=>0,
		    				'Price'=>0,
		    				'Dividend'=>0,
		    				'DividendPerYear'=>array()
		    			);
		    			$companies[$td['companyCode']][1]=array(
		    				'Quantity'=>0,
		    				'Total'=>0,
		    				'Price'=>0,
		    				'Dividend'=>0,
		    				'DividendPerYear'=>array()
		    			);
   					}
	   				$companies[$td['companyCode']][0]['Dividend']=$td['Dividend'];
	   				$companies[$td['companyCode']][1]['Dividend']=$td['Dividend'];
	   				
	   				if ($td['quantity1']) {
	   					$ty=$functions->getTaxYear($td['tradeDate1']->format('Y-m-d H:i:s'));
	   					
	   					if (!isset($companies[$td['companyCode']][1]['DividendPerYear'][$ty])) {
	   						$companies[$td['companyCode']][1]['DividendPerYear'][$ty]=array(
	   							'Quantity'=>0,
			    				'Price'=>0,
			    				'AveragePrice'=>0	   							
	   						);
	   					}
		   				$companies[$td['companyCode']][1]['DividendPerYear'][$ty]['Quantity']+=$td['quantity1'];
		   				$companies[$td['companyCode']][1]['DividendPerYear'][$ty]['Price']+=$td['quantity1']*$td['unitPrice1'];
	   				
		   				$companies[$td['companyCode']][1]['DividendPerYear'][$ty]['AveragePrice']=$companies[$td['companyCode']][1]['DividendPerYear'][$ty]['Price']/$companies[$td['companyCode']][1]['DividendPerYear'][$ty]['Quantity'];
	   				}
   				}
    		}
    	}

    	$order=array(
    		0=>'c.name, d.exDivDate',
    		1=>'d.exDivDate, c.name'
    	);
    	$orderName=array(
    		0=>'Name',
    		1=>'ExDiv Date'
    	);
    	
    	$searchSectors=array();
    	$searchPortfolios=array();
    	 
    	$qb=$em->createQueryBuilder()
    		->select('c.sector')
    		->from('InvestShareBundle:Company', 'c')
    		->where('c.sector!=\'\'')
    		->groupBy('c.sector')
    		->orderBy('c.sector', 'ASC');
    	$results=$qb->getQuery()->getArrayResult();
    	if (count($results)) {
    		foreach ($results as $result) {
   				$searchSectors[$result['sector']]=$result['sector'];
    		}
    	}

    	$qb=$em->createQueryBuilder()
    		->select('p.id')
    		->addSelect('p.name')
    		->from('InvestShareBundle:Portfolio', 'p')
    		->where('p.name!=\'\'')
    		->orderBy('p.name', 'ASC');
    	if ($currentUser->getId()) {
    		$qb->andWhere('p.userId=:uId')
    			->setParameter('uId', $currentUser->getId());
    	}
    	$results=$qb->getQuery()->getArrayResult();
    	if (count($results)) {
    		foreach ($results as $result) {
    			$searchPortfolios[$result['id']]=$result['name'];
    		}
    	}
    	 
    	$searchIncomes=array(1=>'With income', 2=>'Without income', 3=>'All');

    	$searchForm=$this->createForm(new DividendSearchType($this->generateUrl('invest_share_dividend'), $exDivDateSearch, $paymentDateSearch, $searchDateFrom, $searchDateTo, $searchPaymentDateFrom, $searchPaymentDateTo, $searchSector, $searchSectors, $searchPortfolio, $searchPortfolios, $searchIncome, $searchIncomes, $orderName, $orderBy));
    	$searchForm->handleRequest($request);
    	
    	if ($request->isMethod('POST')) {
    		$formData=$searchForm->getData();
			if (isset($formData['exDivDateFrom']) && $formData['exDivDateFrom']) {
				$searchDateFrom=$formData['exDivDateFrom'];
			}
    		if (isset($formData['exDivDateTo']) && $formData['exDivDateTo']) {
				$searchDateTo=$formData['exDivDateTo'];
			}
    	    if (isset($formData['paymentDateFrom']) && $formData['paymentDateFrom']) {
				$searchPaymentDateFrom=$formData['paymentDateFrom'];
			}
    	    if (isset($formData['paymentDateTo']) && $formData['paymentDateTo']) {
				$searchPaymentDateTo=$formData['paymentDateTo'];
			}
    		if (isset($formData['portfolio']) && $formData['portfolio']) {
				$searchPortfolio=$formData['portfolio'];
			}
    	    if (isset($formData['sector']) && $formData['sector']) {
				$searchSector=$formData['sector'];
			}
    	    if (isset($formData['income']) && $formData['income']) {
				$searchIncome=$formData['income'];
			}
    	    if (isset($formData['orderBy']) && $formData['orderBy']) {
				$orderBy=$formData['orderBy'];
			}
    	    if (isset($formData['exDivDateSearch']) && $formData['exDivDateSearch']) {
				$exDivDateSearch=$formData['exDivDateSearch'];
			}
			if (isset($formData['paymentDateSearch']) && $formData['paymentDateSearch']) {
				$paymentDateSearch=$formData['paymentDateSearch'];
			}
				
			$request->getSession()->set('is_div',
				array(
					'f'=>$searchDateFrom,
					't'=>$searchDateTo,
					'pf'=>$searchPaymentDateFrom,
					'pt'=>$searchPaymentDateTo,
					'p'=>$searchPortfolio,
					's'=>$searchSector,
					'i'=>$searchIncome,
					'o'=>$orderBy,
					'd1'=>$exDivDateSearch,
					'd2'=>$paymentDateSearch,
					'updated'=>date('Y-m-d H:i:s')
				)
			);
			
			return $this->redirect($this->generateUrl('invest_share_dividend'));
		}
		
    	$qb=$em->createQueryBuilder()
    		->select('c.code')
    		->addSelect('c.name')
    		->addSelect('c.sector')
    		->addSelect('c.frequency')
    		->addSelect('c.currency')
    		->addSelect('d.amount as Dividend')
    		->addSelect('d.exDivDate')
    		->addSelect('d.paymentDate')
    		->addSelect('d.paymentRate')
    		->addSelect('d.special')
    		->addSelect('c.lastPrice as SharePrice')
    		->addSelect('(d.amount/c.lastPrice)*100 as CurrentYield')
    		->addSelect('0 as PurchasePrice')
    		->addSelect('0 as Yield')
    		->addSelect('0 as Income')
    		->addSelect('0 as IncomeGBP')
    		->addSelect('0 as PredictedIncome')
    		->from('InvestShareBundle:Dividend', 'd')
    		->join('InvestShareBundle:Company', 'c', 'WITH', 'd.companyId=c.id')
    		->where('c.code IN (\''.implode('\',\'', array_keys($companies)).'\')')
    		->orderBy($order[$orderBy], 'ASC');
    		
    	if ($searchSector) {
			$qb->andWhere('c.sector=:sector')
				->setParameter('sector', $searchSector);
		}

		$dividends=$qb->getQuery()->getArrayResult();
    		
    	$companyData=array();
    	if (count($tradeData)) {
    		foreach ($dividends as $kdiv=>$div) {

	    		foreach ($tradeData as $td) {

	    			if (!$searchPortfolio || $searchPortfolio==$td['portfolioId']) {

	    				if ($div['code'] == $td['companyCode'] && $div['exDivDate'] > $td['tradeDate1'] && ($td['reference2'] == '' || $div['exDivDate'] <= $td['tradeDate2'])) {
	    					if ($td['reference2'] != '') {

	    						$companies[$td['companyCode']][1]['Quantity']+=$td['quantity1'];
	    						$companies[$td['companyCode']][1]['Total']+=($td['quantity1']*$td['unitPrice1']);
	    						$companies[$td['companyCode']][1]['Price']=($companies[$td['companyCode']][1]['Total']/$companies[$td['companyCode']][1]['Quantity']);
	    					}
		    				$companies[$td['companyCode']][0]['Currency']=$div['currency'];
		    				$companies[$td['companyCode']][1]['Currency']=$div['currency'];
		    				
		    				$income=$td['quantity1']*$div['Dividend']/(($div['currency']=='GBP')?(100):(1));
		    				$dividends[$kdiv]['Income']+=$income;
		    				$dividends[$kdiv]['IncomeGBP']+=$income/(($div['currency']=='GBP' || $div['paymentRate']==null)?(1):($div['paymentRate']));

		    				$dividends[$kdiv]['Details'][$td['reference1']]=$td;
	    				}	    				
	    				 
	    				if ($div['code'] == $td['companyCode'] && $td['reference2'] == '') {
	    					
							$companies[$td['companyCode']][0]['Quantity']+=$td['quantity1'];
	    					$companies[$td['companyCode']][0]['Total']+=($td['quantity1']*$td['unitPrice1']);
	    					$companies[$td['companyCode']][0]['Price']=($companies[$td['companyCode']][0]['Total']/$companies[$td['companyCode']][0]['Quantity']);

							if (!isset($companyData[$div['code']])) {
	    						$companyData[$div['code']]=array(
	    							'Name'=>$div['name'],
	    							'Sector'=>$div['sector'],
	    							'SharePrice'=>$div['SharePrice'],
	    							'Quantity'=>0,
	    							'Predicted'=>1,
	    							'References'=>array(),
	    							'Currency'=>$div['currency']
	    						);
	    					}
	    					if (!in_array($td['reference1'], $companyData[$div['code']]['References'])) {
	    						$companyData[$div['code']]['References'][]=$td['reference1'];
	    						$companyData[$div['code']]['Quantity']+=$td['quantity1'];
	    					}
	    					$companyData[$div['code']]['Trade'][$td['reference1']]=$td;
	    				}
	    			}
	    		}
    		}
    	}

    	if (count($companies)) {
    		
    		$dividendsTemp=array();
    		
    		foreach ($companies as $k=>$v) {
    			if (isset($dividendsTemp)) {
    				unset($dividendsTemp);
    				$dividendsTemp=array();    				
    			}
    			$d=$functions->getDividendsForCompany($k, true, false);
    			if ($d && count($d)) {
    				foreach ($d as $v1) {
    					if (isset($v1['Predicted']) && $v1['Predicted'] && isset($companyData[$k])) {

   							$dividendsTemp['code']=$k;
   							$dividendsTemp['name']=$companyData[$k]['Name'];
   							$dividendsTemp['sector']=$companyData[$k]['Sector'];
   							$dividendsTemp['SharePrice']=$companyData[$k]['SharePrice'];
   							if (isset($companyData[$k]['Details'])) {
   								$dividendsTemp['Details'][$k]=$companyData[$k]['Details'];
   							} else {
   								if (count($companyData[$k]['Trade'])) {
   									foreach ($companyData[$k]['Trade'] as $tr) {
	   									if ($tr['reference2'] == '' && $k==$tr['companyCode']) {
   											$dividendsTemp['Details'][$tr['reference1']]=$tr;
	   									}
   									}
   								}
   							}

    						$dividendsTemp['exDivDate']=$v1['exDivDate'];
    						$dividendsTemp['paymentDate']=$v1['paymentDate'];
    						$dividendsTemp['declDate']=$v1['declDate'];
    						$dividendsTemp['Dividend']=$v1['amount'];
    						$dividendsTemp['PredictedIncome']=$v1['amount']*$companyData[$k]['Quantity']/(($companyData[$k]['Currency']=='GBP')?(100):(1));
    						$dividendsTemp['PredictedQuantity']=$companyData[$k]['Quantity'];
    						$dividendsTemp['currency']=$companyData[$k]['Currency'];

    						$dividends[]=$dividendsTemp;
    					}
    				}
    			}
    		}
    	}

    	$currencyRates = $functions->getCurrencyRates();
    	
    	if (count($dividends)) {
    		foreach ($dividends as $v) {
    			$ty=$functions->getTaxYear($v['paymentDate']->format('Y-m-d H:i:s'));
    			
    			if (!isset($companies[$v['code']][0]['TotalDividend'][$ty])) {
    				$companies[$v['code']][0]['TotalDividend'][$ty]=0;
    			}
    			 
				$tdiv=$v['Dividend'];
				if ((isset($companyData[$v['code']]['Currency']) && $companyData[$v['code']]['Currency'] != 'GBP')) {
					if (isset($v['PaymentRate']) && $v['PaymentRate']) {
						$tdiv=$tdiv/$v['PaymentRate'];
					} else {
						$tdiv=$tdiv/$currencyRates[$companyData[$v['code']]['Currency']];
					}
				}
				$companies[$v['code']][0]['TotalDividend'][$ty]+=$tdiv;
				
    		}

    		foreach ($dividends as $k=>$v) {

				$pp=(($companies[$v['code']][0]['Price'])?($companies[$v['code']][0]['Price']):($companies[$v['code']][1]['Price']));
    			$dividends[$k]['PurchasePrice']=$pp;
				$dividends[$k]['CurrentYield']=0;
    			$dividends[$k]['Yield']=(($pp)?($companies[$v['code']][0]['TotalDividend'][$functions->getTaxYear($v['paymentDate']->format('Y-m-d H:i:s'))]/$pp*100):(0));
				if (isset($companyData[$v['code']]['Currency']) && $companyData[$v['code']]['Currency'] != 'GBP') {
					$dividends[$k]['Yield']=$dividends[$k]['Yield']*100;					
				}
    			if (isset($companies[$v['code']][0]['TotalDividend'][$functions->getTaxYear($v['paymentDate']->format('Y-m-d H:i:s'))])) {
					$dividends[$k]['TotalDividend']=$companies[$v['code']][0]['TotalDividend'];
						

					if (isset($companies[$v['code']][1]['DividendPerYear'][$functions->getTaxYear($v['paymentDate']->format('Y-m-d H:i:s'))]['AveragePrice'])) {
						$totalDiv=$dividends[$k]['TotalDividend'][$functions->getTaxYear($v['paymentDate']->format('Y-m-d H:i:s'))];
						$ap=$companies[$v['code']][1]['DividendPerYear'][$functions->getTaxYear($v['paymentDate']->format('Y-m-d H:i:s'))]['AveragePrice'];
						$dividends[$k]['CurrentYield']=$totalDiv/$ap*100;
					}
				}

    			if ($dividends[$k]['PredictedIncome']) {
   					$dividends[$k]['Income']=$companies[$v['code']][1]['Dividend'];
    			}
				
    			if ((in_array($searchIncome, array('', 1, 3)) && ($dividends[$k]['Income'] || $dividends[$k]['PredictedIncome'])) || (in_array($searchIncome, array(3,2)) && !$dividends[$k]['Income'] && !$dividends[$k]['PredictedIncome'])) {
    				$dividends[$k]['TaxYear']=$functions->getTaxYear($v['paymentDate']->format('Y-m-d H:i:s'));
    			} else {
    				unset($dividends[$k]);
    			}
    		}
    	}

    	
/*
 * if we have date filter, delete all the unneccessary records
 */
    	if (count($dividends) && ($exDivDateSearch || $paymentDateSearch)) {
    		foreach ($dividends as $k=>$v) {
    			$delete=false;
				if ($exDivDateSearch) {
					if ($v['exDivDate']->format('Y-m-d') < $searchDateFrom->format('Y-m-d') || $v['exDivDate']->format('Y-m-d') > $searchDateTo->format('Y-m-d')) {
						$delete=true;
					}
				}
				if ($paymentDateSearch) {
					if ($v['paymentDate']->format('Y-m-d') < $searchPaymentDateFrom->format('Y-m-d') || $v['paymentDate']->format('Y-m-d') > $searchPaymentDateTo->format('Y-m-d')) {
						$delete=true;
					}
				}
				if ($delete) {
					unset($dividends[$k]);
				}
    		}
    	}
    		
    	switch ($orderBy) {
    		case 1 : {
    			usort($dividends, 'self::divDateSort');
    			break;
    		}
    		default : {
    			usort($dividends, 'self::divSort');
    			break;
    		}
    	}
    	
    	
        return $this->render('InvestShareBundle:Default:dividend.html.twig', array(
        	'dividends'		=> $dividends,
        	'currencyRates'	=> $currencyRates,
        	'searchForm'	=> $searchForm->createView(),
        	'message'		=> $message,
        	'notes'			=> $functions->getConfig('page_dividend')
        ));
    }
    
    
    public function updateAction() {

    	if (!$this->get("security.context")->isGranted('ROLE_ADMIN')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}

/*
 * show only the menu
 */
    	$functions=$this->get('invest.share.functions');
    	
    	return $this->render('InvestShareBundle:Default:update.html.twig', array(
   			'message'	=> '',
    		'notes'		=> $functions->getConfig('page_update')
    	));
    }
    
    
    public function notesAction($action, $id, $additional, Request $request) {
    	
    	$notes=array();
    	$message='';

    	$em=$this->getDoctrine()->getManager();
    	$qb=$em->createQueryBuilder()
    		->select('c.name')
    		->addSelect('c.value')
    		->from('InvestShareBundle:Config', 'c')
    		->where('c.name LIKE :name')
    		->orderBy('c.name', 'ASC')
    		->setParameter('name', 'page_%');
    	$results=$qb->getQuery()->getArrayResult();
		
		if ($results && count($results)) {
			foreach ($results as $result) {
				$notes[substr($result['name'], 5, strlen($result['name']))]=$result['value'];
			}
		}
    	
		$form=$this->createForm(new NotesType($notes));
		$form->handleRequest($request);

		if ($request->isMethod('POST')) {
			if ($form->isValid()) {
				
				$saved=false;
				$formData=$form->getData();
				foreach ($formData as $k=>$v) {
					if (substr($k, 0, 5) == 'page_' && isset($v) && $v && strlen($v)) {
						
						$notes=$this->getDoctrine()
							->getRepository('InvestShareBundle:Config')
							->findOneBy(
								array('name'=>$k)
							);

						$notes->setValue($v);
						$em->flush();
						
						$saved=true;
					}
				}
				if ($saved) {
					
					$this->get('session')->getFlashBag()->add(
						'notice',
						'Notes saved'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_update'));
				}
			}
		}
		
    	return $this->render('InvestShareBundle:Default:notes.html.twig', array(
    		'notes'	=> $notes,
    		'form'	=> $form->createView(),
   			'message' => $message
    	));
    }
    
    
    public function companyAction($action, $id, $additional, Request $request) {

    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}

/*
 * add, delete and edit company details
 * add, delete and edit dividend based on company
 */
    	$message='';
    	$show=false;
		$showForm=1;
		$formTitle='';
		$searchCompany=null;
		$searchSector=null;
		$searchList=null;
		$errors=array();
    	$warnings=array();
    	$pageStart=0;
    	$lastPage=0;
    	$company=new Company();
    	$dividend=new Dividend();
    	$searchCompanies=array();
    	$searchSectors=array();
    	$searchLists=array(
    		'FTSE100'=>'FTSE 100',
    		'FTSE250'=>'FTSE 250',
    		'FTSESmallCap'=>'FTSE Small Cap'
    	);
    	 
    	$em=$this->getDoctrine()->getManager();
    	$functions=$this->get('invest.share.functions');
    	 
    	switch ($action) {
    		case 'page' : {
    			$pageStart=(int)$id;
    			break;
    		}
    		case 'edit' : {
/*
 * Edit company details
 */
    			$company=$this->getDoctrine()
		    		->getRepository('InvestShareBundle:Company')
		    		->findOneBy(
		    			array(
		    				'id'=>$id
		    			)
	    		);
	    		if (!$company) {
					$this->get('session')->getFlashBag()->add(
						'notice',
						'ID not found'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_company'));
	    		}
/*
 * show form
 */
	    		$show=true;
	    		break;
	    	}	 
    		case 'delete' : {
/*
 * Delete company details
 */
	    		$company=$this->getDoctrine()
					->getRepository('InvestShareBundle:Company')
					->findOneBy(
						array(
							'id'=>$id
						)
				);
			  			
				if ($company) {
					$em = $this->getDoctrine()->getManager();

					$em->remove($company);
					$em->flush();

					$this->get('session')->getFlashBag()->add(
						'notice',
						'Company deleted'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_company'));
				} else {
					$this->get('session')->getFlashBag()->add(
						'notice',
						'ID not found'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_company'));
				}
				break;
	    	}
    		case 'add' : {
/*
 * add company, show company form
 */
    			$show=true;
    			break;
    		}
    		case 'adddividend' :
   		   	case 'editdividend' : {
/*
 * add/edit dividend to the selected company
 */
	   			$company=$this->getDoctrine()
					->getRepository('InvestShareBundle:Company')
					->findOneBy(
						array(
							'id'=>$id
						)
					);
   		   		if ($additional) {
    				$dividend=$this->getDoctrine()
						->getRepository('InvestShareBundle:Dividend')
						->findOneBy(
							array(
								'id'=>$additional
							)
						);
					if (!$dividend) {
						$this->get('session')->getFlashBag()->add(
							'notice',
							'ID not found'
						);
					   						
						return $this->redirect($this->generateUrl('invest_share_company'));
					}
   				}
/*
 * create a list from all the companies for dropdown list
 */
/*
 * show 2nd form, add/edit dividend
 */
				$showForm=2;
				$show=true;
				break;
			}
    	   	case 'deletedividend' : {
/*
 * delete dividend, no need form
 */
    	   		if ($additional) {
	   				$dividend=$this->getDoctrine()
						->getRepository('InvestShareBundle:Dividend')
						->findOneBy(
							array(
								'id'=>$additional
							)
					);
					if ($dividend) {
						$em = $this->getDoctrine()->getManager();
						
						$em->remove($dividend);
						$em->flush();
						
						$this->get('session')->getFlashBag()->add(
							'notice',
							'Dividend details deleted'
						);
					   						
						return $this->redirect($this->generateUrl('invest_share_company'));
					} else {
						$this->get('session')->getFlashBag()->add(
							'notice',
							'ID not found'
						);
					   						
						return $this->redirect($this->generateUrl('invest_share_company'));
					}
   				}
				$show=false;
				break;
			}
    	}

    	if ($show) {
    		switch ($showForm) {
/*
 * 1st form with company details
 */
    			case 1 : {
    				$formTitle='Company Details';
    				$form=$this->createForm(new CompanyType($company, $searchLists));
			    	$form->handleRequest($request);
			    	
			    	$validator=$this->get('validator');
			    	$errors=$validator->validate($company);
			    	
			    	if (count($errors) > 0) {
			    		$message=(string)$errors;
			    	} else {
			
			    		if ($form->isValid()) {
			   				switch ($action) {
			   					case 'add' : {
/*
 * add company details manually
 */
			   						$company=$this->getDoctrine()
						    			->getRepository('InvestShareBundle:Company')
						    			->findOneBy(
						    				array(
						    					'code'=>$form->get('code')->getData()
						    				),
						    				array('name'=>'ASC')
						    		);
							   				
						   			if (!$company) {
						   				$em = $this->getDoctrine()->getManager();
							    			
						   				$company=new Company();
							    		$company->setName($form->get('name')->getData());
							    		$company->setAltName($form->get('altName')->getData());
							    		$company->setList($form->get('list')->getData());
							    		$company->setCode($form->get('code')->getData());
							    		$company->setSector($form->get('sector')->getData());
							    		$company->setFrequency($form->get('frequency')->getData());
							    		$company->setCurrency($form->get('currency')->getData());
							    			
							    		$em->persist($company);
							    		$em->flush();
								    			
							    		if ($company->getId()) {
											$this->get('session')->getFlashBag()->add(
												'notice',
												'Company data saved'
											);
										   						
											return $this->redirect($this->generateUrl('invest_share_company'));
							    		}
						    		} else {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Company already exists'
										);
									   						
										return $this->redirect($this->generateUrl('invest_share_company'));
						    		}
						    		$show=false;
						    		break;
				   				}
			   					case 'edit' : {
/*
 * edit company details manually
 */
				   					$em = $this->getDoctrine()->getManager();
				
				   					$company->setName($form->get('name')->getData());
				   					$company->setAltName($form->get('altName')->getData());
				   					$company->setList($form->get('list')->getData());
				   					$company->setCode($form->get('code')->getData());
				   					$company->setSector($form->get('sector')->getData());
				   					$company->setFrequency($form->get('frequency')->getData());
				   					$company->setCurrency($form->get('currency')->getData());
				   					
				   					$em->flush();
				   					 
				   					if ($company->getId()) {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Company details updated'
										);
									   						
										return $this->redirect($this->generateUrl('invest_share_company'));
				   					} else {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Saving problem'
										);
									   						
										return $this->redirect($this->generateUrl('invest_share_company'));
				   					}
				   					$show=false;
				   					break;
			   					}
			   				}
			
			    		}
			    	}
			    	break;
    			}
		    	case 2 : {
/*
 * 2nd form with dividend details
 */
		    		$formTitle='Dividend Details';
		    		$form2=$this->createForm(new DividendDetailsType($id, $company, $dividend));
		    		$form2->handleRequest($request);
		    	
		    		$validator=$this->get('validator');
		    		$errors=$validator->validate($dividend);
		    			
		    		if (count($errors) > 0) {
		    			$message=(string)$errors;
		    		} else {
		    				
		    			if ($form2->isValid()) {
		    				switch ($action) {
		    					case 'adddividend' : {
/*
 * add dividend details manually
 */
		    						$dividend=$this->getDoctrine()
		    							->getRepository('InvestShareBundle:Dividend')
		    							->findOneBy(
		    								array(
		    									'id'=>$form2->get('id')->getData()
		    								)
		    							);
		    	
		    						if (!$dividend) {
		    							
		    							$dividend=new Dividend();
		    							
		    							$em = $this->getDoctrine()->getManager();

		    							$dividend->setCompanyId($form2->get('CompanyId')->getData());
		    							$dividend->setDeclDate($form2->get('DeclDate')->getData());
		    							$dividend->setExDivDate($form2->get('ExDivDate')->getData());
		    							$dividend->setAmount($form2->get('Amount')->getData());
		    							$dividend->setPaymentDate($form2->get('PaymentDate')->getData());
		    							$dividend->setPaymentRate($form2->get('PaymentRate')->getData());
		    							$dividend->setSpecial($form2->get('Special')->getData());
		    								
		    							$em->persist($dividend);
		    							$em->flush();
		    								
		    							if ($dividend->getId()) {
											$this->get('session')->getFlashBag()->add(
												'notice',
												'Dividend details saved'
											);
										   						
											return $this->redirect($this->generateUrl('invest_share_company'));
		    							}
		    						} else {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Dividend already exists'
										);
									   						
										return $this->redirect($this->generateUrl('invest_share_company'));
		    						}
		    						$show=false;
		    						break;
		    					}
		    					case 'editdividend' : {
/*
 * edit dividend details manually
 */
		    						$dividend=$this->getDoctrine()
		    							->getRepository('InvestShareBundle:Dividend')
		    							->findOneBy(
		    								array(
		    									'id'=>$form2->get('id')->getData()
		    								)
		    							);
		    	
		    						if ($dividend) {
		    							$em = $this->getDoctrine()->getManager();
		    	
		    							$dividend->setCompanyId($form2->get('CompanyId')->getData());
		    							$dividend->setExDivDate($form2->get('ExDivDate')->getData());
		    							$dividend->setAmount($form2->get('Amount')->getData());
		    							$dividend->setPaymentDate($form2->get('PaymentDate')->getData());

		    							$em->flush();
		    								
		    							if ($dividend->getId()) {
											$this->get('session')->getFlashBag()->add(
												'notice',
												'Dividend details updated'
											);
										   						
											return $this->redirect($this->generateUrl('invest_share_company'));
		    							}
		    						} else {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Saving problem'
										);
									   						
										return $this->redirect($this->generateUrl('invest_share_company'));
		    						}
		    						$show=false;
		    						break;
		    					}
		    				}
		    			}
		    		}
		    		break;
		    	}
    		}
    	}
    	
   		if (!$request->isMethod('POST') && null !== ($request->getSession()->get('is_comp'))) {
   			$data=$request->getSession()->get('is_comp');
   			$ok=true;
   			if (isset($data['updated'])) {
   				if ($data['updated'] < date('Y-m-d H:i:s', time()-$this->searchTime)) {
   					$ok=false;
   				}
   			}
   			if ($ok) {
   				if (isset($data['c'])) {
   					$searchCompany=$data['c'];
   				}
   				if (isset($data['sc'])) {
   					$searchSector=$data['sc'];
   				}
   				if (isset($data['l'])) {
   					$searchList=$data['l'];
   				}
   			} else {
   				$request->getSession()->remove('is_comp');
   			}
   		}
    		 
		$qb=$em->createQueryBuilder()
			->select('c.id')
			->addSelect('c.code')
			->addSelect('c.name')
			->addSelect('c.sector')
			->addSelect('c.currency')
			->from('InvestShareBundle:Company', 'c')
			->orderBy('c.name');
   		
   		$results=$qb->getQuery()->getArrayResult();
   		
   		$companyNames=array();
   		$dividends=array();
   			
   		if (count($results)) {
   			foreach ($results as $result) {
   				$searchCompanies[$result['id']]=$result['name'];
   				if ($result['sector']) {
   					$searchSectors[$result['sector']]=$result['sector'];
   				}
   			}
   			ksort($searchSectors);
   		}
   				 
    	$searchForm=$this->createForm(new CompanySearchType($this->generateUrl('invest_share_company'), $searchCompany, $searchCompanies, $searchSector, $searchSectors, $searchList, $searchLists));
    	$searchForm->handleRequest($request);
    	 
    	if ($request->isMethod('POST')) {
    		$formData=$searchForm->getData();
			if (isset($formData['company']) && $formData['company']) {
				$searchCompany=$formData['company'];
			}
			if (isset($formData['sector']) && $formData['sector']) {
				$searchSector=$formData['sector'];
			}
			if (isset($formData['list']) && $formData['list']) {
				$searchList=$formData['list'];
			}
			$pageStart=0;
				
			$request->getSession()->set('is_comp',
				array(
					'c'=>$searchCompany,
					'sc'=>$searchSector,
					'l'=>$searchList,
					'updated'=>date('Y-m-d H:i:s'
				)
			));
			
			return $this->redirect($this->generateUrl('invest_share_company'));
			
		}
		 
		if (count($results)) {
			foreach ($results as $result) {
				$cId=$result['id'];
		
				$d=$functions->getDividendsForCompany($result['code'], true);
				if ($d && count($d)) {
					foreach ($d as $k=>$v) {
						$w1=$v['exDivDate']->getTimestamp();
						$w2=time()+$this->dividendWarningDays*24*60*60;
						$warning=(($w1>=time() && $w1<$w2)?(1):(0));
		
						$d[$k]['warning']=$warning;
		
						if ($warning) {
							$d[$k]['CompanyCode']=$result['code'];
							$d[$k]['CompanyName']=$result['name'];
							$d[$k]['Currency']=$result['currency'];
		
							$warnings[]=$d[$k];
						}
							
						$dividends[$cId]=$d;
					}
				}
			}
		}
		
		$searchArray=array();
		if ($searchCompany > 0) {
			$searchArray['id']=$searchCompany;
		}
		if ($searchSector) {
			$searchArray['sector']=$searchSector;
		}
    	if ($searchList) {
			$searchArray['list']=$searchList;
		}
				

		$query='SELECT SQL_CALC_FOUND_ROWS `id`,`Code`,`Name`,`Sector`,`Frequency`,`Currency` FROM `Company`';
		if (count($searchArray)) {
			$q1=array();
			foreach ($searchArray as $k=>$v) {
				$q1[]='`'.$k.'`="'.$v.'"';
			}
			if (count($q1)) {
				$query.=' WHERE ('.implode(') AND (', $q1).')';
			}
		}
		$query.=' ORDER BY `Name` LIMIT '.($pageStart*$this->pager).','.($this->pager);

		$connection=$this->getDoctrine()->getConnection();
		$stmt=$connection->prepare($query);
		$stmt->execute();
		$results=$stmt->fetchAll();
		
		$query2='SELECT FOUND_ROWS() as `last`';
		$stmt=$connection->prepare($query2);
		$stmt->execute();
		$result2=$stmt->fetch();
		if (is_array($result2)) {
			$lastPage=sprintf('%d', ceil($result2['last']/$this->pager)-1);
		}

		$companyNames=array();
		$companyCodes=array();
		$deals=array();

    	if (count($results)) {
    		foreach ($results as $result) {
     			$cId=$result['id'];
    			$companyNames[$cId]=$result;
    			$companyCodes[$result['Code']]=$result['Code'];
    		}
    	}
    	
    	if (count($companyCodes)) {
    		$qb=$em->createQueryBuilder()
    			->select('dd.code')
    			->addSelect('dd.dealDate')
    			->addSelect('dd.type')
    			->addSelect('dd.name')
    			->addSelect('dd.position')
    			->addSelect('dd.shares')
    			->addSelect('dd.price')
    			->addSelect('dd.value')
    			->from('InvestShareBundle:DirectorsDeals', 'dd')
    			->where('dd.code IN (\''.implode('\',\'', $companyCodes).'\')')
    			->orderBy('dd.dealDate', 'ASC')
    			->addOrderBy('dd.name', 'ASC');

    		$results=$qb->getQuery()->getArrayResult();
    		
    		if ($results) {
    			foreach ($results as $result) {
    				$deals[$result['code']][]=$result;
    			}
    		}
    		
    	}
   	
    	$prevPage='';
    	$nextPage='';
    	$firstPage='';
    	if ($lastPage > 0 && $pageStart > 0) {
    		$firstPage='0';
    	}
    	if ($pageStart > 0) {
    		$prevPage = sprintf('%d', $pageStart - 1);
    	}
    	if ($pageStart < $lastPage) {
    		$nextPage = $pageStart + 1;
    	} else {
    		$lastPage = '';
    	}

    	return $this->render('InvestShareBundle:Default:company.html.twig', array(
        	'name' 			=> 'Company',
        	'message' 		=> $message,
        	'errors' 		=> $errors,
			'form' 			=> (($show && $showForm==1)?($form->createView()):(null)),
			'form2' 		=> (($show && $showForm==2)?($form2->createView()):(null)),
    		'formTitle' 	=> $formTitle,
    		'searchForm'	=> $searchForm->createView(),
        	'companies' 	=> $companyNames,
    		'dividends' 	=> $dividends,
    		'deals'			=> $deals,
    		'warnings'		=> $warnings,
    		'warningDays'	=> $this->dividendWarningDays,
    		'actualPage'	=> $pageStart,
    		'prevPage'		=> $prevPage,
    		'nextPage'		=> $nextPage,
    		'firstPage'		=> $firstPage,
    		'lastPage'		=> $lastPage,
    		'notes'			=> $functions->getConfig('page_company')
        ));
    }

    
    public function ddealsAction() {

    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}

    	$currentUser=$this->getUser();
    	$request=$this->getRequest();
    	$message='';
    	$deals=array();
    	$companyShares=array();
    	$companyNames=array();
    	$codes=array();
    	$summary=array();
    	$volumeSummary=array();
    	$types=array();
    	$positions=array();
    	$searchType=null;
    	$searchDateFrom=new \DateTime('-1 month');
   		$searchDateTo=new \DateTime('now');
    	$searchLimit=$this->dealsLimit;
    	$searchCompany=null;
    	$searchPosition=null;
    	$searchSector=null;
    	$searchFilter=1;
    	$searchListType=0;
    	
    	if (!$request->isMethod('POST') && null !== ($request->getSession()->get('is_ddeals'))) {
    		$data=$request->getSession()->get('is_ddeals');
    		$ok=true;
    		if (isset($data['updated'])) {
    				
    			if ($data['updated'] < date('Y-m-d H:i:s', time()-$this->searchTime)) {
    				$ok=false;
    			}
    		}
    		if ($ok) {
    			if (isset($data['t'])) {
    				$searchType=$data['t'];
    			}
    			if (isset($data['c'])) {
    				$searchCompany=$data['c'];
    			}
    			if (isset($data['df'])) {
    				$searchDateFrom=$data['df'];
    			}
    			if (isset($data['dt'])) {
    				$searchDateTo=$data['dt'];
    			}
    			if (isset($data['p'])) {
    				$searchPosition=$data['p'];
    			}
    			if (isset($data['l'])) {
    				$searchLimit=$data['l'];
    			} else {
    				$searchLimit=null;
    			}
    			if (isset($data['f'])) {
    				$searchFilter=$data['f'];
    			} else {
    				$searchFilter=0;
    			}
				if (isset($data['lt'])) {
    				$searchListType=$data['lt'];
    			} else {
    				$searchListType=0;
    			}
    			if (isset($data['s'])) {
    				$searchSector=$data['s'];
    			} else {
    				$searchSector=0;
    			}
    		} else {
    			$request->getSession()->remove('is_ddeals');
    		}
    	}

    	$functions=$this->get('invest.share.functions');
    	$em=$this->getDoctrine()->getManager();   		
   		$qb=$em->createQueryBuilder()
   			->select('dd.type')
   			->addSelect('dd.position')
   			->from('InvestShareBundle:DirectorsDeals', 'dd')
   			->where('LENGTH(dd.type)>0')
   			->groupBy('dd.position')
   			->orderBy('dd.position', 'ASC');
   		
   		$results=$qb->getQuery()->getArrayResult();

   		if ($results) {
   			foreach ($results as $result) {
   				$types[$result['type']]=ucwords($result['type']);
   				if (strlen($result['position'])) {
   					$positions[$result['position']]=ucwords($result['position']);
   				}
   			}
   		}

   		$companyNames=$functions->getCompanyNames(($searchFilter)?(true):(false), $currentUser->getId());
   		$sectors=$functions->getSectors();
   		
   		$searchForm=$this->createForm(new DealsSearchType($searchType, $types, $searchPosition, $positions, $searchCompany, $functions->getCompanyNames(false, $currentUser->getId()), $searchDateFrom, $searchDateTo, $searchLimit, $searchFilter, $searchListType, $searchSector, $sectors));
    	$searchForm->handleRequest($request);
    	
    	if ($searchForm->isValid() && $request->isMethod('POST')) {
    		$formData=$searchForm->getData();
    		if (isset($formData['type']) && $formData['type']) {
    			$searchType=$formData['type'];
    		}
    	    if (isset($formData['company']) && $formData['company']) {
    			$searchCompany=$formData['company'];
    		}
    		if (isset($formData['dateFrom']) && $formData['dateFrom']) {
    			$searchDateFrom=$formData['dateFrom'];
    		}
    		if (isset($formData['dateTo']) && $formData['dateTo']) {
    			$searchDateTo=$formData['dateTo'];
    		}
    	    if (isset($formData['position']) && $formData['position']) {
    			$searchPosition=$formData['position'];
    		}
    		if (isset($formData['limit']) && $formData['limit']) {
    			$searchLimit=$formData['limit'];
    		} else {
    			$searchLimit=null;
    		}
    		if (isset($formData['filter'])) {
    			$searchFilter=$formData['filter'];
    		} else {
    			$searchFilter=0;
    		}
    		if (isset($formData['listType'])) {
    			$searchListType=$formData['listType'];
    		} else {
    			$searchListType=0;
    		}
    		if (isset($formData['sector'])) {
    			$searchSector=$formData['sector'];
    		} else {
    			$searchSector='';
    		}
    		
    		$request->getSession()->set('is_ddeals', array(
   				't'=>$searchType,
   				'c'=>$searchCompany,
   				'df'=>$searchDateFrom,
   				'dt'=>$searchDateTo,
   				'p'=>$searchPosition,
   				'l'=>$searchLimit,
   				'f'=>$searchFilter,
    			'lt'=>$searchListType,
    			's'=>$searchSector,
   				'updated'=>date('Y-m-d H:i:s')));
    		
    		return $this->redirect($this->generateUrl('invest_share_ddeals')); 
    	}
    	 
    	$trades=$functions->getTradesData(null, null, null, null, null, null, $currentUser->getId());
    	
       	if (count($trades)) {
	   		foreach ($trades as $t) {
	   			if ($t['reference2'] == '') {
	   				if (!isset($companyShares[$t['companyCode']])) {
	   					$companyShares[$t['companyCode']]=0;
	   				}
	   				$companyShares[$t['companyCode']]+=$t['quantity1'];
	   			}
	   		}
   		}

   		 
    	if (count($companyNames)) {
    		$qb2=$em->createQueryBuilder()
    			->select('dd.code')
    			->addSelect('SUM(dd.shares) as Shares')
    			->from('InvestShareBundle:DirectorsDeals', 'dd')
    			->where('dd.code IN (\''.implode('\',\'', array_keys($companyNames)).'\')')
    			->andWhere('dd.dealDate BETWEEN \''.$searchDateFrom->format('y-m-d').'\' AND \''.$searchDateTo->format('Y-m-d').'\'')
    			->groupBy('dd.code');

    		if ($searchLimit) {
    			$qb2->having('Shares>='.$searchLimit);
    		}
    		
    		$results=$qb2->getQuery()->getArrayResult();

    		if ($results) {
    			foreach ($results as $result) {
    				$codes[]=$result['code'];
    			}
    		}
    	}
    	if (count($codes)) {
    		$qb3=$em->createQueryBuilder()
    			->select('dd.code')
    			->addSelect('dd.type')
    			->addSelect('dd.shares')
    			->addSelect('dd.value')
    			->addSelect('dd.declDate')
    			->addSelect('dd.dealDate')
    			->addSelect('dd.name')
    			->addSelect('dd.position')
    			->addSelect('dd.price')
    			->addSelect('c.lastPrice')
    			
    			->from('InvestShareBundle:DirectorsDeals', 'dd')
    			->leftJoin('InvestShareBundle:Company', 'c', 'WITH', 'dd.code=c.code')
    			->where('dd.code IN (\''.implode('\',\'', $codes).'\')')
    			->andWhere('dd.dealDate BETWEEN \''.$searchDateFrom->format('Y-m-d').'\' AND \''.$searchDateTo->format('Y-m-d').'\'')
    			->orderBy('dd.code', 'ASC')
    			->addOrderBy('dd.dealDate', 'ASC');

    		if ($searchSector) {
    			$qb3->andWhere('c.sector=:sector')
    				->setParameter('sector', $searchSector);
    		}
    			
    		if ($searchType) {
    			$qb3->andWhere('dd.type=:type')
    				->setParameter('type', $searchType);
    		}
    		if ($searchCompany) {
    			$qb3->andWhere('dd.code=:code')
    				->setParameter('code', $searchCompany);
    		}
    		if ($searchPosition) {
    			$qb3->andWhere('dd.position=:position')
    				->setParameter('position', $searchPosition);
    		}
    		$results=$qb3->getQuery()->getArrayResult();

    		if ($results) {
    			if ($searchListType) {
    				// Calculate summary based on sell/buy volume
    				foreach ($results as $result) {
    					if (in_array($result['type'], array('BUY', 'SELL'))) {
	    					if (!isset($volumeSummary[$result['code']])) {
	    						$volumeSummary[$result['code']]=array(
	    							'BUY'=>0,
	    							'BUY_SHARES'=>0,
	    							'SELL'=>0,
	    							'SELL_SHARES'=>0,
//	    							'BALANCE'=>0,
									'code'=>$result['code'],
	    							'Company'=>$companyNames[$result['code']]
	    						);
	    					}
	    					$volumeSummary[$result['code']][$result['type']]+=$result['value'];	    					
	    					$volumeSummary[$result['code']][$result['type'].'_SHARES']+=$result['shares'];
    					}
    				}
    				if (count($volumeSummary)) {
    					foreach ($volumeSummary as $k=>$vs) {
    						$volumeSummary[$k]['BALANCE']=$vs['BUY']-$vs['SELL'];
    						$volumeSummary[$k]['BALANCE_SHARES']=$vs['BUY_SHARES']-$vs['SELL_SHARES'];
    					}
    					usort($volumeSummary, 'self::vsSort');
    				}
    				
    			} else {
    				// Create a list from all the
	    			foreach ($results as $result) {
	    				$result['Company']=$companyNames[$result['code']];
	   					$result['CurrentShares']=((isset($companyShares[$result['code']]))?($companyShares[$result['code']]):(0));
	    				$result['CurrentValue']=$result['lastPrice'];
	    				
	    				$deals[]=$result;
	    				
	    				if (!isset($summary[$result['type']])) {
	    					$summary[$result['type']]=array('Shares'=>0, 'Value'=>0);
	    				}
	    				$summary[$result['type']]['Shares']+=$result['shares'];
	    				$summary[$result['type']]['Value']+=$result['value'];
	    			}
    			}
    		}
    	}
    	
    	return $this->render('InvestShareBundle:Default:directordeals.html.twig', array(
    		'showmenu'		=> true,
    		'searchForm'	=> $searchForm->createView(),
    		'deals' 		=> $deals,
    		'summary'		=> $summary,
    		'volumeSummary'	=> $volumeSummary,
    		'extra'			=> true,
    		'message' 		=> $message,
    		'notes'			=> $functions->getConfig('page_deals')    			 
    	));	 
    }
    
    
    public function diaryAction() {
    	
    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}
    	 
    	$currentUser=$this->getUser();
    	$request=$this->getRequest();
    	$message='';
    	$diary=array();
    	$searchType=null;
    	$searchDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-6, date('Y'))));
   		$searchDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')+13, date('Y'))));
    	$searchCompany=null;
    	$searchFilter=null;
    	$em=$this->getDoctrine()->getManager();
    	
    	if (!$request->isMethod('POST') && null !== ($request->getSession()->get('is_diary'))) {
    		$data=$request->getSession()->get('is_diary');
    		$ok=true;
    		if (isset($data['updated'])) {
    				
    			if ($data['updated'] < date('Y-m-d H:i:s', time()-$this->searchTime)) {
    				$ok=false;
    			}
    		}
    		if ($ok) {
    			if (isset($data['t'])) {
    				$searchType=$data['t'];
    			}
    			if (isset($data['c'])) {
    				$searchCompany=$data['c'];
    			}
    			if (isset($data['df'])) {
    				$searchDateFrom=$data['df'];
    			}
    			if (isset($data['dt'])) {
    				$searchDateTo=$data['dt'];
    			}
    			if (isset($data['f'])) {
    				$searchFilter=$data['f'];
    			}
    		} else {
    			$request->getSession()->remove('is_diary');
    		}
    	}
  
       	$types=array();
   		$types['']='All';
   		
   		$qb=$em->createQueryBuilder()
   			->select('d.type')
   			->from('InvestShareBundle:Diary', 'd')
   			->where('LENGTH(d.type)>0')
   			->groupBy('d.type')
   			->orderBy('d.type');
   		
   		$results=$qb->getQuery()->getArrayResult();

   		if ($results) {
   			foreach ($results as $result) {
   				$types[$result['type']]=ucwords($result['type']);
   			}
   		}
   		$functions=$this->get('invest.share.functions');
   		$companies=$functions->getCompanyNames(($searchFilter)?(true):(false), $currentUser->getId());
   		 
   		$searchForm=$this->createForm(new DiarySearchType($searchType, $types, $searchCompany, $companies, $searchDateFrom, $searchDateTo, $searchFilter));
    	$searchForm->handleRequest($request);
    	
    	if ($request->isMethod('POST')) {
    		$formData=$searchForm->getData();
    		if (isset($formData['type']) && $formData['type']) {
    			$searchType=$formData['type'];
    		}
    	    if (isset($formData['company']) && $formData['company']) {
    			$searchCompany=$formData['company'];
    		}
    		if (isset($formData['dateFrom']) && $formData['dateFrom']) {
    			$searchDateFrom=$formData['dateFrom'];
    		}
    		if (isset($formData['dateTo']) && $formData['dateTo']) {
    			$searchDateTo=$formData['dateTo'];
    		}
    		if (isset($formData['filter']) && $formData['filter']) {
    			$searchFilter=$formData['filter'];
    		}
    		
    		$request->getSession()->set('is_diary', array(
   				't'=>$searchType,
   				'c'=>$searchCompany,
   				'df'=>$searchDateFrom,
   				'dt'=>$searchDateTo,
   				'f'=>$searchFilter,
   				'updated'=>date('Y-m-d H:i:s')));
    		
    		return $this->redirect($this->generateUrl('invest_share_diary'));
    	}

    	$qb2=$em->createQueryBuilder()
    		->select('d.code')
    		->addSelect('d.type')
    		->addSelect('d.date')
    		->addSelect('d.name')
    		->addSelect('c.lastPrice as CurrentValue')
    		
    		->from('InvestShareBundle:Diary', 'd')
    		->leftJoin('InvestShareBundle:Company', 'c', 'WITH', 'd.code=c.code')
    		->where('d.date BETWEEN :date1 AND :date2')
    		->orderBy('d.code', 'ASC')
    		->addOrderBy('d.date', 'ASC')
    		->setParameter('date1', $searchDateFrom->format('Y-m-d'))
    		->setParameter('date2', $searchDateTo->format('Y-m-d'));
    		
    	if ($searchFilter) {
    		$qb2->andWhere('c.code IN (\''.implode('\',\'', array_keys($companies)).'\')');
    	}
    	if ($searchCompany) {
    		$qb2->andWhere('d.code=:code')
    			->setParameter('code', $searchCompany);
    	}
    	if ($searchType) {
    		$qb2->andWhere('d.type=:type')
    			->setParameter('type', $searchType);
    	}
    	$diary=$qb2->getQuery()->getArrayResult();
    	    	
    	return $this->render('InvestShareBundle:Default:diary.html.twig', array(
    		'searchForm'	=> $searchForm->createView(),
    		'diary' 		=> $diary,
    		'extra'			=> true,
    		'message' 		=> $message,
    		'notes'			=> $functions->getConfig('page_diary')
    	));	 
    }
    
    
    public function tradeAction($action, $id, $additional, $extra) {
        
    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}
    	$currentUser=$this->getUser();
    	$request=$this->getRequest();
/*
 * add/edit/delete trade details
 */
    	
    	$functions=$this->get('invest.share.functions');
		$message='';
		$searchCompany=0;
		$searchPortfolio=0;
		$searchSector='';
		$searchSold=0;
		$searchDateFrom=null;
		$searchDateTo=null;
		$show=false;
		$showForm=1;
		$formTitle='Trade Details';
		$errors=array();
		$trade=new Trade();
		$tradeTransaction=new TradeTransactions();

		$em=$this->getDoctrine()->getManager();
		
   		switch ($action) {
   			case 'list' : {
   				$searchPortfolio=$id;
   				break;
   			}
			case 'edit' : {
/*
 * trade/edit form
 */
				$trade=$this->getDoctrine()
					->getRepository('InvestShareBundle:Trade')
					->findOneBy(
						array(
							'id'=>$id
						)
				);
				if (!$trade) {
					$this->get('session')->getFlashBag()->add(
						'notice',
						'ID not found'
					);
   						
					return $this->redirect($this->generateUrl('invest_share_trade'));
				}
				$show=true;
				break;
			}
			case 'delete' : {
/*
 * trade/delete without form
 */
				$trade=$this->getDoctrine()
					->getRepository('InvestShareBundle:Trade')
					->findOneBy(
						array(
							'id'=>$id
						)
				);

				if ($trade) {
					$em = $this->getDoctrine()->getManager();
					
					$em->remove($trade);
					$em->flush();
					
					$tt=$this->getDoctrine()
						->getRepository('InvestShareBundle:TradeTransactions')
						->findBy(
							array(
								'tradeId'=>$id
							)
						);
					if ($tt) {
						foreach ($tt as $tt1) {
							$em->remove($tt1);
							$em->flush();
						}
					}
					
					$this->get('session')->getFlashBag()->add(
						'notice',
						'Trade deleted'
					);
   						
					return $this->redirect($this->generateUrl('invest_share_trade'));
				} else {
					$this->get('session')->getFlashBag()->add(
						'notice',
						'Wrong ID'
					);
					   						
					return $this->redirect($this->generateUrl('invest_share_trade'));
				}
				$show=false;
				break;
			}
   			case 'addbuy' : {
/*
 * add new "buy" trade, show form
 */

				$tradeTransaction=new TradeTransactions();
				
				$show=true;
				$showForm=2;
				$formTitle='Trade Buy Details';
   				break;
			}
			case 'edittrade' : {
/*
 * edit "buy" trade, show form
 */

				$tradeTransaction=$this->getDoctrine()
					->getRepository('InvestShareBundle:TradeTransactions')
					->findOneBy(
						array(
							'tradeId'=>$id,
							'reference'=>$additional
						)
					);
				if (!$tradeTransaction) {
					$message='ID not found';
				} else {
					$trade=$this->getDoctrine()
						->getRepository('InvestShareBundle:Trade')
						->findOneBy(
							array(
								'id'=>$tradeTransaction->getTradeId()
							)
						);
				}
				
				$show=true;
				$showForm=2;
				$formTitle='Edit Trade Details';
   				break;
			}
			case 'addsell' : {
/*
 * add new "sell" trade, show form
 */

				$tradeTransaction=new TradeTransactions();

				$trade=$this->getDoctrine()
					->getRepository('InvestShareBundle:Trade')
					->findOneBy(
						array(
							'id'=>$id
						)
					);

				$show=true;
				$showForm=3;
				$formTitle='Trade Sell Details';
   				break;
			}
   		}
		
/*
 * fetch all the companies and store in 2 separate array for name and code
 */
		$companies=array();
		$companyCodes=array();
		$sectors=array();

		$qb=$em->createQueryBuilder()
			->select('c.id')
			->addSelect('c.code')
			->addSelect('c.name')
			->addSelect('c.sector')
			->from('InvestShareBundle:Company', 'c')
			->orderBy('c.name');

		$results=$qb->getQuery()->getArrayResult();
		
		if (count($results)) {
			foreach ($results as $result) {
				if (strlen($result['name'])) {
					$companies[$result['id']]=$result['name'];
				}
				$companyCodes[$result['id']]=$result['code'];
				if (strlen($result['sector'])) {
					$sectors[$result['sector']]=$result['sector'];
				}
			}
		}
		if (count($sectors)) {
			ksort($sectors);
		}
/*
 * fetch all the portfolio names and store in an array
 */		
		$portfolios=array();

		$qb2=$em->createQueryBuilder()
			->select('p.id')
			->addSelect('p.name')
			->addSelect('p.clientNumber')
			->from('InvestShareBundle:Portfolio', 'p')
			->orderBy('p.name');
			
		if ($currentUser->getId()) {
			$qb2->andWhere('p.userId=:uId')
				->setParameter('uId', $currentUser->getId());
		}
		$results=$qb2->getQuery()->getArrayResult();
		
		if (count($results)) {
			foreach ($results as $result) {
				$portfolios[$result['id']]=$result['name'].' / '.$result['clientNumber'];
			}
		}
		
	
		if ($show) {
			switch ($showForm) {
				case 1 : {
/*
 * full form
 */
					$form=$this->createForm(new TradeType($trade, $portfolios, $companies));
			    	$form->handleRequest($request);
			    	
			    	$validator=$this->get('validator');
			    	$errors=$validator->validate($trade);
					
			    	if (count($errors) > 0) {
			    		$message=(string)$errors;
			    	} else {
			
			    		if ($form->isValid()) {

			    			switch ($action) {
			   					case 'add' : {
						    		$trade=$this->getDoctrine()
						    			->getRepository('InvestShareBundle:Trade')
						    			->findOneBy(
						    				array(
						    					'id'=>$form->get('id')->getData()
						    				)
						    		);
							   				
						   			if (!$trade) {
						   				$em = $this->getDoctrine()->getManager();
							    			
						   				$trade = new Trade();
						   				
							    		$trade->setPortfolioId($form->get('portfolioId')->getData());
							    		$trade->setCompanyId($form->get('companyId')->getData());
							    		$trade->setPERatio($form->get('pe_ratio')->getData());
							    			
							    		$em->persist($trade);
							    		$em->flush($trade);
								    			
							    		if ($trade->getId()) {
					   						$this->get('session')->getFlashBag()->add(
				   								'notice',
				   								'Data saved'
					   						);
					   						
					   						return $this->redirect($this->generateUrl('invest_share_trade'));
							    		}
						    		} else {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Trade already exists'
										);
					   						
										return $this->redirect($this->generateUrl('invest_share_trade'));
						    		}
						    		$show=false;
						    		break;
				   				}
			   					case 'edit' : {
/*
 * edit trade details
 */
				   					$em = $this->getDoctrine()->getManager();
				
				   					$trade->setCompanyId($form->get('companyId')->getData());
				   					$trade->setPortfolioId($form->get('portfolioid')->getData());
				   					$trade->setPERatio($form->get('pe_ratio')->getData());

				   					$em->flush();
				   					 
				   					if ($trade->getId()) {
				   						$this->get('session')->getFlashBag()->add(
			   								'notice',
			   								'Data updated'
				   						);
				   						
				   						return $this->redirect($this->generateUrl('invest_share_trade'));
				   					} else {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Saving problem'
										);
					   						
										return $this->redirect($this->generateUrl('invest_share_trade'));
				   					}
				   					$show=false;
				   					break;
			   					}
			   				}
			    		}
			    	}
			    	break;
				}
				case 2 : {
/*
 * 2nd form, only "Buy" details
 */
					$form2=$this->createForm(new TradeDetailsType($trade, $tradeTransaction, $portfolios, $companies, 'buy'));
			    	$form2->handleRequest($request);
			    	
			    	$validator=$this->get('validator');
			    	$errors=$validator->validate($tradeTransaction);
					
			    	if (count($errors) > 0) {
			    		$message=(string)$errors;
			    	} else {
			
			    		if ($form2->isValid()) {
				   			$em = $this->getDoctrine()->getManager();
				
				   			if (!$trade->getId()) {
				   				$trade->setCompanyId($form2->get('companyId')->getData());
				   				$trade->setPortfolioId($form2->get('portfolioId')->getData());
				   				$trade->setName($form2->get('reference')->getData());
				   				$em->persist($trade);
				   			}
				   			$em->flush();
				   			
				   			$tradeTransaction->setType($form2->get('type')->getData());
				   			$tradeTransaction->setTradeId($trade->getId());
				   			$tradeTransaction->setSettleDate($form2->get('settleDate')->getData());
				   			$tradeTransaction->setTradeDate($form2->get('tradeDate')->getData());
				   			$tradeTransaction->setReference($form2->get('reference')->getData());
				   			$tradeTransaction->setDescription($form2->get('description')->getData());
				   			$tradeTransaction->setUnitPrice($form2->get('unitPrice')->getData());
				   			$tradeTransaction->setQuantity($form2->get('quantity')->getData());
				   			$tradeTransaction->setCost($form2->get('cost')->getData());
				   			
				   			if (!$tradeTransaction->getId()) {
				   				$em->persist($tradeTransaction);
				   			}
				   			
				   			$em->flush($trade);
				   					 
		   					if ($tradeTransaction->getId()) {
		   						
		   						$this->get('session')->getFlashBag()->add(
	   								'notice',
	   								'Data updated'
		   						);
		   						
		   						return $this->redirect($this->generateUrl('invest_share_trade'));
		   					} else {
								$this->get('session')->getFlashBag()->add(
									'notice',
									'Saving problem'
								);
			   						
								return $this->redirect($this->generateUrl('invest_share_trade'));
		   					}
		   					$show=false;
			    		}
			    	}
			    	break;
				}
				case 3 : {
/*
 * 3rd form, only sell details
 */

					$form3=$this->createForm(new TradeDetailsType($trade, $tradeTransaction, $portfolios, $companies, 'sell'));
					$form3->handleRequest($request);
					
					$validator=$this->get('validator');
					$errors=$validator->validate($tradeTransaction);
						
					if (count($errors) > 0) {
						$message=(string)$errors;
					} else {
							
						if ($form3->isValid()) {

							switch ($action) {
			   					case 'addsell' : {
							    			
					   				$tradeTransaction=new TradeTransactions();
					   				$tradeTransaction->setType($form3->get('type')->getData());
					   				$tradeTransaction->setTradeId($form3->get('tradeId')->getData());
			   						$tradeTransaction->setSettleDate($form3->get('settleDate')->getData());
			   						$tradeTransaction->setTradeDate($form3->get('tradeDate')->getData());
			   						$tradeTransaction->setQuantity($form3->get('quantity')->getData());
			   						$tradeTransaction->setUnitPrice($form3->get('unitPrice')->getData());
			   						$tradeTransaction->setCost($form3->get('cost')->getData());
			   						$tradeTransaction->setReference($form3->get('reference')->getData());
			   						$tradeTransaction->setDescription($form3->get('description')->getData());

			   						$em->persist($tradeTransaction);
			   						
			   						$trade->setSold(true);

						    		$em->flush();
								    			
						    		if ($trade->getId()) {
				   						$this->get('session')->getFlashBag()->add(
			   								'notice',
			   								'Sell details updated'
				   						);
				   						
				   						return $this->redirect($this->generateUrl('invest_share_trade'));
						    		}
						    		$show=false;
						    		break;
				   				}
								case 'editsell' : {

			   						$NoOfDaysInvested=null;
			   						
			   						if ($form3->get('tradeDate')->getData()) {
			   							$date1=$trade->getBuyDate();
			   							$date2=$form3->get('tradeDate')->getData();
			   							$NoOfDaysInvested=$date2->diff($date1)->format("%a");
			   						}
			   						
			   						$tradeTransaction->setSettleDate($form3->get('settleDate')->getData());
			   						$tradeTransaction->setTradeDate($form3->get('tradeDate')->getData());
			   						$tradeTransaction->setQuantity($form3->get('quantity')->getData());
			   						$tradeTransaction->setUnitPrice($form3->get('unitPrice')->getData());
			   						$tradeTransaction->setCost($form3->get('cost')->getData());
			   						$tradeTransaction->setReference($form3->get('reference')->getData());
			   						$tradeTransaction->setDescription($form3->get('description')->getData());
			   						
			   						$trade->setSellDate($form3->get('tradeDate')->getData());
			   						$trade->setSellSettleDate($form3->get('settleDate')->getData());
			   						$trade->setSellPrice($form3->get('unitPrice')->getData());
			   						$trade->setSellCost($form3->get('cost')->getData());
			   						$trade->setSellQuantity($form3->get('quantity')->getData());
			   						$trade->setSellReference($form3->get('reference')->getData());
			   						$trade->setNoOfDaysInvested($NoOfDaysInvested);

						    		$em->flush();
								    			
						    		if ($trade->getId()) {
				   						$this->get('session')->getFlashBag()->add(
			   								'notice',
			   								'Sell details updated'
				   						);
				   						
				   						return $this->redirect($this->generateUrl('invest_share_trade'));
						    		}
						    		$show=false;
						    		break;
				   				}
							}
						}
					}
					break;
				}
			}
		}


		if (!$request->isMethod('POST') && null !== ($request->getSession()->get('is_trade'))) {
			$data=$request->getSession()->get('is_trade');
			$ok=true;
			if (isset($data['updated'])) {
		
				if ($data['updated'] < date('Y-m-d H:i:s', time()-$this->searchTime)) {
					$ok=false;
				}
			}
			if ($ok) {
				if (isset($data['c'])) {
					$searchCompany=$data['c'];
				}
				if (isset($data['p'])) {
					$searchPortfolio=$data['p'];
				}
				if (isset($data['sc'])) {
					$searchSector=$data['sc'];
				}
				if (isset($data['s'])) {
					$searchSold=$data['s'];
				}
				if (isset($data['df'])) {
					$searchDateFrom=$data['df'];
				} else {
					$searchDateFrom=null;
				}
				if (isset($data['dt'])) {
					$searchDateTo=$data['dt'];
				} else {
					$searchDateTo=null;
				}
			} else {
				$this->getRequest()->getSession()->remove('is_trade');
			}
			
		}
		
/*
 * filter by company or portfolio
 */
		$searchForm=$this->createForm(new TradeSearchType($this->generateUrl('invest_share_trade'), $companies, $searchCompany, $portfolios, $searchPortfolio, $sectors, $searchSector, $searchSold, $searchDateFrom, $searchDateTo));
		$searchForm->handleRequest($request);

		if ($request->isMethod('POST')) {
			$formData=$searchForm->getData();
			if (isset($formData['company']) && $formData['company']) {
				$searchCompany=$formData['company'];
			}
	    	if (isset($formData['portfolio']) && $formData['portfolio']) {
				$searchPortfolio=$formData['portfolio'];
			}
			if (isset($formData['sector']) && $formData['sector']) {
				$searchSector=$formData['sector'];
			}
			if (isset($formData['sold']) && $formData['sold']) {
				$searchSold=$formData['sold'];
			}
			if (isset($formData['dateFrom'])) {
				if (strlen($formData['dateFrom'])) {
					$searchDateFrom=$formData['dateFrom'];
				} else {
					$searchDateFrom=null;
				}
			}
			if (isset($formData['dateTo'])) {
				if (strlen($formData['dateTo'])) {
					$searchDateTo=$formData['dateTo'];
				} else {
					$searchDateTo=null;
				}
			}
				
			$this->getRequest()->getSession()->set('is_trade',
				array(
					'c'=>$searchCompany,
					'p'=>$searchPortfolio,
					's'=>$searchSold,
					'sc'=>$searchSector,
					'df'=>$searchDateFrom,
					'dt'=>$searchDateTo,
					'updated'=>date('Y-m-d H:i:s'
				)
			));
			return $this->redirect($this->generateUrl('invest_share_trade'));
		}
		
		
		if ($searchCompany || $searchPortfolio || $searchSector) {
			$find_array=array();
			if ($searchCompany) {
				$find_array=array_merge($find_array, array('companyId'=>$searchCompany));
			}
			if ($searchPortfolio) {
				$find_array=array_merge($find_array, array('portfolioId'=>$searchPortfolio));
			}
			if ($searchSector) {
				$find_array=array_merge($find_array, array('sector'=>$searchSector));
			}
			if ($searchSold) {
				$find_array=array_merge($find_array, array('sold'=>$searchSold));
			}
			if ($searchDateFrom) {
				$find_array=array_merge($find_array, array('date'=>$searchDateFrom));
			}
			if ($searchDateTo) {
				$find_array=array_merge($find_array, array('date'=>$searchDateTo));
			}
		}
		
/*
 * fetch all the dividends
 */		
		$results=$this->getDoctrine()
    		->getRepository('InvestShareBundle:Dividend')
    		->findAll();
    	
    	$dividends=array();
    	if (count($results)) {
    		foreach ($results as $result) {
    			$dividends[$result->getCompanyId()][]=$result;
    		}
    	}

		if ($show) {
			switch ($showForm) {
				case 1 : {
					$formView=$form->createView();
					break;
				}
				case 2 : {
					$formView=$form2->createView();
					break;
				}
				case 3 : {
					$formView=$form3->createView();
					break;
				}
			}
		} else {
/*
 * filters
 */

			$searchFormView=$searchForm->createView();
		}

		$combined=$functions->getTradesData($searchPortfolio, $searchCompany, $searchSector, $searchSold, $searchDateFrom, $searchDateTo, $currentUser->getId());
		
		if (in_array($searchSold, array(1,2)) && $searchDateFrom || $searchDateTo) {
			foreach ($combined as $k=>$v) {			
				if ($searchSold == 2) {
					// if sold
					// if sold reference is there
					// and search for from date earlier than specified
					// or to date later than specified, unset
					if ($v['reference2']
						&& (($searchDateFrom && $v['tradeDate2']->format('Y-m-d') < $searchDateFrom->format('Y-m-d'))
						|| ($searchDateTo && $v['tradeDate2']->format('Y-m-d') > $searchDateTo->format('Y-m-d'))))
						{
						unset($combined[$k]);
					}
					
				} else {
					// if unsold
					// if bought reference is there (should be always)
					// and search for from date earlier than specified
					// or to date later than specified, unset
					if ($v['reference1']
						&& (($searchDateFrom && $v['tradeDate1']->format('Y-m-d') < $searchDateFrom->format('Y-m-d'))
						|| ($searchDateTo && $v['tradeDate1']->format('Y-m-d') > $searchDateTo->format('Y-m-d'))))
						{
						unset($combined[$k]);
					}
				}
			}
		}
		
		$format = $this->get('request')->get('_format');
	
		switch ($format) {
			case 'pdf' : {
				$facade = $this->get('ps_pdf.facade');
				$response = new Response();
				$this->render('InvestShareBundle:Export:trade.pdf.twig', array(
					'name'			=> 'Trade',
					'trades'		=> $combined,
					'companies'		=> $companies,
					'companyCodes'	=> $companyCodes,
					'portfolios'	=> $portfolios,
					'currencyRates'	=> $functions->getCurrencyRates(),
					'dividends'		=> $dividends
	    			), $response);
				$xml = $response->getContent();
				$content = $facade->render($xml);
				return new Response($content, 200,
					array(
						'content-type' => 'application/pdf',
						'Content-Disposition'   => 'attachment; filename="trade.pdf"'
					)
				);
				break;
			}
			case 'csv' : {
				$response=$this->render('InvestShareBundle:Export:trade.csv.twig', array(
	    			'name'			=> 'Trade',
					'trades'		=> $combined,
					'companies'		=> $companies,
					'companyCodes'	=> $companyCodes,
					'portfolios'	=> $portfolios,
					'currencyRates'	=> $functions->getCurrencyRates(),
					'dividends'		=> $dividends
	    		));
				$filename = "trade_".date("Y_m_d_His").".csv";
				$response->headers->set('Content-Type', 'text/csv');
				$response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
				return $response;
				break;
			}
			default : {
				return $this->render('InvestShareBundle:Default:trade.html.twig', array(
					'showmenu'		=> true,
	    			'name'			=> 'Trade',
	    			'message'		=> $message,
					'errors'		=> $errors,
					'form'			=> ($show?$formView:null),
					'showForm'		=> ($show?$showForm:null),
					'searchForm'	=> ($show?null:$searchFormView),
					'formTitle'		=> $formTitle,
					'trades'		=> $combined,
					'companies'		=> $companies,
					'companyCodes'	=> $companyCodes,
					'portfolios'	=> $portfolios,
					'currencyRates'	=> $functions->getCurrencyRates(),
					'dividends'		=> $dividends,
					'notes'			=> $functions->getConfig('page_trade')
	    		));
				break;
			}
			
		}
    }

    
    public function portfolioAction($action, $id, $additional, Request $request) {

    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}
    	

/*
 * add/edit/delete portfolio details
 */

    	$currentUser=$this->getUser();
    	$functions=$this->get('invest.share.functions');
    	$message='';
		$showForm=1;
		$show=false;
		$formTitle='Portfolio Details';
		$errors=array();
		$searchPortfolio=null;
		$portfolio=new Portfolio();
		$portfolioTransaction=new PortfolioTransaction();
		
		$em = $this->getDoctrine()->getManager();		

		switch ($action) {
			case 'list' : {
				$searchPortfolio=$id;
				break;
			}
			case 'edit' : {
/*
 * edit portfolio, show the 1st form
 */				
				$portfolio=$this->getDoctrine()
					->getRepository('InvestShareBundle:Portfolio')
					->findOneBy(
						array(
							'id'=>$id
						)
					);
				if (!$portfolio) {
					$this->get('session')->getFlashBag()->add(
						'notice',
						'ID not found'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_portfolio'));
				}
				$show=true;
				break;
			}
			case 'adddebit' : {
/*
 * add debit/credit values, show the 2nd form
 */
				if ($additional) {
					$portfolio=$this->getDoctrine()
						->getRepository('InvestShareBundle:Portfolio')
						->findOneBy(
							array(
								'id'=>$additional
							)
						);
					if (!$portfolio) {
						$this->get('session')->getFlashBag()->add(
							'notice',
							'ID not found'
						);
					   						
						return $this->redirect($this->generateUrl('invest_share_portfolio'));
					}
				}
				$show=true;
				$showForm=2;
				break;
			}
			case 'editdebit' : {
/*
 * edit debit/credit value, show the 2nd form
 */
				if ($additional) {
					$portfolioTransaction=$this->getDoctrine()
						->getRepository('InvestShareBundle:PortfolioTransaction')
						->findOneBy(
							array(
								'id'=>$additional
							)
						);
					if (!$portfolioTransaction) {
						$this->get('session')->getFlashBag()->add(
							'notice',
							'ID not found'
						);
					   						
						return $this->redirect($this->generateUrl('invest_share_portfolio'));
					}
				}

				$show=true;
				$showForm=2;
				break;
			}
			case 'deletedebit' : {
/*
 * delete debit/credit value
 */				
				$portfolioTransaction=$this->getDoctrine()
					->getRepository('InvestShareBundle:PortfolioTransaction')
					->findOneBy(
						array(
							'id'=>$additional
						)
					);
		
				if ($portfolioTransaction) {

					$em->remove($portfolioTransaction);
					$em->flush();

					$this->get('session')->getFlashBag()->add(
						'notice',
						'Credit/Debit defails deleted'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_portfolio'));
				} else {
					$this->get('session')->getFlashBag()->add(
						'notice',
						'ID not found'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_portfolio'));
				}
				$show=false;
				break;
			}
			case 'delete' : {
/*
 * delete portfolio
 */				
				$portfolio=$this->getDoctrine()
					->getRepository('InvestShareBundle:Portfolio')
					->findOneBy(
						array(
							'id'=>$id
						)
					);
		
				if ($portfolio) {

					$em->remove($portfolio);
					$em->flush();

					$sm=$this->getDoctrine()
						->getRepository('InvestShareBundle:Summary')
						->findBy(
							array(
								'portfolioId'=>$id
							)
						);
					if ($sm) {
						foreach ($sm as $sm1) {
							$em->remove($sm1);
							$em->flush();
						}
						
					}
						
					
					$pt=$this->getDoctrine()
						->getRepository('InvestShareBundle:PortfolioTransaction')
						->findBy(
							array(
								'PortfolioId'=>$id
							)
						);
					if ($pt) {
						foreach ($pt as $pt1) {
							$em->remove($pt1);
							$em->flush();
						}
					}
						
					$trades=$this->getDoctrine()
						->getRepository('InvestShareBundle:Trade')
						->findBy(
							array(
								'portfolioId'=>$id
							)
						);
					if ($trades) {
						foreach ($trades as $trade) {
							$tt=$this->getDoctrine()
								->getRepository('InvestShareBundle:TradeTransactions')
								->findBy(
									array(
										'tradeId'=>$trade->getId()
									)
								);
							if ($tt) {
								foreach ($tt as $t1) {
									$em->remove($t1);
									$em->flush();
								}
							}
							$em->remove($trade);
							$em->flush();
						}
						
					}
					
					$this->get('session')->getFlashBag()->add(
						'notice',
						'Portfolio deleted'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_portfolio'));
				} else {
					$this->get('session')->getFlashBag()->add(
						'notice',
						'ID not found'
					);
				   						
					return $this->redirect($this->generateUrl('invest_share_portfolio'));
				}
				
				$portfolio=new Portfolio();
				$show=false;
				break;
			}
			case 'add' : {
/*
 * add portfolio/ show the 1st form
 */
				$show=true;
				break;
			}
		}
		

		switch ($showForm) {
			case 1 : {
/*
 * 1st form, add/edit portfolio
 */
				$form=$this->createForm(new PortfolioType($portfolio));
			    $form->handleRequest($request);
			    	
			    $validator=$this->get('validator');
			    $errors=$validator->validate($portfolio);
					
			    if (count($errors) > 0) {
			    	$message=(string)$errors;
			    } else {
			
			    	if ($form->isValid()) {
			   			switch ($action) {
			   				case 'add' : {
					    		$portfolios=$this->getDoctrine()
					    			->getRepository('InvestShareBundle:Portfolio')
					    			->findBy(
					    				array(
					    					'name'=>$form->get('name')->getData()
					    				)
						    		);
							   				
					   			if (count($portfolios) == 0) {
					   				
						    		$portfolio->setName($form->get('name')->getData());
						    		$portfolio->setClientNumber($form->get('clientNumber')->getData());
						    		$portfolio->setStartAmount(0);
						    		$portfolio->setfamily($form->get('family')->getData());
						    		$portfolio->setUserId($currentUser->getId());
						    		
						    		$em->persist($portfolio);
						    		$em->flush();
								    			
						    		if ($portfolio->getId()) {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Portfolio data saved'
										);
									   						
										return $this->redirect($this->generateUrl('invest_share_portfolio'));
						    		}
					    		} else {
									$this->get('session')->getFlashBag()->add(
										'notice',
										'Portfolio already exists'
									);

									return $this->redirect($this->generateUrl('invest_share_portfolio'));
					    		}
					    		$show=false;
					    		break;
							}
			   				case 'edit' : {

			   					$portfolio->setName($form->get('name')->getData());
			   					$portfolio->setClientNumber($form->get('clientNumber')->getData());
			   					$portfolio->setStartAmount(0);
			   					$portfolio->setFamily($form->get('family')->getData());
			   					 
								$em->flush();
				   					 
								if ($portfolio->getId()) {
									$this->get('session')->getFlashBag()->add(
										'notice',
										'Portfolio updated'
									);
								   						
									return $this->redirect($this->generateUrl('invest_share_portfolio'));
								} else {
									$this->get('session')->getFlashBag()->add(
										'notice',
										'Saving problem'
									);
								   						
									return $this->redirect($this->generateUrl('invest_share_portfolio'));
								}
								$show=false;
								break;
			   				}
			   			}
			    	}
				break;
				}
			}
			case 2 : {
/*
 * 2nd form, add/edit transaction
*/
				
				$formTitle='Debit/Credit Details';
				$form2=$this->createForm(new PortfolioCreditDebitType($portfolioTransaction));
				$form2->handleRequest($request);
					
				$validator=$this->get('validator');
				$errors=$validator->validate($portfolioTransaction);
						
				if (count($errors)) {
					$message=(string)$errors;
				} else {
			    	if ($form2->isValid()) {
			    		switch ($action) {
			   				case 'adddebit' : {

			   					$portfolioTransaction=$this->getDoctrine()
					    			->getRepository('InvestShareBundle:PortfolioTransaction')
					    			->findBy(
					    				array(
					    					'id'=>$form2->get('id')->getData()
					    				)
					    		);
							   				
					   			if (!$portfolioTransaction) {
					   				
					   				$pt=new PortfolioTransaction();
					   				$pt->setPortfolioId($additional);
					   				$pt->setDate($form2->get('date')->getData());
						    		$pt->setAmount($form2->get('amount')->getData());
						    		$pt->setReference($form2->get('reference')->getData());
						    		$pt->setDescription($form2->get('description')->getData());
						    		
						    		$em->persist($pt);
						    		$em->flush($pt);
								    			
						    		if ($pt->getId()) {
										$this->get('session')->getFlashBag()->add(
											'notice',
											'Data saved'
										);
									   						
										return $this->redirect($this->generateUrl('invest_share_portfolio'));
						    		}
					    		} else {
									$this->get('session')->getFlashBag()->add(
										'notice',
										'Portfolio transaction already exists'
									);
								   						
									return $this->redirect($this->generateUrl('invest_share_portfolio'));
					    		}
					    		$show=false;
					    		break;
							}
			   				case 'editdebit' : {

								$portfolioTransaction->setPortfolioId($form2->get('portfolioid')->getData());
								$portfolioTransaction->setDate($form2->get('date')->getData());
								$portfolioTransaction->setAmount($form2->get('amount')->getData());
								$portfolioTransaction->setReference($form2->get('reference')->getData());
								$portfolioTransaction->setDescription($form2->get('description')->getData());
								
								$em->flush($portfolioTransaction);
				   					 
								if ($portfolioTransaction->getId()) {
									$this->get('session')->getFlashBag()->add(
										'notice',
										'Credit/Debit details updated'
									);
								   						
									return $this->redirect($this->generateUrl('invest_share_portfolio'));
								} else {
									$this->get('session')->getFlashBag()->add(
										'notice',
										'Saving problem'
									);
								   						
									return $this->redirect($this->generateUrl('invest_share_portfolio'));
								}
								$show=false;
								break;
			   				}
			   			}
			    	}
				}
				break;
			}
    	}
/*
 * query for portfolio table with calculated values
 */
    	$trades=$functions->getTradesData($searchPortfolio, null, null, null, null, null, $currentUser->getId());
    	$portfolios=array();
    	if (count($trades)) {
    		
    		$currencyRates=$functions->getCurrencyRates();    	

    		foreach ($trades as $trade) {
    			if (!isset($portfolios[$trade['portfolioId']])) {
    				$portfolios[$trade['portfolioId']]=array(
    					'Investment'=>0,
    					'Dividend'=>0,
    					'startAmount'=>0,
    					'DividendPaid'=>0,
    					'Profit'=>0,
    					'StockValue'=>0,
    					'Cost'=>0
    				);
    			}

    			$portfolios[$trade['portfolioId']]['id']=$trade['portfolioId'];
    			$portfolios[$trade['portfolioId']]['name']=$trade['portfolioName'];
    			$portfolios[$trade['portfolioId']]['clientNumber']=$trade['clientNumber'];
/*
 * calculate the dividend with the current currency exchange rate
 */
    			$portfolios[$trade['portfolioId']]['Dividend']+=$trade['Dividend']/(($trade['Currency'] == 'GBP')?(1):($currencyRates[$trade['Currency']]/100));
    			$portfolios[$trade['portfolioId']]['Cost']+=$trade['cost1']+$trade['cost2'];
    			$portfolios[$trade['portfolioId']]['DividendPaid']+=$trade['DividendPaid']/(($trade['Currency'] == 'GBP')?(1):($currencyRates[$trade['Currency']]/100));

    			if ($trade['reference2'] != '') {
/*
 * Sold
 */
    				$profit=(($trade['quantity2']*$trade['unitPrice2']/100-$trade['cost2'])-($trade['quantity1']*$trade['unitPrice1']/100+$trade['cost1']));
    				$portfolios[$trade['portfolioId']]['startAmount']+=$profit;
    				$portfolios[$trade['portfolioId']]['Profit']+=$profit;
    			} else {
/*
 * Unsold
 */
    				$portfolios[$trade['portfolioId']]['Investment']+=$trade['quantity1']*$trade['unitPrice1']/100+$trade['cost1'];
    				$portfolios[$trade['portfolioId']]['StockValue']+=$trade['quantity1']*$trade['lastPrice']/100;
    			}
    			
    		}
    	}

    	$results=$this->getDoctrine()
    		->getRepository('InvestShareBundle:PortfolioTransaction')
    		->findBy(
    			array(),
    			array(
    				'date'=>'ASC'
    			)
    		);
/*
 * collect all the transactions based on portfolio id
 */
    	$transactions=array();
    	if (count($results)) {
    		foreach ($results as $result) {
    			$transactions[$result->getPortfolioId()][]=array(
    				'id'=>$result->getId(),
    				'amount'=>$result->getAmount(),
    				'date'=>$result->getDate(),
    				'reference'=>$result->getReference(),
    				'description'=>$result->getDescription()
    			);
    		}
    	}

		return $this->render('InvestShareBundle:Default:portfolio.html.twig', array(
    		'name' => 'Portfolio',
    		'message' => $message,
			'errors' => $errors,
			'form' => (($show && $showForm==1)?($form->createView()):(null)),
			'form2' => (($show && $showForm==2)?($form2->createView()):(null)),
			'formTitle' => $formTitle,
			'portfolios' => $portfolios,
			'transactions' => $transactions
    	));
    }

    
    public function menuAction() {
/*
 * menu links
 */
    	$links=array();
    	
    	$links[]=array('name'=>'Summary', 'url'=>$this->generateUrl('invest_share_homepage'));
    	$links[]=array('name'=>'Company', 'url'=>$this->generateUrl('invest_share_company'));
    	$links[]=array('name'=>'Dividend', 'url'=>$this->generateUrl('invest_share_dividend'));
    	$links[]=array('name'=>'Directors\' Deals', 'url'=>$this->generateUrl('invest_share_ddeals'));
    	$links[]=array('name'=>'Financial Diary', 'url'=>$this->generateUrl('invest_share_diary'));
    	$links[]=array('name'=>'Portfolio', 'url'=>$this->generateUrl('invest_share_portfolio'));
    	$links[]=array('name'=>'Trade', 'url'=>$this->generateUrl('invest_share_trade', array('_format'=>'html')));
    	$links[]=array('name'=>'Pricelist', 'url'=>$this->generateUrl('invest_share_pricelist'));
    	$links[]=array('name'=>'Currency', 'url'=>$this->generateUrl('invest_share_currency'));
    	if ($this->get("security.context")->isGranted('ROLE_MANAGER')) {
    		$links[]=array('name'=>'Update', 'url'=>$this->generateUrl('invest_share_update'));
    	}
    	if ($this->get("security.context")->isGranted('ROLE_ADMIN')) {
    		$links[]=array('name'=>'Users', 'url'=>$this->generateUrl('invest_share_users'));
    	} else {
    		$links[]=array('name'=>'Password', 'url'=>$this->generateUrl('invest_share_changepassword'));
    	}

		return $this->render('InvestShareBundle:Default:menu.html.twig', array(
   			'links' => $links,
    	));
    }

    
    public function updatedividendAction() {

    	$message='';
    	$debug_message='';
    	$lines=array();
    	$cell=array();
    	$complete=array();

    	$html_sources=array();
    	$html_host='www.upcomingdividends.co.uk';
//    	$html_host='95.142.159.11';
    	$html_sources[]='http://'.$html_host.'/exdividenddate.py?m=ftse100';
    	$html_sources[]='http://'.$html_host.'/exdividenddate.py?m=ftse250';
    	 
    	$count=count($html_sources);
    	 
		if ($count) {
	    	for ($i=0; $i < $count; $i++) {
	    		try {
	    			$ch=curl_init();
	    			curl_setopt($ch, CURLOPT_URL, $html_sources[$i]);
	    			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
	    			$rss_result=curl_exec($ch);
	    			curl_close($ch);
	    			
	    		} catch(Exception $e) {
	    			$message.='error:'.$e->getMessage();
	    			$rss_result='';
	    		}
/*
 * delete everything before and after the neccessary data then clear the remaining content
 */
	    		if (strlen($rss_result)) {
	    			$pos1=strpos($rss_result, '<table class="mainTable sortable">');
		    		$rss_result=substr($rss_result, $pos1, strlen($rss_result));

		    		$pos2=strpos($rss_result, '</table>');
					$rss_result=substr($rss_result, 0, $pos2);
		    		$rss_result=str_replace(array(chr(9), chr(10), chr(13)), '', $rss_result);

		    		$rss_result=str_replace('&nbsp;', '', $rss_result);

    				preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $rss_result, $lines);
	    				
    				$result = array();
	    				
    				foreach ($lines[1] as $k => $line) {
    					preg_match_all('#<td[^>]*>(.*?)</td>#is', $line, $cell);
	    				
    					foreach ($cell[1] as $cell) {
    						$c1=preg_replace('#<a[^>]*>(.*?)</a>#is', '', $cell);
    						$result[$k][] = trim($c1);
    					}
    				}
	    				
    				if (count($result) == 1) {
/*
 * if the html code still in wrong format (missing </tr> tag
 */	    				
    					foreach ($result as $values) {
    						$k=0;
    						while (count($values)>=9 && $k < count($values)) {

    							$declarationDate=strtotime($values[$k+7]);
								if ($declarationDate > time()) {
									$declarationDate=strtotime($values[$k+7].(date('Y')-1));
								}
								$exDivDate=strtotime($values[$k+8]);
								if ($exDivDate < $declarationDate) {
									$exDivDate=strtotime($values[$k+8].(date('Y')+1));
								}
    							$paymentDate=strtotime($values[$k+9]);
								if ($paymentDate < $exDivDate) {
									$paymentDate=strtotime($values[$k+9].(date('Y')+1));
								}
								$currency='GBP';
								if (strpos($values[$k+5], '$') !== false) {
									$currency='USD';
								}
    							if (strpos($values[$k+5], '€') !== false) {
									$currency='EUR';
								}
								$price=preg_replace('/[^0-9\.]+/', '', $values[$k+5]);
								$price=sprintf('%.06f', $price);

								$special=(strpos($values[$k+6], '*') !== false);
								
								$complete[]=array(
    								'Code'=>$values[$k],
    								'Name'=>$values[$k+2],
    								'Price'=>$price,
    								'DeclarationDate'=>date('Y-m-d', $declarationDate),
    								'ExDivDate'=>date('Y-m-d', $exDivDate),
    								'PaymentDate'=>date('Y-m-d', $paymentDate),
									'Currency'=>$currency,
									'Special'=>$special
    							);
	    							
    							$k += 10;
    						}
    					}
    				} elseif (count($result) > 1) {
/*
 * if the html code format is correct
 */
    					foreach ($result as $value) {
							$datePosition=-1;
							$p=0;
							while ($datePosition == -1 && $p<=count($value)) {
								if (strpos($value[$p], '%') !== false) {
									$datePosition=$p+1;
								}
								$p++;
							}
							if ($datePosition != -1) {
	    						$declarationDate=strtotime($value[$datePosition]);
	    						if ($declarationDate > time()) {
	    							$declarationDate=strtotime($value[$datePosition].' '.(date('Y')-1));
	    						}
	    						$exDivDate=strtotime($value[$datePosition+1]);
	    						if ($exDivDate < $declarationDate) {
	    							$exDivDate=strtotime($value[$datePosition+1].' '.(date('Y')+1));
	    						}
								if (isset($value[$datePosition+2])) {
	    							$paymentDate=strtotime($value[$datePosition+2]);
	    							if ($paymentDate < $exDivDate) {
	    								$paymentDate=strtotime($value[$datePosition+2].' '.(date('Y')+1));
	    							}
	    						} else {
	    							$paymentDate=0;
	    						}

	    						$currency='GBP';
								if (isset($value[$k+5]) && strpos($value[$k+5], '$') !== false) {
									$currency='USD';
								}
	    						if (isset($value[$k+5]) && strpos($value[$k+5], '€') !== false) {
									$currency='EUR';
								}
	    						$price=preg_replace('/[^0-9\.]+/', '', $value[4]);
	    						$price=sprintf('%.06f', $price);
	    								
	    						$special=(isset($value[$k+6]) && strpos($values[$k+6], '*') !== false);
	    						
	    						$complete[]=array(
	    							'Code'=>$value[0],
	    							'Name'=>$value[2],
	    							'Price'=>$price,
	    							'DeclarationDate'=>date('Y-m-d', $declarationDate),
	    							'ExDivDate'=>date('Y-m-d', $exDivDate),
	    							'PaymentDate'=>date('Y-m-d', $paymentDate),
	    							'Currency'=>$currency,
	    							'Special'=>$special
	    						);
							}    					
    					}
    						
    				}
	    		} else {
	    			$message='No dividend data';
	    		}
	    	}
		}
/*
 * update database with downloaded data
 */
		if (count($complete)) {

			$em=$this->getDoctrine()->getManager();
			
			foreach ($complete as $k=>$v) {

				$company=$this->getDoctrine()
					->getRepository('InvestShareBundle:Company')
					->findOneBy(
						array(
							'code'=>$v['Code']
						)
					);
    	 
				if ($company) {
					$dividend=$this->getDoctrine()
						->getRepository('InvestShareBundle:Dividend')
						->findOneBy(
							array(
								'companyId'=>$company->getId(),
								'exDivDate'=>new \DateTime($v['ExDivDate'])	
							)
						);    			

					if (!$dividend) {

						$dividend=new Dividend();
					
						$dividend->setCompanyId($company->getId());
						$dividend->setAmount($v['Price']);
						$dividend->setExDivDate(new \DateTime($v['ExDivDate']));
						$dividend->setPaymentDate(new \DateTime($v['PaymentDate']));
						$dividend->setDeclDate(new \DateTime($v['DeclarationDate']));
						$dividend->setSpecial($v['Special']);
						$dividend->setCreatedDate(new \DateTime('now'));
						
						$em->persist($dividend);
						
						$em->flush();

					}
				}
			}
		}
		
		return $this->render('InvestShareBundle:Default:dividendlist.html.twig', array(
			'showmenu'		=> false,
			'data'			=> $complete,
			'message'		=> $message,
			'debug_message'	=> $debug_message
		));
	}
    
	
	public function updatedealsAction($page) {

		$functions=$this->get('invest.share.functions');
		
		$message='';
		$lines=array();
		$cell=array();
		$urls=array();
		$deals=array();

		$html_host='www.directorsholdings.com';
//    	$html_host='193.243.128.75';
		
		$ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => 1
				)
			)
		);
		

/*
 * History download for last month
 */
		$url='http://'.$html_host.'/search/getTableData/?sEcho=3&iColumns=20&sColumns=&iDisplayStart=0&iDisplayLength=2000&iSortingCols=1&iSortCol_0=1&iSortDir_0=desc&dateFrom='.date('d-m-Y', mktime(0, 0, 0, date('m')-1, date('d'), date('Y'))).'&dateTo='.date('d-m-Y').'&transType=0&epic=&subSector=0&index=UKX%2CMCX%2CSMX&directorName=&directorId=&directorPosition=0&numSharesMin=&numSharesMax=&valueMin=&valueMax=';

		$rss_result=@file_get_contents($url, 0, $ctx);
			
		if ($rss_result !== false && strlen($rss_result)) {
			$pos1=strpos($rss_result, '<div id="container-inner">')+26;
			$rss_result=substr($rss_result, $pos1, strlen($rss_result));
			$pos2=strpos($rss_result, '</div>');
			$rss_result=substr($rss_result, 0, $pos2);
				
			$data=json_decode($rss_result);
				
			if (isset($data->aaData) && count($data->aaData)) {
				foreach ($data->aaData as $d) {
					$date1=\DateTime::createFromFormat('d M, Y H:i:s', $d[1].' 00:00:00');
					$d1=array(
						'declDate'=>$date1,
						'dealDate'=>$date1,
						'type'=>$d[2],
						'code'=>$d[3],
						'Company'=>$d[4],
						'name'=>$d[7],
						'position'=>$d[8],
						'shares'=>str_replace(',', '', $d[9]),
						'price'=>str_replace(',', '', $d[10]),
						'value'=>str_replace(',', '', $d[11])
					);
					if ($functions->addDirectorsDeals($d1)) {
						$deals[]=$d1;
					}
				}
			}
		}

/*
 * Current list download
 */

		$url_tmpl='http://'.$html_host.'/index/getData/?type=ALL&page=::PAGE::&epic=';

		$urls[0]='http://'.$html_host.'/index/getData/?type=ALL&page=0&epic=';

		$count=count($urls);
				
		for ($i=0; $i < $count; $i++) {
		
			$rss_result=@file_get_contents($urls[$i], 0, $ctx);
			
			$pn=trim(substr($rss_result, strpos($rss_result, 'current-page')+24, 5));

			if ($pn > 2 && !$page) {
				$pn=2;
			}
			for ($j=1; $j<$pn; $j++) {
				if (!isset($urls[$j])) {
					if (!$page || $page==$j) {
						$urls[$j]=str_replace('::PAGE::', $j, $url_tmpl);
					}
				}
			}
			$count=count($urls);
			
			/*
			 * delete everything before and after the neccessary data then clear the remaining content
			*/
			if ($rss_result !== false && strlen($rss_result)) {
				
				$pos1=strpos($rss_result, '<table id="director-deals" class="full" cellpadding="0" cellspacing="2">');
		    	$rss_result=substr($rss_result, $pos1, strlen($rss_result));

		    	$pos2=strpos($rss_result, '</table>');
				$rss_result=substr($rss_result, 0, $pos2);

				$rss_result=str_replace(array(chr(9), chr(10), chr(13), '&nbsp;', '&pound;'), '', $rss_result);

    			preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $rss_result, $lines);

    			$result = array();
	    				
    			foreach ($lines[1] as $k => $line) {
    				preg_match_all('#<td[^>]*>(.*?)</td>#is', $line, $cell);
/*
 * we need all the full lines, if broken line, ignore
 */    				
    				if (count($cell[1]) >= 10) {
		    				
	    				foreach ($cell[1] as $kcell=>$c1) {
	    					$c1=preg_replace('#<a[^>]*>#is', '', $c1);
	    					$c1=preg_replace('</a>', '', $c1);
	    					$c1=str_replace(array('<>'), '', $c1);
/*
 * remove comma from numberical values
 */
	    					if (in_array($kcell, array(8, 9, 10))) {
	    						$c1=str_replace(',', '', $c1);
	    					}
	    					$result[$k][] = trim($c1);
	    				}
	   				}
    			}
    			    
    			if (count($result)) {
    				foreach ($result as $d) {
    					$date1=\DateTime::createFromFormat('d/m/Y H:i:s', $d[1].' 00:00:00');
    					$date2=\DateTime::createFromFormat('d/m/Y H:i:s', $d[2].' 00:00:00');
    					$d1=array(
    						'declDate'=>$date1,
    						'dealDate'=>$date2,
    						'type'=>$d[3],
    						'code'=>$d[4],
    						'Company'=>$d[5],
    						'name'=>$d[6],
    						'position'=>$d[7],
    						'shares'=>$d[8],
    						'price'=>$d[9],
    						'value'=>$d[10]
    					);
    					if ($functions->addDirectorsDeals($d1)) {
    						$deals[]=$d1;
    					}
    				}
    			}
			}			 
		}
		
		
		return $this->render('InvestShareBundle:Default:directordeals.html.twig', array(
			'showmenu'	=> false,
			'deals'		=> $deals,
			'message'	=> $message,
			'notes'		=> $functions->getConfig('note_deals')
		));
	}
	
    
	public function updatediaryAction($date) {

		$functions=$this->get('invest.share.functions');
		$message='';
		$lines=array();
		$cell=array();
		$diary=array();
		$links=array();
		$urls=array();
		$result = array();
		
		$html_host='www.lse.co.uk';
    	$html_host_ip='217.158.94.230';
		
		$ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => 1
				)
			)
		);

		if ($date != '') {

			$dates=explode('|', $date);
			if (strpos($date, '|') !== false && count($dates) == 2) {
				$d=mktime(0, 0, 0, date('m', strtotime($dates[0])), date('d', strtotime($dates[0])), date('Y', strtotime($dates[0])));
				$d2=mktime(0, 0, 0, date('m', strtotime($dates[1])), date('d', strtotime($dates[1])), date('Y', strtotime($dates[1])));

				while ($d <= $d2) {
					$s='http://'.$html_host.'/financial-diary.asp?date='.date('j-M-Y', $d);
					$urls[]=$s;
					$d=mktime(0, 0, 0, date('m', $d), date('d', $d)+1, date('Y', $d));
				}
				$d=$d2;
				
			} else {
				$d=strtotime($date);

				$urls[]='http://'.$html_host.'/financial-diary.asp?date='.date('j-M-Y', $d);
			}
		
		} else {

			$d=time();
			for ($i=-1; $i<8; $i++) {
				$d1=mktime(0, 0, 0, date('m'), date('d')+$i, date('Y'));
				$urls[]='http://'.$html_host.'/financial-diary.asp?date='.date('j-M-Y', $d1);
			}

		}
		
		for ($i=-3; $i<4; $i++) {
			$d1=mktime(0, 0, 0, date('m', $d), date('d', $d)+$i, date('Y', $d));
			$links[date('Y-m-d', $d1)]=array('selected'=>($i==0), 'date'=>date('d/m/Y', $d1));
		}
		
/*
 * Download and store the current day's financial diary + 1 week
 */

		foreach ($urls as $url) {
			$try=0;
			$rss_result='';
			
			while ($try < $this->updateRetry && strpos($rss_result, 'financialDiaryTable') === false) {

				try {
	 	  			$rss_result=@file_get_contents($url, 0, $ctx);
	   			} catch(Exception $e) {
	   				$message.='error:'.$e->getMessage();
	   				$rss_result='';
	   			}
	   			$try++;
	   			if ($try >= $this->updateRetry && strpos($url, $html_host) !== false) {
	   				$try=0;
	   				$url=str_replace($html_host, $html_host_ip, $url);
	   			}
			}

	   		$pos1=strpos($rss_result, 'class="financialDiaryTable" align="left">')+41;
			$rss_result=substr($rss_result, $pos1, strlen($rss_result));
	
			$pos2=strpos($rss_result, '</table>');
			$rss_result=substr($rss_result, 0, $pos2);
			
			$rss_result=str_replace(array(chr(9), chr(10), chr(13), '&nbsp;', '&pound;'), '', $rss_result);
	
	    	preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $rss_result, $lines);
	
	    	$type='';
		    				
	    	foreach ($lines[0] as $line) {
	
				if (count($line) == 1 && strpos($line, '<h3>') > 0) {
/*
 * remove all unneccessary elements and store the highlighted type of event 
 */
					$type=str_replace(array('<h3>', '</h3>', '<td colspan="3">', '</td>', '<tr>', '</tr>'), '', $line);
				}
	    		preg_match_all('#<td[^>]*>(.*?)</td>#is', $line, $cell);
/*
 * we need all the full lines, if broken line, ignore
 */    				
				if (count($cell)) {
					foreach ($cell as $kc=>$vc) {
						$vc=preg_replace('#<a[^>]*>#is', '', $vc);
						$vc=preg_replace('</a>', '', $vc);
						$vc=str_replace(array('<>'), '', $vc);
						$cell[$kc]=$vc;
					}
				}
	    		if (count($cell) == 2 && count($cell[1]) == 3) {
/*
 * we need only the events, without announcements
 */
	    			if (strlen(trim($cell[1][2]))) {
		    			$result=array(
							'Type'=>$type,
							'Date'=>\DateTime::createFromFormat('d-M-Y H:i:s', $cell[1][0].' 00:00:00'),
							'Name'=>trim($cell[1][1]),
							'Code'=>trim($cell[1][2])
						);
		    			if ($functions->addFinancialDiary($result)) {
		    				$diary[]=$result;
		    			}
					}
				}			 
			}
		}
		
		
		return $this->render('InvestShareBundle:Default:diarylist.html.twig', array(
			'showmenu'	=> false,
			'links'		=> $links,
			'diary'		=> $diary,
			'message'	=> $message,
			'notes'		=> $functions->getConfig('note_diary')
		));
	}
	
	
	public function updatepricesAction($freq, $part) {
/*
 * update function with automatic update frequency option
 */
    	$ts=date('Y-m-d H:i:s');
/*
 * urls for update
 */    	
    	$html_sources=array();
    	$list_sources=array();
    	
    	$html_host='shares.telegraph.co.uk';
    	$html_host_ip='193.243.128.86';

    	if (!$part || $part==1) {
    		$html_sources[]='http://'.$html_host.'/indices/prices/index/UKX';
    		$list_sources[]='FTSE100';
    	}
    	if (!$part || $part==2) {
    		$html_sources[]='http://'.$html_host.'/indices/prices/index/MCX';
    		$list_sources[]='FTSE250';
    	}
    	if (!$part || $part==3) {
    		$html_sources[]='http://'.$html_host.'/indices/prices/index/SMX';
    		$list_sources[]='FTSESmallCap';
    	}
    	 
    	$count=count($html_sources);
    	
    	$message='';
    	$msg=array();
    	$debug_message='';
    	$new_company=0;
    	$updated_prices=0;
    	$updated_averages=0;
    	$completed=array();
		$data1=array();
		$new_data=array();
		
		$em=$this->getDoctrine()->getManager();
/*
 * check the last update time
 */		
		$latestDate=new \DateTime("now");
		$qb=$em->createQueryBuilder()
			->select('MAX(sp.date) as date')
			->from('InvestShareBundle:StockPrices', 'sp');
		$results=$qb->getQuery()->getArrayResult();
		$result=reset($results);
		$latestDate=new \DateTime($result['date']);

    	if ($freq) {
			$this->refresh_interval=(int)$freq;
		} else {
			$this->refresh_interval=0;
		}
		
		$remains=($this->refresh_interval+$latestDate->getTimestamp())-strtotime($ts);
		
/*
 * if no frequency defined in the url or the last update was run more than the specified seconds ago, can run the script 
 */		
		if ($freq=='' || $freq<=0 || (strtotime($ts) > ($this->refresh_interval+$latestDate->getTimestamp()))) {
			$ctx = stream_context_create(array(
				'http' => array(
					'timeout' => 1
					)
				)
			);

	    	for ($i=0; $i < $count; $i++) {

	    		$try=0;
	    		unset($new_data);
	    		$new_data=array();
	    		
	    		while ($try < $this->updateRetry && !count($new_data)) {

	    			$rss_result=@file_get_contents($html_sources[$i], 0, $ctx);
/*
 * delete everything before and after the neccessary data then clear the remaining content
 */
		    		if ($rss_result !== false) {
			    		$pos1=strpos($rss_result, 'prices-table">')+14;
			    		$rss_result=substr($rss_result, $pos1, strlen($rss_result));
						$pos2=strpos($rss_result, '<tbody>')+7;
						$rss_result=substr($rss_result, $pos2, strlen($rss_result));
						$pos3=strpos($rss_result, '</tbody>');
						$rss_result=substr($rss_result, 0, $pos3);
			    		$rss_result=str_replace(chr(9), '', $rss_result);
		    			
			    		$new_data=explode('</tr>', $rss_result);
		    		} else {
		    			$new_data=null;
		    		}
		    		$try++;
		    		if ($try >= $this->updateRetry && !count($new_data) && strpos($html_sources[$i], $html_host)) {
		    			$try=0;
		    			$html_sources[$i]=str_replace($html_host, $html_host_ip, $html_sources[$i]);
		    		}
	    		}
	    		
				
	    		if (count($new_data)) {
	    			foreach ($new_data as $v) {
	    				$v=str_replace(array('<tr>', '<tr class="odd">', '<tr class="even">', '</tr>'), '', $v);
	    				$data1=explode('</td>', $v);
	    				if (count($data1) > 6) {
	    					foreach ($data1 as $k1=>$v1) {
								switch ($k1) {
									case 0 :
									case 1 : {
										$v1=str_replace('</a>', '', $v1);
										$v1=trim(substr($v1, strpos($v1, '">')+2, strlen($v1)));
										break;
									}
									case 2 :
									case 3 :
									case 4 :
									case 5 :
									case 6 : {
										$v1=preg_replace('/[^0-9.\-]+/', '', $v1);
										$v1=trim(str_replace('--', '-', $v1));
										break;
									}
								}
	    						
	    						$data1[$k1]=$v1;
	    					}
/*
 * store only the necessary data
 */

		    				if ($data1[2] && $data1[2]!='#N/A') {
		    					$completed[$data1[0]]=array(
									'Name'=>$data1[1],
									'Code'=>$data1[0],
									'Date'=>new \DateTime($ts),
									'Price'=>$data1[2],
									'Changes'=>$data1[3],
		    						'List'=>$list_sources[$i],
		    						'lastDayAverage'=>0,
		    						'lastWeekAverage'=>0,
		    						'lastmonthAverage'=>0,
		    						'newPrice'=>0
		    					);
		    				}
	    				}
	    			}
	    		} else {
/*
 * message if the data incorrect
 */
	    			$message.='No data from source : '.$html_sources[$i];
	    		}
	    		
	    	}

/*
 * if we have final data, check the existing data in the database
 */			
	    	if (count($completed)) {
		    	foreach($completed as $key=>$value) {
		    		if ($value['Price'] && $value['Price']!='#N/A') {
				    	$result=$this->getDoctrine()
					    	->getRepository('InvestShareBundle:StockPrices')
					    	->findOneBy(
					    		array(
					    			'code'=>$value['Code']
					    		),
					    		array('date'=>'DESC')
				    		);

				    	if ((!$result) || $result->getPrice() != $value['Price']) {

							$ok=true;
				    		if ($result && count($result)) {
/*
 * if we have already data, store the changes since last stored
 */								
				    			$value['Changes']=sprintf('%.4f', $value['Price']-$result->getPrice());
				    			$completed[$key]['Changes']=$value['Changes'];
				    			
				    			if ($value['Changes'] > 0) {
				    				$diff=$value['Changes'] / $value['Price'];
				    			} else {
				    				$diff=sprintf('%.4f', $result->getPrice()-$value['Price']) / $result->getPrice();
				    			}
				    			
				    			if ($value['Price']<=0 || ($diff > ($this->maxChanges/100))) {

				    				if ($value['Price'] > 0) {
				    					$lp=$this->getDoctrine()
				    						->getRepository('InvestShareBundle:StockPrices')
				    						->findBy(
				    							array(
				    								'code'=>$value['Code']
				    							),
				    							array(
				    								'date'=>'DESC'
				    							),
				    							1,
				    							1
				    						);
/*
 * If too much difference between the new and last price, check the previous.
 * If the previous similar as the new, delete the last and store the new,
 * anyway the new price should be wrong
 */
				    					$value['Changes']=sprintf('%.4f', $value['Price']-((isset($lp[0]))?($lp[0]->getPrice()):(0)));
				    					$completed[$key]['Changes']=$value['Changes'];
				    					 
				    					if ($value['Changes'] > 0) {
				    						$diff=$value['Changes'] / $value['Price'];
				    					} else {
				    						$diff=sprintf('%.4f', $lp[0]->getPrice()-$value['Price']) / $lp[0]->getPrice();
				    					}
				    					 
				    					if ($diff > ($this->maxChanges/100)) {
				    						$ok=false;
				    						if (isset($lp[0]) && round($lp[0]->getPrice()) == 0) {
				    							$ok=true;
				    						}
				    					} else {
				    						$msg[]='[remove previous wrong price for '.$value['Code'].'] ';
				    					
				    						$em->remove($result);
				    						$em->flush();
				    						 
				    						$ok=true;
				    					}
				    					 
				    				} else {
			    						$ok=false;
				    				}
				    			}
				    		}
				    		
				    		
/*
 * if new data or changed since last time, store as new
 */

				    		$d=$value['Date'];
				    		if ($ok) {
					    		$StockPrices=new StockPrices();
					    		
								$StockPrices->setCode($value['Code']);
					    		$StockPrices->setDate($d);
					    		$StockPrices->setPrice($value['Price']);
					    		$StockPrices->setChanges($value['Changes']);
					    		 
					    		$em->persist($StockPrices);
					    		$em->flush($StockPrices);
					    		
					    		$completed[$key]['newPrice']=1;
					    		
					    		if ($StockPrices->getId()) {
					    			$updated_prices++;
					    		}
					    		unset($StockPrices);
				    		} else {
/*
 * anyway the new data can be wrong, save into StockPricesWrong table
 */
				    			$spw=new StockPricesWrong();
				    			
				    			$spw->setCode($value['Code']);
				    			$spw->setDate($d);
				    			$spw->setPrice($value['Price']);
				    			$spw->setChanges($value['Changes']);
				    			
				    			$em->persist($spw);
				    			$em->flush($spw);
				    			 
				    			error_log('Possibly wrong data : '.print_r($value, true));
				    			$msg[]='['.$value['Name'].'] - ['.$value['Code'].'] - ['.$value['Price'].'] - ['.$value['Changes'].']';
				    			unset($spw);
				    		}
/*
 *  Check company, if not exists this EPIC, should add as new Company
 */	
				    		$company=$this->getDoctrine()
					    		->getRepository('InvestShareBundle:Company')
					    		->findOneBy(
					    			array(
					    				'code'=>$value['Code']
					    			)
					    		);
/*
 * if the company code doesn't exists, add as new company
 */
				    		if (!$company) {
				    			$company=new Company();
				    			
				    			$company->setCode($value['Code']);
				    			$company->setName($value['Name']);
				    			$company->setLastPrice($value['Price']);
				    			$company->setSector('');
				    			$company->setLastPriceDate($d);
				    			$company->setLastChange($value['Changes']);
				    			$company->setList($value['List']);
				    			 
				    			$em->persist($company);
				    			$em->flush($company);
				    			
				    			if ($company->getId()) {
				    				$new_company++;
				    			}
				    		} else {
/*
 * Update the last price for all the existing company
*/
				    			if ($ok) {
			    					$company->setLastPrice($value['Price']);
			    					$company->setLastChange($value['Changes']);
				    			}
			    				$company->setList($value['List']);
			    				// if the last price change is in the current day,
			    				// no need to update last day/week/month average price
			    				$lpd=$company->getLastPriceDate();
			    				if ($lpd->format('Y-m-d')!=date('Y-m-d')) {
				    				$qb=$em->createQueryBuilder()
					    				->select('AVG(sp.price) as averageDay')
					    				->addSelect('DATE(sp.date) as day')
					    				->from('InvestShareBundle:StockPrices', 'sp')
					    				->where('sp.code=:code')
					    				->andWhere('DATE(sp.date)<:date')
					    				->groupBy('day')
					    				->orderBy('day', 'DESC')
					    				->setMaxResults(1)
					    				->setParameter('code', $value['Code'])
					    				->setParameter('date', $d->format('Y-m-d'));
				    				$r=$qb->getQuery()->getArrayResult();
				    				if ($r && count($r)) {
				    					$r1=reset($r);
				    					$company->setLastDayAveragePrice($r1['averageDay']);
//				    					$completed[$key]['lastDay']=$r1['averageDay'];
										$updated_averages++;
				    					unset($r1);
				    				}
				    				unset($r);
				    				unset($qb);
	
				    				$d2=clone $d;
				    				$d2->modify('-1 week');
				    				$qb=$em->createQueryBuilder()
					    				->select('AVG(sp.price) as averageWeek')
					    				->addSelect('DATE(sp.date) as day')
					    				->from('InvestShareBundle:StockPrices', 'sp')
					    				->where('sp.code=:code')
					    				->andWhere('DATE(sp.date)<:date1 AND DATE(sp.date)>=:date2')
					    				->setParameter('code', $value['Code'])
					    				->setParameter('date1', $d->format('Y-m-d'))
				    					->setParameter('date2', $d2->format('Y-m-d'));
				    				$r=$qb->getQuery()->getArrayResult();
				    				if ($r && count($r)) {
				    					$r1=reset($r);
				    					$company->setLastWeekAveragePrice($r1['averageWeek']);
//				    					$completed[$key]['lastWeek']=$r1['averageWeek'];
				    					$updated_averages++;
				    					unset($r1);
				    				}
				    				unset($r);
				    				unset($qb);
				    				 
					    			$d3=clone $d;
				    				$d3->modify('-1 month');
				    				$qb=$em->createQueryBuilder()
					    				->select('AVG(sp.price) as averageMonth')
					    				->addSelect('DATE(sp.date) as day')
					    				->from('InvestShareBundle:StockPrices', 'sp')
					    				->where('sp.code=:code')
					    				->andWhere('DATE(sp.date)<:date1 AND DATE(sp.date)>=:date2')
					    				->setParameter('code', $value['Code'])
					    				->setParameter('date1', $d->format('Y-m-d'))
				    					->setParameter('date2', $d3->format('Y-m-d'));
				    				$r=$qb->getQuery()->getArrayResult();
				    				if ($r && count($r)) {
				    					$r1=reset($r);
				    					$company->setLastMonthAveragePrice($r1['averageMonth']);
//				    					$completed[$key]['lastMonth']=$r1['averageMonth'];
				    					$updated_averages++;
				    					unset($r1);
				    				}
				    				unset($r);
				    				unset($qb);
			    				}
			    				$company->setLastPriceDate($d);
			    				$em->flush();

/*
 * if any trade data exists with this company code, update with the last price
 */
				    		}
				    		unset($company);
				    	}
				    	unset($result);
		    		}
				}
	    	}
    	}
/*
 * add some messages if added new company, updated prices or updated trades
 */
    	if ($new_company) {
    		$message.='<br>Added '.$new_company.' new company';
    	}
	    if ($updated_averages) {
    		$message.='<br>Updated '.$updated_averages.' averages';
    	}
    	if ($updated_prices) {
    		$message.='<br>Updated '.$updated_prices.' prices';
    	}
    	if (count($msg)) {
    		$message.='<br>Possibly wrong data:<br>'.implode('<br>', $msg);
    	}
    	 
    	return $this->render('InvestShareBundle:Default:pricelist.html.twig', array(
    		'showmenu'	=> false,
    		'data'		=> $completed,
    		'update'	=> true,
    		'refresh'	=> (($remains > 0)?($remains):($this->refresh_interval)),
    		'message'	=> $message,
    		'debug_message' => $debug_message
    	));
    }

    
    public function pricelistAction($date, $export) {

    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}

    	$functions=$this->get('invest.share.functions');
    	$request=$this->getRequest();
    	$prices=array();
    	$codes=array();
    	$sectorList=array();
    	$availableDates=array();
		$ftseList=array(
			'0'=>'All',
			'\'FTSE100\',\'FTSE250\''=>'FTSE 100 & 250',
			'\'FTSE100\''=>'FTSE 100',
			'\'FTSE250\''=>'FTSE 250',
			'\'FTSESmallCap\''=>'FTSE Small Cap'
		);
		$message='';
		
    	$em=$this->getDoctrine()->getManager();
    	 
/*
 * if form posted, use the selected timestamp to show the updated prices on that time
 */
    	$startDate=new \DateTime('-1 day');
		$startDate->setTime(0, 0, 0);
    	$endDate=new \DateTime('now');
		$endDate->setTime(23, 59, 59);
    	$list=0;
    	$sector=0;

		$qb=$em->createQueryBuilder()
			->select('c.sector')
			->from('InvestShareBundle:Company', 'c')
			->where('LENGTH(c.sector)>0')
			->groupBy('c.sector')
			->orderBy('c.sector');
		
		$results=$qb->getQuery()->getArrayResult();
		if (count($results)) {
			$sectorList[0]='All';
			foreach ($results as $result) {
				$sectorList[$result['sector']]=$result['sector'];
			}
		}

		$qb=$em->createQueryBuilder()
			->select('p.date')
			->from('InvestShareBundle:StockPrices', 'p')
			->groupBy('p.date')
			->orderBy('p.date', 'DESC');
		 
		if (isset($startDate)) {
			$qb->andWhere('p.date>=:startDate')
				->setParameter('startDate', $startDate->format('Y-m-d H:i:s'));
		}
		if (isset($endDate)) {
			$qb->andWhere('p.date<=:endDate')
				->setParameter('endDate', $endDate->format('Y-m-d H:i:s'));
		}
		$results=$qb->getQuery()->getArrayResult();
		foreach($results as $result) {
			$availableDates[$result['date']->getTimestamp()]=$result['date']->format('d/m/Y H:i:s');
		}
		
		$datesForm=$this->createForm(new PricelistSelectType($startDate, $endDate, $sector, $list, $sectorList, $ftseList, $availableDates, $em));
		$datesForm->handleRequest($request);
		if ($request->isMethod('POST')) {
			$formData=$datesForm->getData();
			
			if (isset($formData['date'])) {
				$date=$formData['date'];
			}
			if (isset($formData['startDate'])) {
				$startDate=$formData['startDate'];
				$startDate->setTime(0, 0, 0);
			}
			if (isset($formData['endDate'])) {
				$endDate=$formData['endDate'];
				$endDate->setTime(23, 59, 59);
			}
			$list=((isset($formData['list']))?($formData['list']):(0));
			$sector=((isset($formData['sector']))?($formData['sector']):(0));
		}
		

    	if ($date) {
/*
 * if date(timestamp) selected, show that
 */

    		$qb=$em->createQueryBuilder()
    			->select('c.code')
    			->addSelect('c.name')
    			->addSelect('c.sector')
    			->addSelect('c.list')
    			->addSelect('p.price')
    			->addSelect('p.changes')
    			->addSelect('p.date')
    			->addSelect('c.lastDayAveragePrice as lastDay')
    			->addSelect('c.lastWeekAveragePrice as lastWeek')
    			->addSelect('c.lastMonthAveragePrice as lastMonth')
    			->from('InvestShareBundle:Company', 'c')
    			->join('InvestShareBundle:StockPrices', 'p', 'WITH', 'c.code=p.code')
    			->where('p.date=:date')
    			->setParameter('date', date('Y-m-d H:i:s', $date));

    		if (strlen($list)>1) {
    			$qb->andWhere('c.list IN ('.$list.')');
    		}
    		if (strlen($sector)>1) {
    			$qb->andWhere('c.sector=:sector')
    				->setParameter('sector', $sector);
    		}
    			 
    		$prices1=$qb->getQuery()->getArrayResult();
  		
    		if (count($prices1)) {
    			foreach ($prices1 as $pr1) {
    				$codes[$pr1['code']]=$pr1['code'];
    				$prices[]=array(
   						'Code'=>$pr1['code'],
   						'Name'=>$pr1['name'],
	    				'Sector'=>$pr1['sector'],
	    				'List'=>$pr1['list'],
    					'Price'=>$pr1['price'],
   						'Changes'=>$pr1['changes'],
    					'Date'=>$pr1['date'],
    					'lastDay'=>$pr1['lastDay'],
    					'lastWeek'=>$pr1['lastWeek'],
    					'lastMonth'=>$pr1['lastMonth']
    				);
    			}
    		}
    		
    	} else {
/*
 * else show the latest data
 */
    		
    		$qb=$em->createQueryBuilder()
    			->select('c.code')
    			->addSelect('c.name')
    			->addSelect('c.sector')
    			->addSelect('c.list')
    			->addSelect('c.lastPrice as price')
    			->addSelect('c.lastChange as changes')
    			->addSelect('c.lastPriceDate as date')
    			->addSelect('c.lastDayAveragePrice as lastDay')
    			->addSelect('c.lastWeekAveragePrice as lastWeek')
    			->addSelect('c.lastMonthAveragePrice as lastMonth')
    			 
    			->from('InvestShareBundle:Company', 'c')
    			->where('c.lastPrice>0')
    			->andWhere('c.lastPriceDate>:date')
    			->orderBy('c.name')
    			->setParameter('date', date('Y-m-d H:i:s', strtotime('-1 month')));
    		
    		if (strlen($list)>1) {
    			$qb->andWhere('c.list IN ('.$list.')');
    		}
    		if (strlen($sector)>1) {
    			$qb->andWhere('c.sector=:sector')
    				->setParameter('sector', $sector);
    		}
    		
    		$prices1=$qb->getQuery()->getArrayResult();

   	    	if (count($prices1)) {
	    		foreach ($prices1 as $pr1) {
	    			$class='';
	    			if ($pr1['date'] != null && $pr1['date']->getTimestamp() >= time()-15*60) {
	    				$class='updatedRecently';
	    			}
	    			if ($pr1['date'] != null && $pr1['date']->getTimestamp() < time()-8*60*60) {
	    				$class='updatedLong';
	    			}
	    			$codes[$pr1['code']]=$pr1['code'];
	    			$prices[]=array(
	    				'Code'=>$pr1['code'],
	    				'Name'=>$pr1['name'],
	    				'Sector'=>$pr1['sector'],
	    				'List'=>$pr1['list'],
	    				'Price'=>$pr1['price'],
	    				'Changes'=>$pr1['changes'],
	    				'Date'=>(($pr1['date'] == null || $pr1['date']->getTimestamp()<0)?(''):($pr1['date'])),
    					'lastDay'=>$pr1['lastDay'],
    					'lastWeek'=>$pr1['lastWeek'],
    					'lastMonth'=>$pr1['lastMonth'],
	    				'Class'=>$class
	    			);
	    		}
	    	}
    	}
/*
 * create a form to select multiple companies to compare
 * and show them in a chart 
 */
		$allCompanies=array();
    	if (count($prices)) {
   			foreach ($codes as $result) {
   				$allCompanies[]=array('code'=>$result);
    		}
    	} else {
    		$qb=$em->createQueryBuilder()
    			->select('c.code')
    			->from('InvestShareBundle:Company', 'c')
    			->orderBy('c.name', 'ASC');
    		
    		$allCompanies=$qb->getQuery()->getArrayResult();
    	}
    	
    	$form=$this->createForm(new CompanySelectType($allCompanies));
    	$form->handleRequest($request);
    	if ($form->isSubmitted()) {
    		$formData=$form->getData();

    		$selectedData=array();
    		foreach ($formData as $k=>$v) {
    			if ($v) {
    				$selectedData[]=str_replace('.', '_', $k);
    			}
    		}
    	
    		if (count($selectedData)) {
    			return $this->redirect($this->generateUrl('invest_share_prices', array('company'=>implode(',', $selectedData))));
    		}
    	}
    	 
/*
 * create a list from the updates time and timestamps for a dropdown list
 */
    	
		if ($export) {
			
			$response=$this->render('InvestShareBundle:Export:pricelist.csv.twig', array(
				'data'	=> $prices,
			));
			$filename = "export_".date("Y_m_d_His").".csv";
			$response->headers->set('Content-Type', 'text/csv');
			$response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
	        return $response;
	        
		} else {
			
			return $this->render('InvestShareBundle:Default:pricelist.html.twig', array(
				'showmenu'	=> true,
	   			'datesForm' => $datesForm->createView(),
				'form'		=> $form->createView(),
	   			'data'		=> $prices,
	   			'message'	=> $message,
				'au'		=> (($date)?(false):(true)),
	    		'notes'		=> $functions->getConfig('page_pricelist')
	    	));
			
		}
    }

    
    public function pricesAction($company) {

    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}

/*
 * prices for 1 or more company with graph
 */
    	$request=$this->getRequest();
    	$em=$this->getDoctrine()->getManager();

    	$company=str_replace('_', '.', $company);
    	$company2=$company;
    	
    	$message='';
    	$companies=array();

    	$qb=$em->createQueryBuilder()
    		->select('c.code')
    		->addSelect('c.name')
    		->addSelect('c.list')
    		->from('InvestShareBundle:Company', 'c')
    		->orderBy('c.name');
    	
    	$results=$qb->getQuery()->getArrayResult();
		foreach($results as $result) {
    		$companies[$result['code']]=$result['name'].' ('.$result['list'].')';
		}
		
		$selectForm=$this->createForm(new PricesCompanySelectType($company, $companies));
		$selectForm->handleRequest($request);
		if ($selectForm->isSubmitted()) {
			$formData=$selectForm->getData();
			$company2=((isset($formData['company']))?($formData['company']):($company));
		}
		
		if ($company != $company2) {
		
			return $this->redirect($this->generateUrl('invest_share_prices', array('company'=>str_replace('.', '_', $company2))));
		
		}
		
		return $this->render('InvestShareBundle:Default:pricesgraph.html.twig', array(
			'showmenu'		=> true,
    		'selectForm'	=> $selectForm->createView(),
    		'company'		=> $company,
    		'message'		=> $message
    	));
    }
    
    
    public function currencyAction($currency) {

    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}

    	$limit=50;
    	$message='';
    	$data=array();
    	$dates=array();
    	$currencies=array();
    	$updated=array();
    	$functions=$this->get('invest.share.functions');
    	
    	$this->currencyNeeded=$functions->getCurrencyList();
    	
    	if (!$currency) {
			$em=$this->getDoctrine()->getManager();
			
    		$qb=$em->createQueryBuilder()
    			->select('c.currency')
    			->addSelect('c.rate')
    			->addSelect('c.updated')
    			->from('InvestShareBundle:Currency', 'c')
    			->orderBy('c.updated', 'DESC')
    			->addOrderBy('c.currency', 'ASC')
    			->setMaxResults($limit*count($this->currencyNeeded));
    		
    		if ($currency && in_array($currency, $this->currencyNeeded)) {
    			$qb->andWhere('c.currency=:cur')
    				->setParameter('cur', $currency);
    		}
    		$results=$qb->getQuery()->getArrayResult();
	    	if ($results && count($results)) {
	    		foreach ($results as $result) {
	    			if (in_array($result['currency'], $this->currencyNeeded)) {
	    				if (!in_array($result['currency'], $currencies)) {
	    					$currencies[]=$result['currency'];
	    				}
	    				$updated=explode('-', $result['updated']->format('Y-m-d-H-i-s'));
	    				$data[$result['currency']][$result['updated']->format('Y-m-d-H-i-s')]=array(
	    					'Rate'=>$result['rate'],
	    					'Date'=>$updated
	    				);
	    				$dates[$result['updated']->format('Y-m-d-H-i-s')]=$result['updated']->format('d/m/Y H:i');
	    			}
	    		}
	    		if (count($dates)) {
	    			foreach ($currencies as $cur) {
	    				foreach (array_keys($dates) as $date) {    				
	    					if (!isset($data[$cur][$date])) {
	    						$updated=explode('-', $date);
	    						$data[$cur][$date]=array('Rate'=>null, 'Date'=>array($updated[3], $updated[4], $updated[5], $updated[1], $updated[2], $updated[0]));
	    					}
	    				}
	    				krsort($data[$cur]);
	    			}
	    		}
	    	}
	    	$form=$this->createForm(new CurrencySelectType($this->generateUrl('invest_share_currency'), array_keys($data)));
	    	$form->handleRequest($this->getRequest());
	    	
	    	if ($form->isSubmitted()) {
	    		$formData=$form->getData();
	    		
	    		$message.='Submitted data:'.print_r($formData, true);
	    		
	    		if (count($formData)) {
	    			$selectedCurrencies=array();
	    			foreach ($formData as $k=>$v) {
	    				if ($v) {
	    					$selectedCurrencies[]=$k;
	    				}
	    			}
	    			
	    			if (count($selectedCurrencies)) {
	    				return $this->redirect($this->generateUrl('invest_share_currency', array('currency'=>implode(',', $selectedCurrencies))));
	    			}
	    		}
	    	}
	    	
	    	return $this->render('InvestShareBundle:Default:currencylist.html.twig', array(
	    		'showmenu'	=> true,
	    		'data'		=> $data,
	    		'form'		=> $form->createView(),
	    		'dates'		=> $dates,
	    		'currency'	=> $currency,
	   			'message'	=> $message,
	    		'notes'		=> $functions->getConfig('page_currency')
	    	));
    	
    	} else {
    	
	    	return $this->render('InvestShareBundle:Default:currencygraph.html.twig', array(
	    		'showmenu'	=> true,
	    		'currency'	=> $currency,
	   			'message'	=> $message,
	    		'notes'		=> $functions->getConfig('page_currency')
	    	));
    	}
    }
    
    
    public function tradeuploadAction() {
    	
    	if (!$this->get("security.context")->isGranted('ROLE_USER')) {
    		return $this->redirect($this->generateUrl('invest_share_login'));
    	}
    	
    	$fileData=array();
    	$msg=array();
    	$currentUser=$this->getUser();
    	$request=$this->getRequest();
    	$message='';
    	
    	$companyRepo=$this->getDoctrine()
    		->getRepository('InvestShareBundle:Company');
    	$tradeRepo=$this->getDoctrine()
    		->getRepository('InvestShareBundle:Trade');
    	$tradeTransactionsRepo=$this->getDoctrine()
    		->getRepository('InvestShareBundle:TradeTransactions');
    	$portfolioRepo=$this->getDoctrine()
    		->getRepository('InvestShareBundle:Portfolio');
    	$portfolioTransactionRepo=$this->getDoctrine()
    		->getRepository('InvestShareBundle:PortfolioTransaction');

    	$em=$this->getDoctrine()->getManager();
    	    	
    	$uploadForm=$this->createForm(new TradeUploadType());
		$uploadForm->handleRequest($request);
		
		if ($uploadForm->isSubmitted() && $uploadForm->isValid() && ($request->getMethod() == 'POST')) {
			
			$data=$uploadForm->getData();
			if ($data['file']->move('./files/', 'uploaded.csv')) {
				$f=fopen('./files/uploaded.csv', 'r');
				$fileOK=false;
				$clientNumber=null;
				$clientName='';
				$dataOK=false;
				$fileData=array();
				
				while (!feof($f)) {
					$line=fgetcsv($f);

					switch ($line[0]) {
						case 'Portfolio Summary' : {
							$fileOK=true;
							break;
						}
						case 'Client Name:' : {
							$clientName=$line[1];
							break;
						}
						case 'Client Number:' : {
							$clientNumber=$line[1];
							break;
						}
						case 'Trade date' : {
							$dataOK=true;
							break;
						}
						default : {
							if ($fileOK && $clientNumber && $dataOK && count($line) == 7) {
								$tradeDate=date_create_from_format('d#m#Y H:i:s', $line[0].' 00:00:00');
								$settleDate=date_create_from_format('d#m#Y H:i:s', $line[1].' 00:00:00');
								$reference=$line[2];
								if (in_array(substr($line[2], 0, 1), array('S', 'B'))) {
									$type=((substr($reference, 0, 1)=='S')?(1):(0));
									$unitPrice=str_replace(',', '', $line[4]);
									$quantity=str_replace(',', '', $line[5]);
									$value=(($type==1)?1:-1)*str_replace(',', '', $line[6]);
									$cost=abs(($quantity*$unitPrice/100)-$value);
								} else {
									$type=-1;
									$unitPrice=str_replace(',', '', $line[4]);
									$quantity=1;
									$value=str_replace(',', '', $line[6]);
									$cost=$value;
								}
								$description=$line[3];
								$company=$description;
								
								
								$p1=strpos(strtolower($company), 'plc');
								if ($p1 === false) {
									$p1=strlen($company);
								}
								$p2=strpos(strtolower($company), 'ord');
								if ($p2 === false) {
									$p2=strlen($company);
								}
								
								$p=min($p1, $p2);
								$company=trim(substr($company, 0, $p));

								$companyId=null;
								
								if ($type != -1) {
									
									$cmpny=$companyRepo
										->findOneBy(
											array(
												'name'=>$company
											)
										);
																
									if ($cmpny) {
										$companyId=$cmpny->getId();
									} else {
										$cmpny=$companyRepo
											->findOneBy(
												array(
													'altName'=>$company
												)
											);
											
										if ($cmpny) {
											$companyId=$cmpny->getId();
										} else {
											
											$cmp=explode(' ', $company);
											$query=$companyRepo->createQueryBuilder('c')
												->where('c.name LIKE :cmpny')
												->setParameter('cmpny', '%'.$cmp[0].'%')
												->getQuery();
											
											$cmpny=$query->getResult();
											
											if (count($cmpny)) {
												$companyId=$cmpny[0]->getId();
											}
											
										}
									}
								}
								
								
								$fileData[]=array(
									'type'=>$type,
									'company'=>$company,
									'companyId'=>$companyId,
									'settleDate'=>$settleDate,
									'tradeDate'=>$tradeDate,
									'quantity'=>$quantity,
									'unitPrice'=>$unitPrice,
									'cost'=>$cost,
									'reference'=>$reference,
									'description'=>$description
								);
							}
							break;
						}
					}
				}
				fclose($f);
				
				
			}

/*
 * database update
 */
			
		
			if (count($fileData)) {
				usort($fileData, 'self::typeSort');
/*
 * to check all the references exists and not exists more
 */
				$uploadedReferences=array();
				
				foreach ($fileData as $v) {

					switch ($v['type']) {
						case -1 : {
/*
 * Other, interest and starting price
 */

							switch (strtolower($v['reference'])) {
/*
 * Interest, Chaps, Refund, Transfer
 */
								case 'interest' :
								case 'chaps' :
								case 'refund' :
								case 'fpc' :
								case 'fpd' :
								case 'transfer' : {
									$portfolio=$portfolioRepo
										->findOneBy(
											array(
												'clientNumber'=>$clientNumber
											)
										);
									
									if ($portfolio) {
/*
 * No need to change the name right now
 */										
//										$portfolio->setName(($clientName)?($clientName):('pr'.$clientNumber));
										$portfolio->setStartAmount(0);
										$em->flush();
										
										$pId=$portfolio->getId();
										
									} else {

										$portfolio=new Portfolio();
										
										$portfolio->setName(($clientName)?($clientName):('pr'.$clientNumber));
										$portfolio->setStartAmount(0);
										$portfolio->setClientNumber($clientNumber);
										$portfolio->setUserId($currentUser->getId());										
										
										$em->persist($portfolio);
										$em->flush();
										
										$pId=$portfolio->getId();
	
									}
									
									$pt=$portfolioTransactionRepo
										->findOneBy(
											array(
												'PortfolioId'=>$pId,
												'amount'=>$v['cost'],
												'date'=>$v['tradeDate'],
												'reference'=>strtolower($v['reference']),
												'description'=>strtolower($v['description'])
											)
										);
									if (!$pt) {
										$pt=new PortfolioTransaction();
										
										$pt->setAmount($v['cost']);
										$pt->setDate($v['tradeDate']);
										$pt->setReference(strtolower($v['reference']));
										$pt->setDescription(strtolower($v['description']));
										$pt->setPortfolioId($pId);
										
										$em->persist($pt);
										
										$em->flush();
										
									}
									break;
								}
							}
							break;
						}
						case 0 :
						case 1 : {
/*
 * Buy or Sell
 */

							$portfolio=$portfolioRepo
								->findOneBy(
									array(
										'clientNumber'=>$clientNumber
									)
								);

							if ($portfolio) {
								
								$pId=$portfolio->getId();
								
							} else {

								$portfolio=new Portfolio();
								
								$portfolio->setName(($clientName)?($clientName):('pr'.$clientNumber));
								$portfolio->setStartAmount(0);
								$portfolio->setClientNumber($clientNumber);
								$portfolio->setUserId($currentUser->getId());
								
								$em->persist($portfolio);
								$em->flush();
								
								$pId=$portfolio->getId();

							}
							
							if ($v['companyId']) {
								$trade=$tradeRepo
									->findOneBy(
										array(
											'portfolioId'=>$pId,
											'companyId'=>$v['companyId'],
										)
									);
								
								if ($trade) {
									
									$tradeId=$trade->getId();
									
								} else {
	
									$trade=new Trade();
									$trade->setCompanyId($v['companyId']);
									$trade->setPortfolioId($pId);
											
									$trade->setPERatio(0);
									$trade->setName('');
											
									$em->persist($trade);
									$em->flush();
																				
									$tradeId=$trade->getId();
									
								}
								
								$uploadedReferences[]=$v['reference'];
								
								$tt=$tradeTransactionsRepo
									->findOneBy(
										array(
											'type'=>$v['type'],
											'tradeId'=>$tradeId,
											'reference'=>$v['reference']
										)
									);
								
								if ($tt) {
	
									$tt->setSettleDate($v['settleDate']);
									$tt->setTradeDate($v['tradeDate']);
									$tt->setDescription($v['description']);
									$tt->setUnitPrice($v['unitPrice']);
									$tt->setQuantity($v['quantity']);
									$tt->setCost($v['cost']);
										
									$em->flush();
										
								} else {
									
									$tt=new TradeTransactions();
									
									$tt->setType($v['type']);
									$tt->setTradeId($tradeId);
									$tt->setSettleDate($v['settleDate']);
									$tt->setTradeDate($v['tradeDate']);
									$tt->setReference($v['reference']);
									$tt->setDescription($v['description']);
									$tt->setUnitPrice($v['unitPrice']);
									$tt->setQuantity($v['quantity']);
									$tt->setCost($v['cost']);
									
									$em->persist($tt);
									$em->flush();
									
								}
							} else {
								$msg[]='Missing company : '.$v['company'];
							}
							break;
						}
					}
				}
/*
 * check the references
 */
				$query='SELECT `tt`.`reference`'.
					' FROM `TradeTransactions` `tt`'.
						' JOIN `Trade` `t` ON `t`.`id`=`tt`.`tradeId`'.
					' WHERE `t`.`portfolioId`=:pId'.
						' AND `tt`.`reference` NOT IN (\''.implode('\',\'', $uploadedReferences).'\')';

				$em=$this->getDoctrine()->getManager();
				$connection=$em->getConnection();
				
				$stmt=$connection->prepare($query);
				$stmt->bindValue('pId', $pId);
				$stmt->execute();
				$difference=$stmt->fetchAll();
				
				if ($difference && count($difference)) {
					foreach ($difference as $d) {
						$msg[]='Additional reference:'.$d['reference'];
					}
				}
			}
			
			if (count($msg)) {
				$message.=' - ['.implode('][', $msg).']';	
			} else {
				$message.='All details updated without error';
			}
		}
		
    	return $this->render('InvestShareBundle:Default:tradeupload.html.twig', array(
   			'uploadForm' => $uploadForm->createView(),
    		'title'=>'Upload file',
   			'message' => $message
    	));
    }

    
    public function updatecurrencyAction() {
    
    	$message='';
    	$updated=null;
    	$data=array();
    	$d1=array();
    	$tmp=array();
    	$functions=$this->get('invest.share.functions');
    	
    	$this->currencyNeeded=$functions->getCurrencyList();
    	
    	$em=$this->getDoctrine()->getManager();
    
    	$url='gbp.fxexchangerate.com';
//		$url='198.58.100.208';
    	$XML=@simplexml_load_file('http://'.$url.'/rss.xml');
    
    	if ($XML !== false) {
    		$updated=new \DateTime($XML->channel->lastBuildDate);
    			
    		foreach ($XML->channel->item as $v) {
    			if (count($v) == 6) {
    				$d1['Currency']='';
    				$d1['Rate']=1;
    				$d1['Updated']=$updated;
    				if (preg_match('/\([A-Z]{2,3}\)$/', trim($v->title), $tmp)) {
    					$d1['Currency']=str_replace(array('(', ')'), '', $tmp[0]);
    				}
    				if (preg_match('/ [0-9\.]{1,9}/', trim($v->description), $tmp)) {
    					$d1['Rate']=$tmp[0];
    				}
    					
    				if (!count($this->currencyNeeded) || in_array($d1['Currency'], $this->currencyNeeded)) {
    					$data[]=$d1;
    						
    					$currency=new Currency();
    
    					$currency->setCurrency($d1['Currency']);
    					$currency->setRate($d1['Rate']);
    					$currency->setUpdated($updated);
    
    					$em->persist($currency);
    						
    					$em->flush();
    				}
    					
    			}
    		}
    	} else {
    		$message='No currency data';
    	}
    
    	return $this->render('InvestShareBundle:Default:currency.html.twig', array(
    		'showmenu'	=> false,
    		'data' => $data,
    		'message' => $message
    	));
    }

    
    public function getRoles() {
    	$roles=array(
			'ROLE_ADMIN'=>'Administrator',
			'ROLE_MANAGER'=>'Manager',
			'ROLE_USER'=>'User'
    	);
    	return $roles;
    }
    
    
    public static function divSort($a, $b) {
    	if ($a['name'] == $b['name']) {
    		if ($a['exDivDate'] == $b['exDivDate']) {
    			 
    			return 0;
    		}
    
    		return ($a['exDivDate'] > $b['exDivDate'])?1:-1;
    	}
    	 
    	return ($a['name'] > $b['name'])?1:-1;
    }
    
    
    public static function divDateSort($a, $b) {
    	if ($a['exDivDate'] == $b['exDivDate']) {
    		if ($a['name'] == $b['name']) {
    
    			return 0;
    		}
    
    		return ($a['name'] > $b['name'])?1:-1;
    	}
    	 
    	return ($a['exDivDate'] > $b['exDivDate'])?1:-1;
    }
    
    public static function vsSort($a, $b) {
    	if ($a['BALANCE'] == $b['BALANCE']) {
  			return 0;
    	}    	 
    	return ($a['BALANCE'] < $b['BALANCE'])?1:-1;
    }

}
