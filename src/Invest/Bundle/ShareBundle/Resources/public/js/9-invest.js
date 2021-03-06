/**
 * Invest
 * Author: Imre Incze
 * Created on: 05/09/2014 
 */

jQuery.fn.center = function () {
    this.css("position","absolute");
    this.css("top", Math.max(0, (($(window).height() - $(this).outerHeight()) / 2) + 
                                                $(window).scrollTop()) + "px");
    this.css("left", Math.max(0, (($(window).width() - $(this).outerWidth()) / 2) + 
                                                $(window).scrollLeft()) + "px");
    return this;
}

$(document).ready(function(){
	
	$('body').delegate('input[class=dateInput]', 'focus', function() {
		$(this).datepicker({dateFormat: 'dd/mm/yy'});
	});
	$('body').delegate('a', 'click', function() {
		if ($(this).attr('question')) {
			return (confirm($(this).attr('question')));
		}
		return true;
	});
	
	$('body').delegate('[name=showhide]', 'click', function () {
		var s=$(this).html();
		var col=$(this).attr('column');
		if (s == 'Hide') {
			s='Show';
			$('[class='+col+']').each(function(index) {
				$(this).hide();
			});
		} else {
			s='Hide';
			$('[class='+col+']').each(function(index) {
				$(this).show();
			});
		}
		$(this).html(s);
	});
	
	$('body').delegate('[class=graphButton]', 'click', function () {
		var dName=$(this).attr('dName');
		$.ajax({
			url: $(this).attr('data-url'),
			dataType: 'json',
			timeout: 10000
		})
		.error(function(jqXHR, status, errorThrown) {
			alert('ajax error');
		})
		.done(function(data) {
//
	    	$('<div></div>').html('<div id="chartContainer"></div>')
    		.dialog({
	    		modal: true,
	    		title: 'Portfolio : '+dName,
	    		zIndex: 2000,
	    		autoOpen: true,
	    		width: '920',
	    		position: 'top',
	    		resizable: true,
	    		close: function (event, ui) {
	    			$(this).remove();
	    		}
	    	});

	    	var tmp=new Highcharts.StockChart({
			        credits: {
						enabled: false
				    },
					title: {
						text: 'Portfolio : '+dName
					},
					chart: {
						animation: true,
						width: 900,
						renderTo: 'chartContainer'
					},
					navigator : {
						enabled: true,
						adaptToUpdatedData: false,
					},
					scrollbar: {
						enabled: true,
						liveRedraw: true
					},
					legend: {
						enabled: true,
						align: 'center',
						verticalAlign: 'bottom'
					},
					loading: {
						showDuration: 50,
						hideDuration: 50,
						labelStyle: {
							color: 'white',
							fontSize: 'large'
						},
						style: {
							backgroundColor: 'gray'
						}
					},
					xAxis : {
						min: 1411027700000,
						startOnTick: true,
						maxZoom: 3600 * 1000,
						minRange: 3600 * 1000 // one hour
					},
		            rangeSelector : {
		                buttons: [{
		                    type: 'day',
		                    count: 1,
		                    text: '1d'
		                }, {
		                    type: 'month',
		                    count: 1,
		                    text: '1m'
		                }, {
		                    type: 'month',
		                    count: 3,
		                    text: '3m'
		                }, {
		                    type: 'month',
		                    count: 6,
		                    text: '6m'
						}, {
		                    type: 'year',
		                    count: 1,
		                    text: '1y'
		                }, {
		                    type: 'all',
		                    text: 'All'
		                }],
						inputEnabled: true,
						selected : 2 // 1 month
					},
					plotOptions: {
						bar: {
							pointStart: Date.UTC(2014, 1, 1)
						}
					},
			        series: data
				});
//			
		});
	});
	
	$('body').delegate('[class=closeButton]', 'click', function () {
		$(this).parent().fadeOut(250);
	});
	
});

function ajaxUpdatePrices(url) {
	var codes=[];
	$("td[id$='_price']").each(function() {
		var id=$(this).attr('id').split('_');
		if (id.length == 3) {
			codes.push(id[1]);	
		}
	});
	if (codes.length > 0) {
		$.ajax({
			url: url,
			type: 'POST',
			dataType: 'json',
			data : {
				codes: codes
			},
			timeout: 10000
		})
		.error(function(jqXHR, status, errorThrown) {
//			alert('ajax error');
		})
		.done(function(data) {
			for(idx in data) {
				var old=$('td#au_'+idx+'_price').html();
				if (typeof old !== 'undefined') {
					$('td#au_'+idx+'_price').removeClass();
					$('td#au_'+idx+'_updated').removeClass();
					if (old != data[idx].price) {
						var orig_color=$('td#au_'+idx+'_price').css('backgroundColor');
						if (data[idx].change > 0) {
							var col='#0000c8';
						} else {
							var col='#c80000';
						}
						$('td#au_'+idx+'_price').html(data[idx].price).css('backgroundColor',col).animate({'backgroundColor':orig_color}, 10000);
						$('td#au_'+idx+'_change').html(data[idx].change).removeClass().css('backgroundColor',col).animate({'backgroundColor':orig_color}, 10000);
						$('td#au_'+idx+'_changep').html(data[idx].changep+' %').removeClass().css('backgroundColor',col).animate({'backgroundColor':orig_color}, 10000);
						$('td#au_'+idx+'_updated').html(data[idx].updated);
						if (data[idx].change == 0) {
							$('td#au_'+idx+'_change').addClass('notChanged');
							$('td#au_'+idx+'_changep').addClass('notChanged');
						} else if (data[idx].change > 0) {
							$('td#au_'+idx+'_change').addClass('changedUp');
							$('td#au_'+idx+'_changep').addClass('changedUp');
						} else if (data[idx].change < 0) {
							$('td#au_'+idx+'_change').addClass('changedDown');
							$('td#au_'+idx+'_changep').addClass('changedDown');
						}
					}
					$('td#au_'+idx+'_updated').addClass(data[idx].class);					
				}
			}
		});
	}
	
}