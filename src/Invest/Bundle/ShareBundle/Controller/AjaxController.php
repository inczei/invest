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
use Symfony\Component\HttpFoundation\Symfony\Component\HttpFoundation;

class AjaxController extends Controller
{
	protected $currencyNeeded=array();
	
	protected $defaultCurrencies=array('EUR', 'USD', 'AUD', 'HUF', 'PHP');

	

    public function currencyAction($currency) {

		$request=$this->getRequest();
		if ($request->isXmlHttpRequest()) {
	    	$data=array();
	    	$search=array();
	    	
	    	$currencies=explode(',', $currency);
	
	    	if (count($currencies)) {
	    		
	    		$this->currencyNeeded=$this->getCurrencyList();
	    		
	    		foreach ($currencies as $k=>$v) {
	    			if (!in_array($v, $this->currencyNeeded)) {
	    				unset($currencies[$k]);
	    			}
	    		}
	    		$search=array('currency'=>$currencies);
	    	}
	
	    	$i=0;
	    	foreach ($currencies as $curr) {
		    	$results=$this->getDoctrine()
		    		->getRepository('InvestShareBundle:Currency')
		    		->findBy($search, array('updated'=>'ASC'));
		    	
		    	$data[$i]['name']=$curr;
		    	$data[$i]['tooltip']['valueDecimals']=3;
		    	if ($results && count($results)) {
		    		foreach ($results as $result) {
		    			if ($result->getCurrency() == $curr) {
		    				$data[$i]['data'][]=array($result->getUpdated()->getTimestamp()*1000, $result->getRate());
		    			}
		    		}
		    	}
		    	$i++;
	    	}
	    	
	    	return new JsonResponse($data);
		} else {
			error_log('not ajax request...');
				
			return $this->redirect($this->generateUrl('invest_share_homepage'), 302);
		}
    }
    
    
    public function pricesAction($company, $min, $max) {
		
		$request=$this->getRequest();
		if ($request->isXmlHttpRequest()) {

	    	$data=array();
	    	$min_date=null;
	    	$max_date=null;
	    	$rangeDate=array('min'=>0, 'max'=>date('Y-m-d H:i:s'));
			if ($min) {
				$rangeDate['min']=date('Y-m-d H:i:s', $min/1000);
			}
			if ($max) {
				$rangeDate['max']=date('Y-m-d H:i:s', $max/1000);
			}

	    	$functions=$this->get('invest.share.functions');
    	
	        if ($company) {
	    		$selectedCompanies=explode(',', $company);
	    		
	    		$prices=$this->getDoctrine()
		    		->getRepository('InvestShareBundle:StockPrices')
		    		->findBy(
		   				array(
							'code'=>$selectedCompanies
			    		),
		    			array(
		    				'date'=>'ASC'
		    			)
		   			);
	
	   	    	if (count($prices)) {
   	    		
/*
 * create timescale list
 */

	   	    		foreach ($selectedCompanies as $k=>$v) {
	   	    			$data[$k]=array(
	   	    				'name'=>$v,
	   	    				'id'=>'data_'.$k,
	   	    				'type'=>'line',
	   	    				'gapsize'=>5,
	   	    				'treshold'=>'null',
				    		'tooltip'=>array('valueDecimals'=>2)
	   	    			);
	   	    		}
				    
	   	    		foreach ($prices as $pr1) {
	
	   	    			$prDate=$pr1->getDate()->format('Y-m-d H:i:s');
	   	    			if ($prDate >= $rangeDate['min'] && $prDate <= $rangeDate['max']) {
	   	    				
		   	    			$i=array_search($pr1->getCode(), $selectedCompanies);
			    			$data[$i]['data'][]=array($pr1->getDate()->getTimestamp()*1000, $pr1->getPrice());
		
			    			if ($min_date == null || $min_date > $pr1->getDate()->format('Y-m-d H:i:s')) {
			    				$min_date=$pr1->getDate()->format('Y-m-d H:i:s');
			    			}
			    			if ($max_date == null || $max_date < $pr1->getDate()->format('Y-m-d H:i:s')) {
			    				$max_date=$pr1->getDate()->format('Y-m-d H:i:s');
			    			}
			    			
	   	    			}
		    		}

/*
 * Create dividends points into the graph
*/
		    		$i=count($selectedCompanies);
	
		    		foreach ($selectedCompanies as $k=>$v) {
			    		$divs=$functions->getDividendsForCompany($v, true);
		
			    		if (count($divs)) {
							$i_decl=array();
							$i_exdiv=array();
							$i_pay=array();	
			    			foreach ($divs as $div) {
	
			    				$amount=(($div['Special'])?('Special '):('')).(($div['Currency']=='USD')?('$ '):('')).(($div['Currency']=='EUR')?('€ '):('')).$div['Amount'].(($div['Currency']=='GBP')?('p'):(''));
		    		
			    				if (date('Y-m-d H:i:s', strtotime($div['DeclDate'])) < $max_date && strtotime($div['DeclDate']) > 0 && date('Y-m-d H:i:s', strtotime($div['DeclDate'])) > $min_date) {
	
			    					if (!isset($i_decl[$k])) {
			    						$i_decl[$k]=$i++;
			    					}
	
			    					if (!isset($data[$i_decl[$k]]['data'])) {
			    						$data[$i_decl[$k]]=array(
			    							'type'=>'flags',
			    							'shape'=>'squarepin',
			    							'onSeries'=>'data_'.$k,
			    							'name'=>'Decl.Date ('.$v.')',
			    							'data'=>array()
			    						);
			    					}
			    					$data[$i_decl[$k]]['data'][]=array(
			    						'x'=>strtotime($div['DeclDate'])*1000,
			    						'title'=>$amount,
			    						'text'=>'ExDividend Declaration Date (<b>'.$v.'</b>)',
			    					);
			    				}
			    		
			    				if (date('Y-m-d H:i:s', strtotime($div['ExDivDate'])) < $max_date && strtotime($div['ExDivDate']) > 0 && date('Y-m-d H:i:s', strtotime($div['ExDivDate'])) > $min_date) {
	
			    					if (!isset($i_exdiv[$k])) {
			    						$i_exdiv[$k]=$i++;
			    					}
	
			    					if (!isset($data[$i_exdiv[$k]]['data'])) {
			    						$data[$i_exdiv[$k]]=array(
			    							'type'=>'flags',
			    							'shape'=>'squarepin',
			    							'onSeries'=>'data_'.$k,
			    							'name'=>'ExDiv.Date ('.$v.')',
			    							'data'=>array()
			    						);
			    					}
			    					$data[$i_exdiv[$k]]['data'][]=array(
			    						'x'=>strtotime($div['ExDivDate'])*1000,
			    						'title'=>$amount,
			    						'text'=>'ExDividend Date (<b>'.$v.'</b>)',
			    					);
			    				}
			    		
			    				if (date('Y-m-d H:i:s', strtotime($div['PaymentDate'])) < $max_date && strtotime($div['PaymentDate']) > 0 && date('Y-m-d H:i:s', strtotime($div['PaymentDate'])) > $min_date) {
	
			    					if (!isset($i_pay[$k])) {
			    						$i_pay[$k]=$i++;
			    					}
	
			    					if (!isset($data[$i_pay[$k]]['data'])) {
			    						$data[$i_pay[$k]]=array(
			    							'type'=>'flags',
			    							'shape'=>'squarepin',
			    							'onSeries'=>'data_'.$k,
			    							'name'=>'Payment.Date ('.$v.')',
			    							'data'=>array()
			    						);
			    					}
			    					$data[$i_pay[$k]]['data'][]=array(
			    						'x'=>strtotime($div['PaymentDate'])*1000,
			    						'title'=>$amount,
			    						'text'=>'Payment Date (<b>'.$v.'</b>)'
			    					);
			    				}
			    			}
			    		}
		    			
			    		$ddeals=$functions->getDirectorsDealsForCompany($v);
		
			    		if (count($ddeals)) {
			    			foreach ($ddeals as $d) {
			    				
			    				if (date('Y-m-d H:i:s', strtotime($d['DealDate'])) < $max_date && date('Y-m-d H:i:s', strtotime($d['DealDate'])) > $min_date) {
			    					if (!isset($data[$i]['data'])) {
			    						$data[$i]=array(
			    							'type'=>'flags',
			    							'shape'=>'squarepin',
			    							'onSeries'=>'data_'.$k,
		    								'name'=>'Directors Deal ('.$v.')',
		    								'data'=>array()
			    						);
			    					}
			    					$data[$i]['data'][]=array(
			    						'x'=>strtotime($d['DealDate'])*1000,
			    						'title'=>'DD',
			    						'text'=>'<b>Directors Deal ('.$v.')</b><br>Name: '.$d['Name'].'<br>Position:'.$d['Position'].'<br>Type:'.$d['Type'].'<br>Price:'.$d['Price'].'<br>Value:'.$d['Value']
			    					);
		    					}
		    				}
		    				$i++;
		    			}
			    		 
		   	    		$diary=$functions->getFinancialDiaryForCompany($v, true);
		
			    		if (count($diary)) {
				    		foreach ($diary as $d) {
				    				
				    			if (date('Y-m-d H:i:s', strtotime($d['Date'])) < $max_date && date('Y-m-d H:i:s', strtotime($d['Date'])) > $min_date) {
				    				if (!isset($data[$i]['data'])) {
				    					$data[$i]=array(
				    						'type'=>'flags',
				    						'shape'=>'circlepin',
			    							'name'=>'Financial Diary ('.$v.')',
			    							'data'=>array()
				    					);
				    				}
				    				$data[$i]['data'][]=array(
				    					'x'=>strtotime($d['Date'])*1000,
				    					'title'=>'FD',
				    					'text'=>'<b>Financial Diary ('.$v.')</b><br>'.$d['Type']
				    				);
				    			}
				    		}
				    		$i++;
			    		}
			   	    }
	   	    	}
		    }

			return new JsonResponse($data);
		} else {
			error_log('not ajax request...');
			
			return $this->redirect($this->generateUrl('invest_share_homepage'), 302);
		}
    }
    

