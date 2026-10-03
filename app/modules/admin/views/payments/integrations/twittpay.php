<?php
  $payment_elements = [
    [
      'label'      => form_label('Endpoint URL'),
      'element'    => form_input(['name' => "payment_params[option][api_url]", 'value' => @$payment_option->api_url, 'type' => 'text', 'class' => $class_element, 'placeholder' => 'https://checkout.twittpay.com']),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
    ],
    [
      'label'      => form_label('Brand Key'),
      'element'    => form_input(['name' => "payment_params[option][api_key]", 'value' => @$payment_option->api_key, 'type' => 'text', 'class' => $class_element]),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
    ],
    [
      'label'      => form_label('USD to BDT Rate'),
      'element'    => form_input(['name' => "payment_params[option][currency_rate]", 'value' => @$payment_option->currency_rate, 'type' => 'text', 'class' => $class_element, 'placeholder' => '120']),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
    ],
  ];
  echo render_elements_form($payment_elements);
?>
