<?php

/*
 * Author: Imre Incze
 * 
 */

namespace Invest\Bundle\ShareBundle\Service;

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
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Ps\PdfBundle\Annotation\Pdf;
use Symfony\Component\DependencyInjection\ContainerAware;
use Doctrine\ORM\EntityManager;

class Functions extends ContainerAware
{
	
	protected $em;
	
	protected $doctrine;
	
	protected $startTaxYear = '04-06';
	
	protected $startTaxYearMonth = 4;
	
	protected $startTaxYearDay = 6;
	
	protected $dealsLimit = 60000;
	
	protected $dividendWarningDays = 7;
	
	protected $currencyNeeded=array();
	
	protected $defaultCurrencies=array('EUR', 'USD', 'AUD', 'HUF', 'PHP');

	protected $pager = 20;
	

	public function __construct($doctrine) {
		$this->doctrine = $doctrine; 
		$this->em = $doctrine->getManager();
	}
	

    public function getConfig($key) {
/*
 * read the config table by name
 */  	
    	$result=$this->doctrine
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
    
    
    public function updateSummary() {
    	
    	$currencyRates=$this->getCurrencyRates();
/*
 * update the summary table, only when we show that
 */     
    	$portfolios=$this->doctrine
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
    			$summary=$this->doctrine
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

    			$pData=$this->getTradesData($pId, null, null, null, null, null);
    			
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
    			
    			$pt=$this->doctrine
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
    				$this->em->persist($summary);
    			}
    			$this->em->flush();
    			
    			if (!$summary->getId()) {
    				error_log('Summary update error');
    			}
    			
    		}
    	} else {
/*
 * No portfolio, no update
 */
			$summary=$this->doctrine
    			->getRepository('InvestShareBundle:Summary')
    			->findAll();
    			
    		if ($summary) {
    			foreach ($summary as $sum) {
    				$this->em->remove($sum);
    				$this->em->flush();
    			}
    		}
    			
    		return false;
    	}

    	return true;
    }
    
    
    public static function typeSort($a, $b) {
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
    
    
    public static function buySort($a, $b) {
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
    
    
    	
	
	public function alignSellTrades($t, $tmpBuyTrades, &$combined) {
		
		$qb=$this->em->createQueryBuilder()
			->select('tt')
			->from('InvestShareBundle:TradeTransactions', 'tt')
			->where('tt.type=1')
			->andWhere('tt.tradeId=:tId')
			->orderBy('tt.tradeDate', 'ASC')
			->addOrderBy('tt.reference', 'ASC')
			->setParameter('tId', $t['tradeId']);
		
		$tmpSellTrades=$qb->getQuery()->getArrayResult();
		
		foreach ($tmpBuyTrades as $bt) {
			$combined[]=array(
				'type'=>0,
				'portfolioId'=>$t['portfolioId'],
				'portfolioName'=>$t['PortfolioName'],
				'companyId'=>$t['companyId'],
				'companyCode'=>$t['CompanyCode'],
				'companyName'=>$t['CompanyName'],
				'sector'=>$t['sector'],
				'lastPrice'=>$t['lastPrice'],
				'clientNumber'=>$t['clientNumber'],
				'tradeId'=>$t['tradeId'],
				'PeRatio'=>$t['pERatio'],
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
				'Currency'=>$t['currency'],
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
		
									$days=($st['tradeDate']->getTimestamp()-$c['tradeDate1']->getTimestamp())/(24*60*60);
		
									$combined[$k]['noOfDaysInvested']=$days;
									$usedSellTrades[]=$st['reference'];

									$st['tradeDate']->add($st['tradeDate']->diff($c['tradeDate1']));

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
										
									$days=($st['tradeDate']->getTimestamp()-$c['tradeDate1']->getTimestamp())/(24*60*60);
									
									$combined[$k]['noOfDaysInvested']=$days;
									$usedSellTrades[]=$st['reference'];
									$st['tradeDate']->add($st['tradeDate']->diff($c['tradeDate1']));

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
	
	
    public function getTradesData($searchPortfolio, $searchCompany, $searchSector, $searchSold) {

    	$combined=array();
    	$qb=$this->em->createQueryBuilder()
    		->select('tt.tradeId')
    		->addSelect('t.companyId')
    		->addSelect('c.name as CompanyName')
    		->addSelect('c.code as CompanyCode')
    		->addSelect('c.currency')
    		->addSelect('c.lastPrice')
    		->addSelect('c.sector')
    		->addSelect('p.clientNumber')
    		->addSelect('t.portfolioId')
    		->addSelect('t.pERatio')
    		->addSelect('p.name as PortfolioName')

    		->from('InvestShareBundle:Trade', 't')
    		->join('InvestShareBundle:TradeTransactions', 'tt', 'WITH', 't.id=tt.tradeId')
    		->join('InvestShareBundle:Company', 'c', 'WITH', 't.companyId=c.id')
    		->join('InvestShareBundle:Portfolio', 'p', 'WITH', 't.portfolioId=p.id')
    		
    		->groupBy('tt.tradeId')
    		
    		->orderBy('tt.tradeId');
    	
    	if ($searchSector) {
    		$qb->andWhere('c.Sector=:sector')
    			->setParameter('sector', $searchSector);
    	}
    	if ($searchCompany) {
    		$qb->andWhere('c.id=:company')
    			->setParameter('company', $searchCompany);
    	}
    	if ($searchPortfolio) {
    		$qb->andWhere('p.id=:portfolio')
    			->setParameter('portfolio', $searchPortfolio);
    	}
    		

    	$tmpTrades=$qb->getQuery()->getArrayResult();
    	
    	if ($tmpTrades) {
    		foreach ($tmpTrades as $t) {
  
    			$qb2=$this->em->createQueryBuilder()
    				->select('tt')
    				->from('InvestShareBundle:Tradetransactions', 'tt')
    				->where('tt.type=0')
    				->andWhere('tt.tradeId=:tId')
    				->orderBy('tt.tradeDate', 'ASC')
    				->addOrderBy('tt.reference')
    				->setParameter('tId', $t['tradeId']);
    			$tmpBuyTrades=$qb2->getQuery()->getArrayResult();
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
    		$repo=$this->doctrine
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

    
    public function getDividendsForCompany($code, $predict = null, $special = null) {

    	$dividends=array();
    	
    	$company=$this->doctrine
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
	    	 
			$qb1=$this->em->createQueryBuilder()
				->select('d.id')
				->addSelect('d.companyId')
				->addSelect('d.exDivDate')
				->addSelect('d.declDate')
				->addSelect('d.amount')
				->addSelect('d.paymentDate')
				->addSelect('d.special')
				->addSelect('d.paymentRate')
				->addSelect('c.currency')
				->from('InvestShareBundle:Dividend', 'd')
				->join('InvestShareBundle:Company', 'c', 'WITH', 'd.companyId=c.id')
				->where('c.id=:cId')
				->orderBy('d.exDivDate', 'ASC')
				->setParameter('cId', $cId);
				
			$dividends=$qb1->getQuery()->getArrayResult();
    	}
    	
    	$q=array();
    	if ($predict) {

    		if ($dividends) {
	    		$d2=count($dividends);
	    		if ($d2) {
	    			$d=$dividends[0];
	    			foreach ($dividends as $div) {
	    				if (!$div['special']) {
	    					$d=$div;
							// Save quarterly/half year data
							switch ($d1) {
								case 4 : {
	    							$q[$this->quarterYear($div['exDivDate']->format('Y-m-d H:i:s'))]=$div;
	    							break;
								}
								case 2 : {
									$q[$this->halfYear($div['exDivDate']->format('Y-m-d H:i:s'))]=$div;
									break;
								}
								case 1 : {
									$q[1]=$div;
									break;
								}
							}
	    				}
	    				if ($div['special'] || $div['exDivDate'] < ((date('m-d')>$this->startTaxYear)?(date('Y')):(date('Y')-1)).'-'.$this->startTaxYear.' 00:00:00') {
	    					$d2--;
	    				}
	    			}
	    		}
	    	}

	    	if ($d1>0 && ($d2>0 || (isset($dividends) && count($dividends)))) {
	    		$diff=$d1-$d2;

	    		$d['Predicted']=1;
	    		$d['PaymentRate']=null;
	    		$date1=strtotime($d['exDivDate']->format('Y-m-d H:i:s'));
	    		$date2=strtotime($d['paymentDate']->format('Y-m-d H:i:s'));
	    		
	    		$endOfPrediction=mktime(0, 0, 0, $this->startTaxYearMonth, 1+$this->startTaxYearDay, (date('m-d')>=$this->startTaxYear)?((date('Y')+2)):(date('Y')+1));
	    		
	    		switch ($d1) {
	    			case 1 : {

	    				for ($i=0; $i<(2*$d1-$diff); $i++) {

	    					$ExDivDate=date('Y-m-d H:i:s', mktime(0, 0, 0, date('m', $date1)+12*($i+1), date('d', $date1), date('Y', $date1)));
	    					$PaymentDate=date('Y-m-d H:i:s', mktime(0, 0, 0, date('m', $date2)+12*($i+1), date('d', $date2), date('Y', $date2)));
	    					if ($ExDivDate < date('Y-m-d H:i:s', $endOfPrediction)) {
	    						$d['declDate']=null;
	    						$d['exDivDate']=new \DateTime($ExDivDate);
	    						$d['paymentDate']=new \DateTime($PaymentDate);
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
	    						$d['declDate']=null;
	    						$d['exDivDate']=new \DateTime($ExDivDate);
	    						$d['paymentDate']=new \DateTime($PaymentDate);
	    						
	    						if (isset($q[$this->halfYear(date('Y-m-d', strtotime($ExDivDate)))])) {
	    							$d['amount']=$q[$this->halfYear(date('Y-m-d', strtotime($ExDivDate)))]['amount'];
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
	    						$d['declDate']=null;
	    						$d['exDivDate']=new \DateTime($ExDivDate);
	    						$d['paymentDate']=new \DateTime($PaymentDate);
	    						if (isset($q[$this->quarterYear(date('Y-m-d', strtotime($ExDivDate)))])) {
	    							$d['amount']=$q[$this->quarterYear(date('Y-m-d', strtotime($ExDivDate)))]['amount'];
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
    			$dividends[$k]['TaxYear']=$this->getTaxYear($v['paymentDate']->format('Y-m-d H:i:s'));
    		}
    	}

    	return $dividends;
    }
    
    
    public function getDirectorsDealsForCompany($company) {
    	
    	$qb=$this->em->createQueryBuilder()
    		->select('dd.id')
    		->addSelect('dd.code')
    		->addSelect('dd.name')
    		->addSelect('dd.createdOn')
    		->addSelect('dd.declDate')
    		->addSelect('dd.dealDate')
    		->addSelect('dd.type')
    		->addSelect('dd.position')
    		->addSelect('dd.shares')
    		->addSelect('dd.price')
    		->addSelect('dd.value')
    		->from('InvestShareBundle:DirectorsDeals', 'dd')
    		->where('dd.code=:code')
    		->orderBy('dd.dealDate', 'ASC')
    		->setParameter('code', $company);
    	
    	$deals=$qb->getQuery()->getArrayResult();
    	
    	return $deals;
    }
    
    
    public function getFinancialDiaryForCompany($code) {

    	$qb=$this->em->createQueryBuilder()
    		->select('d.id')
    		->addSelect('d.code')
    		->addSelect('d.type')
    		->addSelect('d.name')
    		->addSelect('d.date')
    		->from('InvestShareBundle:Diary', 'd')
    		->where('d.code=:code')
    		->orderBy('d.date', 'ASC')
    		->setParameter('code', $code);
    	
    	$diary=$qb->getQuery()->getArrayResult();

    	return $diary;

    }


    public function getCurrencyRates() {
    	
    	$connection=$this->em->getConnection();
    	 
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
    
    
    public function quarterYear($dateStr) {
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
    
    
    public function halfYear($dateStr) {
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
    
    
    public function isCurrentTaxYear($date) {
    	
    	$taxYearFrom = date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay, date('Y')));
    	$taxYearTo = date('Y-m-d', mktime(0, 0, 0, $this->startTaxYearMonth, $this->startTaxYearDay-1, date('Y')+1));
    	if (date('Y-m-d', strtotime($date)) >= $taxYearFrom && date('Y-m-d', strtotime($date)) <= $taxYearTo) {
    		return true;
    	}
    	return false;
    	
    }
    
    
    public function getTaxYear($dateStr) {
    	
    	$date=strtotime($dateStr);
    	if (date('m-d', $date) > '04-05') {
    		$current=date('y', $date);
    	} else {
    		$current=date('y', $date)-1;
    	}
    	
    	return sprintf('%02d%02d', $current, $current+1);
    	 
    }
    
    
    public function addDirectorsDeals($data) {
    	$ret=false;

    	$dd=$this->doctrine
    		->getRepository('InvestShareBundle:DirectorsDeals')
    		->findOneBy(
    			array(
    				'declDate'	=>$data['declDate'],
    				'dealDate'	=>$data['dealDate'],
    				'type'		=>$data['type'],
    				'code'		=>$data['code'],
    				'shares'	=>$data['shares']
    			)
    		);

    	if (!$dd) {
    		$dd=new DirectorsDeals();
    		
    		$dd->setCreatedOn(new \DateTime('now'));
    		$dd->setCode($data['code']);
    		$dd->setName($data['name']);
    		$dd->setDeclDate($data['declDate']);
    		$dd->setDealDate($data['dealDate']);
    		$dd->setType($data['type']);
    		$dd->setPosition($data['position']);
    		$dd->setShares($data['shares']);
    		$dd->setPrice($data['price']);
    		$dd->setValue($data['value']);
    		
    		$this->em->persist($dd);
    		$this->em->flush();
    		
    		if ($dd->getId()) {
    			$ret=true;
    		}
    	}
    	
    	return $ret;
    }

    
    public function addFinancialDiary($data) {
    	$ret=false;
    
    	if ($this->isFTSE($data['Code'])) {
	    	$fd=$this->doctrine
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
	    
	    		$this->em->persist($fd);
	    		$this->em->flush();
	    
	    		if ($fd->getId()) {
	    			$ret=true;
	    		}
	    	}
    	}
    	 
    	return $ret;
    }
    
    
    public function isFTSE($code) {
    	
    	$qb=$this->em->createQueryBuilder()
    		->select('c.id')
    		->from('InvestShareBundle:Company', 'c')
    		->where('c.code=:code')
    		->setParameter('code', $code);
    	$results=$qb->getQuery()->getArrayResult();
    	
    	if (count($results)) {
			return true;
    	}
    	return false;
    }
    

    public function getCompanyNames($current) {
    	
    	$companies=array();
    	
    	if ($current) {
    		$trades=$this->getTradesData(null, null, null, null, null, null);
    	 
	    	if (count($trades)) {
	    		foreach ($trades as $t) {
	    			if ($t['reference2'] == '') {
	    				$companies[$t['companyCode']]=$t['companyName'];
    				}
    			}
	    	}
    	} else {
    		$qb=$this->em->createQueryBuilder()
    			->select('c.code')
    			->addSelect('c.name')
    			->from('InvestShareBundle:Company', 'c')
    			->orderBy('c.code');
    		
    		$results=$qb->getQuery()->getArrayResult();

    		if (count($results)) {
    			foreach ($results as $result) {
    				$companies[$result['code']]=$result['name'];
    			}
    		}
    	}

    	return $companies;
    }
    

    public function getCurrencyList() {
    	
    	$ret=array();
    	
    	$qb=$this->em->createQueryBuilder()
    		->select('c.currency')
    		->from('InvestShareBundle:Currency', 'c')
    		->groupBy('c.currency');
    	$results=$qb->getQuery()->getArrayResult();
    	
    	if ($results) {
    		foreach ($results as $result) {
    			$ret[]=$result['currency'];
    		}
    	}
    	
    	return $ret;
    }
    
}