    private function getCurrencyList() {
    	
    	$ret=array();
    	$query='SELECT `Currency`'.
    		' FROM `Currency`'.
    		' GROUP BY `Currency`';
    	
    	$em=$this->getDoctrine()->getManager();
    	$connection=$em->getConnection();
    	
    	$stmt=$connection->prepare($query);
    	$stmt->execute();
    	$results=$stmt->fetchAll();
    	
    	if ($results) {
    		foreach ($results as $result) {
    			$ret[]=$result['Currency'];
    		}
    	}
    	
    	return $ret;
    }
    

    public function tradeAction($id) {

		$request=$this->getRequest();
		if ($request->isXmlHttpRequest()) {
			
			$data=array();
			$i=0;
			$value=0;

			$functions=$this->get('invest.share.functions');
			
			$trades=$functions->getTradesData($id, null, null, null);
			
			if (count($trades)) {
				$dates=array();
				
				foreach ($trades as $trade) {
					if (!isset($dates[strtotime($trade['tradeDate1'])*1000])) {
						$dates[strtotime($trade['tradeDate1'])*1000]=0;
					}
					$dates[strtotime($trade['tradeDate1'])*1000]+=$trade['unitPrice1']*$trade['quantity1']/100-$trade['cost1'];
					if ($trade['reference2']) {
						if (!isset($dates[strtotime($trade['tradeDate2'])*1000])) {
							$dates[strtotime($trade['tradeDate2'])*1000]=0;
						}
						$dates[strtotime($trade['tradeDate2'])*1000]-=$trade['unitPrice2']*$trade['quantity2']/100-$trade['cost2'];
					}
					ksort($dates);
				}
				
				foreach ($dates as $k=>$v) {
					if (!isset($data[$i]['data'])) {
						$data[$i]=array(
							'name'=>'Trades',
							'id'=>'data_'.$i,
							'type'=>'line',
//							'gapsize'=>5,
//							'treshold'=>'null',
							'tooltip'=>array('valueDecimals'=>2),
							'data'=>array()
						);
					}
// error_log('trade : '.print_r($trade, true));
					$value+=$v;
	    			$data[$i]['data'][]=array($k, $value);
				}	
			}
			
			$i++;
	    	$query='SELECT `pt`.*'.
	    		' FROM `Portfolio` `p` JOIN `PortfolioTransaction` `pt` ON `p`.`id`=`pt`.`portfolioId`'.
	    		' WHERE `p`.`id`=:pId'.
	    		' ORDER BY `p`.`id`, `pt`.`Date`';
error_log('query:'.$query.', pId:'.$id);	    	
	    	$em=$this->getDoctrine()->getManager();
	    	$connection=$em->getConnection();
	    	
	    	$stmt=$connection->prepare($query);
	    	$stmt->bindValue('pId', $id);
	    	$stmt->execute();
	    	$results=$stmt->fetchAll();
	    	
	    	if (count($results)) {
	    		foreach ($results as $result) {

	    			if (!isset($data[$i]['data'])) {
		   	    		$data[$i]=array(
		   	    			'name'=>'Trade Transactions',
		   	    			'id'=>'data_'.$i,
			    			'type'=>'flags',
			    			'shape'=>'squarepin',
		   	    			'gapsize'=>5,
		   	    			'treshold'=>'null',
					    	'tooltip'=>array('valueDecimals'=>2),
		   	    			'data'=>array()
		   	    		);
	    			}
	    			$data[$i]['data'][]=array(
			    		'x'=>strtotime($result['Date'])*1000,
			    		'title'=>'£ '.$result['Amount'],
			    		'text'=>'<b>'.$result['Reference'].'</b><br>'.'£ '.$result['Amount']
	    			);
	    		}
	    	}
				    	
	    	
	    	return new JsonResponse($data);
		} else {
			error_log('not ajax request...');
				
			return $this->redirect($this->generateUrl('invest_share_homepage'), 302);
		}
    }
}
