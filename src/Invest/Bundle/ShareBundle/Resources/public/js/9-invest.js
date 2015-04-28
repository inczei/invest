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
			$('#popupDiv')
				.html('<input type="button" class="closeButton" value="X">'+'<div id="container" style="height: '+(Math.round($(window).height()*0.7))+'px; width: '+(Math.round($(window).width()*0.7))+'px"></div>')
				.css('width', Math.round($(window).width()*0.8))
				.css('height', Math.round($(window).height()*0.8))
				.center()
				.fadeIn(250);
//
			
			$('#container').highcharts('StockChart', {
			        credits: {
						enabled: false
				    },
					title: {
						text: 'Portfolio : '+dName
					},
					chart: {
						animation: true,
						width: 900 // (Math.round($(window).width()*0.7))
					},
					navigator : {
						enabled: true,
						adaptToUpdatedData: false,
						series : {
							data : data
						}
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
			        series: data,
				});
			
//			
		});
	});
	
	$('body').delegate('[class=closeButton]', 'click', function () {
		$(this).parent().fadeOut(250);
	});
	
});
