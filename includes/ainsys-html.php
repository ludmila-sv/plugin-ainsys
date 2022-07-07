<?php
namespace Ainsysconnector\Master;


class ainsys_html{


    /**
     * Get property from array.
     *
     * @return string
     */
    static function get_property($name, $prop_val, $entiti_saved_settings){
        if ( is_array($prop_val['default'])){
            return isset($entiti_saved_settings[strtolower($name)]) ? $entiti_saved_settings[strtolower($name)] : array_search('1', $prop_val['default']);
        }

        return isset($entiti_saved_settings[strtolower($name)]) ?
            $entiti_saved_settings[strtolower($name)] : $prop_val['default'];
    }

    /**
     * Generate properties for entity field.
     *
     * @return string
     */
    static function generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ){

        $inner_fields = '';
        if (empty($properties))
            return '';

        foreach ( $properties as $item => $settings ){
            $checker_property = $settings['type'] === 'bool' || $item === 'api' ? 'small_property' : '';
            $inner_fields .= '<div class="properties_field ' . $checker_property . '">' ;
            $field_value = $item === 'id' ? $field_slug : self::get_property($item, $settings, $entiti_saved_settings );
            switch ($settings['type']){
                case 'constant':
                    $field_value = $field_value ? $field_value : '<i>' . __('empty', AINSYS_CONNECTOR_TEXTDOMAIN) . '</i>';
                    $inner_fields .= $item === 'api' ? '<div class="entiti_settings_value constant ' . $field_value . '"></div>' : '<div class="entiti_settings_value constant">' . $field_value . '</div>';
                    break;
                case 'bool':
                    $checked = (int)$field_value ? 'checked="" value="1"' : ' value="0"';
                    $checked_text = (int)$field_value ? __('On', AINSYS_CONNECTOR_TEXTDOMAIN) : __('Off', AINSYS_CONNECTOR_TEXTDOMAIN);
                    $inner_fields .= '<input type="checkbox"  class="editor_mode entiti_settings_value " id="' . $item  . '" ' . $checked . '/> ';
                    $inner_fields .= '<div class="entiti_settings_value">' . $checked_text . '</div> ';
                    break;
                case 'int':
                    $inner_fields .= '<input size="10" type="text"  class="editor_mode entiti_settings_value" id="' . $item . '" value="' . $field_value . '"/> ';
                    $field_value = $field_value ? $field_value : '<i>' . __('empty', AINSYS_CONNECTOR_TEXTDOMAIN) . '</i>';
                    $inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
                    break;
                case 'select':
                    $inner_fields .= '<select id="' . $item . '" class="editor_mode entiti_settings_value" name="' . $item . '">';
                    $state_text = '';
                    foreach ( $settings["default"] as $option => $state ){
                        $selected = $option ===  $field_value ? 'selected="selected"' : '';
                        $state_text = $option ===  $field_value ? $option : $state_text;
                        $inner_fields .= '<option size="10" value="' . $option . '" ' . $selected . '>' . $option . '</option>';
                    }
                    $inner_fields .= '</select>';
                    $inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
                    break;
                default:
                    $field_length = $item === 'description' ? 20 : 8;
                    $inner_fields .= '<input size="' . $field_length . '" type="text" class="editor_mode entiti_settings_value" id="' . $item . '" value="' . $field_value . '"/>';
                    $field_value = $field_value ? $field_value : '<i>' . __('empty', AINSYS_CONNECTOR_TEXTDOMAIN) . '</i>';
                    $inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
            }
            /// close //// div class="properties_field"
            $inner_fields .= '</div>';
        }
        return $inner_fields;
    }

    /**
     * Get entiti field settings from DB.
     *
     * @param string $where
     * @param bool $single
     * @return array
     */
    static function get_saved_entiti_settings_from_db( $where = '', $single = true ){
        global $wpdb;
        $query = "SELECT * 
        FROM " . $wpdb->prefix . Ainsys_Settings::$ainsys_entitis_settings . $where;
        $resoult = $wpdb->get_results($query, ARRAY_A);
        if ( isset($resoult[0]["value"]) && $single ){
            $keys = array_column($resoult, 'setting_key');
            if (count($resoult) > 1 && isset(array_flip($keys)['saved_field'])){
                $saved_settins_id = array_flip($keys)['saved_field'];
                $data = maybe_unserialize($resoult[$saved_settins_id]["value"]);
                $data['id'] = $resoult[$saved_settins_id]["id"] ?? 0;
            } else {
                $data = maybe_unserialize($resoult[0]["value"]);
                $data['id'] = $resoult[0]["id"] ?? 0;
            }
        } else{
            $data = $resoult;
        }
        return $data ?? array();
    }

    /**
     * Generate server data transactions HTML.
     *
     * @return string
     */
    static function generate_log_html( $where = '' ){

        global $wpdb;

        $log_html = '<div id="connection_log"><table class="form-table">';
        $log_html_body = '';
        $log_html_header = '';
        $query = "SELECT * 
        FROM " . $wpdb->prefix . Ainsys_Init::$ainsys_log_table . $where;
        $output = $wpdb->get_results($query, ARRAY_A);

        if (empty($output)){
            return '<div class="empty_tab"><h3>' . __('No transactions to display', AINSYS_CONNECTOR_TEXTDOMAIN) . '</h3></div>';
        }

        foreach ( $output as $item ){
            $log_html_body .= '<tr valign="top">';
            $header_full = empty($log_html_header) ? true : false;
            foreach ($item as $name => $value){
                $log_html_header .= $header_full ? '<th>' . strtoupper(str_replace('_', ' ', $name)) . '</th>' : '';
                $log_html_body .= '<td class="' . $name . '">';
                if ($name === 'incoming_call') {
                    $value = (int)$value === 0 ? 'No' : 'Yes';
                }
                if ($name === 'request_data'){
                    $value = maybe_unserialize($value);
                    if ( empty( $value["request_data"] ) ){
                        $log_html_body .= $value ? '<div class="gray_header">' . __('empty', AINSYS_CONNECTOR_TEXTDOMAIN) . '</div>' : $value;
                        continue;
                    }
                    if ( is_array($value) ){
                        if (count($value["request_data"]) > 2 )
                            $log_html_body .= '<div class="request_data_contaner"> <a class="button expand_data_contaner">more</a>';
                        foreach ( $value["request_data"] as $title => $param ){
                            if ($title === "products" && ! empty($param) ){
                                foreach ($param as $prod_id => $product ){
                                    $log_html_body .= '</br> <strong>Prod# ' . $prod_id . '</strong>';
                                    foreach ( $product as $param_title => $poduct_param ){
                                        if (is_array($poduct_param)) continue;
                                        $log_html_body .= '<div><span class="gray_header">' . $param_title . ' : </span>' . maybe_serialize($poduct_param) . '</div>';
                                    }
                                }
                            } else {
                                $log_html_body .= '<div><span class="gray_header">' . $title . ' : </span>' . maybe_serialize($param) . '</div>';
                            }
                        }
                        $log_html_body .= '</div>';
                    }
                } else {
                    $log_html_body .= $value;
                }
                $log_html_body .= '</td>';
            }
            $log_html_body .= '</tr>';
        }
        $log_html .= '<thead><tr>' . $log_html_header . '</tr></thead>' . $log_html_body . '</table> </div>';

        return $log_html ;
    }

    /**
     * Generate debug log HTML.
     *
     * @return string
     */
    static function generate_debug_log(){

        if ( ! (int)Ainsys_Settings::get_option('display_debug') )
            return;

        $html = '
        <div style="color: grey; padding-top: 20px">
        !!Debug info!!
            <ul>
                <li>' . 'connector #' . Ainsys_Settings::get_option('connectors') . '</li>
                <li>' . 'handshake_url - ' . Ainsys_Settings::get_option('handshake_url') . '</li>
                <li>' . 'webhook_url - ' . Ainsys_Settings::get_option('webhook_url') . '</li>
                <li>' . 'debug_log - ' . Ainsys_Settings::get_option('debug_log') . '</li>
            </ul>
        </div>';

        return $html;
    }
}