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
use Symfony\Component\HttpFoundation\Request;
use Invest\Bundle\ShareBundle\Entity\Company;
use Invest\Bundle\ShareBundle\Entity\Diary;
use Invest\Bundle\ShareBundle\Entity\Trade;
use Invest\Bundle\ShareBundle\Entity\Dividend;
use Invest\Bundle\ShareBundle\Entity\Portfolio;
use Invest\Bundle\ShareBundle\Entity\PortfolioTransaction;
use Invest\Bundle\ShareBundle\Entity\StockPrices;
use Invest\Bundle\ShareBundle\Entity\Currency;
use Invest\Bundle\ShareBundle\InvestShareBundle;
use Symfony\Component\HttpFoundation\JsonResponse;

class AjaxController extends Controller
{
	protected $currencyNeeded=array();
	
	protected $defaultCurrencies=array('EUR', 'USD', 'AUD', 'HUF', 'PHP');

	

    public function currencyAction($currency) {

		$request=$this->getRequest();
		if ($request->isXmlHttpRequest()) {
			$functions=$this->get('invest.share.functions');
			
	    	$data=array();
	    	
	    	$currencies=explode(',', $currency);
	
	    	if (count($currencies)) {
	    		
	    		$this->currencyNeeded=$functions->getCurrencyList();
	    		
	    		foreach ($currencies as $k=>$v) {
	    			if (!in_array($v, $this->currencyNeeded)) {
	    				unset($currencies[$k]);
	    			}
	    		}
	    	}
	
	    	$i=0;
	    	$em=$this->getDoctrine()->getManager();
	    	$qb=$em->createQueryBuilder()
	    		->addSelect('c.rate')
	    		->addSelect('c.updated')
	    		->from('InvestShareBundle:Currency', 'c')
	    		->where('c.currency=:cur')
	    		->orderBy('c.updated', 'ASC');

	    	foreach ($currencies as $curr) {
		    	$qb->setParameter('cur', $curr);
		    	$results=$qb->getQuery()->getArrayResult();
		    	
		    	$data[$i]['name']=$curr;
		    	$data[$i]['tooltip']['valueDecimals']=3;
		    	if ($results && count($results)) {
		    		foreach ($results as $result) {
		    			$data[$i]['data'][]=array($result['updated']->getTimestamp()*1000, $result['rate']);
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

	    		$em=$this->getDoctrine()->getManager();
	    		$qb=$em->createQueryBuilder()
	    			->select('sp')
	    			->from('InvestShareBundle:StockPrices', 'sp')
	    			->where('sp.code IN (\''.implode('\',\'', $selectedCompanies).'\')')
	    			->orderBy('sp.date', 'ASC');
	    		
	    		$prices=$qb->getQuery()->getArrayResult();
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
	
	   	    			$prDate=$pr1['date']->format('Y-m-d H:i:s');
	   	    			if ($prDate >= $rangeDate['min'] && $prDate <= $rangeDate['max']) {
	   	    				
		   	    			$i=array_search($pr1['code'], $selectedCompanies);
			    			$data[$i]['data'][]=array($pr1['date']->getTimestamp()*1000, $pr1['price']);
		
			    			if ($min_date == null || $min_date > $pr1['date']->format('Y-m-d H:i:s')) {
			    				$min_date=$pr1['date']->format('Y-m-d H:i:s');
			    			}
			    			if ($max_date == null || $max_date < $pr1['date']->format('Y-m-d H:i:s')) {
			    				$max_date=$pr1['date']->format('Y-m-d H:i:s');
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
	
			    				$amount=(($div['special'])?('Special '):('')).(($div['currency']=='USD')?('$ '):('')).(($div['currency']=='EUR')?('€ '):('')).$div['amount'].(($div['currency']=='GBP')?('p'):(''));
		    		
			    				if (!is_null($div['declDate']) && $div['declDate']->format('Y-m-d H:i:s') < $max_date && $div['declDate']->getTimestamp() > 0 && $div['declDate']->format('Y-m-d H:i:s') > $min_date) {
	
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
			    						'x'=>$div['declDate']->getTimestamp()*1000,
			    						'title'=>$amount,
			    						'text'=>'ExDividend Declaration Date (<b>'.$v.'</b>)',
			    					);
			    				}
			    		
			    				if ($div['exDivDate']->format('Y-m-d H:i:s') < $max_date && $div['exDivDate']->getTimestamp() > 0 && $div['exDivDate']->format('Y-m-d H:i:s') > $min_date) {
	
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
			    						'x'=>$div['exDivDate']->getTimestamp()*1000,
			    						'title'=>$amount,
			    						'text'=>'ExDividend Date (<b>'.$v.'</b>)',
			    					);
			    				}
			    		
			    				if ($div['paymentDate']->format('Y-m-d H:i:s') < $max_date && $div['paymentDate']->getTimestamp() > 0 && $div['paymentDate']->format('Y-m-d H:i:s') > $min_date) {
	
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
			    						'x'=>$div['paymentDate']->getTimestamp()*1000,
			    						'title'=>$amount,
			    						'text'=>'Payment Date (<b>'.$v.'</b>)'
			    					);
			    				}
			    			}
			    		}

			    		$ddeals=$functions->getDirectorsDealsForCompany($v);

			    		if (count($ddeals)) {
			    			foreach ($ddeals as $d) {
			    				
			    				if ($d['dealDate']->format('Y-m-d H:i:s') < $max_date && $d['dealDate']->format('Y-m-d H:i:s') > $min_date) {
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
			    						'x'=>$d['dealDate']->getTimestamp()*1000,
			    						'title'=>'DD',
			    						'text'=>'<b>Directors Deal ('.$v.')</b><br>Name: '.$d['name'].'<br>Position:'.$d['position'].'<br>Type:'.$d['type'].'<br>Price:'.$d['price'].'<br>Value:'.$d['value']
			    					);
		    					}
		    				}
		    				$i++;
		    			}
			    		 
		   	    		$diary=$functions->getFinancialDiaryForCompany($v, true);
		
			    		if (count($diary)) {
				    		foreach ($diary as $d) {
				    				
				    			if ($d['date']->format('Y-m-d H:i:s') < $max_date && $d['date']->format('Y-m-d H:i:s') > $min_date) {
				    				if (!isset($data[$i]['data'])) {
				    					$data[$i]=array(
				    						'type'=>'flags',
				    						'shape'=>'circlepin',
			    							'name'=>'Financial Diary ('.$v.')',
			    							'data'=>array()
				    					);
				    				}
				    				$data[$i]['data'][]=array(
				    					'x'=>$d['date']->getTimestamp()*1000,
				    					'title'=>'FD',
				    					'text'=>'<b>Financial Diary ('.$v.')</b><br>'.$d['type']
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
					if (!isset($dates[$trade['tradeDate1']->getTimestamp()*1000])) {
						$dates[$trade['tradeDate1']->getTimestamp()*1000]=0;
					}
					$dates[$trade['tradeDate1']->getTimestamp()*1000]+=$trade['unitPrice1']*$trade['quantity1']/100-$trade['cost1'];
					if ($trade['reference2']) {
						if (!isset($dates[$trade['tradeDate2']->getTimestamp()*1000])) {
							$dates[$trade['tradeDate2']->getTimestamp()*1000]=0;
						}
						$dates[$trade['tradeDate2']->getTimestamp()*1000]-=$trade['unitPrice2']*$trade['quantity2']/100-$trade['cost2'];
					}
					ksort($dates);
				}
				
				foreach ($dates as $k=>$v) {
					if (!isset($data[$i]['data'])) {
						$data[$i]=array(
							'name'=>'Trades',
							'id'=>'data_'.$i,
							'type'=>'line',
							'tooltip'=>array('valueDecimals'=>2),
							'data'=>array()
						);
					}
					$value+=$v;
	    			$data[$i]['data'][]=array($k, $value);
				}	
			}
			
			$i++;
			
			$em=$this->getDoctrine()->getManager();
			$qb=$em->createQueryBuilder()
				->select('pt.date')
				->addSelect('pt.amount')
				->addSelect('pt.reference')
				->from('InvestShareBundle:Portfolio', 'p')
				->join('InvestShareBundle:PortfolioTransaction', 'pt', 'WITH', 'p.id=pt.PortfolioId')
				->where('p.id=:pId')
				->orderBy('p.id', 'ASC')
				->addOrderBy('pt.date', 'ASC')
				->setParameter('pId', $id);
			
			$results=$qb->getQuery()->getArrayResult();

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
			    		'x'=>$result['date']->getTimestamp()*1000,
			    		'title'=>'£ '.$result['amount'],
			    		'text'=>'<b>'.$result['reference'].'</b><br>'.'£ '.$result['amount']
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
