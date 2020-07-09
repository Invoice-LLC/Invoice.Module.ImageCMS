<?php

(defined('BASEPATH')) OR exit('No direct script access allowed');

$com_info = [
             'menu_name'   => lang('Invoice', 'payment_method_invoice'), // Menu name
             'description' => lang('Invoice Payment Module', 'payment_method_invoice'), // Module Description
             'admin_type'  => 'window', // Open admin class in new window or not. Possible values window/inside
             'window_type' => 'xhr', // Load method. Possible values xhr/iframe
             'w'           => 600, // Window width
             'h'           => 550, // Window height
             'version'     => '1.0', // Module version
             'author'      => 'Invoice LLC', // Author info
             'icon_class'  => 'icon-qrcode',
            ];

/* End of file module_info.php */