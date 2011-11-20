jQuery( document ).ready( function( $ ){
	
	//hide content on load
	if ( pagenow == 'leter' || pagenow == 'letter' ) {
		$('.postarea').hide().after( '<div id="contentToggle"><a href="#">' + last_letter.showContent + '</a></div>' );
	}
	
	//content toggle
	$('#contentToggle').click( function() {
		if ( $('.postarea').is(':hidden') ) {
			$('#contentToggle').fadeOut('slow', function() {
				$('.postarea').fadeIn( );
				$('#contentToggle').addClass( 'minimized' );
				$('#contentToggle a').text( last_letter.hideContent );
				$('#contentToggle').fadeIn();
			});
		} else {
			$('#contentToggle').fadeOut();
			$('.postarea').fadeOut( 'slow', function() {
				$('#contentToggle a').text( last_letter.showContent );
				$('#contentToggle').removeClass( 'minimized' );
				$('#contentToggle').fadeIn();
			});
			
		}
	});
	
	//add new recipient row
	$('#newRecipient').click( function() {
		$('.recipientDiv:last').after( $('.recipientDiv:last').clone() );
		$('.recipientDiv:last input').val( '' );
		return false;
	});
	
	//show remove on hover
	$('.recipientDiv').not(':last').hover( function() {
		//in
		$(this).children('.recipientRemove').fadeIn();
		
	}, function() {
		//out
		$(this).children('.recipientRemove').fadeOut();
	});
	
	$('.recipientRemove a').click( function() {
		$(this).parent().parent().remove();
		return false;
	});
	
});