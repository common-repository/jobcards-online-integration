<?PHP

class InvoicesOnlineAPI {

    var $username = ''; 
    var $BusinessID = '';
    var $password = '';
    var $error = array();
    var $API_url = 'https://www.jobcardsonline.co.za/api/';
    var $cache_for = 10; //10 minutes
    var $cache_file;
    var $cache_dir = '/io_api_cache/'; //set with api to a full path
    var $data_dir = '/io_data/'; //set with api to a full path

    public function __construct() {
        $this->cache_dir = dirname(__FILE__).$this->cache_dir;
        $this->data_dir = dirname(__FILE__).$this->data_dir;
        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
        if (!is_dir($this->data_dir)) {
            @mkdir($this->data_dir, 0755, true);
        }
    }
    
    public function clearCache($dir = false) {
        if (!$dir) {
            $dir = $this->cache_dir;
        }
        $dh = opendir($dir);
        //echo $dh.'<br />';
        while (false !== ($obj = readdir($dh))) {
            //echo $obj.'<br />';
            if ($obj != '.' && $obj != '..') {
                unlink($dir . '/' . $obj);
            }
        }
        closedir($dh);
    }
    
    public function Setcachedir($dir) {
        $this->cache_dir = $dir;
    }
    
    public function Setdatadir($dir) {
        $this->data_dir = $dir;
    }
    public function Setusername($username) {
        $this->username = $username;
    }

    public function Setpassword($password) {
        $this->password = $password;
    }
    
    public function Setbusinessid($businessid) {
        $this->BusinessID = $businessid;
    }

    public function GetErrors() {
        return $this->error;
    }

    private function _SetIOError($error) {
        $this->error[] = $error;
    }

    private function _callCurl($type, $request, $ClientID, $OrderID = false, $docType = false) {
        switch ($type) {
            case 'details';
                $file = 'getClientDetails_JSON.php';
                $this->cache_file =  $this->cache_dir . $ClientID . '.cDetails.json';
                break;
            case 'balance';
                $file = 'getClientBalance_JSON.php';
                $this->cache_file = $this->cache_dir . $ClientID . '.cBalance.json';
                $this->cache_for = 1; //1 minute
                break;
            case 'all_clients';
                $file = 'getClients_JSON.php';
                $this->cache_file = $this->cache_dir . 'allClients.json';
                break;
            case 'history';
                $file = 'getClientHistory_JSON.php';
                $this->cache_file = $this->cache_dir . $ClientID . '.cHistory.json';
                break;
            case 'documents_by_type';
                $file = 'getDocumentsByType_JSON.php';
                $this->cache_file = $this->cache_dir . $ClientID . '.' . $docType . '.json';
                break;
            case 'order_invoice';
                $file = 'getClientOrderInvoice_JSON.php';
                $this->cache_file = $this->cache_dir . $ClientID . '.' . $OrderID . '.coInvoice.json';
                break;
            case 'remove_code';
                $file = 'RemoveItemByCode.php';
                // $this->cache_file = $this->cache_dir . $ClientID . '.RemoveItemByCode.json';
                $this->cache_file = false;
                break;
            case 'add_item';
                $file = 'AddItemTo.php';
                // $this->cache_file = $this->cache_dir . $ClientID . '.AddItemTo.json';
                $this->cache_file = false;
                break;
            case 'convert';
                $file = 'ConvertProFormaInvoiceToInvoice.php';
                // $this->cache_file = $this->cache_dir . '.ConvertProFormaInvoiceToInvoice.json';
                $this->cache_file = false;
                break;
            case 'new_client';
                $file = 'NewClient.php';
                // $this->cache_file = $this->cache_dir . '.NewClient.json';
                $this->cache_file = false;
                break;
            case 'update_client.php':
                $file = 'updateClient.php';
                // $this->cache_file = $this->cache_dir . '.UpdateClient.json';
                $this->cache_file = false;
                break;
            case 'generate_invoice_batch':
                $file = 'GenerateInvoiceBatch.php';
                // $this->cache_file = $this->cache_dir . '.GenerateInvoiceBatch.json';
                $this->cache_file = false;
                break;
            case 'generate_new_invoice':
                $file = 'GenerateNewInvoice.php';
                // $this->cache_file = $this->cache_dir . '.GenerateNewInvoice.json';
                $this->cache_file = false;
                break;
            case 'generate_new_deliverynote':
                $file = 'GenerateNewDeliveryNote.php';
                // $this->cache_file = $this->cache_dir . '.GenerateNewDeliveryNote.json';
                $this->cache_file = false;
                break;
            case 'generate_new_proforma':
                $file = 'GenerateNewProFormaInvoice.php';
                // $this->cache_file = $this->cache_dir . '.GenerateNewProFormaInvoice.json';
                $this->cache_file = false;
                break;
            case 'record_payment':
                $file = 'RecordPayment.php';
                // $this->cache_file = $this->cache_dir . '.RecordPayment.json';
                $this->cache_file = false;
                break;
            default:
                $file = false;
                break;
        }
        if ($file && $request) {
            $url = $this->API_url . $file;
            if (!file_exists($this->cache_file) || time() - filemtime($this->cache_file) > $this->cache_for) {

                $args = array(
                    'body'        => $request,
                    'timeout'     => '5',
                    'redirection' => '5',
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => array(),
                    'cookies'     => array(),
                );
    
                $response = wp_remote_post( $url, $args );

                if (isset($response['error'])) {
                    $this->_SetIOError($response['error']);
                    return false;
                } else {
                    if ($this->cache_file !== false){
                        $fp = fopen($this->cache_file, 'w+'); // open or create cache
                        if ($fp) {
                            fwrite($fp, json_encode($response));
                            fclose($fp);
                        }
                    }
                    return $response;
                }
            } else {
                return json_decode(file_get_contents($this->cache_file), true);
            }
        } else {
            $this->_SetIOError('Invalid type or file');
        }
    }

