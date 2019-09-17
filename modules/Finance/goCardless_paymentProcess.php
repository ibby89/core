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

include 'moduleFunctions.php';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Finance/invoices_payOnline.php';
//Get variables
$redirectFlowID = '';
if (isset($_GET['redirect_flow_id'])) {
    $redirectFlowID = $_GET['redirect_flow_id'];
}
$gibbonFinanceInvoiceID = '';
if (isset($_GET['gibbonFinanceInvoiceID'])) {
    $gibbonFinanceInvoiceID = $_GET['gibbonFinanceInvoiceID'];
}
$key = '';
if (isset($_GET['key'])) {
    $key = $_GET['key'];
}

//Check variables
if ($gibbonFinanceInvoiceID == '' or $key == '' or $redirectFlowID == '') {
    echo "<div class='error'>";
    echo __('You have not specified one or more required parameters.');
    echo '</div>';
} else {
    //Check for record
    $keyReadFail = false;
    try {
        $dataKeyRead = array('gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID, 'key' => $key);
        $sqlKeyRead = "SELECT * FROM gibbonFinanceInvoice WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID AND `key`=:key AND status='Issued'";
        $resultKeyRead = $connection2->prepare($sqlKeyRead);
        $resultKeyRead->execute($dataKeyRead);
    } catch (PDOException $e) {
        echo "<div class='error'>";
        echo __('Your request failed due to a database error.');
        echo '</div>';
    }

        if ($resultKeyRead->rowCount() != 1) { //If not exists, report error
            echo "<div class='error'>";
            echo __('The selected record does not exist, or you do not have access to it.');
            echo '</div>';
        } else {    //If exists check confirmed
            $rowKeyRead = $resultKeyRead->fetch();
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

            //Get value of the invoice.
            $feeOK = true;
            try {
                $dataFees['gibbonFinanceInvoiceID'] = $gibbonFinanceInvoiceID;
                $sqlFees = 'SELECT gibbonFinanceInvoiceFee.gibbonFinanceInvoiceFeeID, gibbonFinanceInvoiceFee.feeType, gibbonFinanceFeeCategory.name AS category, gibbonFinanceInvoiceFee.name AS name, gibbonFinanceInvoiceFee.fee, gibbonFinanceInvoiceFee.description AS description, NULL AS gibbonFinanceFeeID, gibbonFinanceInvoiceFee.gibbonFinanceFeeCategoryID AS gibbonFinanceFeeCategoryID, sequenceNumber FROM gibbonFinanceInvoiceFee JOIN gibbonFinanceFeeCategory ON (gibbonFinanceInvoiceFee.gibbonFinanceFeeCategoryID=gibbonFinanceFeeCategory.gibbonFinanceFeeCategoryID) WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID ORDER BY sequenceNumber';
                $resultFees = $connection2->prepare($sqlFees);
                $resultFees->execute($dataFees);
            } catch (PDOException $e) {
                echo "<div class='error'>";
                echo __('Your request failed due to a database error.');
                echo '</div>';
                $feeOK = false;
            }

            if ($feeOK == true) {
                $feeTotal = 0;
                while ($rowFees = $resultFees->fetch()) {
                    $feeTotal += $rowFees['fee'];
                }

                $currency = getSettingByScope($connection2, 'System', 'currency');
                
                $enableGoCardLess = getSettingByScope($connection2, 'System', 'enableGoCardLess');
                $GoCardlessAPIKey = getSettingByScope($connection2, 'System', 'GoCardlessAPIkey');
                if($enableGoCardLess == "Y" and $GoCardlessAPIKey != ''){
                    $rowGibbonPerson = false;
                    try {
                        $dataGibbonPerson = array('gibbonPersonID' => $gibbonPersonID);
                        $sqlGibbonPerson = "SELECT * FROM gibbonGoCardlessCustomers WHERE gibbonPersonID=:gibbonPersonID ORDER BY gibbonGoCardlessCustomersID DESC";
                        $resultGibbonPerson = $connection2->prepare($sqlGibbonPerson);
                        $resultGibbonPerson->execute($dataGibbonPerson);
                    } catch (PDOException $e) {
                        echo "<div class='error'>";
                        echo __('The selected record does not exist, or you do not have access to it. Please contact admin.');
                        echo '</div>';
                    }
                    if($resultGibbonPerson->rowCount() > 0){
                        $rowGibbonPerson = $resultGibbonPerson->fetch();
                        // Init goCardLess
                        $client = new \GoCardlessPro\Client([
                            'access_token' => $GoCardlessAPIKey,
                            'environment' => \GoCardlessPro\Environment::SANDBOX
                            ]);

                        // Create redirectflow method
                        $redirectFlowComplete = $client->redirectFlows()->complete(
                            $redirectFlowID,
                            ["params" => ["session_token" => $key]]
                            );
                        $manDate = $redirectFlowComplete->links->mandate;
                        $customer = $redirectFlowComplete->links->customer;

                        // Add gibbonGoCardlessCustomers table Mandate/customer object
                        $updateCustomerMandate = array('gibbonCustomerMandate' => json_encode($redirectFlowComplete), 'gibbonGoCardlessCustomersID' => $rowGibbonPerson['gibbonGoCardlessCustomersID']);
                        $sqlCustomerMandate = "UPDATE gibbonGoCardlessCustomers SET gibbonCustomerMandate=:gibbonCustomerMandate WHERE gibbonGoCardlessCustomersID=:gibbonGoCardlessCustomersID";
                        $resultCustomerMandate = $connection2->prepare($sqlCustomerMandate);
                        $resultCustomerMandate->execute($updateCustomerMandate);

                        if($rowKeyRead['billingScheduleType'] == "Ad Hoc"){
                            // Create Payment flow
                            $paymentFlow = $client->payments()->create([
                              "params" => [
                                              "amount" => $feeTotal*100, // 10 GBP in pence
                                              "currency" => "GBP",
                                              "description" => "Invoice Number: ".$gibbonFinanceInvoiceID,                                          
                                              "links" => [
                                              "mandate" => $manDate
                                              ],
                                              "metadata" => [
                                              "invoice_number" => $gibbonFinanceInvoiceID,
                                              "student_id" => $gibbonFamilyChildID
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

                        // Add gibbonGoCardlessCustomers table Payment object
                        $updateCustomerPayment = array('gibbonCustomerPaymentStatus' => $paymentFlow->status,'gibbonCustomerPaymentStatusData' => json_encode($paymentFlow), 'gibbonGoCardlessCustomersID' => $rowGibbonPerson['gibbonGoCardlessCustomersID']);
                        $sqlCustomerPayment = "UPDATE gibbonGoCardlessCustomers SET gibbonCustomerPaymentStatus=:gibbonCustomerPaymentStatus, gibbonCustomerPaymentStatusData=:gibbonCustomerPaymentStatusData WHERE gibbonGoCardlessCustomersID=:gibbonGoCardlessCustomersID";
                        $resultCustomerPayment = $connection2->prepare($sqlCustomerPayment);
                        $resultCustomerPayment->execute($updateCustomerPayment);

                        
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

                    } else {
                        echo "<div class='error'>";
                        echo __('The selected record does not exist, or you do not have access to it. Please contact admin.');
                        echo '</div>';   
                    }
                }
            }
        }
    }
