<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Order extends MY_Controller {

  public function __construct() {
    parent::__construct();
    $this->load->model( 'Order_model' );
    $this->_default_store = $this->session->userdata('shop');

    // Define the search values
    $this->_searchConf  = array(
      'product_name' => '',
      'customer_name' => '',
      'order_name' => '',
      'shop' => $this->_default_store,
      'collect' => '',
      'page_size' => $this->config->item('PAGE_SIZE'),
      'created_at' => '',
      'sort_field' => 'created_at',
      'sort_direction' => 'DESC',
    );

    $this->_searchSession = 'order_sels';
  }

  private function _checkDispatchCode( $code1, $code2 )
  {
    // if the first code is empty or both are same, return code2
    if( $code1 == '' || $code1 == $code2 ) return $code2;

    // If the second code is empty, return code1
    if( $code2 == '' ) return $code1;

    $arrRule = array( 'HH', 'YH', 'GM', 'SU', 'SF', 'FR', 'AP', 'JM', 'AO', 'AJ', 'NO' );

    $pos1 = array_search( $code1, $arrRule );
    $pos2 = array_search( $code2, $arrRule );

    if( $pos2 !== false && $pos1 < $pos2 ) return $code1;

    return $code2;
  }

  public function index(){
      $this->is_logged_in();

      $this->manage();
  }

  public function manage( $page =  0 ){

    //echo 123456789;
    //header( "HTTPS/1.1 200 OK" );exit();

    $this->_searchVal['shop'] = trim( $this->_searchVal['shop'], 'http://' );
    $this->_searchVal['shop'] = trim( $this->_searchVal['shop'], 'https://' );

    // Check the login
    $this->is_logged_in();

    // Init the search value
    $this->initSearchValue();

    $this->load->model( 'Collection_model' );

    //Collection List
    $arrCondition =  array();
    $collect_arr = array();
    $collect_arr[0] = '';
    $temp_arr =  $this->Collection_model->getList( $arrCondition );
    $temp_arr = $temp_arr->result();
    foreach( $temp_arr as $collect ) $collect_arr[ $collect->collection_id ] = $collect->title;
    $data['arrCollectionList'] = $collect_arr;

    $created_at = $this->_searchVal['created_at'];
    if($created_at == '')
    {
        $this->_searchVal['created_at'] = date('m/d/Y');
    }
    // Get data
    $arrCondition =  array(
       'product_name' => $this->_searchVal['product_name'],
       'customer_name' => $this->_searchVal['customer_name'],
       'order_name' => $this->_searchVal['order_name'],
       'page_number' => $page,
       'page_size' => $this->_searchVal['page_size'],
       'created_at' => $this->_searchVal['created_at'],
       'sort' => $this->_searchVal['sort_field'] . ' ' . $this->_searchVal['sort_direction'],
    );

    $this->Order_model->rewriteParam($this->_default_store);

    /**Be sure product is in the selct collection via API request,
    better solution will be needed in the future**/
    /*$this->load->model( 'Process_model' );
    if(empty($shop))
        $shop = $this->_default_store;

    $this->load->model( 'Shopify_model' );
    $this->Shopify_model->setStore( $shop, $this->_arrStoreList[$shop]->app_id, $this->_arrStoreList[$shop]->app_secret );

    $collect = $this->_searchVal['collect'];
    $q_orders =  $this->Order_model->getList( $arrCondition );
    $orders = $q_orders->result();

    if($collect == 0)
    {
      $r_orders = $orders;
    }
    else{
      $r_orders = array();
      foreach($orders as $order)
      {
        $action = 'collects.json?' . 'collection_id=' . $collect . '&product_id=' . $order->product_id;
        $CollectInfo = $this->Shopify_model->accessAPI( $action );
        if(sizeof($CollectInfo->collects) != 0)
          array_push($r_orders, $order);
      }
    }

    $data['query'] = $r_orders;
    $data['total_count'] = $this->Order_model->getTotalCount() - sizeof($orders) + sizeof($r_orders);
    $data['page'] = $page;
    */
    
    $data['query'] =  $this->Order_model->getList( $arrCondition );
    $data['total_count'] = $this->Order_model->getTotalCount();
    $data['page'] = $page;

    //var_dump($data['query']);exit;

    // Define the rendering data
    $data = $data + $this->setRenderData();

    // Store List
    $arr = array();
    foreach( $this->_arrStoreList as $shop => $row ) $arr[ $shop ] = $shop;
    $data['arrStoreList'] = $arr;

    // Rate
    //$data['sel_rate'] = $this->_arrStoreList[ $this->_searchVal['shop'] ]->rate;

    // Load Pagenation
    $this->load->library('pagination');

    // Renter to view
    $this->load->view('view_header');
    $this->load->view('view_order', $data );
    $this->load->view('view_footer');
  }

  public function sync( $shop )
  {
    $this->load->model( 'Process_model' );

    if(empty($shop))
        $shop = $this->_default_store;

    $this->load->model( 'Shopify_model' );
    $this->Shopify_model->setStore( $shop, $this->_arrStoreList[$shop]->app_id, $this->_arrStoreList[$shop]->app_secret );

    // Get the lastest day
    $this->Order_model->rewriteParam( $shop );
    $last_day = $this->Order_model->getLastOrderDate();

    $last_day = str_replace(' ', 'T', $last_day);

    $param = 'status=any&limit=250';
    if( $last_day != '' ) $param .= '&processed_at_min=' . $last_day ;
      $action = 'orders.json?' . $param;

    //$action = 'smart_collections.json';

    // Retrive Data from Shop
    $orderInfo = $this->Shopify_model->accessAPI( $action );

    //var_dump($orderInfo);exit;

    if($orderInfo != null){
        foreach( $orderInfo->orders as $order )
        {
          $this->Process_model->order_create( $order, $this->_arrStoreList[$shop] );
        }
    }

    echo 'success';
  }
}
