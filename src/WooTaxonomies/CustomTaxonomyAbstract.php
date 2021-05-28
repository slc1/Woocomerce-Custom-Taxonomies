<?php


namespace Slc\WooTaxonomies;


abstract class CustomTaxonomyAbstract
{

    public $slug;

    public $singularName;

    public $pluralName;

    public $postType = 'product';


    /**
     * Source: https://github.com/woocommerce/woocommerce/wiki/Product-CSV-Importer-&-Exporter#adding-custom-import-columns-developers
     **/

    /**
     * Register the 'Custom Column' column in the importer.
     *
     * @param array $options
     * @return array $options
     */
    public function addColumnToImporter($options)
    {

        // column slug => column name
        $options[$this->slug] = $this->singularName;

        return $options;
    }

    /**
     * Add automatic mapping support for 'Custom Column'.
     * This will automatically select the correct mapping for columns named 'Custom Column' or 'custom column'.
     *
     * @param array $columns
     * @return array $columns
     */
    public function addColumnToMappingScreen($columns)
    {

        // potential column name => column slug
        $columns[ucfirst($this->slug)] = $this->slug;
        $columns[$this->slug] = $this->slug;

        return $columns;
    }

    /**
     * @param $product
     * @param $data
     * @return mixed
     */
    public function woocommerceAddTaxonomy($product, $data)
    {
        // set a variable with your custom taxonomy slug

        if (is_a($product, 'WC_Product')) {
            if (!empty($data[$this->slug])) {
                $product->save();
                $terms = $this->parse_taxonomy_field(str_replace('&gt;', '>', $data[$this->slug]));
                wp_set_object_terms($product->get_id(), $terms, $this->slug);
            }
        }

        return $product;
    }

    /**
     * Parse a category field from a CSV.
     * Categories are separated by commas and subcategories are "parent > subcategory".
     *
     * @param string $value Field value.
     *
     * @return array of arrays with "parent" and "name" keys.
     */
    public function parse_taxonomy_field( $value ) {
        if ( empty( $value ) ) {
            return array();
        }

        $row_terms  = $this->explode_values( $value );
        $categories = array();

        foreach ( $row_terms as $row_term ) {
            $parent = null;
            $_terms = array_map( 'trim', explode( '>', $row_term ) );
            $total  = count( $_terms );

            foreach ( $_terms as $index => $_term ) {
                // Don't allow users without capabilities to create new categories.
                if ( ! current_user_can( 'manage_product_terms' ) ) {
                    break;
                }

                $term = wp_insert_term( $_term, $this->slug, array( 'parent' => intval( $parent ) ) );

                if ( is_wp_error( $term ) ) {
                    if ( $term->get_error_code() === 'term_exists' ) {
                        // When term exists, error data should contain existing term id.
                        $term_id = $term->get_error_data();
                    } else {
                        break; // We cannot continue on any other error.
                    }
                } else {
                    // New term.
                    $term_id = $term['term_id'];
                }

                // Only requires assign the last category.
                if ( ( 1 + $index ) === $total ) {
                    $categories[] = $term_id;
                } else {
                    // Store parent to be able to insert or query categories based in parent ID.
                    $parent = $term_id;
                }
            }
        }

        return $categories;
    }

    /**
     * Explode CSV cell values using commas by default, and handling escaped
     * separators.
     *
     * @since  3.2.0
     * @param  string $value     Value to explode.
     * @param  string $separator Separator separating each value. Defaults to comma.
     * @return array
     */
    protected function explode_values( $value, $separator = ',' ) {
        $value  = str_replace( '\\,', '::separator::', $value );
        $values = explode( $separator, $value );
        $values = array_map( array( $this, 'explode_values_formatter' ), $values );

        return $values;
    }

    /**
     * Remove formatting and trim each value.
     *
     * @since  3.2.0
     * @param  string $value Value to format.
     * @return string
     */
    protected function explode_values_formatter( $value ) {
        return trim( str_replace( '::separator::', ',', $value ) );
    }


    /**
     *
     */
    public function registerTaxonomy()
    {
        $labels = array(
            'name' => $this->pluralName,
            'singular_name' => $this->singularName,
            'menu_name' => $this->singularName,
            'all_items' => __('All') . $this->pluralName,
            'parent_item' => __('Parent') . ' ' .  $this->singularName,
            'parent_item_colon' => __('Parent') . ' ' .  $this->singularName . ':',
            'new_item_name' => __('New') . ' ' . $this->singularName . ' ' . __('Name'),
            'add_new_item' => __('Add New') . ' ' .  $this->singularName,
            'edit_item' => __('Edit') . ' ' .  $this->singularName,
            'update_item' => __('Update') . ' ' .  $this->singularName,
            'separate_items_with_commas' => __('Separate') . ' ' . $this->singularName . ' ' . __('with commas'),
            'search_items' => __('Search') . ' ' .  $this->pluralName,
            'add_or_remove_items' => __('Add or remove'). ' ' .  $this->pluralName,
            'choose_from_most_used' => __('Choose from the most used'). ' ' .  $this->pluralName,
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
        );
        register_taxonomy($this->slug, $this->postType, $args);
        register_taxonomy_for_object_type('item', $this->postType);
    }

}