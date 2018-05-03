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

  public function sync( $shop = '' )
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

  public function update( $type, $pk )
  {
    $data = array();

    switch( $type )
    {
        case 'note' : $data['note'] = $this->input->post('value'); break;
        case 'shipping_option' : $data['shipping_option'] = $this->input->post('shipping_option'); break;
    }

    $this->Order_model->update( $pk, $data );
  }

  public function syncPMI()
  {
    $order_id = $this->input->get('order_id');
    $this->Order_model->rewriteParam($this->_default_store);
    $arr_order =  $this->Order_model->getOrderfromId( $order_id );
    $order = $arr_order[0];

    $this->load->model( 'Product_model' );
    $this->Product_model->rewriteParam($this->_default_store);
    $variant = $this->Product_model->getVariant($order->variant_id);

    $shop_url = $this->config->item('PRIVATE_SHOP');
    $url = $this->config->item('pmi_path');
    $shared_secret = $this->config->item('shared_secret');
    $your_name = $this->config->item('your_name');

    $product_url = 'https://' . $shop_url . '/products/' . $variant->handle;
    $p_code = $variant->p_code;
    $img_resource = $variant->img_resource;
    if($variant->img_resource == '')
      $img_resource = $product_url;

    $shipping_option = $order->shipping_option;
    if($order->shipping_option == '')
      $shipping_option = 'standard';

    $created_at = $order->created_at;
    $created_at = str_replace(' ', 'T', $created_at);
    $billing_address = json_decode( base64_decode( $order->billing_address ));
    $shipping_address = json_decode( base64_decode( $order->shipping_address ));
    $shipping_lines = json_decode( base64_decode( $order->shipping_lines ));
    $shipping_lines = $shipping_lines[0];
    $currency = 'AUD';//$order->currency;
    $tax_price = ' ';
    $tax_title = ' ';
    $tax_rate = ' ';
    if(isset( $shipping_lines->tax_lines['price'] ))
      $tax_price = $shipping_lines->tax_lines['price'];
    if(isset( $shipping_lines->tax_lines['price'] ))
      $tax_title = $shipping_lines->tax_lines['title'];
    if(isset( $shipping_lines->tax_lines['price'] ))
      $tax_rate = $shipping_lines->tax_lines['rate'];

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<cXML version="1.2.005" xml:lang="en-US" payloadID="' . $order_id . '@' . $your_name . '.com" timestamp="' . $created_at . '">'
               . '<Header>'
                  . '<From>'
                     . '<Credential domain="DUNS">'
                        . '<Identity>' . $your_name . '</Identity>'
                     . '</Credential>'
                     . '<Credential domain="CompanyName">'
                        . '<Identity>' . $your_name . '</Identity>'
                     . '</Credential>'
                  . '</From>'
                  . '<To>'
                     . '<Credential domain="CompanyName">'
                        . '<Identity>Colorcentric</Identity>'
                     . '</Credential>'
                  . '</To>'
                  . '<Sender>'
                     . '<Credential domain="DUNS">'
                        . '<Identity>' . $your_name . '</Identity>'
                        . '<SharedSecret>' . $shared_secret . '</SharedSecret>'
                     . '</Credential>'
                  . '</Sender>'
               . '</Header>'
               . '<Request deploymentMode="production">'
                  . '<OrderRequest>'
                     . '<OrderRequestHeader orderID="' . $order_id . '" orderDate="' . $created_at . '" type="new">'
                        . '<BillTo>'
                           . '<Address addressID="address1">'
                              . '<Name xml:lang="en-US">' . $billing_address->company . '</Name>'
                              . '<PostalAddress name="' . $billing_address->company . '">'
                                 . '<DeliverTo>Billing</DeliverTo>'
                                 . '<Street>' . $billing_address->address1 . '</Street>'
                                 . '<Street />'
                                 . '<City>' . $billing_address->city . '</City>'
                                 . '<State>' . $billing_address->province_code . '</State>'
                                 . '<PostalCode>' . $billing_address->zip . '</PostalCode>'
                                 . '<Country isoCountryCode="' . $billing_address->country_code . '">' . $billing_address->country_code . '</Country>'
                              . '</PostalAddress>'
                              . '<Phone>'
                                 . '<TelephoneNumber>'
                                    . '<CountryCode isoCountryCode="" />'
                                    . '<AreaOrCityCode />'
                                    . '<Number>' . $billing_address->phone . '</Number>'
                                 . '</TelephoneNumber>'
                              . '</Phone>'
                           . '</Address>'
                        . '</BillTo>'
                        . '<Shipping>'
                           . '<Money currency="' . $currency . '">' . $shipping_lines->price . '</Money>'
                           . '<Description xml:lang="en-US">' . $shipping_lines->code . '</Description>'
                        . '</Shipping>'
                        . '<Tax>'
                           . '<Money currency="' . $currency . '">' . $tax_price . '</Money>'
                           . '<Description xml:lang="en-US">' . $tax_title . 'rate:' . $tax_rate . '</Description>'
                        . '</Tax>'
                        . '<Comments xml:lang="en-US">' . $order->note . '</Comments>'
                     . '</OrderRequestHeader>'
                     . '<ItemOut lineNumber="1" quantity="' . $order->num_products . '" requestedDeliveryDate="">'
                        . '<ItemID>'
                           . '<SupplierPartID>' . $p_code . '</SupplierPartID>'
                           . '<SupplierPartAuxiliaryID>' . $order->product_name . '</SupplierPartAuxiliaryID>'
                        . '</ItemID>'
                        . '<ItemDetail>'
                           . '<UnitPrice>'
                              . '<Money currency="' . $currency . '">' . $order->amount . '</Money>'
                           . '</UnitPrice>'
                           . '<Description xml:lang="en-US">' . $order->product_name . '</Description>'
                           . '<UnitOfMeasure>EA</UnitOfMeasure>'
                           /*. '<Classification domain="">' . $shop_url . '</Classification>'*/
                           . '<URL>' . $img_resource . '</URL>'
                           . '<Extrinsic name="quantityMultiplier">1</Extrinsic>'
                           . '<Extrinsic name="Pages">1</Extrinsic>'
                           . '<Extrinsic name="endCustomerOrderID">' . $order->order_id . '</Extrinsic>'
                           . '<Extrinsic name="requestedShipper">' . $shipping_option . '</Extrinsic>'
                           . '<Extrinsic name="requestedShippingAccount">12345678</Extrinsic>'
                        . '</ItemDetail>'
                        . '<ShipTo>'
                           . '<Address addressID="address1">'
                              . '<Name xml:lang="en-US">' . $shipping_address->name . '</Name>'
                              . '<PostalAddress name="' . $shipping_address->company . '">'
                                 . '<DeliverTo>' . $shipping_address->name . '</DeliverTo>'
                                 . '<Street>' . $shipping_address->address1 . '</Street>'
                                 . '<Street>' . $shipping_address->address2 . '</Street>'
                                 . '<City>' . $shipping_address->city . '</City>'
                                 . '<State>' . $shipping_address->province_code . '</State>'
                                 . '<PostalCode>' . $shipping_address->zip . '</PostalCode>'
                                 . '<Country isoCountryCode="' . $shipping_address->country_code . '">' . $shipping_address->country_code . '</Country>'
                              . '</PostalAddress>'
                              . '<Phone>'
                                 . '<TelephoneNumber>'
                                    . '<Number>' . $shipping_address->phone . '</Number>'
                                 . '</TelephoneNumber>'
                              . '</Phone>'
                           . '</Address>'
                        . '</ShipTo>'
                     . '</ItemOut>'
                  . '</OrderRequest>'
               . '</Request>'
            . '</cXML>';

    $simplexml = $this->sendXmlOverPost($url, $xml);
    $code = simplexml_load_string( $simplexml )->Response->Status->attributes()->code->__toString();
    $text = simplexml_load_string( $simplexml )->Response->Status->attributes()->text->__toString();

    $this->Order_model->setExported( array( array( 'order_id' => $order_id )) );

    //echo json_encode(array('code' => '200', 'text' => 'Success'));

    echo json_encode(array('code' => $code, 'text' => $text));

  }

  public function shippedNotice()
  {
    $this->load->model( 'Process_model' );

    if(empty($shop))
        $shop = $this->_default_store;

    $this->load->model( 'Shopify_model' );
    $this->Shopify_model->setStore( $shop, $this->_arrStoreList[$shop]->app_id, $this->_arrStoreList[$shop]->app_secret );

    $shared_secret = $this->config->item('shared_secret');
    $shippedNotice = $this->input->post();

    /*$shippedNotice = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?>
                        <cXML version="1.2.006" payloadID="bcb664daae21a24a7a1e6f1e8b1a1106348361@YourName.com" xml:lang="enUS" timestamp="2005-01-25 10:22:50">
                           <Header>
                              <From>
                                 <Credential domain="DUNS">
                                    <Identity>ColorCentric</Identity>
                                 </Credential>
                              </From>
                              <Sender>
                                 <Credential domain="DUNS">
                                    <Identity>ColorCentric</Identity>
                                    <SharedSecret>eMdh78Ki7UjjU9x</SharedSecret>
                                 </Credential>
                              </Sender>
                           </Header>
                           <Request deploymentMode="production">
                              <ShipNoticeRequest>
                                 <ShipNoticeHeader ShipmentID="CC00493_YourName-Test-001-1" noticeDate="2005-01-25" />
                                 <ShipControl>
                                    <CarrierIdentifier domain="companyName">DHL 2nd Day</CarrierIdentifier>
                                    <ShipmentIdentifier>814826258492</ShipmentIdentifier>
                                 </ShipControl>
                                 <ShipNoticePortion>
                                    <OrderReference orderID="201055600658">
                                       <DocumentReference payloadID="f4cfbcb664daae21a24a7a1e6f1e8b1a1106348361@YourName.com" />
                                    </OrderReference>
                                    <ShipNoticeItem quantity="1" lineNumber="1" />
                                 </ShipNoticePortion>
                              </ShipNoticeRequest>
                           </Request>
                        </cXML>');*/

    if($shared_secret == $shippedNotice->Header->Sender->Credential->SharedSecret->__toString())
    {
      $CarrierIdentifier = $shippedNotice->Request->ShipNoticeRequest->ShipControl->CarrierIdentifier->__toString();
      $ShipmentIdentifier = $shippedNotice->Request->ShipNoticeRequest->ShipControl->ShipmentIdentifier->__toString();
      $order_id = $shippedNotice->Request->ShipNoticeRequest->ShipNoticePortion->OrderReference->attributes()->orderID->__toString();
      $arr_order =  $this->Order_model->getOrderfromId( $order_id );
      $order = $arr_order[0];

      $action = 'orders/' . $order->orderID . '/fulfillments.json';
      $fulfillment = array("fulfillment" => array( "tracking_number" => $ShipmentIdentifier, "tracking_company" => $CarrierIdentifier, "line_items" => [array( "id" => $order_id )] ));

      // Retrive Data from Shop
      $fulfillInfo = $this->Shopify_model->accessAPI( $action, $fulfillment, 'POST' );

      $date = date('Y-m-d H:i:s');
      $date = str_replace(' ', 'T', (String)$date);
      $response = '<?xml version="1.0" encoding="UTF-8"?>
                   <cXML timestamp="' . $date . '" payloadID="281b9b0b-cc28-45f0-9316-812882384b6a">
                     <Response>
                      <Status code="200" text="OK" />
                     </Response>
                   </cXML> ';
      echo $response;
      //$this->sync($shop);
    }
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
