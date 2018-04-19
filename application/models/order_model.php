<?php
class Order_model extends Master_model
{
    protected $_tablename = 'orderlist';
    private $_total_count = 0;
    private $_map_property = array(
      'House number/name and street',
      'Postcode',
      'Year',
      'Message',
      'Map Address',
      'Times',
      'custom address',
      'custom city',
      'custom State',
      'custom zip'
    );
    function __construct() {
        parent::__construct();
    }

    public function getTotalCount(){ return $this->_total_count; }
    public function getMapProperties(){ return $this->_map_property; }

    public function checkMapProduct( $properties )
    {
      $return = false;
      if( count($properties) > 0 )
      foreach( $properties as $item )
      {
        $return = true; // If there is any property, we consider it as Map Product : 2017.05.12 : By Jubin Ri
        if( in_array($item->name, $this->_map_property ) ){
          $return = true;
          break;
        }
      }

      return $return;
    }

    /**
    * Get the list of product/ varints
    * array(
    *     'customer_name' => '',       // String
    *     'sort' => '',                   // String "{column} {order}"
    *     'page_number' => '',            // Int, default : 0
    *     'page_size' => '',              // Int, default Confing['PAGE_SIZE'];
    *     'is_coupon' => '',              // Int, 0: all, 1: discount, 2: other / default : 0
    );
    */
    public function getList( $arrCondition )
    {
        $where = array();

        // Build the where clause

        $originalDate = $arrCondition['created_at'];
        $arrCondition['created_at'] = date("Y-m-d", strtotime($originalDate));

        $where['shop'] = $this->_shop;
        if( !empty( $arrCondition['product_name'] ) ) $where["product_name LIKE '%" . str_replace( "'", "\\'", $arrCondition['product_name'] ) . "%'"] = '';
        if( !empty( $arrCondition['customer_name'] ) ) $where["customer_name LIKE '%" . str_replace( "'", "\\'", $arrCondition['customer_name'] ) . "%'"] = '';
        if( !empty( $arrCondition['order_name'] ) ) $where["order_name LIKE '%" . str_replace( "'", "\\'", $arrCondition['order_name'] ) . "%'"] = '';
        //if( !empty( $arrCondition['created_at'] ) ) $where["created_at LIKE '" . str_replace( "'", "\\'", $arrCondition['created_at'] ) . "%'"] = '';

        // Get the count of records
        foreach( $where as $key => $val )
        if( $val == '' )
            $this->db->where( $key );
        else
            $this->db->where( $key, $val );
        $query = $this->db->get( $this->_tablename);
        $this->_total_count = $query->num_rows();

        // Select fields
        $select = !empty( $arrCondition['is_all'] ) ? '*' : "id, orderID, order_id, order_name, email, created_at, customer_name, amount, fulfillment_status, num_products, country, product_id, variant_id, product_name, financial_status, sku, shipping_option, note, exported_status";
        $this->db->select( $select );

        // Sort
        if( isset( $arrCondition['sort'] ) ) $this->db->order_by( $arrCondition['sort'] );
        $this->db->order_by( 'created_at', 'DESC' );

        // Limit
        if( isset( $arrCondition['page_number'] ) )
        {
            $page_size = isset( $arrCondition['page_size'] ) ? $arrCondition['page_size'] : $this->config->item('PAGE_SIZE');
            $this->db->limit( $page_size, $arrCondition['page_number'] );
        }

        foreach( $where as $key => $val )
        if( $val == '' )
            $this->db->where( $key );
        else
            $this->db->where( $key, $val );

        //$this->db->where ( 'fulfillment_status', 'fulfilled');
        //$this->db->where ( 'exported_status', '0');
        $query = $this->db->get( $this->_tablename );

        return $query;
    }

