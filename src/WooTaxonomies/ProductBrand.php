<?php

namespace Slc\WooTaxonomies;

class ProductBrand extends CustomTaxonomyAbstract
{

    public function __construct()
    {
        $this->slug = 'product_brand';
        $this->singularName = __('Product brand', 'woocommerce-custom-taxonomies');
        $this->pluralName = __('Product brands', 'woocommerce-custom-taxonomies');
    }

}