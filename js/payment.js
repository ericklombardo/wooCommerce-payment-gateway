function fbl_formatCardElem(elem, type){
	const isInit = $(elem).data('init');
	if(!isInit){
	  $(elem).payform(`formatCard${type}`);
	  $(elem).data('init', true);
	}
}

function fbl_onBlurCardNumber(elem){
	const iconElem = $('#fbl_number_field i')
	const number = $(elem).val(); 
	const cardType = $.payform.parseCardType(number);
	iconElem.removeClass((index, className) => 
		(className.match(/(^|\s)icon-\S+/g) || []).join(' ')
	);	
	if(cardType === 'visa'){
		iconElem.addClass('icon-cc-visa');
	}
	else if(cardType === 'mastercard'){
		iconElem.addClass('icon-cc-mastercard');
	}
	else {
		iconElem.addClass('icon-credit-card-alt');
	}	
}

jQuery($ => {

    let processing = false;

	const isStringNullOrEmpty = (val) => {
		switch (val) {
			case "":
			case 0:
			case "0":
			case null:
			case false:
			case undefined:
			case typeof this === 'undefined':
				return true;
			default: 
				return false;
		}
    };

	const isStringNullOrWhiteSpace = (val) => 
		isStringNullOrEmpty(val) || val.replace(/\s/g, "") === '';
		
	const onError = () => {
		const btn = document.getElementById('place_order');
        btn.disabled = false;
		processing = false;
		$('.woocommerce-error li').each(function(){
            console.error($(this).text());
        });		
	};	

    const sendPayment = () => {          
        if(processing){
            return false;
        }          
        const btn = document.getElementById('place_order');
        btn.disabled = true;
        processing = true;
        let message;     
		const validCardTypes = ['visa', 'mastercard'];	
        const number = $('#fbl_number').val(); 
        const name = $('#fbl_name').val(); 
		const expiryStr = $('#fbl_expiry').val(); 
		const cvc = $('#fbl_cvc').val(); 
        const expiry = $.payform.parseCardExpiry(expiryStr);
		const cardType = $.payform.parseCardType(number);

		if(isStringNullOrWhiteSpace(name)){
			message = 'Por favor ingrese el titular de la tarjeta';
		}
        else if(!$.payform.validateCardNumber(number)){
            message = 'Por favor ingrese un número de tarjeta válido';
        }
        else if(!validCardTypes.includes(cardType)){
            message = 'Solo se permiten tarjetas Visa y Mastercard';
        }
        else if(!$.payform.validateCardExpiry(expiry.month, expiry.year)){
            message = 'Por favor ingrese un mes y año válido';
        }
        else if(!$.payform.validateCardCVC(cvc, cardType)){
            message = 'Por favor ingrese un código de verificación válido';
        }

        if(message){
            alert(message);
            btn.disabled = false;
            processing = false;			
            return false;
        }
		
		$('#fbl_type').val(cardType);
		$('#fbl_month').val(expiry.month);
		$('#fbl_year').val(expiry.year);

        $('form.checkout.woocommerce-checkout').submit(); 
    }

	$('form.checkout.woocommerce-checkout').on('checkout_place_order', sendPayment);
	$(document.body).on('checkout_error', onError);
	
});