<?php

// Heading
$_['heading_title'] = 'Getepay';

// Text 
$_['text_payment'] = 'Payment';
$_['text_extension'] = 'Extensions';
$_['text_edit'] = 'Edit Getepay';
$_['text_success'] = 'Success: You have modified Getepay account details!';
$_['text_getepay'] = '<a href="http://getepay.in" target="_blank"><img src="view/image/payment/gplogo.png" alt="Getepay" title="Getepay" style="border: 0px solid #EEEEEE; width:40px" /></a>';
$_['text_authorize'] = 'Authorize Only';
$_['text_capture'] = 'Authorize and Capture';

// Entry
$_['entry_key_mid'] = 'Getepay Mid';
$_['entry_key_terminalId'] = 'Getepay Terminal Id';
$_['entry_key_key'] = 'Getepay Key';
$_['entry_key_iv'] = 'Getepay IV';
$_['entry_key_url'] = 'Getepay Url';
$_['entry_order_status'] = 'Order Status';
$_['entry_status'] = 'Status';
$_['entry_sort_order'] = 'Sort Order';
$_['entry_webhook_secret'] = 'Getepay Webhook Secret';
$_['entry_webhook_status'] = 'Webhook Status';
$_['entry_webhook_url'] = 'Webhook URL:';
$_['entry_payment_action'] = 'Payment Action';
$_['entry_max_capture_delay'] = 'Max Delay in Payment Capture';
$_['entry_max_capture_delay1'] = 'Max Delay in Payment Capture in minutes';

//tooltips
$_['help_key_id'] = 'The Api Key Id and Key Secret you will recieve from the API keys section of Getepay Dashboard. Use test Key for testing purposes.';
$_['help_order_status'] = 'The status of the order to be marked on completion of payment.';
$_['help_webhook_url'] = 'Set Getepay \'order.paid\' webhooks to call this URL with the below secret.';
$_['help_max_delay'] = 'It will gets used by \'payment.authorized\' webhooks to capture the payment after this much time, in case of Authorize Only Pament Action.';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify payment Getepay!';
$_['error_key_mid'] = 'Getepay Mid is Required!';
$_['error_key_terminalId'] = 'Getepay Terminal Id is Required!';
$_['error_key_key'] = 'Getepay Key is Required!';
$_['error_key_iv'] = 'Getepay IV is Required!';
$_['error_key_url'] = 'Getepay Url is Required!';
$_['error_webhook_secret'] = 'Webhook Secret Required!';