    public function getOrderNameList($arrCondition)
    {
        $where = array();

        // Build the where clause

        $originalDate = $arrCondition['created_at'];
        $arrCondition['created_at'] = date("Y-m-d", strtotime($originalDate));

        $where['shop'] = $this->_shop;
        if( !empty( $arrCondition['customer_name'] ) ) $where["customer_name LIKE '%" . str_replace( "'", "\\'", $arrCondition['customer_name'] ) . "%'"] = '';
        if( !empty( $arrCondition['order_name'] ) ) $where["order_name LIKE '%" . str_replace( "'", "\\'", $arrCondition['order_name'] ) . "%'"] = '';
        //if( !empty( $arrCondition['created_at'] ) ) $where["created_at LIKE '" . str_replace( "'", "\\'", $arrCondition['created_at'] ) . "%'"] = '';

        //Get prefix from sku
        $this->db->select ( 'prefix' );
        $this->db->from ( 'sku');
        $this->db->where ( 'shop', $this->_shop);
        $query = $this->db->get ();
        $prefix_array = $query->result ();

        if(sizeof($prefix_array) > 0){

            $prefix_line = '';
            foreach($prefix_array as $obj)
            {
                $prefix_line = $prefix_line . "sku LIKE '" . str_replace( "'", "\\'", $obj->prefix ) . "%'" . " OR ";
            }
            //remove last 'OR'
            $prefix_line = substr($prefix_line, 0, (strlen($prefix_line) - 4));
            $prefix_line = '(' . $prefix_line . ')';

            $where[ $prefix_line ] = '';
        }

        // Select fields
        $this->db->distinct();
        $select = "order_name";
        $this->db->select( $select );

        foreach( $where as $key => $val )
        if( $val == '' )
            $this->db->where( $key );
        else
            $this->db->where( $key, $val );

        $this->db->where ( 'fulfillment_status', 'fulfilled');
        $this->db->where ( 'exported_status', '0');
        $query = $this->db->get( $this->_tablename );

        return $query->result();
    }

    public function setExported($result)
    {
        foreach($result as $line){
            $this->db->where('order_id', $line['order_id']);
            $this->db->update( $this->_tablename, array('exported_status' => '1'));
        }
    }

    public function getFileList()
    {
        $this->db->select( 'COUNT(id) AS cnt, file_no, down_date');
        $this->db->where( 'file_no != 0' );
        $this->db->where( 'shop', $this->_shop );
        $this->db->order_by( 'file_no DESC' );
        $this->db->group_by( 'file_no');

        return $this->db->get( $this->_tablename );
    }

    public function getNewFileNo()
    {
        $return = 0;
        $query = $this->getFileList();

        if( $query->num_rows() > 0 )
        foreach( $query->result() as $row )
        if( $row->file_no > $return ) $return = $row->file_no;

        return $return + 1;
    }

    // Get the lastest order date
    public function getLastOrderDate()
    {
        $return = '';

        $this->db->select( 'created_at' );
        $this->db->order_by( 'created_at DESC' );
        $this->db->limit( 1 );
        $this->db->where( 'shop', $this->_shop );

        $query = $this->db->get( $this->_tablename );

        if( $query->num_rows() > 0 )
        {
            $res = $query->result();

            $return = $res[0]->created_at;
        }

        return $return;
    }

