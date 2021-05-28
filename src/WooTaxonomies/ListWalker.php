<?php

namespace Slc\WooTaxonomies;

class ListWalker extends \WC_Product_Cat_List_Walker {

    public function __construct($taxonomySlug)
    {
        $this->tree_type = $taxonomySlug;
	}
}
