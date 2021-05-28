<?php
/*
Plugin Name: Woocommerce Custom Taxonomies
Plugin URI: http://slc.org.ua/
Description: Create environment to create Woocommerce Custom Taxonomies
Version: 1.0.0
Author: SLC
Author http://slc.org.ua/
*/

use Slc\WooTaxonomies\CustomTaxonomyBuilder;

require_once('vendor/autoload.php');

add_action('init', function () {
    if (class_exists('WooCommerce')) {
        $customTaxonomyBuilder = CustomTaxonomyBuilder::getInstance();
        $customTaxonomyBuilder->build(new \Slc\WooTaxonomies\ProductBrand());
    }
});

