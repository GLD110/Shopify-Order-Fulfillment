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

    //var_dump($orderInfo->orders[2]);exit;

    if($orderInfo != null){
        foreach( $orderInfo->orders as $order )
        {
          $this->Process_model->order_create( $order, $this->_arrStoreList[$shop] );
        }
    }

    echo 'success';
  }

  public function syncPMI()
  {
    $order_id = $this->input->get('order_id');
    $this->Order_model->rewriteParam($this->_default_store);
    $arr_order =  $this->Order_model->getOrderfromId( $order_id );
    $order = $arr_order[0];
    $url = $this->config->item('pmi_path');
    $shared_secret = $this->config->item('shared_secret');
    $your_name = $this->config->item('your_name');
    $created_at = $order->created_at;
    $billing_address = json_decode( base64_decode( $order->billing_address ));

        var_dump( json_decode( base64_decode( $order->billing_address )) );exit;

    $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <cXML version="1.2.005" xml:lang="en-US" payloadID="f0d4c4cad2768467e774a20328e9fa141106242345@"' . $your_name . '".com" timestamp="' . $created_at . '">
               <Header>
                  <From>
                     <Credential domain="DUNS">
                        <Identity>' . $your_name . '</Identity>
                     </Credential>
                     <Credential domain="CompanyName">
                        <Identity>' . $your_name . '</Identity>
                     </Credential>
                  </From>
                  <To>
                     <Credential domain="CompanyName">
                        <Identity>Colorcentric</Identity>
                     </Credential>
                  </To>
                  <Sender>
                     <Credential domain="DUNS">
                        <Identity>' . $your_name . '</Identity>
                        <SharedSecret>' . $shared_secret . '</SharedSecret>
                     </Credential>
                  </Sender>
               </Header>
               <Request deploymentMode="production">
                  <OrderRequest>
                     <OrderRequestHeader orderID="' . $order_id . '" orderDate="' . $created_at . '" type="new">
                        <BillTo>
                           <Address addressID="12345">
                              <Name xml:lang="en-US">Your Name, Inc.</Name>
                              <PostalAddress name="Your Name, Inc.">
                                 <DeliverTo>Billing</DeliverTo>
                                 <Street>20 1st Ave.</Street>
                                 <Street />
                                 <City>New York</City>
                                 <State>NY</State>
                                 <PostalCode>10010</PostalCode>
                                 <Country isoCountryCode="US">US</Country>
                              </PostalAddress>
                              <Phone>
                                 <TelephoneNumber>
                                    <CountryCode isoCountryCode="" />
                                    <AreaOrCityCode />
                                    <Number />
                                 </TelephoneNumber>
                              </Phone>
                           </Address>
                        </BillTo>
                        <Shipping>
                           <Money currency="USD" />
                           <Description xml:lang="en-US" />
                        </Shipping>
                        <Tax>
                           <Money currency="USD" />
                           <Description xml:lang="en-US" />
                        </Tax>
                        <Comments xml:lang="en-US" />
                     </OrderRequestHeader>
                     <ItemOut lineNumber="1" quantity="10" requestedDeliveryDate="">
                        <ItemID>
                           <SupplierPartID>P600</SupplierPartID>
                           <SupplierPartAuxiliaryID>Joe Simth</SupplierPartAuxiliaryID>
                        </ItemID>
                        <ItemDetail>
                           <UnitPrice>
                              <Money currency="USD">1.99</Money>
                           </UnitPrice>
                           <Description xml:lang="en-US">Your Name Duplex Business Cards</Description>
                           <UnitOfMeasure>EA</UnitOfMeasure>
                           <Classification domain="" />
                           <URL>http://www.YourName.com/files/JoeSmith.pdf</URL>
                           <Extrinsic name="quantityMultiplier">50</Extrinsic>
                           <Extrinsic name="Pages">88</Extrinsic>
                           <Extrinsic name="endCustomerOrderID">Your Customers PO Number</Extrinsic>
                           <Extrinsic name="requestedShipper">DHL Next Day 3:00 pm</Extrinsic>
                           <Extrinsic name="requestedShippingAccount">12345678</Extrinsic>
                        </ItemDetail>
                        <ShipTo>
                           <Address addressID="0001">
                              <Name xml:lang="en-US">Your Name</Name>
                              <PostalAddress name="">
                                 <DeliverTo>Joe Smith</DeliverTo>
                                 <Street>100 Oak Tree Road</Street>
                                 <Street>Suite 315</Street>
                                 <City>Pittsburg</City>
                                 <State>PA</State>
                                 <PostalCode>20998</PostalCode>
                                 <Country isoCountryCode="US">US</Country>
                              </PostalAddress>
                              <Phone>
                                 <TelephoneNumber>
                                    <Number>6549889989</Number>
                                 </TelephoneNumber>
                              </Phone>
                           </Address>
                        </ShipTo>
                     </ItemOut>
                  </OrderRequest>
               </Request>
            </cXML>';
  }

  private function sendXmlOverPost($url, $xml) {
  	$ch = curl_init();
  	curl_setopt($ch, CURLOPT_URL, $url);

  	// For xml, change the content-type.
  	curl_setopt ($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));

  	curl_setopt($ch, CURLOPT_POST, 1);
  	curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

  	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // ask for results to be returned

  	// Send to remote and return data to caller.
  	$result = curl_exec($ch);
  	curl_close($ch);
  	return $result;
  }
}