    public function GetClientDetails($ClientID) {
        if (!is_numeric($ClientID) || $ClientID < 1) {
            $this->_SetIOError('method GetClientDetails :: Invalid ClientID');
            return false;
        }
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('details', $request, $ClientID);
        return $response;
    }
    
    public function GetClientBalance($ClientID) {
        if (!is_numeric($ClientID) || $ClientID < 1) {
            $this->_SetIOError('method GetClientBalance :: Invalid ClientID');
            return false;
        }
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('balance', $request, $ClientID);
        return $response;
    }

    public function GetAllClients() {
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('all_clients', $request, false);
        return $response;
    }

    //'recurring_pro-forma_invoices',$i,$ClientID,$res['item_code']
    public function RemoveItemByCode($type, $IID, $ClientID, $ItemCode) {
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['IID'] = $IID;

        $param['ClientID'] = $ClientID;
        $param['item_code'] = $ItemCode;

        switch (strtolower($type)) {
            case 'recurring_pro-forma_invoices':
                $param['type'] = strtolower($type);
                break;
        }


        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);

        $response = $this->_callCurl('remove_code', $request, $ClientID);

        return $result;
    }

    //item is json_encoded array
    public function AddItemTo($type, $IID, $ClientID, $item) {
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['IID'] = $IID;

        $param['ClientID'] = $ClientID;
        $param['item'] = json_encode($item);

        switch (strtolower($type)) {
            case 'recurring_pro-forma_invoices':
                $param['type'] = strtolower($type);
                break;
        }


        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);

        $response = $this->_callCurl('add_item', $request, $ClientID);

        return $result;
    }
    
    //Convert Pro-Forma invoice to Invoice
    public function ConvertProformaToInvoice($BID, $PINR) {
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;

        $param['BusinessID'] = $BID;
        $param['ProFormaInvoiceNR'] = $PINR;

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        
        $request = substr($request, 0, strlen($request) - 1);

        $response = $this->_callCurl('convert', $request, 0);

        return $result;
    }

    public function GetAllDocumentsByType($type, $ClientID = 'all') {
        $param = array();
        $request = isset($request) ? $request : '';
        switch (strtolower($type)) {
            case 'recurring_pro-forma_invoices':
            case 'invoices':
            case 'quotes':
            case 'recurring_invoices':
            case 'credit_notes':
            case 'pro-forma_invoices':
            case 'delivery_notes':
                $type = strtolower($type);
                break;
            default:
                $this->_SetIOError('Invalid type');
                return false;
                break;
        }
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        $param['type'] = $type;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('documents_by_type', $request, $ClientID, false, $type);
        return $response;
    }

    public function GetOrderInvoice($ClientID, $OrderID) {
        if (!is_numeric($ClientID) || $ClientID < 1) {
            $this->_SetIOError('method GetOrderInvoice :: Invalid ClientID');
            return false;
        }
        if (!is_numeric($OrderID) || $OrderID < 1) {
            $this->_SetIOError('method GetOrderInvoice :: Invalid OrderID');
            return false;
        }
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        $param['OrderID'] = $OrderID;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('order_invoice', $request, $ClientID, $OrderID);
        return $response;
    }

    public function ClearClientCache($ClientID) {
        unlink($this->cache_dir . $ClientID . '.cHistory.json');
        unlink($this->cache_dir . $ClientID . '.cDetails.json');
        $arr = dirList($this->cache_dir);
        foreach ($arr as $index => $value) {
            $val = pathinfo($value);
            if (strstr($val['filename'], $ClientID . '.')) {
                unlink($this->cache_dir . $value);
            }
        }
    }

    public function GetClientHistory($ClientID, $from = false, $to = false, $Order = false) {
        if (!is_numeric($ClientID) || $ClientID < 1) {
            $this->_SetIOError('method GetClientHistory :: Invalid ClientID');
            return false;
        }
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        if (strlen($from) == 10) {
            $param['StartDate'] = date('Y-m-d', strtotime($from));
        }
        if (strlen($to) == 10) {
            $param['EndDate'] = date('Y-m-d', strtotime($to));
        }
        if ($Order) {
            switch ($Order) {
                case 'ASC':
                    $param['Order'] = 'ASC';
                    break;
                case 'DESC':
                default:
                    $param['Order'] = 'DESC';
                    break;
            }
        }
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('history', $request, $ClientID);
        return $response;
    }

    public function CreateNewClient($ClientParams) {
        $param = array();
        $request = isset($request) ? $request : '';
        $ClientID = 0;
        
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $this->BusinessID;

        $param['client_invoice_name'] = $ClientParams['client_invoice_name'];
        $param['client_phone_nr'] = $ClientParams['client_phone_nr'];
        $param['client_phone_nr2'] = $ClientParams['client_phone_nr2'];
        $param['client_mobile_nr'] = $ClientParams['client_mobile_nr'];
        $param['client_email'] = $ClientParams['client_email'];
        $param['client_vat_nr'] = $ClientParams['client_vat_nr'];
        $param['client_fax_nr'] = $ClientParams['client_fax_nr'];
        $param['contact_name'] = $ClientParams['contact_name'];
        $param['contact_surname'] = $ClientParams['contact_surname'];
        $param['client_postal_address1'] = $ClientParams['client_postal_address1'];
        $param['client_postal_address2'] = $ClientParams['client_postal_address2'];
        $param['client_postal_address3'] = $ClientParams['client_postal_address3'];
        $param['client_postal_address4'] = $ClientParams['client_postal_address4'];
        $param['client_physical_address1'] = $ClientParams['client_physical_address1'];
        $param['client_physical_address2'] = $ClientParams['client_physical_address2'];
        $param['client_physical_address3'] = $ClientParams['client_physical_address3'];
        $param['client_physical_address4'] = $ClientParams['client_physical_address4'];

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);

        $ClientID = $this->_callCurl('new_client', $request, $ClientID);

        return $ClientID['body'];
    }

    public function UpdateClient($BID, $ClientID, $ClientParams) {
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['ClientID'] = $ClientID;
        $param['client_invoice_name'] = $ClientParams['client_invoice_name'];
        $param['client_phone_nr'] = $ClientParams['client_phone_nr'];
        $param['client_phone_nr2'] = $ClientParams['client_phone_nr2'];
        $param['client_mobile_nr'] = $ClientParams['client_mobile_nr'];
        $param['client_email'] = $ClientParams['client_email'];
        $param['client_vat_nr'] = $ClientParams['client_vat_nr'];
        $param['client_fax_nr'] = $ClientParams['client_fax_nr'];
        $param['contact_name'] = $ClientParams['contact_name'];
        $param['contact_surname'] = $ClientParams['contact_surname'];
        $param['client_postal_address1'] = $ClientParams['client_postal_address1'];
        $param['client_postal_address2'] = $ClientParams['client_postal_address2'];
        $param['client_postal_address3'] = $ClientParams['client_postal_address3'];
        $param['client_postal_address4'] = $ClientParams['client_postal_address4'];
        $param['client_physical_address1'] = $ClientParams['client_physical_address1'];
        $param['client_physical_address2'] = $ClientParams['client_physical_address2'];
        $param['client_physical_address3'] = $ClientParams['client_physical_address3'];
        $param['client_physical_address4'] = $ClientParams['client_physical_address4'];

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);

        $ClientID = $this->_callCurl('update_client', $request, $ClientID);

        return $ClientID;
    }

    public function InvoiceBatch($BID, $data) {
        /*

         * data is array like this
         * invoice_nr :: incremented number - should be the same for all items on a single invoice
         * product_nr :: incremented number - each invoice line has it's own product_nr

         * data[invoice_nr][Products][] => array('code','desc','qty','currency','vat_percentage','item_amount','includes_vat','vat_applies');
         * data[invoice_nr][ClientID] = ClientID;
         */

        /**/
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['data'] = json_encode($data);

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, -1);

        $result = $this->_callCurl('generate_invoice_batch', $request, 0);

        return $result;
    }

    public function CreateNewInvoice($BID, $ClientID, $OrderNR, $data, $opts = false) {
        /*

         * data is array like this
          $data[$i][0] = 'D_'.$value.'_'.date('n', time()).'_'.date('Y', time());
          $data[$i][1] = 1;
          $data[$i][2] = stripslashes($domres['child_name']).' '.stripslashes($domres['child_surname']).' Subscription for '.date('F', time()).' '.date('Y', time());
          $data[$i][3] = ($amount);
          $data[$i][4] = 'ZAR';
          $data[$i][5] = '1';
          $data[$i][6] = '14.00';
          $data[$i][7] = '1';
          $data[$i][8] = '';
          $data[$i][9] = '';
          $data[$i][10] = '';
         *
          data[0] - prod_code
          data[1] - qty
          data[2] - description
          data[3] - amount
          data[4] - currency
          data[5] - vat_applies
          data[6] - vat_percentage
          data[7] - amount_includes_vat
          data[8] - custom1
          data[9] - custom2
          data[10] - custom3
         */

        /**/
        if (!isset($opts['IncludesVat'])) {
            $opts['IncludesVat'] = 'true';
        }
        if (!isset($opts['VatApplies'])) {
            $opts['VatApplies'] = 'true';
        }
        if (!isset($opts['EmailToClient'])) {
            $opts['EmailToClient'] = 'true';
        }
        if (!isset($opts['Paid'])) {
            $opts['Paid'] = 'false';
        }
        if (!isset($opts['MarkAsPaid'])) {
            $opts['MarkAsPaid'] = 'false';
        }
        if (!isset($opts['DiscountPercentage'])) {
            $opts['DiscountPercentage'] = 0;
        }
        if (!isset($opts['DiscountAmount'])) {
            $opts['DiscountAmount'] = 0;
        }
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['ClientID'] = $ClientID;
        $param['ClientOrderNr'] = $OrderNR;
        $param['IncludesVat'] = $opts['IncludesVat'];
        $param['VatApplies'] = $opts['VatApplies'];
        $param['EmailToClient'] = $opts['EmailToClient'];
        $param['Paid'] = $opts['Paid'];
        $param['MarkAsPaid'] = $opts['MarkAsPaid'];
        $param['DiscountPercentage'] = $opts['DiscountPercentage'];
        $param['DiscountAmount'] = $opts['DiscountAmount'];
        $i = 0;
        foreach ($data as $val) {
            $param["data[$i][0]"] = $val[0];
            $param["data[$i][1]"] = $val[1];
            $param["data[$i][2]"] = $val[2];
            $param["data[$i][3]"] = $val[3];
            $param["data[$i][4]"] = $val[4];
            $param["data[$i][5]"] = $val[5];
            $param["data[$i][6]"] = $val[6];
            $param["data[$i][7]"] = $val[7];
            $i++;
        }

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, -1);

        $result = $this->_callCurl('generate_new_invoice', $request, $ClientID);

        return $result;
    }
    
    public function CreateNewDeliveryNote($BID, $ClientID, $OrderNR, $data, $opts = false) {
        /*

         * data is array like this
          $data[$i][0] = 'D_'.$value.'_'.date('n', time()).'_'.date('Y', time());
          $data[$i][1] = 2;
          $data[$i][2] = description;
          $data[$i][3] = amount;
          $data[$i][4] = 'ZAR';
          $data[$i][5] = '1';
          $data[$i][6] = '14.00';
          $data[$i][7] = '1';
          $data[$i][8] = '5';
          $data[$i][9] = '3';
          $data[$i][10] = '';
          $data[$i][12] = '';
          $data[$i][13] = '';
          $data[$i][14] = '';
         * 
         *
          data[0] - prod_code
          data[1] - qty //delivered qty
          data[2] - description
          data[3] - amount
          data[4] - currency
          data[5] - vat_applies
          data[6] - vat_percentage
          data[7] - amount_includes_vat
          data[8] - order_qty //total ordered qty
          data[9] - backorder_qty //qty on backorder = order_qty - qty
          data[10] - custom3
         //note that data[11] is not set, it is for internal use only
          data[12] - discount_amount
          data[13] - discount_percentage
          data[14] - custom4
          data[15] - custom5
         */

        /**/
        if (!isset($opts['IncludesVat'])) {
            $opts['IncludesVat'] = 'true';
        }
        if (!isset($opts['VatApplies'])) {
            $opts['VatApplies'] = 'true';
        }
        if (!isset($opts['EmailToClient'])) {
            $opts['EmailToClient'] = 'true';
        }
        if (!isset($opts['DiscountPercentage'])) {
            $opts['DiscountPercentage'] = 0;
        }
        if (!isset($opts['DiscountAmount'])) {
            $opts['DiscountAmount'] = 0;
        }
        if (!isset($opts['overridden_recipients'])) {
            $opts['overridden_recipients'] = '';
        }
        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['ClientID'] = $ClientID;
        $param['ClientOrderNr'] = $OrderNR;
        $param['IncludesVat'] = $opts['IncludesVat'];
        $param['VatApplies'] = $opts['VatApplies'];
        $param['EmailToClient'] = $opts['EmailToClient'];
        $param['overridden_recipients'] = $opts['overridden_recipients'];
        $param['DiscountPercentage'] = $opts['DiscountPercentage'];
        $param['DiscountAmount'] = $opts['DiscountAmount'];
        $i = 0;
        foreach ($data as $val) {
            $param["data[$i][0]"] = $val[0];
            $param["data[$i][1]"] = $val[1];
            $param["data[$i][2]"] = $val[2];
            $param["data[$i][3]"] = $val[3];
            $param["data[$i][4]"] = $val[4];
            $param["data[$i][5]"] = $val[5];
            $param["data[$i][6]"] = $val[6];
            $param["data[$i][7]"] = $val[7];
            $param["data[$i][8]"] = $val[8];
            $param["data[$i][9]"] = $val[9];
            $param["data[$i][10]"] = $val[10];
            $param["data[$i][12]"] = $val[12];
            $param["data[$i][13]"] = $val[13];
            $param["data[$i][14]"] = $val[14];
            $i++;
        }

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, -1);

        $result = $this->_callCurl('generate_new_deliverynote', $request, $ClientID);

        return $result;
    }
    
    public function CreateNewProformaInvoice($BID, $ClientID, $OrderNR, $data, $opts = false) {
        /*

         * data is array like this
          $data[$i][0] = 'D_'.$value.'_'.date('n', time()).'_'.date('Y', time());
          $data[$i][1] = 1;
          $data[$i][2] = stripslashes($domres['child_name']).' '.stripslashes($domres['child_surname']).' Subscription for '.date('F', time()).' '.date('Y', time());
          $data[$i][3] = ($amount);
          $data[$i][4] = 'ZAR';
          $data[$i][5] = '1';
          $data[$i][6] = '14.00';
          $data[$i][7] = '1';
          $data[$i][8] = '';
          $data[$i][9] = '';
          $data[$i][10] = '';
         *
          data[0] - prod_code
          data[1] - qty
          data[2] - description
          data[3] - amount
          data[4] - currency
          data[5] - vat_applies
          data[6] - vat_percentage
          data[7] - amount_includes_vat
          data[8] - custom1
          data[9] - custom2
          data[10] - custom3
         */

        /**/
        if (!isset($opts['IncludesVat'])) {
            $opts['IncludesVat'] = 'true';
        }
        if (!isset($opts['VatApplies'])) {
            $opts['VatApplies'] = 'true';
        }
        if (!isset($opts['EmailToClient'])) {
            $opts['EmailToClient'] = 'true';
        }
        if (!isset($opts['Paid'])) {
            $opts['Paid'] = 'false';
        }
        if (!isset($opts['MarkAsPaid'])) {
            $opts['MarkAsPaid'] = 'false';
        }
        if (!isset($opts['DiscountPercentage'])) {
            $opts['DiscountPercentage'] = 0;
        }
        if (!isset($opts['DiscountAmount'])) {
            $opts['DiscountAmount'] = 0;
        }
        $params = array();
        $request = '';
        $params['username'] = $this->username;
        $params['password'] = $this->password;
        $params['BID'] = $BID;

        $params['ClientID'] = $ClientID;
        $params['ClientOrderNr'] = $OrderNR;
        $params['IncludesVat'] = $opts['IncludesVat'];
        $params['VatApplies'] = $opts['VatApplies'];
        $params['EmailToClient'] = $opts['EmailToClient'];
        $params['Paid'] = $opts['Paid'];
        $params['MarkAsPaid'] = $opts['MarkAsPaid'];
        $params['DiscountPercentage'] = $opts['DiscountPercentage'];
        $params['DiscountAmount'] = $opts['DiscountAmount'];
        $i = 0;
        foreach ($data as $val) {
            $params["data[$i][0]"] = $val[0];
            $params["data[$i][1]"] = $val[1];
            $params["data[$i][2]"] = $val[2];
            $params["data[$i][3]"] = $val[3];
            $params["data[$i][4]"] = $val[4];
            $params["data[$i][5]"] = $val[5];
            $params["data[$i][6]"] = $val[6];
            $params["data[$i][7]"] = $val[7];
            $i++;
        }

        foreach ($params as $key => $val) {
            if(!is_array($val)){
                $request.= $key . '=' . urlencode($val);
                $request.= '&';
            }
        }
        $request = substr($request, 0, -1);

        $result = $this->_callCurl('generate_new_proforma', $request, $ClientID);

        return $result;
    }

    public function CreateNewPayment($BID, $ClientID, $opts = false) {
        /*

         * data is array like this
          $data[$i][0] = 'D_'.$value.'_'.date('n', time()).'_'.date('Y', time());
          $data[$i][1] = 1;
          $data[$i][2] = stripslashes($domres['child_name']).' '.stripslashes($domres['child_surname']).' Subscription for '.date('F', time()).' '.date('Y', time());
          $data[$i][3] = ($amount);
          $data[$i][4] = 'ZAR';
          $data[$i][5] = '1';
          $data[$i][6] = '14.00';
          $data[$i][7] = '1';
          $data[$i][8] = '';
          $data[$i][9] = '';
          $data[$i][10] = '';
         *
          data[0] - prod_code
          data[1] - qty
          data[2] - description
          data[3] - amount
          data[4] - currency
          data[5] - vat_applies
          data[6] - vat_percentage
          data[7] - amount_includes_vat
          data[8] - custom1
          data[9] - custom2
          data[10] - custom3
         */

        /**/
        if (!isset($opts['PaymentAmount'])) {
            return false;
        }
        if (!isset($opts['payment_method'])) {
            $opts['payment_method'] = 'EFT';
        }
        if (!isset($opts['payment_date'])) {
            $opts['payment_date'] = date('Y-m-d');
        }
        if (!isset($opts['reference_number'])) {
            $opts['reference_number'] = 'Payment ' . $opts['payment_date'];
        }

        $param = array();
        $request = isset($request) ? $request : '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['ClientID'] = $ClientID;
        $param['reference_number'] = $opts['reference_number'];
        $param['payment_method'] = $opts['payment_method'];
        $param['payment_date'] = $opts['payment_date'];
        $param['PaymentAmount'] = $opts['PaymentAmount'];
        $param['Description'] = $opts['Description'];

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, -1);

        $result = $this->_callCurl('record_payment', $request, $ClientID);

        return $result;
    }

}

?>