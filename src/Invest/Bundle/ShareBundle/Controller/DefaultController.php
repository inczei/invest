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
// use Symfony\Component\BrowserKit\Response;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Ps\PdfBundle\Annotation\Pdf;

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
	
	
	/*
	 * @Pdf()
	 */	
	public function helloAction($name)
	{

		$format = $this->get('request')->get('_format');
// error_log('format:'.$format);		
		
		switch ($format) {
			case 'pdf' : {
				$facade = $this->get('ps_pdf.facade');
				$response = new Response();
				$this->render('InvestShareBundle:Default:hello.pdf.twig', array('name'=>$name), $response);
				$xml = $response->getContent();
				$content = $facade->render($xml);
				return new Response($content, 200, array('content-type' => 'application/pdf'));
				break;
			}
			default : {
				return $this->render('InvestShareBundle:Default:hello.html.twig', array('name' => $name));
				break;
			}
		}

	}
	
	
    public function indexAction() {
/*
 * on the 1st page can see the summary of all investment
 */
		$em=$this->getDoctrine()->getManager();
		
		$message='';
		
		if ($this->updateSummary()) {
/*
 * Summary updated, at the moment nothing to do with this
 */
		}

		$graphs=array();
		
		$summary=$this->getDoctrine()
			->getRepository('InvestShareBundle:Summary')
			->findAll();
		
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
				$overall['CurrentDividend']+=$s->getCurrentDividend();
				$overall['Investment']+=$s->getInvestment();
				$overall['CurrentValue']+=$s->getCurrentValue();
				$overall['Profit']+=$s->getProfit();
				$overall['DividendPaid']+=$s->getDividendPaid();
				$overall['RealisedProfit']+=$s->getRealisedProfit();
				
				$overall['CashIn']+=$s->getCashIn();
				$overall['UnusedCash']+=$s->getUnusedCash();
				$overall['ActualDividendIncome']+=$s->getActualDividendIncome();
				$overall['CgtProfitsRealised']+=$s->getCgtProfitsRealised();
				$overall['UnusedBasicRateBand']+=$s->getUnusedBasicRateBand();

				$js=json_decode($s->getCurrentValueBySector());

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
		$query=$em->createQuery('SELECT p.id, p.name FROM InvestShareBundle:Portfolio p');
		$results=$query->getResult();
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
        	'notes'			=> $this->getConfig('page_summary')
        ));
    }

    
    public function dividendAction() {

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
		
    	$connection=$em->getConnection();

    	$companies=array();
    	$tradeData=$this->getTradesData(null, null, null, 0);

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
	   					$ty=$this->getTaxYear($td['tradeDate1']);
	   					
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

        if ($request->isMethod('POST')) {
			if (isset($_POST['form']['exDivDateFrom']) && $_POST['form']['exDivDateFrom']) {
				$searchDateFrom=\DateTime::createFromFormat('d/m/Y', $_POST['form']['exDivDateFrom']);
			}
			if (isset($_POST['form']['exDivDateTo']) && $_POST['form']['exDivDateTo']) {
				$searchDateTo=\DateTime::createFromFormat('d/m/Y', $_POST['form']['exDivDateTo']);
			}
        	if (isset($_POST['form']['paymentDateFrom']) && $_POST['form']['paymentDateFrom']) {
				$searchPaymentDateFrom=\DateTime::createFromFormat('d/m/Y', $_POST['form']['paymentDateFrom']);
			}
			if (isset($_POST['form']['paymentDateTo']) && $_POST['form']['paymentDateTo']) {
				$searchPaymentDateTo=\DateTime::createFromFormat('d/m/Y', $_POST['form']['paymentDateTo']);
			}
			if (isset($_POST['form']['portfolio']) && $_POST['form']['portfolio']) {
				$searchPortfolio=$_POST['form']['portfolio'];
			}
			if (isset($_POST['form']['sector']) && $_POST['form']['sector']) {
				$searchSector=$_POST['form']['sector'];
			}
			if (isset($_POST['form']['income']) && $_POST['form']['income']) {
				$searchIncome=$_POST['form']['income'];
			}
			if (isset($_POST['form']['orderby']) && $_POST['form']['orderby']) {
				$orderBy=$_POST['form']['orderby'];
			}
			if (isset($_POST['form']['exDivDateSearch']) && $_POST['form']['exDivDateSearch']) {
				$exDivDateSearch=$_POST['form']['exDivDateSearch'];
			}
			if (isset($_POST['form']['paymentDateSearch']) && $_POST['form']['paymentDateSearch']) {
				$paymentDateSearch=$_POST['form']['paymentDateSearch'];
			}
				
			$this->getRequest()->getSession()->set('is_div',
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
		} else {
			if (null !== ($this->getRequest()->getSession()->get('is_div'))) {
				$data=$this->getRequest()->getSession()->get('is_div');
				$ok=true;
				if (isset($data['updated'])) {
					if ($data['updated'] < date('Y-m-d H:i:s', time()-$this->searchTime)) {
						$ok=false;
					}
				}
				if ($ok) {
					if (isset($data['f'])) {
						$searchDateFrom=$data['f'];
					}
					if (isset($data['t'])) {
						$searchDateTo=$data['t'];
					}
					if (isset($data['pf'])) {
						$searchPaymentDateFrom=$data['pf'];
					}
					if (isset($data['pt'])) {
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
					$this->getRequest()->getSession()->remove('is_div');
				}
			}
		}
    	
		$searchArray=array();

		if ($searchSector) {
			$searchArray[]='`c`.`Sector`=\''.$searchSector.'\'';
		}
    	$order=array(
    		0=>'`c`.`Name`, `d`.`ExDivDate`',
    		1=>'`d`.`ExDivDate`, `c`.`Name`'
    	);
    	$orderName=array(
    		0=>'Name',
    		1=>'ExDiv Date'
    	);
    	$query1='SELECT'.
    		' `c`.`Code`,'.
      		' `c`.`Name`,'.
      		' `c`.`Sector`,'.
      		' `c`.`Frequency`,'.
      		' `c`.`Currency`,'.
      		' `d`.`Amount` as `Dividend`,'.
    		' `d`.`ExDivDate`,'.
    		' `d`.`PaymentDate`,'.
    		' `d`.`PaymentRate`,'.
    		' `d`.`Special`,'.
    		' `c`.`lastPrice` `SharePrice`,'.
    		' (`d`.`Amount`/`c`.`lastPrice`)*100 as `CurrentYield`,'.
    		' "0" `PurchasePrice`,'.
    		' "0" `Yield`,'.
    		' "0" `Income`,'.
    		' "0" `IncomeGBP`,'.
    		' "0" `PredictedIncome`'.
    		' FROM `Dividend` `d`'.
    		' JOIN `Company` `c` ON `d`.`CompanyId`=`c`.`id`'.
    		' WHERE `c`.`Code` IN ("'.implode('","', array_keys($companies)).'")'.
    		((count($searchArray))?(' AND ('.implode(') AND (', $searchArray).')'):('')).
    		' ORDER BY '.$order[$orderBy];

    	$stmt=$connection->prepare($query1);
    	$stmt->execute();
    	$dividends=$stmt->fetchAll();

    	$companyData=array();
    	if (count($tradeData)) {
    		foreach ($dividends as $kdiv=>$div) {

	    		foreach ($tradeData as $td) {

	    			if (!$searchPortfolio || $searchPortfolio==$td['portfolioId']) {

	    				if ($div['Code'] == $td['companyCode'] && $div['ExDivDate'] > $td['tradeDate1'] && ($td['reference2'] == '' || $div['ExDivDate'] <= $td['tradeDate2'])) {
	    					if ($td['reference2'] != '') {

	    						$companies[$td['companyCode']][1]['Quantity']+=$td['quantity1'];
	    						$companies[$td['companyCode']][1]['Total']+=($td['quantity1']*$td['unitPrice1']);
	    						$companies[$td['companyCode']][1]['Price']=($companies[$td['companyCode']][1]['Total']/$companies[$td['companyCode']][1]['Quantity']);
	    					}
		    				$companies[$td['companyCode']][0]['Currency']=$div['Currency'];
		    				$companies[$td['companyCode']][1]['Currency']=$div['Currency'];
		    				
		    				$income=$td['quantity1']*$div['Dividend']/(($div['Currency']=='GBP')?(100):(1));
		    				$dividends[$kdiv]['Income']+=$income;
		    				$dividends[$kdiv]['IncomeGBP']+=$income/(($div['Currency']=='GBP' || $div['PaymentRate']==null)?(1):($div['PaymentRate']));

		    				$dividends[$kdiv]['Details'][$td['reference1']]=$td;
	    				}	    				
	    				 
	    				if ($div['Code'] == $td['companyCode'] && $td['reference2'] == '') {
	    					
							$companies[$td['companyCode']][0]['Quantity']+=$td['quantity1'];
	    					$companies[$td['companyCode']][0]['Total']+=($td['quantity1']*$td['unitPrice1']);
	    					$companies[$td['companyCode']][0]['Price']=($companies[$td['companyCode']][0]['Total']/$companies[$td['companyCode']][0]['Quantity']);

							if (!isset($companyData[$div['Code']])) {
	    						$companyData[$div['Code']]=array(
	    							'Name'=>$div['Name'],
	    							'Sector'=>$div['Sector'],
	    							'SharePrice'=>$div['SharePrice'],
	    							'Quantity'=>0,
	    							'Predicted'=>1,
	    							'References'=>array(),
	    							'Currency'=>$div['Currency']
	    						);
	    					}
	    					if (!in_array($td['reference1'], $companyData[$div['Code']]['References'])) {
	    						$companyData[$div['Code']]['References'][]=$td['reference1'];
	    						$companyData[$div['Code']]['Quantity']+=$td['quantity1'];
	    					}
	    					$companyData[$div['Code']]['Trade'][$td['reference1']]=$td;
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
    			$d=$this->getDividendsForCompany($k, true, false);
    			if ($d && count($d)) {
    				foreach ($d as $v1) {
    					if (isset($v1['Predicted']) && $v1['Predicted'] && isset($companyData[$k])) {

   							$dividendsTemp['Code']=$k;
   							$dividendsTemp['Name']=$companyData[$k]['Name'];
   							$dividendsTemp['Sector']=$companyData[$k]['Sector'];
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

    						$dividendsTemp['ExDivDate']=$v1['ExDivDate'];
    						$dividendsTemp['PaymentDate']=$v1['PaymentDate'];
    						$dividendsTemp['DeclDate']=$v1['DeclDate'];
//    						if (isset($v1['Details'])) {
//    							$dividendsTemp['Details']=$v1['Details'];
//    						}
//    						$dividendsTemp['CurrentYield']=$v1['Amount']/$companyData[$k]['SharePrice']*100;
// print '<hr>'.$companyData[$k]['Currency'].' - '.(($companyData[$k]['Currency']=='GBP' || $v1['PaymentRate']==null)?(1):($v1['PaymentRate'])).' - v1:'.print_r($v1, true);
// print '<hr>'.print_r($companyData[$k], true).'<hr>';    						
    						$dividendsTemp['Dividend']=$v1['Amount'];
    						$dividendsTemp['PredictedIncome']=$v1['Amount']*$companyData[$k]['Quantity']/(($companyData[$k]['Currency']=='GBP')?(100):(1));
    						$dividendsTemp['PredictedQuantity']=$companyData[$k]['Quantity'];
    						$dividendsTemp['Currency']=$companyData[$k]['Currency'];

    						$dividends[]=$dividendsTemp;
    					}
    				}
    			}
    		}
    	}

    	$currencyRates = $this->getCurrencyRates();
    	
    	if (count($dividends)) {
    		foreach ($dividends as $v) {
    			
    			$ty=$this->getTaxYear($v['PaymentDate']);
    			
    			if (!isset($companies[$v['Code']][0]['TotalDividend'][$ty])) {
    				$companies[$v['Code']][0]['TotalDividend'][$ty]=0;
    			}
    			 
				$tdiv=$v['Dividend'];
				if ((isset($companyData[$v['Code']]['Currency']) && $companyData[$v['Code']]['Currency'] != 'GBP')) {
					if (isset($v['PaymentRate']) && $v['PaymentRate']) {
						$tdiv=$tdiv/$v['PaymentRate'];
					} else {
						$tdiv=$tdiv/$currencyRates[$companyData[$v['Code']]['Currency']];
					}
				}
				$companies[$v['Code']][0]['TotalDividend'][$ty]+=$tdiv;
				
    		}

    		foreach ($dividends as $k=>$v) {

				$pp=(($companies[$v['Code']][0]['Price'])?($companies[$v['Code']][0]['Price']):($companies[$v['Code']][1]['Price']));
    			$dividends[$k]['PurchasePrice']=$pp;
				$dividends[$k]['CurrentYield']=0;
    			$dividends[$k]['Yield']=(($pp)?($companies[$v['Code']][0]['TotalDividend'][$this->getTaxYear($v['PaymentDate'])]/$pp*100):(0));
				if (isset($companyData[$v['Code']]['Currency']) && $companyData[$v['Code']]['Currency'] != 'GBP') {
					$dividends[$k]['Yield']=$dividends[$k]['Yield']*100;					
				}
    			if (isset($companies[$v['Code']][0]['TotalDividend'][$this->getTaxYear($v['PaymentDate'])])) {
					$dividends[$k]['TotalDividend']=$companies[$v['Code']][0]['TotalDividend'];
						

					if (isset($companies[$v['Code']][1]['DividendPerYear'][$this->getTaxYear($v['PaymentDate'])]['AveragePrice'])) {
						$totalDiv=$dividends[$k]['TotalDividend'][$this->getTaxYear($v['PaymentDate'])];
						$ap=$companies[$v['Code']][1]['DividendPerYear'][$this->getTaxYear($v['PaymentDate'])]['AveragePrice'];
//print '<br>code:'.$v['Code'].', td:'.$totalDiv.', ap:'.$ap;
						$dividends[$k]['CurrentYield']=$totalDiv/$ap*100;
//						if (isset($companyData[$v['Code']]['Currency']) && $companyData[$v['Code']]['Currency'] != 'GBP') {
//							$dividends[$k]['Yield']=$dividends[$k]['Yield']*100;
//						}
//print ', Yield:'.$dividends[$k]['Yield'];
					} else {
//						$dividends[$k]['Yield']=0;
					}
				}

    			if ($dividends[$k]['PredictedIncome']) {
   					$dividends[$k]['Income']=$companies[$v['Code']][1]['Dividend'];
//					$dividends[$k]['PurchasePrice']=$companies[$v['Code']][0]['Price'];
//					$dividends[$k]['Yield']=((isset($companies[$v['Code']][0]['Price']) && $companies[$v['Code']][0]['Price'])?($dividends[$k]['Dividend']/$companies[$v['Code']][0]['Price']*100):(0));
//    				if (isset($companyData[$v['Code']]['Currency']) && $companyData[$v['Code']]['Currency'] != 'GBP') {
//						$dividends[$k]['Yield']=$dividends[$k]['Yield']*100;
//					}
    			} else {
//					$dividends[$k]['PurchasePrice']=$companies[$v['Code']][1]['Price'];
//					$dividends[$k]['Yield']=((isset($companies[$v['Code']][1]['Price']) && $companies[$v['Code']][1]['Price'])?($dividends[$k]['Dividend']/$companies[$v['Code']][1]['Price']*100):(0));
//   				if (isset($companyData[$v['Code']]['Currency']) && $companyData[$v['Code']]['Currency'] != 'GBP') {

//    					$dividends[$k]['Yield']=$dividends[$k]['Yield']*10000;
//					}
    			}
				
    			if ((in_array($searchIncome, array('', 1, 3)) && ($dividends[$k]['Income'] || $dividends[$k]['PredictedIncome'])) || (in_array($searchIncome, array(3,2)) && !$dividends[$k]['Income'] && !$dividends[$k]['PredictedIncome'])) {
    				$dividends[$k]['TaxYear']=$this->getTaxYear($v['PaymentDate']);
    			} else {
    				unset($dividends[$k]);
    			}
    		}
    	}

    	$searchSectors=array();
    	$searchPortfolios=array();
    	 
    	$results=$this->getDoctrine()
	    	->getRepository('InvestShareBundle:Company')
	    	->findBy(
    			array(),
    			array(
    				'sector'=>'ASC'
    			)
	    	);
    	if (count($results)) {
    		foreach ($results as $result) {
    			if ($result->getSector()) {
    				$searchSectors[$result->getSector()]=$result->getSector();
    			}
    		}
    	}

    	$results=$this->getDoctrine()
    		->getRepository('InvestShareBundle:Portfolio')
    		->findBy(
    			array(),
    			array(
    				'name'=>'ASC'
    			)
    	);
    	if (count($results)) {
    		foreach ($results as $result) {
    			if ($result->getName()) {
    				$searchPortfolios[$result->getId()]=$result->getName();
    			}
    		}
    	}
    	 
    	$empty_array=array(0=>'All');
    	$searchIncomes=array(1=>'With income', 2=>'Without income', 3=>'All');
    	
    	$searchForm=$this->createFormBuilder()
    		->setAction($this->generateUrl('invest_share_dividend'))
    		->add('exDivDateSearch', 'checkbox', array(
    			'required'=>false,
    			'data'=>($exDivDateSearch?true:false)
    		))
    		->add('paymentDateSearch', 'checkbox', array(
    			'required'=>false,
    			'data'=>($paymentDateSearch?true:false)
    		))
    		->add('exDivDateFrom', 'date', array(
    			'widget'=>'single_text',
    			'label'=>'Ex Dividend Date : ',
    			'data'=>$searchDateFrom,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('exDivDateTo', 'date', array(
    			'widget'=>'single_text',
    			'label'=>' - ',
    			'data'=>$searchDateTo,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('paymentDateFrom', 'date', array(
    			'widget'=>'single_text',
    			'label'=>'Payment Date : ',
    			'data'=>$searchPaymentDateFrom,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('paymentDateTo', 'date', array(
    			'widget'=>'single_text',
    			'label'=>' - ',
    			'data'=>$searchPaymentDateTo,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('sector', 'choice', array(
    			'choices'=>$empty_array+$searchSectors,
    			'label'=>'Sector : ',
    			'data'=>$searchSector,
			    'attr'=>array(
		    		'style'=>'width: 150px'
				)
    		))
    		->add('portfolio', 'choice', array(
    			'choices'=>$empty_array+$searchPortfolios,
    			'label'=>'Portfolio : ',
    			'data'=>$searchPortfolio,
			    'attr'=>array(
		    		'style'=>'width: 150px'
				)
    		))
    		->add('income', 'choice', array(
    			'choices'=>$searchIncomes,
    			'label'=>'Income : ',
    			'data'=>$searchIncome,
			    'attr'=>array(
		    		'style'=>'width: 100px'
				)
    		))
    		->add('orderby', 'choice', array(
    			'choices'=>$orderName,
    			'label'=>'Order By : ',
    			'data'=>$orderBy,
			    'attr'=>array(
		    		'style'=>'width: 80px'
				)
    		))
    		->add('search', 'submit')
    		->getForm();

/*
 * if we have date filter, delete all the unneccessary records
 */
    	if (count($dividends) && ($exDivDateSearch || $paymentDateSearch)) {
    		foreach ($dividends as $k=>$v) {
    			$delete=false;
				if ($exDivDateSearch) {
					if ($v['ExDivDate'] < $searchDateFrom->format('Y-m-d').' 00:00:00' || $v['ExDivDate'] > $searchDateTo->format('Y-m-d').' 23:59:59') {
						$delete=true;
					}
				}
				if ($paymentDateSearch) {
					if ($v['PaymentDate'] < $searchPaymentDateFrom->format('Y-m-d').' 00:00:00' || $v['PaymentDate'] > $searchPaymentDateTo->format('Y-m-d').' 23:59:59') {
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
        	'notes'			=> $this->getConfig('page_dividend')
        ));
    }
    
    
    public function updateAction() {
/*
 * show only the menu
 */
    	return $this->render('InvestShareBundle:Default:update.html.twig', array(
   			'message'	=> '',
    		'notes'		=> $this->getConfig('page_update')
    	));
    }
    
    
    public function notesAction($action, $id, $additional, Request $request) {
    	
    	$notes=array();
    	$message='';
    	
		$query='SELECT * FROM `Config` WHERE `name` like "page_%" ORDER BY `name`';
		
		$connection=$this->getDoctrine()->getConnection();
		$stmt=$connection->prepare($query);
		$stmt->execute();
		$results=$stmt->fetchAll();
		
		if ($results && count($results)) {
			foreach ($results as $result) {
				$notes[substr($result['name'], 5, strlen($result['name']))]=$result['value'];
			}
		}
		$form=$this->createFormBuilder()
			->add('save', 'submit', array(
				'label'=>'Save'
			))
			->getForm();
    	
		$form->handleRequest($request);

		if ($request->isMethod('POST')) {
			if ($form->isValid()) {
				
				$em=$this->getDoctrine()->getManager();

				$saved=false;
				foreach ($_POST as $k=>$v) {
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
    	

    	$em=$this->getDoctrine()->getManager();
    	 
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
			    	$form=$this->createFormBuilder($company)
			    		->add('id', 'hidden', array(
			    			'data'=>$company->getId()
			    		))
			    		->add('name', 'text', array(
			    			'label'=>'Name',
			    			'data'=>$company->getName()
			    		))
			    		->add('code', 'text', array(
			    			'label'=>'EPIC',
			    			'data'=>$company->getCode()
			    		))
			    		->add('sector', 'text', array(
			    			'label'=>'Sector',
		    				'required'=>false,
			    			'data'=>$company->getSector()
			    		))
			    		->add('currency', 'text', array(
			    			'label'=>'Currency',
		    				'required'=>true,
			    			'data'=>$company->getCurrency()
			    		))
			    		->add('frequency', 'text', array(
			    			'label'=>'Dividend Payments per Year',
		    				'required'=>false,
			    			'data'=>$company->getFrequency()
			    		))
			    		->add('altName', 'text', array(
			    			'label'=>'Alternative name',
		    				'required'=>false,
			    			'data'=>$company->getAltName()
			    		))
			    		->add('save', 'submit', array(
			    			'label'=>'Save'
			    		))
			    		->getForm();
			    	
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
		    		$form2=$this->createFormBuilder($dividend)
		    			->add('id', 'hidden', array(
		    				'data'=>$dividend->getId()
		    			))
		    			->add('CompanyId', 'hidden', array(
		    				'data'=>$id
		    			))
		    			->add('EPIC', 'text', array(
		    				'label'=>'EPIC',
		    				'read_only'=>true,
		    				'mapped'=>false,
		    				'data'=>$company->getCode()
		    			))
		    			->add('Company', 'text', array(
		    				'label'=>'Company',
		    				'read_only'=>true,
		    				'mapped'=>false,
		    				'data'=>$company->getName()
		    			))
		    			->add('DeclDate', 'date', array(
		    				'widget'=>'single_text',
		    				'label'=>'Declaration Date',
		    				'data'=>$dividend->getDeclDate(),
		    				'format'=>'dd/MM/yyyy',
		    				'attr'=>array(
		    					'class'=>'dateInput',
		    					'size'=>10
		    				)
		    			))
		    			->add('ExDivDate', 'date', array(
		    				'widget'=>'single_text',
		    				'label'=>'ExDiv Date',
		    				'data'=>$dividend->getExDivDate(),
		    				'format'=>'dd/MM/yyyy',
		    				'attr'=>array(
		    					'class'=>'dateInput',
		    					'size'=>10
		    				)
		    			))
		    			->add('Amount', 'text', array(
		    				'label'=>'Amount'.(($company->getCurrency()=='GBP')?(''):(' ('.$company->getCurrency().')')),
		    				'data'=>$dividend->getAmount()
		    			))
		    			->add('PaymentDate', 'date', array(
		    				'widget'=>'single_text',
		    				'label'=>'Payment Date',
		    				'data'=>$dividend->getPaymentDate(),
		    				'format'=>'dd/MM/yyyy',
		    				'empty_value'=>null,
		    				'required'=>false,
		    				'attr'=>array(
		    					'class'=>'dateInput',
		    					'size'=>10
		    				)
		    			))
		    			->add('PaymentRate', 'text', array(
		    				'label'=>'Payment Exchange Rate',
		    				'required'=>false,
		    				'read_only'=>($company->getCurrency()=='GBP'),
		    				'data'=>$dividend->getPaymentRate()
		    			))
		    			->add('Special', 'choice', array(
		    				'choices'=>array(0=>'No', 1=>'Yes'),
		    				'label'=>'Special Dividend',
		    				'expanded'=>false,
		    				'multiple'=>false,
		    				'data'=>$dividend->getSpecial()
		    			))
		    			->add('save', 'submit')
		    			->getForm();
		    	
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
		    							$dividend->setCreatedDate(new \DateTime("now"));
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

    	if ($request->isMethod('POST')) {
			if (isset($_POST['form']['company']) && $_POST['form']['company']) {
				$searchCompany=$_POST['form']['company'];
			}
			if (isset($_POST['form']['sector']) && $_POST['form']['sector']) {
				$searchSector=$_POST['form']['sector'];
			}
			if (isset($_POST['form']['list']) && $_POST['form']['list']) {
				$searchList=$_POST['form']['list'];
			}
			$pageStart=0;
				
			$this->getRequest()->getSession()->set('is_comp',
				array(
					'c'=>$searchCompany,
					'sc'=>$searchSector,
					'l'=>$searchList,
					'updated'=>date('Y-m-d H:i:s'
				)
			));
		} else {
			if (null !== ($this->getRequest()->getSession()->get('is_comp'))) {
				$data=$this->getRequest()->getSession()->get('is_comp');
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
					$this->getRequest()->getSession()->remove('is_comp');
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
		
		$searchCompanies=array();
		$searchSectors=array();
		$searchLists=array(
			'FTSE100'=>'FTSE 100',
			'FTSE250'=>'FTSE 250',
			'FTSESmallCap'=>'FTSE Small Cap'
		);

		$query='SELECT * FROM `Company` ORDER BY `Name`';
		
		$connection=$this->getDoctrine()->getConnection();
		$stmt=$connection->prepare($query);
		$stmt->execute();
		$results=$stmt->fetchAll();

    	$companyNames=array();
    	$dividends=array();
    	
    	if (count($results)) {
    		foreach ($results as $result) {
    			$searchCompanies[$result['id']]=$result['Name'];
    			if ($result['Sector']) {
    				$searchSectors[$result['Sector']]=$result['Sector'];
    			}
    		    			
    			$cId=$result['id'];

    			$d=$this->getDividendsForCompany($result['Code'], true);
    			if ($d && count($d)) {
					foreach ($d as $k=>$v) {
						$w1=strtotime($v['ExDivDate']);
						$w2=time()+$this->dividendWarningDays*24*60*60;
						$warning=(($w1>=time() && $w1<$w2)?(1):(0));
						
    					$d[$k]['warning']=$warning;
    						
						if ($warning) {
							$d[$k]['CompanyCode']=$result['Code'];
							$d[$k]['CompanyName']=$result['Name'];
							$d[$k]['Currency']=$result['Currency'];

							$warnings[]=$d[$k];
						}
    					
						$dividends[$cId]=$d;
					}
    			}
    		}
    		ksort($searchSectors);
    	}

    	$empty_array=array('0'=>'All');
		$searchForm=$this->createFormBuilder()
			->setAction($this->generateUrl('invest_share_company'))
    		->add('company', 'choice', array(
    			'choices'=>$empty_array+$searchCompanies,
    			'label'=>'Company : ',
		    	'data'=>$searchCompany,
    			'attr'=>array(
    				'style'=>'width: 200px'
    			)
		    ))
    		->add('sector', 'choice', array(
    			'choices'=>$empty_array+$searchSectors,
    			'label'=>'Sector : ',
		    	'data'=>$searchSector,
    			'attr'=>array(
    				'style'=>'width: 200px'
    			)
    		))
    		->add('list', 'choice', array(
    			'choices'=>$empty_array+$searchLists,
    			'label'=>'List : ',
		    	'data'=>$searchList,
    			'attr'=>array(
    				'style'=>'width: 100px'
    			)
    		))
    		->add('search', 'submit')
		    ->getForm();
    	
    		
		$results=$this->getDoctrine()
    		->getRepository('InvestShareBundle:Company')
    		->findBy(
    			$searchArray,
    			array(
    				'name'=>'ASC'
    			),
    			$this->pager,
    			$pageStart
    		);

		$query='SELECT SQL_CALC_FOUND_ROWS * FROM `Company`';
		if (count($searchArray)) {
			$query.=' WHERE 1';
			foreach ($searchArray as $k=>$v) {
				$query.=' AND (`'.$k.'`="'.$v.'")';
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
    		$query='SELECT * FROM `DirectorsDeals` WHERE `Code` IN (\''.implode('\',\'', $companyCodes).'\') ORDER BY `DealDate`, `Name`';
    		
    		$stmt=$connection->prepare($query);
    		$stmt->execute();
    		$results=$stmt->fetchAll();
    		
    		if ($results) {
    			foreach ($results as $result) {
    				$deals[$result['Code']][]=$result;
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
    		'notes'			=> $this->getConfig('page_company')
        ));
    }

    
    public function ddealsAction() {

    	$message='';
    	$deals=array();
    	$companyShares=array();
    	$companyNames=array();
    	$codes=array();
    	$summary=array();

    	$searchType=null;
    	$searchDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m')-1, date('d'), date('Y'))));
   		$searchDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), date('Y'))));
    	$searchLimit=$this->dealsLimit;
    	$searchCompany=null;
    	$searchPosition=null;
    	$searchFilter=1;
    	
    	$request=$this->getRequest();

    	if ($request->isMethod('POST')) {
    		if (isset($_POST['form']['type']) && $_POST['form']['type']) {
    			$searchType=$_POST['form']['type'];
    		}
    	    if (isset($_POST['form']['company']) && $_POST['form']['company']) {
    			$searchCompany=$_POST['form']['company'];
    		}
    		if (isset($_POST['form']['dateFrom']) && $_POST['form']['dateFrom']) {
    			$searchDateFrom=\DateTime::createFromFormat('d/m/Y', $_POST['form']['dateFrom']);
    		}
    		if (isset($_POST['form']['dateTo']) && $_POST['form']['dateTo']) {
    			$searchDateTo=\DateTime::createFromFormat('d/m/Y', $_POST['form']['dateTo']);
    		}
    	    if (isset($_POST['form']['position']) && $_POST['form']['position']) {
    			$searchPosition=$_POST['form']['position'];
    		}
    		if (isset($_POST['form']['limit']) && $_POST['form']['limit']) {
    			$searchLimit=$_POST['form']['limit'];
    		}
    		if (isset($_POST['form']['filter'])) {
    			$searchFilter=$_POST['form']['filter'];
    		}
    		
    		$this->getRequest()->getSession()->set('is_ddeals', array(
    				't'=>$searchType,
    				'c'=>$searchCompany,
    				'df'=>$searchDateFrom,
    				'dt'=>$searchDateTo,
    				'p'=>$searchPosition,
    				'l'=>$searchLimit,
    				'f'=>$searchFilter,
    				'updated'=>date('Y-m-d H:i:s')));
    	} else {
    		if (null !== ($this->getRequest()->getSession()->get('is_ddeals'))) {
    			$data=$this->getRequest()->getSession()->get('is_ddeals');
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
    				}
    			    if (isset($data['f'])) {
    					$searchFilter=$data['f'];
    				}
    			} else {
    				$this->getRequest()->getSession()->remove('is_ddeals');
    			}
    		}
    	}
    	 
    	$connection=$this->getDoctrine()->getConnection();
    	
    	$trades=$this->getTradesData(null, null, null, null);
    	
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

   		$companyNames=$this->getCompanyNames(($searchFilter)?(true):(false));
   		
   		$types=array();
   		
   		$positions=array();
   		
   		$query='SELECT'.
			' `Type`,'.
			' `Position`'.
			' FROM `DirectorsDeals`'.
			' WHERE LENGTH(`Type`)'.
			' GROUP BY `Type`, `Position`'.
			' ORDER BY `Type`, `Position`';
   		 
   		$stmt=$connection->prepare($query);
   		$stmt->execute();
   		$results=$stmt->fetchAll();
   		if ($results) {
   			foreach ($results as $result) {
   				$types[$result['Type']]=ucwords($result['Type']);
   				if (strlen($result['Position'])) {
   					$positions[$result['Position']]=ucwords($result['Position']);
   				}
   			}
   		}
   		 
    	if (count($companyNames)) {
    		$query='SELECT'.
    			' `Code`,'.
    			' SUM(`Shares`) as `Shares`'.
    			' FROM `DirectorsDeals`'.
    			' WHERE `Code` IN (\''.implode('\',\'', array_keys($companyNames)).'\')'.
    				' AND `DealDate` BETWEEN \''.$searchDateFrom->format('y-m-d').'\' AND \''.$searchDateTo->format('Y-m-d').'\''.
    			' GROUP BY `Code`'.
    			' HAVING `Shares`>='.$searchLimit;

    		$stmt=$connection->prepare($query);
    		$stmt->execute();
    		$results=$stmt->fetchAll();
    		if ($results) {
    			foreach ($results as $result) {
    				$codes[]=$result['Code'];
    			}
    		}
    	}
    	if (count($codes)) {
    		$query='SELECT `dd`.*, `c`.`lastPrice`'.
    			' FROM `DirectorsDeals` `dd`'.
    				' LEFT JOIN `Company` `c` ON `dd`.`Code`=`c`.`Code`'.
    			' WHERE `dd`.`Code` IN (\''.implode('\',\'', $codes).'\')'.
					(($searchType)?(' AND `dd`.`Type`=\''.$searchType.'\''):('')).
    				(($searchCompany)?(' AND `dd`.`Code`=\''.$searchCompany.'\''):('')).
    				(($searchPosition)?(' AND `dd`.`Position`=\''.$searchPosition.'\''):('')).
    				' AND `dd`.`DealDate` BETWEEN \''.$searchDateFrom->format('Y-m-d').'\' AND \''.$searchDateTo->format('Y-m-d').'\''.
				' ORDER BY `dd`.`Code`, `dd`.`DealDate`';

    		$stmt=$connection->prepare($query);
    		$stmt->execute();
    		$results=$stmt->fetchAll();
    		
    		if ($results) {
    			foreach ($results as $result) {
    				$result['Company']=$companyNames[$result['Code']];
   					$result['CurrentShares']=((isset($companyShares[$result['Code']]))?($companyShares[$result['Code']]):(0));
    				$result['CurrentValue']=$result['lastPrice'];
    				
    				$deals[]=$result;
    				
    				if (!isset($summary[$result['Type']])) {
    					$summary[$result['Type']]=array('Shares'=>0, 'Value'=>0);
    				}
    				$summary[$result['Type']]['Shares']+=$result['Shares'];
    				$summary[$result['Type']]['Value']+=$result['Value'];
    			}
    		}
    	}

    	$empty_array=array();
    	$empty_array['0']='All';
    	 
		$searchForm=$this->createFormBuilder()
			->add('type', 'choice', array(
				'label'=>'Type : ',
				'choices'=>$empty_array+$types,
				'data'=>$searchType,
				'required'=>true,
			))
			->add('position', 'choice', array(
				'label'=>'Position : ',
				'choices'=>$empty_array+$positions,
				'data'=>$searchPosition,
				'required'=>true,
			    'attr'=>array(
		    		'style'=>'width: 80px'
			    )
			))
			->add('company', 'choice', array(
				'label'=>'Company : ',
				'choices'=>($empty_array+$companyNames),
				'data'=>$searchCompany,
				'required'=>true,
			    'attr'=>array(
		    		'style'=>'width: 120px'
			    )
			))
			->add('dateFrom', 'date', array(
    			'widget'=>'single_text',
    			'label'=>'Date : ',
    			'data'=>$searchDateFrom,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('dateTo', 'date', array(
    			'widget'=>'single_text',
    			'label'=>' - ',
    			'data'=>$searchDateTo,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
			->add('limit', 'text', array(
				'label'=>'Limit : ',
				'data'=>$searchLimit,
				'attr'=>array(
					'size'=>10,
					'style'=>'width: 80px'
				)
			))
			->add('filter', 'choice', array(
				'label'=>'Filter : ',
				'choices'=>$empty_array+array('1'=>'Only hold'),
				'data'=>$searchFilter,
				'required'=>true,
			    'attr'=>array(
		    		'style'=>'width: 80px'
			    )
			))
			->add('search', 'submit', array(
				'label'=>'Search'
			))
			->getForm();
    	    	
    	return $this->render('InvestShareBundle:Default:directordeals.html.twig', array(
    		'searchForm'	=> $searchForm->createView(),
    		'deals' 		=> $deals,
    		'summary'		=> $summary,
    		'extra'			=> true,
    		'message' 		=> $message,
    		'notes'			=> $this->getConfig('page_deals')    			 
    	));	 
    }
    
    
    public function diaryAction() {

    	$message='';
    	$diary=array();

    	$searchType=null;
    	$searchDateFrom=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-6, date('Y'))));
   		$searchDateTo=new \DateTime(date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')+13, date('Y'))));
    	$searchCompany=null;
    	$searchFilter=null;
    	
    	$request=$this->getRequest();

    	if ($request->isMethod('POST')) {
    		if (isset($_POST['form']['type']) && $_POST['form']['type']) {
    			$searchType=$_POST['form']['type'];
    		}
    	    if (isset($_POST['form']['company']) && $_POST['form']['company']) {
    			$searchCompany=$_POST['form']['company'];
    		}
    		if (isset($_POST['form']['dateFrom']) && $_POST['form']['dateFrom']) {
    			$searchDateFrom=\DateTime::createFromFormat('d/m/Y', $_POST['form']['dateFrom']);
    		}
    		if (isset($_POST['form']['dateTo']) && $_POST['form']['dateTo']) {
    			$searchDateTo=\DateTime::createFromFormat('d/m/Y', $_POST['form']['dateTo']);
    		}
    		if (isset($_POST['form']['filter']) && $_POST['form']['filter']) {
    			$searchFilter=$_POST['form']['filter'];
    		}
    		
    		$this->getRequest()->getSession()->set('is_diary', array(
    				't'=>$searchType,
    				'c'=>$searchCompany,
    				'df'=>$searchDateFrom,
    				'dt'=>$searchDateTo,
    				'f'=>$searchFilter,
    				'updated'=>date('Y-m-d H:i:s')));
    	} else {
    		if (null !== ($this->getRequest()->getSession()->get('is_diary'))) {
    			$data=$this->getRequest()->getSession()->get('is_diary');
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
    				$this->getRequest()->getSession()->remove('is_diary');
    			}
    		}
    	}
    	 
    	$connection=$this->getDoctrine()->getConnection();
    	
   		$companyNames=$this->getCompanyNames(($searchFilter)?(true):(false));
// error_log('filter: ['.$searchFilter.']-'.(($searchFilter)?('true'):('false')));
   		$types=array();
   		$types['']='All';
   		   		
   		$query='SELECT'.
			' `Type`'.
			' FROM `Diary`'.
			' WHERE LENGTH(`Type`)'.
			' GROUP BY `Type`'.
			' ORDER BY `Type`';
   		 
   		$stmt=$connection->prepare($query);
   		$stmt->execute();
   		$results=$stmt->fetchAll();
   		if ($results) {
   			foreach ($results as $result) {
   				$types[$result['Type']]=ucwords($result['Type']);
   			}
   		}
   		$query='SELECT `d`.*, `c`.`lastPrice` as `CurrentValue`'.
   			' FROM `Diary` `d` LEFT JOIN `Company` `c` ON `d`.`Code`=`c`.`Code`'.
   			' WHERE '.(($searchFilter)?('`c`.`Code` IN (\''.implode('\',\'', array_keys($companyNames)).'\')'):('1')).
				(($searchType)?(' AND `d`.`Type`=\''.$searchType.'\''):('')).
   				(($searchCompany)?(' AND `d`.`Code`=\''.$searchCompany.'\''):('')).
   				' AND `d`.`Date` BETWEEN \''.$searchDateFrom->format('Y-m-d').'\' AND \''.$searchDateTo->format('Y-m-d').'\''.
			' ORDER BY `d`.`Code`, `d`.`Date`';
// print '<hr>'.$query;
   		$stmt=$connection->prepare($query);
   		$stmt->execute();
   		$diary=$stmt->fetchAll();

   		$empty_array=array();
   		$empty_array['0']='All';
   		
		$searchForm=$this->createFormBuilder()
			->add('type', 'choice', array(
				'label'=>'Type : ',
				'choices'=>$types,
				'data'=>$searchType,
				'required'=>false,
			    'attr'=>array(
		    		'style'=>'width: 150px'
			    )
			))
			->add('filter', 'choice', array(
				'label'=>'Filter : ',
				'choices'=>$empty_array+array('1'=>'Only hold'),
				'data'=>$searchFilter,
				'required'=>true,
			    'attr'=>array(
		    		'style'=>'width: 80px'
			    )
			))
			->add('company', 'choice', array(
				'label'=>'Company : ',
				'choices'=>$empty_array+$companyNames,
				'data'=>$searchCompany,
				'required'=>true,
			    'attr'=>array(
		    		'style'=>'width: 150px'
			    )
			))
			->add('dateFrom', 'date', array(
    			'widget'=>'single_text',
    			'label'=>'Date : ',
    			'data'=>$searchDateFrom,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
    		->add('dateTo', 'date', array(
    			'widget'=>'single_text',
    			'label'=>' - ',
    			'data'=>$searchDateTo,
			    'format'=>'dd/MM/yyyy',
			    'attr'=>array(
		    		'class'=>'dateInput',
			    	'size'=>10,
		    		'style'=>'width: 90px'
			    )
    		))
			->add('search', 'submit', array(
				'label'=>'Search'
			))
			->getForm();
    	    	
    	return $this->render('InvestShareBundle:Default:diary.html.twig', array(
    		'searchForm'	=> $searchForm->createView(),
    		'diary' 		=> $diary,
    		'extra'			=> true,
    		'message' 		=> $message,
    		'notes'			=> $this->getConfig('page_diary')
    	));	 
    }
    
    
    public function tradeAction($action, $id, $additional, $extra) {
/*
 * add/edit/delete trade details
 */
    	
    	$request=$this->getRequest();
    	
		$message='';
		
		$searchCompany=0;
		$searchPortfolio=0;
		$searchSector='';
		$searchSold=0;
		
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
		$query=$em->createQuery('SELECT c.id, c.code, c.name, c.sector FROM InvestShareBundle:Company c ORDER BY c.name');
		$results=$query->getResult();
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
		$query=$em->createQuery('SELECT p.id, p.name, p.clientNumber FROM InvestShareBundle:Portfolio p');
		$results=$query->getResult();
		
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
					$form=$this->createFormBuilder($trade)
			    		->add('id', 'hidden', array(
			    			'data'=>$trade->getId()
			    		))
			    		->add('portfolioid', 'choice', array(
			    			'choices'=>$portfolios,
			    			'data'=>$trade->getPortfolioId()
			    		))
			    		->add('companyId', 'choice', array(
			    			'choices'=>$companies,
			    			'data'=>$trade->getCompanyId()
			    		))
			    		->add('pe_ratio', 'text', array(
			    			'data'=>$trade->getPERatio(),
			    			'required'=>false
			    		))
			    		->add('save', 'submit')
			    		->getForm();
			    	
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
					$form2=$this->createFormBuilder($tradeTransaction)
			    		->add('id', 'hidden', array(
			    			'data'=>$tradeTransaction->getId()
			    		))
			    		->add('portfolioId', 'choice', array(
							'label'=>'Portfolio',
			    			'choices'=>(($trade->getPortfolioId())?(array($portfolios[$trade->getPortfolioId()])):($portfolios)),
			    			'data'=>$trade->getPortfolioId(),
			    			'mapped'=>false,
			    			'read_only'=>(($trade->getPortfolioId())?true:false)
			    		))
			    		->add('companyId', 'choice', array(
			    			'label'=>'Company',
			    			'choices'=>(($trade->getCompanyId())?(array($companies[$trade->getCompanyId()])):($companies)),
			    			'data'=>$trade->getCompanyId(),
			    			'mapped'=>false,
			    			'read_only'=>(($trade->getCompanyId())?true:false)
			    		))
			    		->add('type', 'choice', array(
			    			'choices'=>(($tradeTransaction->getId())?($tradeTransaction->getType()?(array(1=>'Sell')):(array(0=>'Buy'))):(array(0=>'Buy', 1=>'Sell'))),
			    			'data'=>$tradeTransaction->getType(),
			    			'read_only'=>true
			    		))
			    		->add('tradeDate', 'date', array(
			    			'label'=>'Trade date',
			    			'widget'=>'single_text',
			    			'data'=>$tradeTransaction->getTradeDate(),
			    			'format'=>'dd/MM/yyyy',
			    			'attr'=>array(
		    					'class'=>'dateInput',
			    				'size'=>10
							)
			    		))
			    		->add('settleDate', 'date', array(
			    			'label'=>'Settle date',
			    			'widget'=>'single_text',
			    			'data'=>$tradeTransaction->getSettleDate(),
			    			'format'=>'dd/MM/yyyy',
			    			'attr'=>array(
		    					'class'=>'dateInput',
			    				'size'=>10
							)
			    		))
			    		->add('reference', 'text', array(
			    			'data'=>$tradeTransaction->getReference()
			    		))
			    		->add('description', 'text', array(
			    			'required'=>false,
			    			'data'=>$tradeTransaction->getDescription()
			    		))
			    		->add('quantity', 'text', array(
			    			'data'=>$tradeTransaction->getQuantity()
			    		))
			    		->add('unitPrice', 'text', array(
			    			'label'=>'Unit cost (p)',
			    			'data'=>$tradeTransaction->getUnitPrice()
			    		))
			    		->add('cost', 'text', array(
			    			'label'=>'Cost (£)',
			    			'data'=>$tradeTransaction->getCost()
			    		))
			    		->add('save', 'submit')
			    		->getForm();
			    	
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

					$form3=$this->createFormBuilder($tradeTransaction)
			    		->add('id', 'hidden', array(
			    			'data'=>$tradeTransaction->getId()
			    		))
			    		->add('tradeId', 'hidden', array(
			    			'data'=>$trade->getId()
			    		))
			    		->add('type', 'choice', array(
			    			'choices'=>array(1=>'Sell'),
			    			'data'=>$tradeTransaction->getType(),
			    			'read_only'=>true
			    		))
			    		->add('portfolioName', 'text', array(
			    			'data'=>$portfolios[$trade->getPortfolioId()],
			    			'disabled'=>true,
			    			'mapped'=>false
			    		))
			    		->add('companyName', 'text', array(
			    			'data'=>$companies[$trade->getCompanyId()],
			    			'disabled'=>true,
			    			'mapped'=>false
			    		))
			    		->add('tradeDate', 'date', array(
			    			'widget'=>'single_text',
			    			'data'=>$tradeTransaction->getTradeDate(),
			    			'format'=>'dd/MM/yyyy',
			    			'empty_value'=>null,
			    			'required'=>false,
			    			'attr'=>array(
		    					'class'=>'dateInput',
			    				'size'=>10
			    			)
			    		))
			    		->add('settleDate', 'date', array(
			    			'label'=>'Settle date',
			    			'widget'=>'single_text',
			    			'data'=>$tradeTransaction->getSettleDate(),
			    			'format'=>'dd/MM/yyyy',
			    			'attr'=>array(
		    					'class'=>'dateInput',
			    				'size'=>10
							)
			    		))
			    		->add('reference', 'text', array(
			    			'data'=>$tradeTransaction->getReference()
			    		))
			    		->add('description', 'text', array(
			    			'required'=>false,
			    			'data'=>$tradeTransaction->getDescription()
			    		))
			    		->add('quantity', 'text', array(
			    			'label'=>'Quantity',
			    			'data'=>$tradeTransaction->getQuantity(),
			    			'required'=>true
			    		))
			    		->add('unitPrice', 'text', array(
			    			'label'=>'Unit Price (p)',
			    			'data'=>$tradeTransaction->getUnitPrice(),
			    			'required'=>true
			    		))
			    		->add('cost', 'text', array(
			    			'label'=>'Cost (£)',
			    			'data'=>$tradeTransaction->getCost(),
			    			'required'=>true
			    		))
			    		->add('save', 'submit')
						->getForm();
					
					$form3->handleRequest($request);
					
					$validator=$this->get('validator');
					$errors=$validator->validate($tradeTransaction);
						
					if (count($errors) > 0) {
						$message=(string)$errors;
					} else {
							
						if ($form3->isValid()) {

							switch ($action) {
			   					case 'addsell' : {

					   				$em = $this->getDoctrine()->getManager();
							    			
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
			   						
					   				$em = $this->getDoctrine()->getManager();
							    			
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

/*
 * filter by company or portfolio
 */
		if ($request->isMethod('POST')) {
			if (isset($_POST['form']['company']) && $_POST['form']['company']) {
				$searchCompany=$_POST['form']['company'];
			}
	    	if (isset($_POST['form']['portfolio']) && $_POST['form']['portfolio']) {
				$searchPortfolio=$_POST['form']['portfolio'];
			}
			if (isset($_POST['form']['sector']) && $_POST['form']['sector']) {
				$searchSector=$_POST['form']['sector'];
			}
			if (isset($_POST['form']['sold']) && $_POST['form']['sold']) {
				$searchSold=$_POST['form']['sold'];
			}
			
			$this->getRequest()->getSession()->set('is_trade', array('c'=>$searchCompany, 'p'=>$searchPortfolio, 's'=>$searchSold, 'sc'=>$searchSector, 'updated'=>date('Y-m-d H:i:s')));
		} else {
			if (null !== ($this->getRequest()->getSession()->get('is_trade'))) {
				$data=$this->getRequest()->getSession()->get('is_trade');
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
				} else {
					$this->getRequest()->getSession()->remove('is_trade');
				}
			}
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
			$empty_array=array('0'=>'All');
			$searchForm=$this->createFormBuilder()
				->setAction($this->generateUrl('invest_share_trade'))
	    		->add('company', 'choice', array(
	    			'choices'=>$empty_array+$companies,
	    			'label'=>'Company : ',
			    	'required'=>true,
	    			'data'=>$searchCompany,
	    			'attr'=>array(
	    				'style'=>'width: 200px'
	    			)
			    ))
	    		->add('portfolio', 'choice', array(
	    			'choices'=>$empty_array+$portfolios,
	    			'label'=>'Portfolio : ',
	    			'required'=>true,
	    			'data'=>$searchPortfolio,
	    			'attr'=>array(
	    				'style'=>'width: 200px'
	    			)
	    		))
	    		->add('sector', 'choice', array(
	    			'choices'=>$empty_array+$sectors,
	    			'label'=>'Sector : ',
	    			'required'=>true,
			    	'data'=>$searchSector,
	    			'attr'=>array(
	    				'style'=>'width: 200px'
	    			)
	    		))
			    ->add('sold', 'choice', array(
	    			'choices'=>array(0=>'All', 1=>'Unsold', 2=>'Sold'),
	    			'label'=>'Status : ',
	    			'required'=>true,
			    	'data'=>$searchSold,
			    	'attr'=>array(
			    		'style'=>'width: 80px'
			    	)
			    ))
			    ->add('search', 'submit')
			    ->getForm();
			    	
			$searchForm->handleRequest($request);
			
			$searchFormView=$searchForm->createView();
		}

		$combined=$this->getTradesData($searchPortfolio, $searchCompany, $searchSector, $searchSold);

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
					'currencyRates'	=> $this->getCurrencyRates(),
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
					'currencyRates'	=> $this->getCurrencyRates(),
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
	    			'name'			=> 'Trade',
	    			'message'		=> $message,
					'errors'		=> $errors,
					'form'			=> (($show)?($formView):(null)),
					'showForm'		=> ($show?$showForm:null),
					'searchForm'	=> ($show?null:$searchFormView),
					'formTitle'		=> $formTitle,
					'trades'		=> $combined,
					'companies'		=> $companies,
					'companyCodes'	=> $companyCodes,
					'portfolios'	=> $portfolios,
					'currencyRates'	=> $this->getCurrencyRates(),
					'dividends'		=> $dividends,
					'notes'			=> $this->getConfig('page_trade')
	    		));
				break;
			}
			
		}
    }

    
    public function portfolioAction($action, $id, $additional, Request $request) {
/*
 * add/edit/delete portfolio details
 */

    	$message='';
		$showForm=1;
		$show=false;
		$formTitle='Portfolio Details';
		$errors=array();
		$searchPortfolio=null;

		$em = $this->getDoctrine()->getManager();		
		
		$portfolio=new Portfolio();
		$portfolioTransaction=new PortfolioTransaction();
		
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
				)	;
		
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
				$form=$this->createFormBuilder($portfolio)
			    	->add('id', 'hidden', array(
			    		'data'=>$portfolio->getId()
			    	))
			    	->add('name', 'text', array(
			    		'label'=>'Name',
			    		'data'=>$portfolio->getName()
			    	))
			    	->add('clientNumber', 'text', array(
			    		'label'=>'Client Number',
			    		'data'=>$portfolio->getClientNumber()
			    	))
			    	->add('family', 'text', array(
			    		'label'=>'Number of family member',
			    		'data'=>$portfolio->getFamily()
			    	))
			    	->add('save', 'submit')
			    	->getForm();
			    	
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
				$form2=$this->createFormBuilder($portfolioTransaction)
					->add('id', 'hidden', array(
						'data'=>$portfolioTransaction->getId()
					))
					->add('portfolioid', 'hidden', array(
						'data'=>$portfolioTransaction->getPortfolioId()
					))
					->add('date', 'date', array(
						'label'=>'Date',
						'widget'=>'single_text',
    					'format'=>'dd/MM/yyyy',
						'data'=>$portfolioTransaction->getDate(),
						'attr'=>array(
		    				'class'=>'dateInput',
							'size'=>10
						)
					))
					->add('amount', 'text', array(
						'label'=>'Amount',
						'data'=>$portfolioTransaction->getAmount()
					))
			    	->add('reference', 'text', array(
			    		'label'=>'Reference',
			    		'data'=>$portfolioTransaction->getReference()
			    	))
					->add('description', 'text', array(
			    		'label'=>'Description',
			    		'data'=>$portfolioTransaction->getDescription()
			    	))
					->add('save', 'submit')
					->getForm();
					
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
    	$trades=$this->getTradesData($searchPortfolio, null, null, null);
    	$portfolios=array();
    	if (count($trades)) {
    		
    		$currencyRates=$this->getCurrencyRates();    	

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
    	$links[]=array('name'=>'Update', 'url'=>$this->generateUrl('invest_share_update'));
//    	$links[]=array('name'=>'Hello html', 'target'=>'_blank', 'url'=>$this->generateUrl('invest_share_hello', array('name'=>'test', '_format'=>'html')));
//    	$links[]=array('name'=>'Hello pdf', 'target'=>'_blank', 'url'=>$this->generateUrl('invest_share_hello', array('name'=>'test', '_format'=>'pdf')));
    	 
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
// print '<hr>i:'.$i;
// print '<br>url:'.$html_sources[$i];
	    		try {
//	    			$rss_result=@file_get_contents($html_sources[$i]);
	    			
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
// print '<br>length:'.strlen($rss_result);
/*
 * delete everything before and after the neccessary data then clear the remaining content
 */
	    		if (strlen($rss_result)) {
// print '<br><b>'.htmlentities($rss_result).'</b>';
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
// print '<br>count:'.count($result);	    				
    				if (count($result) == 1) {
/*
 * if the html code still in wrong format (missing </tr> tag
 */	    				
    					foreach ($result as $values) {
    						$k=0;
    						while ($k < count($values)) {

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
    						$declarationDate=strtotime($value[7]);
    						if ($declarationDate > time()) {
    							$declarationDate=strtotime($value[7].' '.(date('Y')-1));
    						}
    						$exDivDate=strtotime($value[8]);
    						if ($exDivDate < $declarationDate) {
    							$exDivDate=strtotime($value[8].' '.(date('Y')+1));
    						}
    						$paymentDate=strtotime($value[9]);
    						if ($paymentDate < $exDivDate) {
    							$paymentDate=strtotime($value[9].' '.(date('Y')+1));
    						}

    						$currency='GBP';
							if (strpos($values[$k+5], '$') !== false) {
								$currency='USD';
							}
    						if (strpos($values[$k+5], '€') !== false) {
								$currency='EUR';
							}
    						$price=preg_replace('/[^0-9\.]+/', '', $value[4]);
    						$price=sprintf('%.06f', $price);
    								
    						$special=(strpos($values[$k+6], '*') !== false);
    						
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
				'data' => $complete,
				'message' => $message,
				'debug_message' => $debug_message
		));
	}
    
	
	public function updatedealsAction($page) {

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
				
// print '<hr>'.$url.'<hr>'.print_r($data->aaData, true).'<hr>';
			if (isset($data->aaData) && count($data->aaData)) {
				foreach ($data->aaData as $d) {
					$date1=\DateTime::createFromFormat('d M, Y H:i:s', $d[1].' 00:00:00');
					$d1=array(
						'DeclDate'=>$date1,
						'DealDate'=>$date1,
						'Type'=>$d[2],
						'Code'=>$d[3],
						'Company'=>$d[4],
						'Name'=>$d[7],
						'Position'=>$d[8],
						'Shares'=>str_replace(',', '', $d[9]),
						'Price'=>str_replace(',', '', $d[10]),
						'Value'=>str_replace(',', '', $d[11])
					);
					if ($this->addDirectorsDeals($d1)) {
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
    						'DeclDate'=>$date1,
    						'DealDate'=>$date2,
    						'Type'=>$d[3],
    						'Code'=>$d[4],
    						'Company'=>$d[5],
    						'Name'=>$d[6],
    						'Position'=>$d[7],
    						'Shares'=>$d[8],
    						'Price'=>$d[9],
    						'Value'=>$d[10]
    					);
    					if ($this->addDirectorsDeals($d1)) {
    						$deals[]=$d1;
    					}
    				}
    			}
			}			 
		}
		
		
		return $this->render('InvestShareBundle:Default:directordeals.html.twig', array(
				'deals'		=> $deals,
				'message'	=> $message,
				'notes'		=> $this->getConfig('note_deals')
		));
	}
	
    
	public function updatediaryAction($date) {

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
// print '<br>2 dates:'.print_r($dates, true).'<br>';
				$d=mktime(0, 0, 0, date('m', strtotime($dates[0])), date('d', strtotime($dates[0])), date('Y', strtotime($dates[0])));
				$d2=mktime(0, 0, 0, date('m', strtotime($dates[1])), date('d', strtotime($dates[1])), date('Y', strtotime($dates[1])));

				while ($d <= $d2) {
					$s='http://'.$html_host.'/financial-diary.asp?date='.date('j-M-Y', $d);
					$urls[]=$s;
// print '<br>url:'.$s;
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
		    			if ($this->addFinancialDiary($result)) {
		    				$diary[]=$result;
		    			}
					}
				}			 
			}
		}
		
		
		return $this->render('InvestShareBundle:Default:diarylist.html.twig',
			array(
				'links'		=> $links,
				'diary'		=> $diary,
				'message'	=> $message,
				'notes'		=> $this->getConfig('note_diary')
			)
		);
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
    	$updated_trades=0;
		$completed=array();
		$data1=array();
		$new_data=array();

		$em=$this->getDoctrine()->getManager();
/*
 * check the last update time
 */		
		$latestDate=new \Datetime("now");
		$query=$em->createQuery('SELECT max(sp.date) as date FROM InvestShareBundle:StockPrices sp GROUP BY sp.date');
		$results=$query->getResult();
		if (count($results)) {
			foreach ($results as $result) {
				$latestDate=new \DateTime($result['date']);
			}
		}
		
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
// error_log('i:'.$i.', try:'.$try.', url:'.$html_sources[$i]);

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
									'Date'=>$ts,
									'Price'=>$data1[2],
									'Changes'=>$data1[3],
		    						'List'=>$list_sources[$i],
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
				    		if ($result) {
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
				    					$value['Changes']=sprintf('%.4f', $value['Price']-$lp[0]->getPrice());
				    					$completed[$key]['Changes']=$value['Changes'];
				    					 
				    					if ($value['Changes'] > 0) {
				    						$diff=$value['Changes'] / $value['Price'];
				    					} else {
				    						$diff=sprintf('%.4f', $lp[0]->getPrice()-$value['Price']) / $lp[0]->getPrice();
				    					}
				    					 
				    					if ($diff > ($this->maxChanges/100)) {
				    						$ok=false;
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

				    		if ($ok) {
					    		$StockPrices=new StockPrices();
					    		
								$StockPrices->setCode($value['Code']);
					    		$StockPrices->setDate(new \DateTime($value['Date']));
					    		$StockPrices->setPrice($value['Price']);
					    		$StockPrices->setChanges($value['Changes']);
					    		 
					    		$em->persist($StockPrices);
					    		$em->flush($StockPrices);
					    		
					    		$completed[$key]['newPrice']=1;
					    		
					    		if ($StockPrices->getId()) {
					    			$updated_prices++;
					    		}
					    		
					    		$spw=$this->getDoctrine()
					    			->getRepository('InvestShareBundle:StockPricesWrong')
					    			->findOneBy(array(
					    				'code'=>$value['Code']
					    		));
					    		 
					    		if ($spw) {
					    			$em->remove($spw);
					    			$em->flush();
					    		}
				    		} else {
/*
 * anyway the new data should be wrong
 */
				    			error_log('Possibly wrong data : '.print_r($value, true));
				    			$msg[]='['.$value['Name'].'] - ['.$value['Code'].'] - ['.$value['Price'].'] - ['.$value['Changes'].']';
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
				    			$company->setLastPriceDate(new \DateTime($value['Date']));
				    			$company->setLastChange($value['Changes']);
				    			$company->setList($value['List']);
				    			 
				    			$em->persist($company);
				    			$em->flush($company);
				    			
				    			if ($company->getId()) {
				    				$new_company++;
				    			}
				    		} else {
/*
 * Update stock prices for all the trades where the same company
*/
			    				$company->setLastPrice($value['Price']);
			    				$company->setLastPriceDate(new \DateTime($value['Date']));
			    				$company->setLastChange($value['Changes']);
			    				$company->setList($value['List']);
			    				 
			    				$em->flush();

/*
 * if any trade data exists with this company code, update with the last price
 */
				    		}
				    	}
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
       	if ($updated_prices) {
    		$message.='<br>Updated '.$updated_prices.' prices';
    	}
    	if ($updated_trades) {
    		$message.='<br>Updated '.$updated_trades.' trades';
    	}
    	if (count($msg)) {
    		$message.='<br>Possibly wrong data:<br>'.implode('<br>', $msg);
    	}
    	 
    	return $this->render('InvestShareBundle:Default:pricelist.html.twig', array(
    		'data' => $completed,
    		'refresh' => (($remains > 0)?($remains):($this->refresh_interval)),
    		'message' => $message,
    		'debug_message' => $debug_message
    	));
    }

    
    public function pricelistAction($date, $export) {

    	$request=$this->getRequest();
    	$prices=array();
//    	$companies=array();
    	$codes=array();
		$message='';
    	$em=$this->getDoctrine()->getManager();
    	$connection=$em->getConnection();
    	 
/*
 * if form posted, use the selected timestamp to show the updated prices on that time
 */
		$date=((isset($_POST['form']['date']))?($_POST['form']['date']):($date));
		if (isset($_POST['form']['startDate'])) {
			$startDate=\DateTime::createFromFormat('d/m/Y', $_POST['form']['startDate']);
		} else {
			$startDate=new \DateTime('-1 day');
		}
		$startDate->setTime(0, 0, 0);
		
    	if (isset($_POST['form']['endDate'])) {
			$endDate=\DateTime::createFromFormat('d/m/Y', $_POST['form']['endDate']);
		} else {
			$endDate=new \DateTime('now');
		}
		$endDate->setTime(0, 0, 0);
		
		$list=((isset($_POST['form']['list']))?($_POST['form']['list']):(0));
		$sector=((isset($_POST['form']['sector']))?($_POST['form']['sector']):(0));
		

		$ftseList=array(
				'0'=>'All',
				'\'FTSE100\',\'FTSE250\''=>'FTSE 100 & 250',
				'\'FTSE100\''=>'FTSE 100',
				'\'FTSE250\''=>'FTSE 250',
				'\'FTSESmallCap\''=>'FTSE Small Cap'
		);
		
		
		$sectorList=array();
		$query=$em->createQuery('SELECT c.sector FROM InvestShareBundle:Company c WHERE LENGTH(c.sector)>0 GROUP BY c.sector ORDER BY c.sector');
		$results=$query->getResult();
		if (count($results)) {
			$sectorList[0]='All';
			foreach ($results as $result) {
				$sectorList[$result['sector']]=$result['sector'];
			}
		}
		

    	if ($date) {
/*
 * if date(timestamp) selected, show that
 */
    		$query='SELECT `c`.`Code`, `c`.`Name`, `c`.`Sector`, `c`.`List`, `p`.`Price`, `p`.`Changes`, `p`.`Date` FROM `Company` `c` JOIN `StockPrices` `p` ON `c`.`Code`=`p`.`Code` AND `p`.`Date`=:pDate';
    		 
    		$stmt=$connection->prepare($query);
    		$stmt->bindValue('pDate', date('Y-m-d H:i:s', $date));
    		$stmt->execute();
    		$prices1=$stmt->fetchAll();
    		
    		
    		if (count($prices1)) {
    			foreach ($prices1 as $pr1) {
    				$codes[$pr1['Code']]=$pr1['Code'];
    				$prices[]=array(
   						'Code'=>$pr1['Code'],
   						'Name'=>$pr1['Name'],
	    				'Sector'=>$pr1['Sector'],
	    				'List'=>$pr1['List'],
    					'Price'=>$pr1['Price'],
   						'Changes'=>$pr1['Changes'],
   						'Date'=>date('d/m/Y H:i:s', strtotime($pr1['Date']))
    				);
    			}
    		}
    		
    	} else {
/*
 * else show the latest data
 */
    		
    		$dql='SELECT'.
    			' c.code,'.
    			' c.name,'.
    			' c.sector,'.
    			' c.list,'.
    			' c.lastPrice as price,'.
    			' c.lastChange as changes,'.
    			' c.lastPriceDate as date'.
    			' FROM InvestShareBundle:Company c'.
    			' WHERE 1=1'.
    			((strlen($list)>1)?(' AND c.list IN ('.$list.')'):('')).
    			((strlen($sector)>1)?(' AND c.sector=\''.$sector.'\''):('')).
    			' ORDER BY c.name';

    		$query=$em->createQuery($dql);
			$prices1=$query->getResult();

   	    	if (count($prices1)) {
	    		foreach ($prices1 as $pr1) {
	    			$class='';
	    			if ($pr1['date'] != null && $pr1['date']->format('Y-m-d H:i:s') >= date('Y-m-d H:i:s', time()-15*60)) {
	    				$class='updatedRecently';
	    			}
	    			if ($pr1['date'] != null && $pr1['date']->format('Y-m-d H:i:s') < date('Y-m-d H:i:s', time()-8*60*60)) {
	    				$class='updatedLong';
	    			}
	    			$codes[$pr1['code']]=$pr1['code'];
	    			$prices[]=array(
	    				'Code'=>$pr1['code'],
	    				'Name'=>$pr1['name'], // companies[$pr1['code']],
	    				'Sector'=>$pr1['sector'],
	    				'List'=>$pr1['list'],
	    				'Price'=>$pr1['price'],
	    				'Changes'=>$pr1['changes'],
	    				'Date'=>(($pr1['date'] == null || $pr1['date']->format('Y-m-d H:i:s')<0)?(''):($pr1['date']->format('d/m/Y H:i:s'))),
	    				'Class'=>$class
	    			);
	    		}
	    	}
    	}

// create a form to select multiple companies to compare
// and show them in a chart

    	$fb=$this->createFormBuilder()
    		->add('submit', 'submit', array(
    				'label'=>'Compare'
    		));
    	
    	if (count($prices)) {
   			foreach ($codes as $result) {
   				$c=str_replace('.', '_', $result);
   				$fb->add($c, 'checkbox', array(
					'label'=>$result,
					'required'=>false
   				));
    		}
    		
    	} else {

    		$allCompanies=$this->getDoctrine()
    			->getRepository('InvestShareBundle:Company')
    			->findBy(
    				array(),
    				array(
   						'name'=>'ASC'
    				)
    			);
    		 
    		
    		if (count($allCompanies)) {
    			foreach ($allCompanies as $result) {
    				$c=str_replace('.', '_', $result->getCode());
    				$fb->add($c, 'checkbox', array(
   						'label'=>$result->getCode(),
    					'value'=>1,
   						'required'=>false
    				));
    			}
    		}
    		
    	}
    	
    	$form=$fb->getForm();
    	 
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
    	
    	$availableDates=array();

    	$query='SELECT `date`'.
    		' FROM `StockPrices`'.
    		' WHERE 1'.
    		(($startDate)?(' AND `date`>="'.$startDate->format('Y-m-d H:i:s').'"'):('')).
    		(($endDate)?(' AND `date`<="'.$endDate->format('Y-m-d H:i:s').'"'):('')).
    		' GROUP BY `date`'.
    		' ORDER BY `date` DESC';

		$stmt=$connection->query($query);
		$availableDates[0]='Show latest prices';
		while ($result = $stmt->fetch()) {
			$availableDates[strtotime($result['date'])]=date('d/m/Y H:i:s', strtotime($result['date']));
		}
		
		$datesForm=$this->createFormBuilder()
	    	->add('date', 'choice', array(
	    		'choices'=>$availableDates,
	    		'label'=>'Available updates : ',
			    'data'=>'',
	    		'attr'=>array(
	    			'style'=>'width: 150px'
	    		)
			))
	    	->add('startDate', 'datetime', array(
	    		'label'=>'Updated : ',
			    'data'=>$startDate,
		    	'widget'=>'single_text',
   				'format'=>'dd/MM/yyyy',
		    		'attr'=>array(
		    			'class'=>'dateInput',
		    			'size'=>10
		    		)
	    	))
	    	->add('endDate', 'datetime', array(
	    		'label'=>' - ',
			    'data'=>$endDate,
		    	'widget'=>'single_text',
		    	'format'=>'dd/MM/yyyy',
		    	'attr'=>array(
		    		'class'=>'dateInput',
		    		'size'=>10
		    	)
	    	))
	    	->add('sector', 'choice', array(
	    		'label'=>'Sector : ',
	    		'choices'=>$sectorList,
			    'data'=>$sector,
		    	'attr'=>array(
		    		'style'=>'width: 120px'
		    	)
	    	))
	    	->add('list', 'choice', array(
	    		'label'=>'List : ',
	    		'choices'=>$ftseList,
			    'data'=>$list,
		    	'attr'=>array(
		    		'style'=>'width: 120px'
		    	)
	    	))
	    	->add('search', 'submit', array(
				'label'=>'Select'
			))
		    ->getForm();
			    	
		$datesForm->handleRequest($request);

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
	   			'datesForm' => $datesForm->createView(),
				'form'		=> $form->createView(),
	   			'data'		=> $prices,
	   			'message'	=> $message,
	    		'notes'		=> $this->getConfig('page_pricelist')
	    	));
			
		}
    }

    
    public function pricesAction($company) {
/*
 * prices for 1 or more company with graph
 */
    	$request=$this->getRequest();
    	$em=$this->getDoctrine()->getManager();
    	$connection=$em->getConnection();

    	$company=str_replace('_', '.', $company);
    	
		$company2=((isset($_POST['form']['company']))?($_POST['form']['company']):($company));
		
		if ($company != $company2) {
// error_log('redirect');
			return $this->redirect($this->generateUrl('invest_share_prices', array('company'=>$company2)));

		}
    	 
    	$message='';
    	$companies=array();

        $query='SELECT `code`, `name`, `list`'.
    		' FROM `Company`'.
    		' ORDER BY `name`';

		$stmt=$connection->query($query);
		while ($result = $stmt->fetch()) {
    		$companies[$result['code']]=$result['name'].' ('.$result['list'].')';
		}
    	
		$selectForm=$this->createFormBuilder()
	    	->add('company', 'choice', array(
	    		'choices'=>$companies,
	    		'label'=>'Company : ',
			    'data'=>$company,
	    		'attr'=>array(
	    			'style'=>'width: 200px'
	    		)
			))
		    ->add('search', 'submit', array(
				'label'=>'Select'
			))
		    ->getForm();
			    	

		$selectForm->handleRequest($request);

		return $this->render('InvestShareBundle:Default:pricesgraph.html.twig', array(
    		'selectForm'	=> $selectForm->createView(),
    		'company'		=> $company,
    		'message'		=> $message
    	));
    }
    
    
    public function currencyAction($currency) {

    	$limit=100;
    	$message='';
    	$data=array();
    	$dates=array();
    	$currencies=array();
    	$search=array();
    	$updated=array();
    	
    	$this->currencyNeeded=$this->getCurrencyList();
    	
    	if ($currency && in_array($currency, $this->currencyNeeded)) {
    		$search=array('currency'=>$currency);
    	}
    	
    	if (!$currency) {

	    	$results=$this->getDoctrine()
	    		->getRepository('InvestShareBundle:Currency')
	    		->findBy($search, array('updated'=>'DESC', 'currency'=>'ASC'), $limit*count($this->currencyNeeded));
	    	
	    	if ($results && count($results)) {
	    		foreach ($results as $result) {
	    			if (in_array($result->getCurrency(), $this->currencyNeeded)) {
	    				if (!in_array($result->getCurrency(), $currencies)) {
	    					$currencies[]=$result->getCurrency();
	    				}
	    				$updated=explode('-', $result->getUpdated()->format('Y-m-d-H-i-s'));
	    				$data[$result->getCurrency()][$result->getUpdated()->format('Y-m-d-H-i-s')]=array(
	    					'Rate'=>$result->getRate(),
	    					'Date'=>$updated
	    					);
	    				$dates[$result->getUpdated()->format('Y-m-d-H-i-s')]=$result->getUpdated()->format('d/m/Y H:i');
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
	    				ksort($data[$cur]);
	    			}
	    		}
	    	}
	    	
	    	$fb=$this->createFormBuilder()
	    		->setAction($this->generateUrl('invest_share_currency'))
	    		->add('submit', 'submit', array(
	    			'label'=>'Compare'
	    		));
	    		
	    	foreach (array_keys($data) as $cur) {
	    		$fb->add($cur, 'checkbox', array(
	    			'label'=>$cur,
	    			'required'=>false
	    		));
	    	}
	    		
	    	$form=$fb->getForm();

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
	    		'data'		=> $data,
	    		'form'		=> $form->createView(),
	    		'dates'		=> $dates,
	    		'currency'	=> $currency,
	   			'message'	=> $message,
	    		'notes'		=> $this->getConfig('page_currency')
	    	));
    	
    	} else {
    	
	    	return $this->render('InvestShareBundle:Default:currencygraph.html.twig', array(
	    		'currency'	=> $currency,
	   			'message'	=> $message,
	    		'notes'		=> $this->getConfig('page_currency')
	    	));
    	}
    }
    
    
    public function graphAction($selected, $startDate, $endDate) {
/*
 * create graph for 1 or more company
 */
		$message='';

/*
 * start and end date
 */
		$date1 = date_create_from_format('d/m/Y H:i:s', $startDate.' 00:00:00');
		$date2 = date_create_from_format('d/m/Y H:i:s', $endDate.' 23:59:59');

		$company='';
		$prices=array();
/*
 * create a time scale
 */
		$em=$this->getDoctrine()->getManager();

		if (is_array($selected)) {
			$query=$em->createQuery('SELECT DISTINCT sp.date'.
				' FROM InvestShareBundle:StockPrices sp'.
				' WHERE sp.code IN (\''.implode('\',\'', $selected).'\')'.
					' AND sp.date BETWEEN \''.$date1->format('Y-m-d H:i:s').'\' AND \''.$date2->format('Y-m-d H:i:s').'\'');
			$dates=$query->getResult();
		} else {
			$dates=array();
		}
/*
 * fetch all the values between selected dates
 */		
		if (is_array($selected)) {
			$query=$em->createQuery('SELECT sp'.
				' FROM InvestShareBundle:StockPrices sp'.
				' WHERE'.
					' sp.code IN (\''.implode('\',\'', $selected).'\')'.
					' AND sp.date BETWEEN \''.$date1->format('Y-m-d H:i:s').'\' AND \''.$date2->format('Y-m-d H:i:s').'\''.
				' ORDER BY sp.date ASC');
			$results=$query->getResult();
		} else {
			$results=array();
		}
		
        if (count($results)) {
        	foreach ($results as $result) {
        		
        		$prices[$result->getCode()][$result->getDate()->format('Y-m-d H:i:s')]=array(
        			'Date'    => $result->getDate()->format('Y-m-d H:i:s'),
        			'Price'   => $result->getPrice(),
        			'Changes' => $result->getChanges(),
    				'DateFields'=>array(
    					'Y'=>$result->getDate()->format('Y'),
    					'm'=>$result->getDate()->format('m'),
    					'd'=>$result->getDate()->format('d'),
    					'H'=>$result->getDate()->format('H'),
    					'i'=>$result->getDate()->format('i'),
    					's'=>$result->getDate()->format('s')
    				),
        			'New'     => 0
        		);
        	}
/*
 *  each data field should have the same index,
 *  so if missing, create with average of the neighbours' value
 */        	
        	foreach ($prices as $key=>$value) {
        		foreach ($dates as $date) {
        			if (!isset($value[$date['date']->format('Y-m-d H:i:s')])) {
        				$prices[$key][$date['date']->format('Y-m-d H:i:s')]=array(
        					'Date'=>$date['date']->format('Y-m-d H:i:s'),
        					'Price'=>-999999,
        					'Changes'=>-999999,
        					'DateFields'=>array('Y'=>0, 'm'=>0, 'd'=>0, 'H'=>0, 'i'=>0, 's'=>0),
        					'New'=>1
        				);
        			}
        		}
        		ksort($prices[$key]);
        		
        		$temp=$prices[$key];
        		
        		foreach ($temp as $k=>$v) {
        			if ($v['New']) {

        				reset($prices[$key]);
        				$first_idx=key($prices[$key]);
/*
 * Looking for the first not inserted value
 */ 
        				while ($prices[$key][$first_idx]['New']) {
        					next($prices[$key]);
        					$first_idx=key($prices[$key]);
        				}
        				reset($prices[$key]);
						while (key($prices[$key]) !== null && key($prices[$key]) !== $k) {
        					next($prices[$key]);
        				}

        				if (key($prices[$key]) == null) {
/*
 * If this is the first and already inserted,
 * should replace the value with the first available, not inserted value
 */ 
        					$prev_val=$prices[$key][$first_idx];
        					$next_val=$prices[$key][$first_idx];
        				} else {
        					$prev_val=prev($prices[$key]);
        					$next_val=next($prices[$key]);
/*
 * If the next value still new, go the the first available, not inserted value
 */ 
        					while ($next_val['New']) {
        						$next_val=next($prices[$key]);
        					}
        					
        				}
/*
 * Insert the average of the previous and next value
 */ 
        				$prices[$key][$k]['Price']=(($prev_val['Price'] + $next_val['Price'])/2);
        				$prices[$key][$k]['Changes']=(($prev_val['Changes'] + $next_val['Changes'])/2);
        				$prices[$key][$k]['DateFields']=array(
    						'Y'=>date('Y', strtotime($k)),
    						'm'=>date('m', strtotime($k)),
    						'd'=>date('d', strtotime($k)),
    						'H'=>date('H', strtotime($k)),
    						'i'=>date('i', strtotime($k)),
    						's'=>date('s', strtotime($k))
    					);

        			}
        		}
        	}
        }
        if (is_object($dates)) {
        	$dates->free(true);
        }
		if (is_object($results)) {
        	$results->free(true);
		}
        
    	return $this->render('InvestShareBundle:Default:prices.html.twig', array(
   			'companyEPIC'	=> $company,
   			'data'			=> $prices,
    		'showData'		=> false,
   			'message'		=> $message
    	));
    }

    
    public function tradeuploadAction() {

    	$message='';
    	
    	$request=$this->getRequest();
    	
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
    	$fileData=array();
    	$msg=array();
    	    	
    	$uploadForm=$this->createFormBuilder()
	    	->add('file', 'file', array(
	    		'label'=>'Select a file'
			   ))
			->add('upload', 'submit', array(
				'label'=>'Upload'
			))
		    ->getForm();
			    	
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
 * No need to change the name
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
    
    	$this->currencyNeeded=$this->getCurrencyList();
    	
    	$em=$this->getDoctrine()->getManager();
    
    	$url='gbp.fxexchangerate.com';
//		$url='198.58.100.208';
    	$XML=@simplexml_load_file('http://'.$url.'/rss.xml');
    
    	if ($XML !== false) {
    		$updated=new \Datetime($XML->channel->lastBuildDate);
    			
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
    			'data' => $data,
    			'message' => $message
    	));
    }
    
/*
 * Private functions
 * - usort functions
 * - align data
 * - get data
 */
    
    private function getConfig($key) {
/*
 * read the config table by name
 */  	
    	$result=$this->getDoctrine()
    		->getRepository('InvestShareBundle:Config')
    		->findBy(
    			array(
    				'name'=>$key,
    			),
    			array(),
    			1
    		);

   		return ((count($result))?($result[0]->getValue()):(''));
    }
    
    
    private function updateSummary() {
    	
    	$currencyRates=$this->getCurrencyRates();
/*
 * update the summary table, only when we show that
 */     
    	$em=$this->getDoctrine()->getManager();
    	
    	$portfolios=$this->getDoctrine()
    		->getRepository('InvestShareBundle:Portfolio')
    		->findAll();
    	
    	if ($portfolios && count($portfolios)) {
/*
 * get config values
 */ 
    		
    		
    		$CgtAllowance=$this->getConfig('cgt_allowance_2014_2015');
    		$BasicRate=$this->getConfig('basic_rate_treshold_2014_2015');
    		
    		
    		foreach ($portfolios as $portfolio) {
    			$pId=$portfolio->getId();
    			$summary=$this->getDoctrine()
    				->getRepository('InvestShareBundle:Summary')
    				->findOneBy(
    					array(
    						'portfolioId' => $pId
    					),
    					array(),
    					1);
    			
    			if ($summary) {
    				$new=false;
    			} else {
    				$summary=new Summary();
    				$new=true;
    				$summary->setPortfolioId($pId);
    			}
/*
 * complex sql query for update
 * "id", "name" and "startAmount" of portfolio
 * all the added transactions, debit(+)/credit(-) as "Debit"
 * summary of the bought prices*quantity as "Investment"
 * summary of the amounts of dividends between buy and sell date as "Dividend"
 * if sold, calculate the "Profit" based on the sold price and buy price
 * calculate the "CurrentStock"
 * calculate the "PaidDividend"
 */

    			$pData=$this->getTradesData($pId, null, null, null);
    			
				$data=array(
					'CurrentDividend'=>0,
					'DividendPaid'=>0,
					'Investment'=>0,
					'CurrentValue'=>0,
					'CurrentValueBySector'=>array(),
					'CashIn'=>0,
					'ActualDividendIncome'=>0,
					'Profit'=>0,
					'CgtProfitsRealised'=>0,
					'Family'=>$portfolio->getFamily()
				);

    			if ($pData) {
    				foreach ($pData as $p) {
						if ($p['reference2'] == '') {
/*
 * Unsold
 */
    						$data['Investment']+=$p['quantity1']*$p['unitPrice1']/100+$p['cost1'];
							$data['CurrentValue']+=$p['quantity1']*$p['lastPrice']/100;
							if (!isset($data['CurrentValueBySector'][$p['sector']])) {
								$data['CurrentValueBySector'][$p['portfolioName']][$p['sector']]=0;
							}
							$data['CurrentValueBySector'][$p['portfolioName']][$p['sector']]+=$p['quantity1']*$p['lastPrice']/100;
							$data['Profit']+=(($p['quantity1']*$p['lastPrice']/100)-($p['quantity1']*$p['unitPrice1']/100+$p['cost1']));
								
						} else {
/*
 * Sold
 */							
							$data['Profit']+=(($p['quantity2']*$p['unitPrice2']/100-$p['cost2'])-($p['quantity1']*$p['unitPrice1']/100+$p['cost1']));
							$data['CgtProfitsRealised']+=(($p['quantity2']*$p['unitPrice2']/100-$p['cost2'])-($p['quantity1']*$p['unitPrice1']/100+$p['cost1']));
						}

						$rate=(($p['Currency']=='GBP')?(1):($currencyRates[$p['Currency']]/100));

						if (isset($p['DividendRate']) && $p['DividendRate']) {
							$data['CurrentDividend']+=$p['Dividend']/$p['DividendRate']*100;
							$data['DividendPaid']+=$p['DividendPaid']/$p['DividendRate']*100;
						} else {					
							$data['CurrentDividend']+=$p['Dividend']/$rate;
							$data['DividendPaid']+=$p['DividendPaid']/$rate;
						}

						$data['ActualDividendIncome']+=$p['DividendPaid']/$rate;
    				}
    			}
    			
    			$pt=$this->getDoctrine()
    				->getRepository('InvestShareBundle:PortfolioTransaction')
    				->findBy(
    					array(
    						'PortfolioId'=>$pId
    					)
    				);
    			
    			if ($pt) {
    				foreach ($pt as $pt1) {
    					$data['CashIn']+=$pt1->getAmount();
    				}
    			}

    			
    			$summary->setCurrentDividend($data['CurrentDividend']);
    			$summary->setInvestment($data['Investment']);
    			$summary->setCurrentValue($data['CurrentValue']);
    			$summary->setCurrentValueBySector(json_encode($data['CurrentValueBySector']));
    			$summary->setProfit($data['Profit']);
    			$summary->setDividendPaid($data['DividendPaid']);
    			$summary->setRealisedProfit($data['CgtProfitsRealised']+$data['CurrentDividend']);
    			$summary->setDividendYield((($data['Investment']!=0)?($data['CurrentDividend']/$data['Investment']):(0)));
    			$summary->setCurrentROI(($data['Investment']!=0)?(($data['CurrentValue']-$data['Investment']+$data['ActualDividendIncome']+$data['CgtProfitsRealised'])/$data['Investment']):(0));
    			$summary->setCashIn($data['CashIn']);
    			$summary->setUnusedCash($data['CashIn']+$data['CgtProfitsRealised']-$data['Investment']);
    			$summary->setActualDividendIncome($data['ActualDividendIncome']);
    			$summary->setCgtProfitsRealised($data['CgtProfitsRealised']);
    			$summary->setUnusedCgtAllowance(($CgtAllowance/$data['Family'])-$data['CgtProfitsRealised']);
    			$summary->setUnusedBasicRateBand(($BasicRate/$data['Family'])-$data['ActualDividendIncome']);
    			$summary->setFamily($data['Family']);
    			 
    			$summary->setUpdatedOn(new \Datetime('now'));
    			
    			if ($new) {
    				$em->persist($summary);
    			}
    			$em->flush();
    			
    			if (!$summary->getId()) {
    				error_log('Summary update error');
    			}
    			
    		}
    	} else {
/*
 * No portfolio, no update
 */
			$summary=$this->getDoctrine()
    			->getRepository('InvestShareBundle:Summary')
    			->findAll();
    			
    		if ($summary) {
    			foreach ($summary as $sum) {
    				$em->remove($sum);
    				$em->flush();
    			}
    		}
    			
    		return false;
    	}

    	return true;
    }
    
    
    private static function typeSort($a, $b) {
    	if ($a['type'] == $b['type']) {
    		if ($a['settleDate'] == $b['settleDate']) {
    			if ($a['reference'] == $b['reference']) {

    				return 0;
    			}
    			
    			return ($a['reference'] > $b['reference'])?1:-1;
    		}
    		
    		return ($a['settleDate'] > $b['settleDate'])?1:-1;
    	}
    	
    	return ($a['type'] > $b['type'])?1:-1;
    }
    
    
    private static function buySort($a, $b) {
    	if ($a['tradeId'] == $b['tradeId']) {
    		if ($a['settleDate1'] == $b['settleDate1']) {
    			if ($a['reference1'] == $b['reference1']) {
	    			if (strlen($a['reference2']) == strlen($b['reference2'])) {

	    				return 0;
	    			}
	    			
	    			return (strlen($a['reference2']) < strlen($b['reference2']))?1:-1;
    			}
    			
    			return ($a['reference1'] > $b['reference1'])?1:-1;
    		}
    		
    		return ($a['settleDate1'] > $b['settleDate1'])?1:-1;
    	}
    	
    	return ($a['tradeId'] > $b['tradeId'])?1:-1;
    }
    
    
    private static function divSort($a, $b) {
    	if ($a['Name'] == $b['Name']) {
    		if ($a['ExDivDate'] == $b['ExDivDate']) {
    			
				return 0;
    		}
    		
    		return ($a['ExDivDate'] > $b['ExDivDate'])?1:-1;
    	}
    	
    	return ($a['Name'] > $b['Name'])?1:-1;
    }
    
    
    private static function divDateSort($a, $b) {
   		if ($a['ExDivDate'] == $b['ExDivDate']) {
    		if ($a['Name'] == $b['Name']) {

    			return 0;
    		}
    		
    		return ($a['Name'] > $b['Name'])?1:-1;
    	}
    	
    	return ($a['ExDivDate'] > $b['ExDivDate'])?1:-1;
    }
    	
	
	private function alignSellTrades($t, $tmpBuyTrades, &$combined) {
		
		$em=$this->getDoctrine()->getManager();
		$connection=$em->getConnection();
		
		$query3='SELECT * FROM `TradeTransactions` WHERE `type`=1 AND `tradeId`=:tId ORDER BY `tradeDate`, `reference`';
		$stmt=$connection->prepare($query3);
		$stmt->bindValue('tId', $t['tradeId']);
		$stmt->execute();
		$tmpSellTrades=$stmt->fetchAll();

		foreach ($tmpBuyTrades as $bt) {
			$combined[]=array(
				'type'=>0,
				'portfolioId'=>$t['PortfolioId'],
				'portfolioName'=>$t['PortfolioName'],
				'companyId'=>$t['CompanyId'],
				'companyCode'=>$t['CompanyCode'],
				'companyName'=>$t['CompanyName'],
				'sector'=>$t['Sector'],
				'lastPrice'=>$t['lastPrice'],
				'clientNumber'=>$t['clientNumber'],
				'tradeId'=>$t['tradeId'],
				'PeRatio'=>$t['PE_Ratio'],
				'reference1'=>$bt['reference'],
				'settleDate1'=>$bt['settleDate'],
				'tradeDate1'=>$bt['tradeDate'],
				'quantity1'=>$bt['quantity'],
				'unitPrice1'=>$bt['unitPrice'],
				'cost1'=>$bt['cost'],
						
				'reference2'=>'',
				'settleDate2'=>'',
				'tradeDate2'=>'',
				'quantity2'=>'',
				'unitPrice2'=>'',
				'cost2'=>'',
						
				'noOfDaysInvested'=>0,
				'rows'=>1,
				'Currency'=>$t['Currency'],
				'comment'=>''
			);
			
		}
		
		$usedSellTrades=array();
		$quantity=array();
		foreach ($tmpSellTrades as $st) {
			$quantity[$st['tradeId']]=0;
		}
		
		for ($i=0; $i<count($tmpSellTrades); $i++) {

			$st=$tmpSellTrades[$i];
			$ok=false;
			foreach ($combined as $k=>$c) {
				if (!in_array($c['reference2'], $usedSellTrades)) {
					if (!$ok && $c['reference2']!=$st['reference']) {
						if ($st['tradeId'] == $c['tradeId']) {
							if ($st['tradeDate'] > $c['tradeDate1']) {
								$quantity[$st['tradeId']]+=$c['quantity1'];
								if ($quantity[$st['tradeId']] >= $st['quantity']) {

									$ok=true;
									$combined[$k]['reference2']=$st['reference'];
									$combined[$k]['tradeDate2']=$st['tradeDate'];
									$combined[$k]['settleDate2']=$st['settleDate'];
									$combined[$k]['reference2']=$st['reference'];
									
									$combined[$k]['quantity2']=$st['quantity'];
									// we need the remaining quantity only
									$quantity[$st['tradeId']]-=$st['quantity'];

									$combined[$k]['unitPrice2']=$st['unitPrice'];
									$combined[$k]['cost2']=$st['cost'];
		
									$days=(strtotime($st['tradeDate'])-strtotime($c['tradeDate1']))/(24*60*60);
		
									$combined[$k]['noOfDaysInvested']=$days;
									$usedSellTrades[]=$st['reference'];
									$st['tradeDate']-=$c['tradeDate1'];

								} else {

									$ok=true;
									$combined[$k]['reference2']=$st['reference'];
									$combined[$k]['tradeDate2']=$st['tradeDate'];
									$combined[$k]['settleDate2']=$st['settleDate'];
									$combined[$k]['reference2']=$st['reference'];
									
									$combined[$k]['quantity2']=$quantity[$st['tradeId']];

									if ($quantity[$st['tradeId']] > $quantity[$st['tradeId']]) {
										$i++;
									}
									
									$combined[$k]['unitPrice2']=$st['unitPrice'];
									$combined[$k]['cost2']=$st['cost'];
									$st['quantity']-=$quantity[$st['tradeId']];
// decrease the summary of the quantity to remove all the remainig amount and duplicates
									$tmpSellTrades[$i]['quantity']-=$quantity[$st['tradeId']];
									
									$quantity[$st['tradeId']]-=$c['quantity1'];
										
									$days=(strtotime($st['tradeDate'])-strtotime($c['tradeDate1']))/(24*60*60);
									
									$combined[$k]['noOfDaysInvested']=$days;
									$usedSellTrades[]=$st['reference'];
									$st['tradeDate']-=$c['tradeDate1'];
									$i--;

								}
							}
						}
					}
				}
			}
		}
		return $combined;		
	}
	
	
    private function getTradesData($searchPortfolio, $searchCompany, $searchSector, $searchSold) {

    	$combined=array();
		$em=$this->getDoctrine()->getManager();
		
    	$connection=$em->getConnection();
    	
    	$query1='SELECT'.
    			' `tt`.`tradeId`,'.
    			' `t`.`CompanyId`,'.
    			' `c`.`Name` `CompanyName`,'.
    			' `c`.`Code` `CompanyCode`,'.
    			' `c`.`Currency`,'.
    			' `c`.`lastPrice`,'.
    			' `c`.`Sector`,'.
    			' `p`.`clientNumber`,'.
    			' `t`.`PortfolioId`,'.
    			' `t`.`PE_Ratio`,'.
    			' `p`.`Name` `PortfolioName`'.
    			' FROM `Trade` `t`'.
    			' JOIN `TradeTransactions` `tt` ON `t`.`id`=`tt`.`tradeId`'.
    			' JOIN `Company` `c` ON `t`.`CompanyId`=`c`.`id`'.
    			' JOIN `Portfolio` `p` ON `t`.`PortfolioId`=`p`.`id`'.
    			' WHERE 1'.
    			(($searchSector)?(' AND `c`.`Sector`="'.$searchSector.'"'):('')).
    			(($searchCompany)?(' AND `c`.`id`="'.$searchCompany.'"'):('')).
    			(($searchPortfolio)?(' AND `p`.`id`="'.$searchPortfolio.'"'):('')).

    	' GROUP BY `tt`.`tradeId`'.
    	' ORDER BY `tt`.`tradeId`';

    	$stmt=$connection->prepare($query1);
    	$stmt->execute();
    	$tmpTrades=$stmt->fetchAll();
    	
    	if ($tmpTrades) {
    		foreach ($tmpTrades as $t) {
    			$query2='SELECT * FROM `TradeTransactions` WHERE `type`=0 AND `tradeId`=:tId ORDER BY `tradeDate`, `reference`';
    			$stmt=$connection->prepare($query2);
    			$stmt->bindValue('tId', $t['tradeId']);
    			$stmt->execute();
    			$tmpBuyTrades=$stmt->fetchAll();

    			$this->alignSellTrades($t, $tmpBuyTrades, $combined);
    	
    		}
    	}
    	$ok=false;
    	
    	while (!$ok) {
    		$additional=array();
    		if (count($combined)) {
    			foreach ($combined as $k=>$c) {
    				if ($c['reference2'] != '' && $c['quantity1']>$c['quantity2']) {
    					$rate=($c['quantity2'] / $c['quantity1']);
    					$add = $c;
    					$add['type'] = 0;
    					$add['quantity1'] = $c['quantity1']-$c['quantity2'];
    					$add['cost1'] = $c['cost1']*(1-$rate);
    					$add['reference2'] = '';
    					$add['settleDate2'] = null;
    					$add['tradeDate2'] = null;
    					$add['quantity2'] = 0;
    					$add['unitPrice2'] = 0;
    					$add['cost2'] = 0;
    					$add['noOfDaysInvested'] = 0;
    					$add['rows'] = 1;
    					$add['tradeId'] = $c['tradeId'];
    					$add['Currency'] = $c['Currency'];
    						
    					$combined[$k]['quantity1'] = $c['quantity2'];
    					$combined[$k]['cost1'] = $c['cost1']*$rate;

    					$additional[] = $add;
    				}
    			}
    		}
    			
    	
    		if (count($additional)) {
    			$combined=array_merge($combined, $additional);
    			usort($combined, 'self::buySort');
    	
    		} else {
    			$ok=true;
    		}

    		if (count($additional)) {
    	
    			$tmpCombined=array();
    			foreach ($tmpTrades as $t) {
    				unset($tmpBuyTrades);
    				$tmpBuyTrades=array();
    				foreach ($combined as $c) {
    					if ($c['tradeId'] == $t['tradeId']) {

    						$tmpBuyTrades[]=array(
    								'tradeId'=>$c['tradeId'],
    								'type'=>0,
    								'settleDate'=>$c['settleDate1'],
    								'tradeDate'=>$c['tradeDate1'],
    								'reference'=>$c['reference1'],
    								'description'=>'',
    								'unitPrice'=>$c['unitPrice1'],
    								'quantity'=>$c['quantity1'],
    								'cost'=>$c['cost1']
    						);
    					}
    				}

    				$this->alignSellTrades($t, $tmpBuyTrades, $tmpCombined);
    			}
    			$combined=$tmpCombined;
    	
    		}
    	
    		if (count($combined) && $searchSold) {
    			foreach ($combined as $k=>$v) {
    				switch ($searchSold) {
    					case 1 : {
    						// Unsold
    						if ($v['reference2'] != '') {
    							unset($combined[$k]);
    						}
    						break;
    					}
    					case 2 : {
    						// Sold
    						if ($v['reference2'] == '') {
    							unset($combined[$k]);
    						}
    						break;
    					}
    				}
    			}
    		}
    	}

    	if (count($combined)) {
    		$repo=$this->getDoctrine()
    			->getRepository('InvestShareBundle:Dividend');
    		foreach ($combined as $k=>$c) {
    			$combined[$k]['Dividend']=0;
    			$combined[$k]['DividendPaid']=0;

    			$dividends=$repo->findBy(
    				array(
    					'companyId'=>$c['companyId']
    				)
    			);
    			
    			if ($dividends && count($dividends)) {
    				foreach ($dividends as $div) {
    					if ($div->getExDivDate()->format('Y-m-d') <= date('Y-m-d') && $div->getExDivDate()->format('Y-m-d H:i:s') > $c['tradeDate1'] && ($div->getExDivDate()->format('Y-m-d H:i:s') <= $c['tradeDate2'] || $c['reference2'] == '')) {
    						$combined[$k]['Dividend']+=($c['quantity1']*$div->getAmount()/100);
    						$combined[$k]['DividendRate']=$div->getPaymentRate();
    						if ($div->getPaymentDate()->format('Y-m-d H:i:s') < date('Y-m-d H:i:s')) {
    							$combined[$k]['DividendPaid']+=$c['quantity1']*$div->getAmount()/100;
    						}
    					}
    				}
    			}
    		}
    	}
    	
    	return $combined;
    }

    
    private function getDividendsForCompany($code, $predict = null, $special = null) {

    	$dividends=array();
    	
    	$company=$this->getDoctrine()
			->getRepository('InvestShareBundle:Company')
			->findOneBy(
				array(
					'code'=>$code
				)
	    	);
    	    	
    	if ($company) {
    		$cId=$company->getId();

    		$d1=$company->getFrequency();
	    	$d2=0;
	    	 
			$em=$this->getDoctrine()->getManager();
			
	    	$connection=$em->getConnection();
	    	
	    	$query1='SELECT `d`.*, `c`.`Currency` FROM `Dividend` `d` JOIN `Company` `c` ON `d`.`CompanyId`=`c`.`id` WHERE `d`.`CompanyId`=:cId ORDER BY `d`.`ExDivDate`';
	
	    	$stmt=$connection->prepare($query1);
	    	$stmt->bindValue('cId', $cId);
	    	$stmt->execute();
	    	$dividends=$stmt->fetchAll();
    	}
    	
    	
    	$q=array();
    	if ($predict) {
// print '<hr>predict<br>'.print_r($dividends, true).'<br>';

    		if ($dividends) {
	    		$d2=count($dividends);
	    		if ($d2) {
	    			$d=$dividends[0];
	    			foreach ($dividends as $div) {
	    				if (!$div['Special']) {
	    					$d=$div;
// Save quarterly/half year data
							switch ($d1) {
								case 4 : {
	    							$q[$this->quarterYear($div['ExDivDate'])]=$div;
	    							break;
								}
								case 2 : {
									$q[$this->halfYear($div['ExDivDate'])]=$div;
									break;
								}
								case 1 : {
									$q[1]=$div;
									break;
								}
							}
	    				}
	    				if ($div['Special'] || $div['ExDivDate'] < ((date('m-d')>$this->startTaxYear)?(date('Y')):(date('Y')-1)).'-'.$this->startTaxYear.' 00:00:00') {
	    					$d2--;
	    				}
	    			}
	    		}
	    	}

	    	if ($d1>0 && ($d2>0 || (isset($dividends) && count($dividends)))) {
	    		$diff=$d1-$d2;

	    		$d['Predicted']=1;
	    		$d['PaymentRate']=null;
	    		$date1=strtotime($d['ExDivDate']);
	    		$date2=strtotime($d['PaymentDate']);
	    		
	    		$endOfPrediction=mktime(0, 0, 0, $this->startTaxYearMonth, 1+$this->startTaxYearDay, (date('m-d')>=$this->startTaxYear)?((date('Y')+2)):(date('Y')+1));
	    		
	    		switch ($d1) {
	    			case 1 : {

	    				for ($i=0; $i<(2*$d1-$diff); $i++) {

	    					$ExDivDate=date('Y-m-d H:i:s', mktime(0, 0, 0, date('m', $date1)+12*($i+1), date('d', $date1), date('Y', $date1)));
	    					$PaymentDate=date('Y-m-d H:i:s', mktime(0, 0, 0, date('m', $date2)+12*($i+1), date('d', $date2), date('Y', $date2)));
	    					if ($ExDivDate < date('Y-m-d H:i:s', $endOfPrediction)) {
	    						$d['DeclDate']=null;
	    						$d['ExDivDate']=$ExDivDate;
	    						$d['PaymentDate']=$PaymentDate;
	    						$dividends[]=$d;
	    					}
	    				}
	    				break;
	    			}
	    			case 2 : {

	    				for ($i=0; $i<(4*$d1-$diff); $i++) {
   							$ExDivDate=date('Y-m-d H:i:s', mktime(0, 0, 0, date('m', $date1)+6*($i+1), date('d', $date1), date('Y', $date1)));
	    					$PaymentDate=date('Y-m-d H:i:s', mktime(0, 0, 0, date('m', $date2)+6*($i+1), date('d', $date2), date('Y', $date2)));
	    					if ($ExDivDate < date('Y-m-d H:i:s', $endOfPrediction)) {
	    						$d['DeclDate']=null;
	    						$d['ExDivDate']=$ExDivDate;
	    						$d['PaymentDate']=$PaymentDate;
	    						
	    						if (isset($q[$this->halfYear(date('Y-m-d', strtotime($ExDivDate)))])) {
	    							$d['Amount']=$q[$this->halfYear(date('Y-m-d', strtotime($ExDivDate)))]['Amount'];
	    						}
	    						$dividends[]=$d;
	    					}
	    				}
	    				break;
	    			}
	    			case 4 : {

	    				for ($i=0; $i<(8*$d1-$diff); $i++) {
	    					$ExDivDate=date('Y-m-d H:i:s', mktime(0, 0, 0, date('m', $date1)+3*($i+1), date('d', $date1), date('Y', $date1)));
	    					$PaymentDate=date('Y-m-d H:i:s', mktime(0, 0, 0, date('m', $date2)+3*($i+1), date('d', $date2), date('Y', $date2)));
	    					if ($ExDivDate < date('Y-m-d H:i:s', $endOfPrediction)) {
	    						$d['DeclDate']=null;
	    						$d['ExDivDate']=$ExDivDate;
	    						$d['PaymentDate']=$PaymentDate;
	    						if (isset($q[$this->quarterYear(date('Y-m-d', strtotime($ExDivDate)))])) {
	    							$d['Amount']=$q[$this->quarterYear(date('Y-m-d', strtotime($ExDivDate)))]['Amount'];
	    						}
	    						$dividends[]=$d;
	    					}
	    				}
	    				break;
	    			}
	    		}

	    	}
    	}
    	if (count($dividends)) {
    		foreach ($dividends as $k=>$v) {
    			$dividends[$k]['TaxYear']=$this->getTaxYear($v['PaymentDate']);
    		}
    	}
    	 
    	return $dividends;
    }

    
    private function getDirectorsDealsForCompany($company) {
    	
    	$em=$this->getDoctrine()->getManager();
    	$connection=$em->getConnection();
    	 
    	$query='SELECT * FROM `DirectorsDeals` WHERE `Code`="'.$company.'" ORDER BY `DealDate`';
    	$stmt=$connection->prepare($query);
    	$stmt->execute();
    	$deals=$stmt->fetchAll();
    	
    	return $deals;
    	
    }
    
    
    private function getFinancialDiaryForCompany($company) {

    	$em=$this->getDoctrine()->getManager();
    	$connection=$em->getConnection();
    	
    	$query='SELECT * FROM `Diary` WHERE `Code`="'.$company.'" ORDER BY `Date`';
    	$stmt=$connection->prepare($query);
    	$stmt->execute();
    	$diary=$stmt->fetchAll();
    	 
    	return $diary;

    }


    private function getCurrencyRates() {
    	
    	$em=$this->getDoctrine()->getManager();
    	$connection=$em->getConnection();    	
    	
    	$currencyRates=array();
    	$query='SELECT `c`.`Currency`, (SELECT `Rate` FROM `Currency` WHERE `Currency`=`c`.`Currency` ORDER BY `Updated` DESC LIMIT 1) `Rate` FROM `Currency` `c` GROUP BY `c`.`Currency`';
    	$stmt=$connection->prepare($query);
    	$stmt->execute();
    	$results=$stmt->fetchAll();
    	if ($results && count($results)) {
    		foreach ($results as $result) {
    			$currencyRates[$result['Currency']]=$result['Rate'];
    		}
    	}

    	return $currencyRates;
    }
    
    
    private function quarterYear($dateStr) {
    	$ret=1;
    	$m=date('m', strtotime($dateStr));
    	switch ($m) {
    		case 1 :
    		case 2 :
    		case 3 : {
    			$ret=1;
    			break;
    		}
    		case 4 :
    		case 5 :
    		case 6 : {
    			$ret=2;
    			break;
    		}
    	    case 7 :
    		case 8 :
    		case 9 : {
    			$ret=3;
    			break;
    		}
    	    case 10 :
    		case 11 :
    		case 12 : {
    			$ret=4;
    			break;
    		}
    	} 
    	 
    	return $ret;
    }
    
    
    private function halfYear($dateStr) {
    	$ret=1;
    	$m=date('m', strtotime($dateStr));
    	switch ($m) {
    		case 1 :
    		case 2 :
    		case 3 :
    		case 4 :
    		case 5 :
    		case 6 : {
    			$ret=0;
    			break;
    		}
    		case 7 :
    		case 8 :
    		case 9 :
    		case 10 :
    		case 11 :
    		case 12 : {
    			$ret=1;
    			break;
    		}
    	}
    
    	return $ret;
    }
    
    
    private function isCurrentTaxYear($date) {
    	
    	$taxYearFrom = date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay, date('Y')));
    	$taxYearTo = date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay-1, date('Y')+1));
// print '<br>'.date('Y-m-d', strtotime($date)).' between '.$taxYearFrom.' and '.$taxYearTo;    	    	
    	if (date('Y-m-d', strtotime($date)) >= $taxYearFrom && date('Y-m-d', strtotime($date)) <= $taxYearTo) {
    		return true;
    	}
    	return false;
    	
    }
    
    
    private function getTaxYear($dateStr) {
    	
    	$date=strtotime($dateStr);
    	if (date('m-d', $date) > '04-05') {
    		$current=date('y', $date);
    	} else {
    		$current=date('y', $date)-1;
    	}
    	
    	return sprintf('%02d%02d', $current, $current+1);
    	 
    }
    
    
    private function addDirectorsDeals($data) {
    	$ret=false;

    	$dd=$this->getDoctrine()
    		->getRepository('InvestShareBundle:DirectorsDeals')
    		->findOneBy(
    			array(
    				'declDate'	=>$data['DeclDate'],
    				'dealDate'	=>$data['DealDate'],
    				'type'		=>$data['Type'],
    				'code'		=>$data['Code'],
    				'shares'	=>$data['Shares']
    			)
    		);

    	if (!$dd) {
    		$dd=new DirectorsDeals();
    		
    		$dd->setCreatedOn(new \DateTime('now'));
    		$dd->setCode($data['Code']);
    		$dd->setName($data['Name']);
    		$dd->setDeclDate($data['DeclDate']);
    		$dd->setDealDate($data['DealDate']);
    		$dd->setType($data['Type']);
    		$dd->setPosition($data['Position']);
    		$dd->setShares($data['Shares']);
    		$dd->setPrice($data['Price']);
    		$dd->setValue($data['Value']);
    		
    		$em=$this->getDoctrine()->getManager();
    		
    		$em->persist($dd);
    		$em->flush();
    		
    		if ($dd->getId()) {
    			$ret=true;
    		}
    	}
    	
    	return $ret;
    }

    
    private function addFinancialDiary($data) {
    	$ret=false;
    
    	if ($this->isFTSE($data['Code'])) {
	    	$fd=$this->getDoctrine()
	    		->getRepository('InvestShareBundle:Diary')
	    		->findOneBy(
	    			array(
	    				'date'	=>$data['Date'],
	    				'name'	=>$data['Name'],
	    				'type'	=>$data['Type'],
	    				'code'	=>$data['Code']
	    			)
	    		);
	    
	    	if (!$fd) {
	    		$fd=new Diary();
	    
	    		$fd->setCreatedOn(new \DateTime('now'));
	    		$fd->setCode($data['Code']);
	    		$fd->setName($data['Name']);
	    		$fd->setDate($data['Date']);
	    		$fd->setType($data['Type']);
	    
	    		$em=$this->getDoctrine()->getManager();
	    
	    		$em->persist($fd);
	    		$em->flush();
	    
	    		if ($fd->getId()) {
	    			$ret=true;
	    		}
	    	}
    	}
    	 
    	return $ret;
    }
    
    
    private function isFTSE($code) {
    	
    	$em=$this->getDoctrine()->getManager();
    	$connection=$em->getConnection();
    	
    	$query='SELECT `id` FROM `Company` WHERE `Code`=:cId';
    	$stmt=$connection->prepare($query);
    	$stmt->bindValue('cId', $code);
    	$stmt->execute();
    	$results=$stmt->fetchAll();
    	
    	if (count($results)) {
			return true;
    	}
    	return false;
    }
    

    private function getCompanyNames($current) {
    	
    	$companies=array();
    	
    	if ($current) {
    		$trades=$this->getTradesData(null, null, null, null);
    	 
	    	if (count($trades)) {
	    		foreach ($trades as $t) {
	    			if ($t['reference2'] == '') {
	    				$companies[$t['companyCode']]=$t['companyName'];
    				}
    			}
	    	}
    	} else {
    		$em=$this->getDoctrine()->getManager();
    		$connection=$em->getConnection();
    		
    		$query='SELECT `Code`, `Name` FROM `Company` ORDER BY `Code`';
    		$results=$connection->fetchAll($query);
    		
    		if (count($results)) {
    			foreach ($results as $result) {
    				$companies[$result['Code']]=$result['Name'];
    			}
    		}
    	}

// error_log('no of companies:'.count($companies));
    	return $companies;
    }
    

    private function getCurrencyList() {
    	
    	$ret=array();
    	
    	$em=$this->getDoctrine()->getManager();
    	$connection=$em->getConnection();
    	
    	$query='SELECT `Currency`'.
    			' FROM `Currency`'.
    			' GROUP BY `Currency`';
    	
    	$results=$connection->fetchAll($query);
    	
    	if ($results) {
    		foreach ($results as $result) {
    			$ret[]=$result['Currency'];
    		}
    	}
    	
    	return $ret;
    }
    
}