    /**
    * Add order and check whether it's exist already
    *
    * @param mixed $order
    */
    public function add( $order )
    {
        // Check the order is exist already
        $query = parent::getList('order_name = \'' . $order->name . '\'' );
        if( $query->num_rows() > 0 ) {
            $customer_name = '';
            if( isset( $order->customer)) $customer_name = $order->customer->first_name . ' ' . $order->customer->last_name;

            $country = '';
            if( isset($order->shipping_address->country_code)) $country = $order->shipping_address->country_code;

            // Get the number of map products
            foreach( $order->line_items as $line_item )
            {
              if(($line_item->product_id != null) && isset($order->billing_address) && isset($order->shipping_address)){
                // Insert data
                $data = array(
                    'orderID' => $order->id,
                    'order_id' => $line_item->id,
                    'customer_name' => $customer_name,
                    'email' => $order->email,
                    'product_name' => $line_item->name,
                    'product_id' => $line_item->product_id,
                    'variant_id' => $line_item->variant_id,
                    'order_name' => $order->name,
                    'created_at' =>  str_replace('T', ' ', $order->created_at) ,
                    'amount' => $line_item->price,
                    'country' => $country,
                    'num_products' => $line_item->quantity,
                    'fulfillment_status' => empty($line_item->fulfillment_status) ? '' :  $line_item->fulfillment_status,
                    'data' => base64_encode( json_encode( $line_item ) ),
                    'shipping_lines' => base64_encode( json_encode( $order->shipping_lines ) ),
                    'billing_address' => base64_encode( json_encode( $order->billing_address ) ),
                    'shipping_address' => base64_encode( json_encode( $order->shipping_address ) ),
                    'financial_status' => empty($order->financial_status) ? '' :  $order->financial_status,
                    'sku' => $line_item->sku,
                    'shipping_option' => 'standard',
                    'note' => $order->note,
                    'currency' => $order->currency,
                    'exported_status' => 0
                );

                $query = parent::getList('order_id = \'' . $line_item->id . '\'' );
                if($query->num_rows() == 0){
                    parent::add( $data );
                }
                else
                {
                    $old_array = $query->result();
                    $old_order = $old_array[0];
                    $id = $old_order->id;
                    $data = array(
                        'orderID' => $order->id,
                        'order_id' => $line_item->id,
                        'customer_name' => $customer_name,
                        'email' => $order->email,
                        'product_name' => $line_item->name,
                        'product_id' => $line_item->product_id,
                        'variant_id' => $line_item->variant_id,
                        'order_name' => $order->name,
                        'created_at' =>  str_replace('T', ' ', $order->created_at) ,
                        'amount' => $line_item->price,
                        'country' => $country,
                        'num_products' => $line_item->quantity,
                        'fulfillment_status' => empty($line_item->fulfillment_status) ? '' :  $line_item->fulfillment_status,
                        'data' => base64_encode( json_encode( $line_item ) ),
                        'shipping_lines' => base64_encode( json_encode( $order->shipping_lines ) ),
                        'billing_address' => base64_encode( json_encode( $order->billing_address ) ),
                        'shipping_address' => base64_encode( json_encode( $order->shipping_address ) ),
                        'financial_status' => empty($order->financial_status) ? '' :  $order->financial_status,
                        'sku' => $line_item->sku,
                        'shipping_option' => $old_order->shipping_option,
                        'note' => $old_order->note,
                        'currency' => $order->currency,
                        'exported_status' => $old_order->exported_status
                    );
                    parent::update( $id, $data );
                }
              }
            }

            return true;
        }
        else{
            $customer_name = '';
            if( isset( $order->customer)) $customer_name = $order->customer->first_name . ' ' . $order->customer->last_name;

            $country = '';
            if( isset($order->shipping_address->country_code)) $country = $order->shipping_address->country_code;

            // Get the number of map products
            foreach( $order->line_items as $line_item )
            {
              if(($line_item->product_id != null) && isset($order->billing_address) && isset($order->shipping_address)){
                // Insert data
                $data = array(
                    'orderID' => $order->id,
                    'order_id' => $line_item->id,
                    'customer_name' => $customer_name,
                    'email' => $order->email,
                    'product_name' => $line_item->name,
                    'product_id' => $line_item->product_id,
                    'variant_id' => $line_item->variant_id,
                    'order_name' => $order->name,
                    'created_at' =>  str_replace('T', ' ', $order->created_at) ,
                    'amount' => $line_item->price,
                    'country' => $country,
                    'num_products' => $line_item->quantity,
                    'fulfillment_status' => empty($line_item->fulfillment_status) ? '' :  $line_item->fulfillment_status,
                    'data' => base64_encode( json_encode( $line_item ) ),
                    'shipping_lines' => base64_encode( json_encode( $order->shipping_lines ) ),
                    'billing_address' => base64_encode( json_encode( $order->billing_address ) ),
                    'shipping_address' => base64_encode( json_encode( $order->shipping_address ) ),
                    'financial_status' => empty($order->financial_status) ? '' :  $order->financial_status,
                    'sku' => $line_item->sku,
                    'shipping_option' => 'standard',
                    'note' => $order->note,
                    'currency' => $order->currency,
                    'exported_status' => 0
                );

                $query = parent::getList('order_id = \'' . $line_item->id . '\'' );
                if($query->num_rows() == 0){
                    parent::add( $data );
                }
              }
            }

            return true;
        }
    }

    /**
    * Get one Order info from Order_Id
    *
    * @param mixed $order_id
    */
    public function getOrderfromId ( $order_id )
    {
      $query = parent::getList('order_id = \'' . $order_id . '\'' );
        return $query->result();
    }

    /**
    * Clear the download record
    *
    * @param mixed $file_no
    */
    public function clear( $file_no )
    {
        $this->db->where( 'file_no', $file_no );
        $this->db->where( 'shop', $this->_shop );

        $data = array(
            'file_no' => 0,
        );

        $this->db->update( $this->_tablename, $data );
    }

    // ********************** //
}
?>
