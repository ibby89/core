<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Contracts\Comms\Mailer;

include '../../gibbon.php';

include './moduleFunctions.php';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Finance/invoices_payOnline.php';

$paid = null;
$paymentMethods = array("PayPal","GoCardless");
if (isset($_GET['paid'])) {
    $paid = $_GET['paid'];
}

if ($paid != 'Y') { //IF PAID IS NOT Y, LET'S REDIRECT TO MAKE PAYMENT
    //Get variables
$gibbonFinanceInvoiceID = '';
if (isset($_POST['gibbonFinanceInvoiceID'])) {
    $gibbonFinanceInvoiceID = $_POST['gibbonFinanceInvoiceID'];
}
$key = '';
if (isset($_POST['key'])) {
    $key = $_POST['key'];
}

$paymentType = '';
if (isset($_POST['paymentType'])) {
    $paymentType = $_POST['paymentType'];
}

    //Check variables
if ($gibbonFinanceInvoiceID == '' or $key == '' or $paymentType == '') {
    $URL .= '&return=error1';
    header("Location: {$URL}");
} else {
    if(!in_array($paymentType, $paymentMethods)){
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit();
    }

    //Check for record
    $keyReadFail = false;
    try {
        $dataKeyRead = array('gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID, 'key' => $key);
        $sqlKeyRead = "SELECT * FROM gibbonFinanceInvoice WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID AND `key`=:key AND status='Issued'";
        $resultKeyRead = $connection2->prepare($sqlKeyRead);
        $resultKeyRead->execute($dataKeyRead);
    } catch (PDOException $e) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit();
    }

        if ($resultKeyRead->rowCount() != 1) { //If not exists, report error
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        } else {    //If exists check confirmed
            $rowKeyRead = $resultKeyRead->fetch();

            //Get value of the invoice.
            $feeOK = true;
            try {
                $dataFees['gibbonFinanceInvoiceID'] = $gibbonFinanceInvoiceID;
                $sqlFees = 'SELECT gibbonFinanceInvoiceFee.gibbonFinanceInvoiceFeeID, gibbonFinanceInvoiceFee.feeType, gibbonFinanceFeeCategory.name AS category, gibbonFinanceInvoiceFee.name AS name, gibbonFinanceInvoiceFee.fee, gibbonFinanceInvoiceFee.description AS description, NULL AS gibbonFinanceFeeID, gibbonFinanceInvoiceFee.gibbonFinanceFeeCategoryID AS gibbonFinanceFeeCategoryID, sequenceNumber FROM gibbonFinanceInvoiceFee JOIN gibbonFinanceFeeCategory ON (gibbonFinanceInvoiceFee.gibbonFinanceFeeCategoryID=gibbonFinanceFeeCategory.gibbonFinanceFeeCategoryID) WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID ORDER BY sequenceNumber';
                $resultFees = $connection2->prepare($sqlFees);
                $resultFees->execute($dataFees);
            } catch (PDOException $e) {
                $feeOK = false;
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit();
            }

            if ($feeOK == true) {
                $feeTotal = 0;
                while ($rowFees = $resultFees->fetch()) {
                    $feeTotal += $rowFees['fee'];
                }

                $currency = getSettingByScope($connection2, 'System', 'currency');

                if($paymentType == "PayPal"){
                    $enablePayments = getSettingByScope($connection2, 'System', 'enablePayments');
                    $paypalAPIUsername = getSettingByScope($connection2, 'System', 'paypalAPIUsername');
                    $paypalAPIPassword = getSettingByScope($connection2, 'System', 'paypalAPIPassword');
                    $paypalAPISignature = getSettingByScope($connection2, 'System', 'paypalAPISignature');

                    if ($enablePayments == 'Y' and $paypalAPIUsername != '' and $paypalAPIPassword != '' and $paypalAPISignature != '' and $feeTotal > 0) {
                        $financeOnlinePaymentEnabled = getSettingByScope($connection2, 'Finance', 'financeOnlinePaymentEnabled');
                        $financeOnlinePaymentThreshold = getSettingByScope($connection2, 'Finance', 'financeOnlinePaymentThreshold');
                        if ($financeOnlinePaymentEnabled == 'Y') {
                            if ($financeOnlinePaymentThreshold == '' or $financeOnlinePaymentThreshold >= $feeTotal) {
                                //Let's call for the payment to be done!
                                $_SESSION[$guid]['gatewayCurrencyNoSupportReturnURL'] = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Finance/invoices_payOnline.php&return=error3';
                                $URL = $_SESSION[$guid]['absoluteURL']."/lib/paypal/expresscheckout.php?Payment_Amount=$feeTotal&return=".urlencode("modules/Finance/invoices_payOnlineProcess.php?return=success1&paid=Y&feeTotal=$feeTotal&gibbonFinanceInvoiceID=$gibbonFinanceInvoiceID&key=$key").'&fail='.urlencode("modules/Finance/invoices_payOnlineProcess?return=success2&paid=N&feeTotal=$feeTotal&gibbonFinanceInvoiceID=$gibbonFinanceInvoiceID&key=$key");
                                header("Location: {$URL}");
                            } else {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit();
                            }
                        } else {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }
                    } else {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }
                }

                if($paymentType == "GoCardless"){
                    $enableGoCardLess = getSettingByScope($connection2, 'System', 'enableGoCardLess');
                    $GoCardlessAPIKey = getSettingByScope($connection2, 'System', 'GoCardlessAPIkey');
                    if($enableGoCardLess == "Y" and $GoCardlessAPIKey != ''){
                        $personID = $rowKeyRead['gibbonPersonIDCreator'];

                        $gibbonFinanceInvoiceeID = $rowKeyRead['gibbonFinanceInvoiceeID'];
                        $gibbonFinanceBillingScheduleID = $rowKeyRead['gibbonFinanceBillingScheduleID'];

                        try {
                            $dataKeyInvoicee = array('gibbonFinanceInvoiceeID' => $gibbonFinanceInvoiceeID);
                            $sqlKeyInvoicee = "SELECT * FROM gibbonFinanceInvoicee WHERE gibbonFinanceInvoiceeID=:gibbonFinanceInvoiceeID ";
                            $resultKeyInvoicee = $connection2->prepare($sqlKeyInvoicee);
                            $resultKeyInvoicee->execute($dataKeyInvoicee);                
                        } catch (PDOException $e) {
                            echo "<div class='error'>";
                            echo __('Your request failed due to a database error.');
                            echo '</div>';
                        }

                        if ($resultKeyInvoicee->rowCount() == 0) { //If not exists, report error
                            echo "<div class='error'>";
                            echo __('The selected record does not exist, or you do not have access to it.');
                            echo '</div>';
                            exit;
                        }

                        $rowKeyInvoicee = $resultKeyInvoicee->fetch();
                        //$gibbonPersonID = $rowKeyInvoicee['gibbonPersonID'];
                        $gibbonPersonIDInvoicee = $rowKeyInvoicee['gibbonPersonID'];

                        try {
                            $dataKeyFamilyChild = array('gibbonPersonID' => $gibbonPersonIDInvoicee);
                            $sqlKeyFamilyChild = "SELECT * FROM gibbonFamilyChild WHERE gibbonPersonID=:gibbonPersonID ";
                            $resultKeyFamilyChild = $connection2->prepare($sqlKeyFamilyChild);
                            $resultKeyFamilyChild->execute($dataKeyFamilyChild);                
                        } catch (PDOException $e) {
                            echo "<div class='error'>";
                            echo __('Your request failed due to a database error.');
                            echo '</div>';
                        }

                        if ($resultKeyFamilyChild->rowCount() == 0) { //If not exists, report error
                            echo "<div class='error'>";
                            echo __('The selected record does not exist, or you do not have access to it.');
                            echo '</div>';
                            exit;
                        }

                        $rowKeyFamilyChild = $resultKeyFamilyChild->fetch();
                        $gibbonFamilyChildID = $rowKeyFamilyChild['gibbonFamilyChildID'];
                        $gibbonFamilyID = $rowKeyFamilyChild['gibbonFamilyID'];

                        try {
                            $dataKeyFamilyAdult = array('gibbonFamilyID' => $gibbonFamilyID, 'contactPriority' => 1);
                            $sqlKeyFamilyAdult = "SELECT gibbonPersonID FROM gibbonFamilyAdult WHERE gibbonFamilyID=:gibbonFamilyID AND contactPriority=:contactPriority";
                            $resultKeyFamilyAdult = $connection2->prepare($sqlKeyFamilyAdult);
                            $resultKeyFamilyAdult->execute($dataKeyFamilyAdult);
                        } catch (PDOException $e) {
                            echo "<div class='error'>";
                            echo __('Your request failed due to a database error.');
                            echo '</div>';
                        }

                        if ($resultKeyFamilyAdult->rowCount() == 0) { //If not exists, report error
                            echo "<div class='error'>";
                            echo __('The selected record does not exist, or you do not have access to it.');
                            echo '</div>';
                            exit;
                        }

                        $rowKeyFamilyAdult = $resultKeyFamilyAdult->fetch();
                        $gibbonPersonID = $rowKeyFamilyAdult['gibbonPersonID'];


                        $rowGibbonPerson = false;
                        try {
                            $dataGibbonPerson = array('gibbonPersonID' => $gibbonPersonID);
                            $sqlGibbonPerson = "SELECT * FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID";
                            $resultGibbonPerson = $connection2->prepare($sqlGibbonPerson);
                            $resultGibbonPerson->execute($dataGibbonPerson);
                            $rowGibbonPerson = $resultGibbonPerson->fetch();
                        } catch (PDOException $e) {
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }
                        require '../../vendor/autoload.php';
                        $client = new \GoCardlessPro\Client([
                            'access_token' => $GoCardlessAPIKey,
                            'environment' => \GoCardlessPro\Environment::SANDBOX
                            ]);

                        $rowGibbonCustomer = false;
                        try {
                            $dataGibbonCustomer = array('gibbonPersonID' => $gibbonPersonID);
                            $sqlGibbonCustomer = "SELECT * FROM gibbonGoCardlessCustomers WHERE gibbonPersonID=:gibbonPersonID AND gibbonCustomerMandate!='' ORDER BY gibbonGoCardlessCustomersID DESC";
                            $resultGibbonCustomer = $connection2->prepare($sqlGibbonCustomer);
                            $resultGibbonCustomer->execute($dataGibbonCustomer);
                            $rowGibbonCustomer = $resultGibbonCustomer->fetch();
                        } catch (PDOException $e) {
                            $rowGibbonCustomer = false;
                        }

                        if($rowGibbonCustomer){
                            $customerDetails = json_decode($rowGibbonCustomer['gibbonCustomerMandate']);                            
                            try {
                                $manDate = $customerDetails->api_response->body->redirect_flows->links->mandate;
                                $customerID = $customerDetails->api_response->body->redirect_flows->links->customer;                                
                                $c = $client->customers()->get($customerID);

                                if($rowKeyRead['billingScheduleType'] == "Ad Hoc"){
                                    // Create Payment flow
                                    $paymentFlow = $client->payments()->create([
                                      "params" => [
                                                      "amount" => $feeTotal*100, // 10 GBP in pence
                                                      "currency" => "GBP",
                                                      "description" => "The reference is: ".$gibbonFinanceInvoiceID,
                                                      "links" => [
                                                      "mandate" => $manDate
                                                      ],
                                                      "metadata" => [
                                                      "invoice_number" => $gibbonFinanceInvoiceID,
                                                      "student_id" => $gibbonFamilyChildID,
                                                      ]
                                                      ],
                                                      "headers" => [
                                                      "Idempotency-Key" => $key
                                                      ]
                                                      ]);
                                } else {

                                    $rowGibbonFinanceBillingSchedule = false;
                                    try {
                                        $dataGibbonFinanceBillingSchedule = array('gibbonFinanceBillingScheduleID' => $gibbonFinanceBillingScheduleID);
                                        $sqlGibbonFinanceBillingSchedule = "SELECT * FROM gibbonFinanceBillingSchedule WHERE gibbonFinanceBillingScheduleID=:gibbonFinanceBillingScheduleID";
                                        $resultGibbonFinanceBillingSchedule = $connection2->prepare($sqlGibbonFinanceBillingSchedule);
                                        $resultGibbonFinanceBillingSchedule->execute($dataGibbonFinanceBillingSchedule);
                                        $rowGibbonFinanceBillingSchedule = $resultGibbonFinanceBillingSchedule->fetch();
                                    } catch (PDOException $e) {
                                        $rowGibbonFinanceBillingSchedule = false;
                                    }

                                    if($rowGibbonFinanceBillingSchedule){
                                        $subscriptionStartDate = $rowGibbonFinanceBillingSchedule['invoiceDueDate'];
                                    }
                                    
                                    $subscriptionArray = [
                                      "params" => [
                                                      "amount" => $feeTotal*100, // 10 GBP in pence
                                                      "currency" => "GBP",
                                                      "interval_unit" => "monthly",
                                                      "links" => [
                                                      "mandate" => $manDate
                                                      ],
                                                      "metadata" => [
                                                      "invoice_number" => $gibbonFinanceInvoiceID,
                                                      "student_id" => $gibbonFamilyChildID,
                                                      ]
                                                      ],
                                                      "headers" => [
                                                      "Idempotency-Key" => $key
                                                      ]
                                                      ];                                    

                                    $todaDate = date("Y-m-d");
                                    $getDay = date("d", strtotime($subscriptionStartDate));
                                    if(strtotime($subscriptionStartDate) > strtotime($todaDate)){
                                        $subscriptionArray['params']['start_date'] = $subscriptionStartDate;
                                    }
                                    if($getDay){
                                        $subscriptionArray['params']['day_of_month'] = $getDay;
                                    } else {
                                        $subscriptionArray['params']['day_of_month'] = "5";
                                    }

                                    // Create Payment flow subscription
                                    $paymentFlow = $client->subscriptions()->create($subscriptionArray);

                                }
                                try {                                    
                                    $dataCustomerAdd = array('gibbonPersonID' => $gibbonPersonID, 'gibbonCustomerData' => $rowGibbonCustomer['gibbonCustomerData'], 'gibbonCustomerMandate' => $rowGibbonCustomer['gibbonCustomerMandate'], 'gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID, 'key' => $key, 'gibbonCustomerPaymentStatus' => $paymentFlow->status,'gibbonCustomerPaymentStatusData' => json_encode($paymentFlow));
                                    $sqlCustomerAdd = "INSERT INTO gibbonGoCardlessCustomers SET gibbonPersonID=:gibbonPersonID, gibbonCustomerData=:gibbonCustomerData, gibbonCustomerMandate=:gibbonCustomerMandate, gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID, gibbonCustomerPaymentStatus=:gibbonCustomerPaymentStatus, gibbonCustomerPaymentStatusData=:gibbonCustomerPaymentStatusData, `key`=:key, timeStampCreator='".date('Y-m-d H:i:s')."'";
                                    $resultCustomerAdd = $connection2->prepare($sqlCustomerAdd);
                                    $resultCustomerAdd->execute($dataCustomerAdd);                                    
                                } catch (PDOException $e) {
                                    echo $e->getMessage(); exit;
                                }

                                //Save payment details to gibbonPayment
                                $paymentToken = '';
                                $paymentPayerID = $gibbonPersonID;
                                $paymentTransactionID = $paymentFlow->id;
                                $paymentReceiptID = '';
                                $gibbonPaymentID = setPaymentLog($connection2, $guid, 'gibbonFinanceInvoice', $gibbonFinanceInvoiceID, 'Bank Transfer', 'Awaiting', $feeTotal, 'GoCardless', 'Awaiting', $paymentToken, $paymentPayerID, $paymentTransactionID, $paymentReceiptID);

                                //Link gibbonPayment record to gibbonApplicationForm, and make note that payment made
                                if ($gibbonPaymentID != '') {
                                    try {
                                        $data = array('status' => 'Pending','gibbonPaymentID' => $gibbonPaymentID, 'gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID);
                                        $sql = 'UPDATE gibbonFinanceInvoice SET status=:status, gibbonPaymentID=:gibbonPaymentID WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID';
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        echo "<div class='error'>";
                                        echo __('Failed to update payment status in database.');
                                        echo '</div>';
                                        exit;
                                    }
                                    //Success GC
                                    $URL .= "&return=successGC&gibbonFinanceInvoiceID=$gibbonFinanceInvoiceID&key=$key";
                                    header("Location: {$URL}");
                                    exit;
                                }  
                            } catch (\GoCardlessPro\Core\Exception\ApiException $e) {
                                $redirectFlow = $client->redirectFlows()->create([
                                    "params" => array(
                                        // This will be shown on the payment pages
                                        "description" => "Gibbon will collect fee ".$currency.$feeTotal,
                                        // Not the access token
                                        "session_token" => $key,
                                        "success_redirect_url" => $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Finance/goCardless_paymentProcess.php&key='.$key.'&gibbonFinanceInvoiceID='.$gibbonFinanceInvoiceID,
                                        // Optionally, prefill customer details on the payment page
                                        "prefilled_customer" => array(
                                            "given_name" => $rowGibbonPerson['firstName'],
                                            "family_name" => $rowGibbonPerson['surname'],
                                            "email" => $rowGibbonPerson['email'],
                                            "address_line1" => "",
                                            "city" => "",
                                            "postal_code" => ""
                                            )
                                        )
                                    ]);
                                try {
                                    $dataCustomerAdd = array('gibbonPersonID' => $gibbonPersonID, 'gibbonCustomerData' => json_encode($redirectFlow), 'gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID, 'key' => $key);
                                    $sqlCustomerAdd = "INSERT INTO gibbonGoCardlessCustomers SET gibbonPersonID=:gibbonPersonID, gibbonCustomerData=:gibbonCustomerData, gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID, `key`=:key, timeStampCreator='".date('Y-m-d H:i:s')."'";
                                    $resultCustomerAdd = $connection2->prepare($sqlCustomerAdd);
                                    $resultCustomerAdd->execute($dataCustomerAdd);
                                    $redirectURL = $redirectFlow->redirect_url;
                                    header("Location: {$redirectURL}");
                                } catch (PDOException $e) {
                                    echo $e->getMessage(); exit;
                                }
                            } catch (\GoCardlessPro\Core\Exception\MalformedResponseException $e) {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit();
                            } catch (\GoCardlessPro\Core\Exception\ApiConnectionException $e) {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit();
                            }                            
                        } else {
                            // $customers = $client->customers()->list()->records;
                            // print_r($customers); exit;
                            $redirectFlow = $client->redirectFlows()->create([
                                "params" => array(
                                    // This will be shown on the payment pages
                                    "description" => "Gibbon will collect fee ".$currency.$feeTotal,
                                    // Not the access token
                                    "session_token" => $key,
                                    "success_redirect_url" => $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Finance/goCardless_paymentProcess.php&key='.$key.'&gibbonFinanceInvoiceID='.$gibbonFinanceInvoiceID,
                                    // Optionally, prefill customer details on the payment page
                                    "prefilled_customer" => array(
                                        "given_name" => $rowGibbonPerson['firstName'],
                                        "family_name" => $rowGibbonPerson['surname'],
                                        "email" => $rowGibbonPerson['email'],
                                        "address_line1" => "",
                                        "city" => "",
                                        "postal_code" => ""
                                        )
                                    )
                                ]);
                            try {
                                $dataCustomerAdd = array('gibbonPersonID' => $gibbonPersonID, 'gibbonCustomerData' => json_encode($redirectFlow), 'gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID, 'key' => $key);
                                $sqlCustomerAdd = "INSERT INTO gibbonGoCardlessCustomers SET gibbonPersonID=:gibbonPersonID, gibbonCustomerData=:gibbonCustomerData, gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID, `key`=:key, timeStampCreator='".date('Y-m-d H:i:s')."'";
                                $resultCustomerAdd = $connection2->prepare($sqlCustomerAdd);
                                $resultCustomerAdd->execute($dataCustomerAdd);
                                $redirectURL = $redirectFlow->redirect_url;
                                header("Location: {$redirectURL}");
                            } catch (PDOException $e) {
                                echo $e->getMessage(); exit;
                            }
                        }
                    } else {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit();
                    }

                    exit;
                }
            }
        }
    }
} else { //IF PAID IS Y WE ARE JUST RETURNING TO FINALISE PAYMENT AND RECORD OF PAYMENT, SO LET'S DO IT.
    //Get returned paypal tokens, ids, etc
$paymentMade = 'N';
if ($_GET['return'] == 'success1') {
    $paymentMade = 'Y';
}
$paymentToken = null;
if (isset($_GET['token'])) {
    $paymentToken = $_GET['token'];
}
$paymentPayerID = null;
if (isset($_GET['PayerID'])) {
    $paymentPayerID = $_GET['PayerID'];
}
$feeTotal = null;
if (isset($_GET['feeTotal'])) {
    $feeTotal = $_GET['feeTotal'];
}
$gibbonFinanceInvoiceID = '';
if (isset($_GET['gibbonFinanceInvoiceID'])) {
    $gibbonFinanceInvoiceID = $_GET['gibbonFinanceInvoiceID'];
}
$key = '';
if (isset($_GET['key'])) {
    $key = $_GET['key'];
}

$gibbonFinanceInvoiceeID = '';
$invoiceTo = '';
$gibbonSchoolYearID = '';
try {
    $dataKeyRead = array('gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID, 'key' => $key);
    $sqlKeyRead = 'SELECT * FROM gibbonFinanceInvoice WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID AND `key`=:key';
    $resultKeyRead = $connection2->prepare($sqlKeyRead);
    $resultKeyRead->execute($dataKeyRead);
} catch (PDOException $e) {
}
if ($resultKeyRead->rowCount() == 1) {
    $rowKeyRead = $resultKeyRead->fetch();
    $gibbonFinanceInvoiceeID = $rowKeyRead['gibbonFinanceInvoiceeID'];
    $invoiceTo = $rowKeyRead['invoiceTo'];
    $gibbonSchoolYearID = $rowKeyRead['gibbonSchoolYearID'];
}

    //Check return values to see if we can proceed
if ($paymentToken == '' or $feeTotal == '' or $gibbonFinanceInvoiceID == '' or $key == '' or $gibbonFinanceInvoiceeID == '' or $invoiceTo = '' or $gibbonSchoolYearID == '') {
        //Success $URL.="&addReturn=success2&gibbonFinanceInvoiceID=$gibbonFinanceInvoiceID&key=$key" ;
    header("Location: {$URL}");
    exit();
} else {
        //PROCEED AND FINALISE PAYMENT
    require '../../lib/paypal/paypalfunctions.php';

        //Ask paypal to finalise the payment
    $confirmPayment = confirmPayment($guid, $feeTotal, $paymentToken, $paymentPayerID);

    $ACK = $confirmPayment['ACK'];
    $paymentTransactionID = $confirmPayment['PAYMENTINFO_0_TRANSACTIONID'];
    $paymentReceiptID = $confirmPayment['PAYMENTINFO_0_RECEIPTID'];

        //Payment was successful. Yeah!
    if ($ACK == 'Success') {
        $updateFail = false;

            //Save payment details to gibbonPayment
        $gibbonPaymentID = setPaymentLog($connection2, $guid, 'gibbonFinanceInvoice', $gibbonFinanceInvoiceID, 'Online', 'Complete', $feeTotal, 'Paypal', 'Success', $paymentToken, $paymentPayerID, $paymentTransactionID, $paymentReceiptID);

            //Link gibbonPayment record to gibbonApplicationForm, and make note that payment made
        if ($gibbonPaymentID != '') {
            try {
                $data = array('paidDate' => date('Y-m-d'), 'paidAmount' => $feeTotal, 'gibbonPaymentID' => $gibbonPaymentID, 'gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID);
                $sql = "UPDATE gibbonFinanceInvoice SET status='Paid', paidDate=:paidDate, paidAmount=:paidAmount, gibbonPaymentID=:gibbonPaymentID WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $updateFail = true;
            }
        } else {
            $updateFail = true;
        }

        if ($updateFail == true) {
            $URL .= "&addReturn=success3&gibbonFinanceInvoiceID=$gibbonFinanceInvoiceID&key=$key";
            header("Location: {$URL}");
            exit;
        }

            //EMAIL RECEIPT (no error reporting)
            //Populate to email.
        $emails = array();
        $emailsCount = 0;
        if ($invoiceTo == 'Company') {
            try {
                $dataCompany = array('gibbonFinanceInvoiceeID' => $gibbonFinanceInvoiceeID);
                $sqlCompany = 'SELECT * FROM gibbonFinanceInvoicee WHERE gibbonFinanceInvoiceeID=:gibbonFinanceInvoiceeID';
                $resultCompany = $connection2->prepare($sqlCompany);
                $resultCompany->execute($dataCompany);
            } catch (PDOException $e) {
            }
            if ($resultCompany->rowCount() != 1) {
            } else {
                $rowCompany = $resultCompany->fetch();
                if ($rowCompany['companyEmail'] != '' and $rowCompany['companyContact'] != '' and $rowCompany['companyName'] != '') {
                    $emails[$emailsCount] = $rowCompany['companyEmail'];
                    ++$emailsCount;
                    $rowCompany['companyCCFamily'];
                    if ($rowCompany['companyCCFamily'] == 'Y') {
                        try {
                            $dataParents = array('gibbonFinanceInvoiceeID' => $gibbonFinanceInvoiceeID);
                            $sqlParents = "SELECT parent.title, parent.surname, parent.preferredName, parent.email, parent.address1, parent.address1District, parent.address1Country, homeAddress, homeAddressDistrict, homeAddressCountry FROM gibbonFinanceInvoicee JOIN gibbonPerson AS student ON (gibbonFinanceInvoicee.gibbonPersonID=student.gibbonPersonID) JOIN gibbonFamilyChild ON (gibbonFamilyChild.gibbonPersonID=student.gibbonPersonID) JOIN gibbonFamily ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamily.gibbonFamilyID) JOIN gibbonFamilyAdult ON (gibbonFamily.gibbonFamilyID=gibbonFamilyAdult.gibbonFamilyID) JOIN gibbonPerson AS parent ON (gibbonFamilyAdult.gibbonPersonID=parent.gibbonPersonID) WHERE gibbonFinanceInvoiceeID=:gibbonFinanceInvoiceeID AND (contactPriority=1 OR (contactPriority=2 AND contactEmail='Y')) ORDER BY contactPriority, surname, preferredName";
                            $resultParents = $connection2->prepare($sqlParents);
                            $resultParents->execute($dataParents);
                        } catch (PDOException $e) {
                            $emailFail = true;
                        }
                        if ($resultParents->rowCount() < 1) {
                            $emailFail = true;
                        } else {
                            while ($rowParents = $resultParents->fetch()) {
                                if ($rowParents['preferredName'] != '' and $rowParents['surname'] != '' and $rowParents['email'] != '') {
                                    $emails[$emailsCount] = $rowParents['email'];
                                    ++$emailsCount;
                                }
                            }
                        }
                    }
                } else {
                    $emailFail = true;
                }
            }
        } else {
            try {
                $dataParents = array('gibbonFinanceInvoiceeID' => $gibbonFinanceInvoiceeID);
                $sqlParents = "SELECT parent.title, parent.surname, parent.preferredName, parent.email, parent.address1, parent.address1District, parent.address1Country, homeAddress, homeAddressDistrict, homeAddressCountry FROM gibbonFinanceInvoicee JOIN gibbonPerson AS student ON (gibbonFinanceInvoicee.gibbonPersonID=student.gibbonPersonID) JOIN gibbonFamilyChild ON (gibbonFamilyChild.gibbonPersonID=student.gibbonPersonID) JOIN gibbonFamily ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamily.gibbonFamilyID) JOIN gibbonFamilyAdult ON (gibbonFamily.gibbonFamilyID=gibbonFamilyAdult.gibbonFamilyID) JOIN gibbonPerson AS parent ON (gibbonFamilyAdult.gibbonPersonID=parent.gibbonPersonID) WHERE gibbonFinanceInvoiceeID=:gibbonFinanceInvoiceeID AND (contactPriority=1 OR (contactPriority=2 AND contactEmail='Y')) ORDER BY contactPriority, surname, preferredName";
                $resultParents = $connection2->prepare($sqlParents);
                $resultParents->execute($dataParents);
            } catch (PDOException $e) {
                $emailFail = true;
            }
            if ($resultParents->rowCount() < 1) {
                $emailFail = true;
            } else {
                while ($rowParents = $resultParents->fetch()) {
                    if ($rowParents['preferredName'] != '' and $rowParents['surname'] != '' and $rowParents['email'] != '') {
                        $emails[$emailsCount] = $rowParents['email'];
                        ++$emailsCount;
                    }
                }
            }
        }

            //Send emails
        if (count($emails) > 0) {
                //Get receipt number
            try {
                $dataPayments = array('foreignTable' => 'gibbonFinanceInvoice', 'foreignTableID' => $gibbonFinanceInvoiceID);
                $sqlPayments = 'SELECT gibbonPayment.*, surname, preferredName FROM gibbonPayment JOIN gibbonPerson ON (gibbonPayment.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE foreignTable=:foreignTable AND foreignTableID=:foreignTableID ORDER BY timestamp, gibbonPaymentID';
                $resultPayments = $connection2->prepare($sqlPayments);
                $resultPayments->execute($dataPayments);
            } catch (PDOException $e) {
            }
            $receiptCount = $resultPayments->rowCount();

                //Prep message
            $body = receiptContents($guid, $connection2, $gibbonFinanceInvoiceID, $gibbonSchoolYearID, $_SESSION[$guid]['currency'], true, $receiptCount)."<p style='font-style: italic;'>Email sent via ".$_SESSION[$guid]['systemName'].' at '.$_SESSION[$guid]['organisationName'].'.</p>';
            $bodyPlain = 'This email is not viewable in plain text: enable rich text/HTML in your email client to view the receipt. Please reply to this email if you have any questions.';

            $mail = $container->get(Mailer::class);
            $mail->SetFrom(getSettingByScope($connection2, 'Finance', 'email'), sprintf(__('%1$s Finance'), $_SESSION[$guid]['organisationName']));
            foreach ($emails as $address) {
                $mail->AddBCC($address);
            }
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->IsHTML(true);
            $mail->Subject = 'Receipt From '.$_SESSION[$guid]['organisationNameShort'].' via '.$_SESSION[$guid]['systemName'];
            $mail->Body = $body;
            $mail->AltBody = $bodyPlain;

            $mail->Send();
        }

        $URL .= "&return=success1&gibbonFinanceInvoiceID=$gibbonFinanceInvoiceID&key=$key";
        header("Location: {$URL}");
    } else {
        $updateFail = false;

            //Save payment details to gibbonPayment
        $gibbonPaymentID = setPaymentLog($connection2, $guid, 'gibbonFinanceInvoice', $gibbonFinanceInvoiceID, 'Online', 'Failure', $feeTotal, 'Paypal', 'Failure', $paymentToken, $paymentPayerID, $paymentTransactionID, $paymentReceiptID);

            //Link gibbonPayment record to gibbonApplicationForm, and make note that payment made
        if ($gibbonPaymentID != '') {
            try {
                $data = array('gibbonPaymentID' => $gibbonPaymentID, 'gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID);
                $sql = 'UPDATE gibbonFinanceInvoice gibbonPaymentID=:gibbonPaymentID WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $updateFail = true;
            }
        } else {
            $updateFail = true;
        }

        if ($updateFail == true) {
                //Success 2
            $URL .= "&return=success2&gibbonFinanceInvoiceID=$gibbonFinanceInvoiceID&key=$key";
            header("Location: {$URL}");
            exit;
        }

            //Success 2
        $URL .= "&return=success2&gibbonFinanceInvoiceID=$gibbonFinanceInvoiceID&key=$key";
        header("Location: {$URL}");
    }
}
}
