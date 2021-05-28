<?php


namespace Slc\WooTaxonomies;


class CustomTaxonomyBuilder
{

    private static $instance;

    protected $builtTaxonomySlugs = [];

    public function build(CustomTaxonomyAbstract $customTaxonomy)
    {
        $customTaxonomy->registerTaxonomy();
        add_filter('woocommerce_csv_product_import_mapping_options', [$customTaxonomy, 'addColumnToImporter']);
        add_filter('woocommerce_csv_product_import_mapping_default_columns', [$customTaxonomy, 'addColumnToMappingScreen']);
        add_filter('woocommerce_product_import_inserted_product_object', [$customTaxonomy, 'woocommerceAddTaxonomy'], 10, 2);
        new WooAdminTaxonomy($customTaxonomy->slug);
        add_action('woocommerce_product_meta_end', function () use ($customTaxonomy) {
            global $product;
            echo get_the_term_list( $product->get_id(), $customTaxonomy->slug,  '<span class="posted_in">' . $customTaxonomy->singularName . ': ', '</span>' );
        });
        //register_widget(new CustomTaxonomyWidget($customTaxonomy->slug, $customTaxonomy->pluralName));
        $this->builtTaxonomySlugs[] = $customTaxonomy->slug;
    }

    /**
     * Singleton
     * @return CustomTaxonomyBuilder
     */
    public static function getInstance(): CustomTaxonomyBuilder
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getTaxonomySlugs()
    {
        return $this->builtTaxonomySlugs;
    }

}