/* Modifided script from the simple-page-ordering plugin */
jQuery(document).ready(function($) {

	$('table.widefat.wp-list-table tbody th, table.widefat tbody td').css('cursor','move');
		
	$("table.widefat.wp-list-table tbody").sortable({
		items: 'tr:not(.inline-edit-row)',
		cursor: 'move',
		axis: 'y',
		containment: 'table.widefat',
		placeholder: 'product-cat-placeholder',
		scrollSensitivity: 40,
		placeholder: 'product-cat-placeholder',
		helper: function(e, ui) {					
			ui.children().each(function() { jQuery(this).width(jQuery(this).width()); });
			return ui;
		},
		start: function(event, ui) {
			if ( ! ui.item.hasClass('alternate') ) ui.item.css( 'background-color', '#ffffff' );
			ui.item.children('td,th').css('border-bottom-width','0');
			ui.item.css( 'outline', '1px solid #aaa' );
		},
		stop: function(event, ui) {		
			ui.item.removeAttr('style');
			ui.item.children('td,th').css('border-bottom-width','1px');		
		},
		update: function(event, ui) {	
			var termid = ui.item.find('.check-column input').val();	// this post id
			if(termid==undefined) termid=1;
			var termparent = ui.item.find('.parent').html(); 	// post parent
			
			var prevtermid = ui.item.prev().find('.check-column input').val();
			var nexttermid = ui.item.next().find('.check-column input').val();
			
			// can only sort in same tree
			var prevtermparent = undefined;
			if ( prevtermid != undefined ) {
				var prevtermparent = ui.item.prev().find('.parent').html();
				if ( prevtermparent != termparent) prevtermid = undefined;
			}
			
			var nexttermparent = undefined;
			if ( nexttermid != undefined ) {
				nexttermparent = ui.item.next().find('.parent').html();
				if ( nexttermparent != termparent) nexttermid = undefined;
			}	
			
			// if previous and next not at same tree level, or next not at same tree level and the previous is the parent of the next, or just moved item beneath its own children 					
			if ( ( prevtermid == undefined && nexttermid == undefined ) || ( nexttermid == undefined && nexttermparent == prevtermid ) || ( nexttermid != undefined && prevtermparent == termid ) ) {
				$("table.widefat tbody").sortable('cancel');
				return;
			}
						
			// show spinner
			ui.item.find('.check-column input').hide().after('<img alt="processing" src="images/wpspin_light.gif" class="waiting" style="margin-left: 6px;" />');
			
			// go do the sorting stuff via ajax
			$.post( ajaxurl, { action: 'terms-ordering', id: termid, nextid: nexttermid, taxonomy: terms_order.taxonomy }, function(response){			
				if ( response == 'children' ) window.location.reload();
				else {
					ui.item.find('.check-column input').show().siblings('img').remove();
				}
			});
			
			// fix cell colors
			$( 'table.widefat tbody tr' ).each(function(){
				var i = jQuery('table.widefat tbody tr').index(this);
				if ( i%2 == 0 ) jQuery(this).addClass('alternate');
				else jQuery(this).removeClass('alternate');
			});
		}
	});

});
