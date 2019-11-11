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


$enableGoCardLess = getSettingByScope($connection2, 'System', 'enableGoCardLess');
$GoCardlessAPIKey = getSettingByScope($connection2, 'System', 'GoCardlessAPIkey');
//if($enableGoCardLess == "Y" and $GoCardlessAPIKey != ''){

try {
    $dataGibbonGoCardlessCustomers = array('gibbonCustomerPaymentStatus' => "pending_submission");
    $sqlGibbonGoCardlessCustomers = "SELECT * FROM gibbonGoCardlessCustomers WHERE gibbonCustomerPaymentStatus=:gibbonCustomerPaymentStatus ORDER BY gibbonGoCardlessCustomersID ASC";
    $resultGibbonGoCardlessCustomers = $connection2->prepare($sqlGibbonGoCardlessCustomers);
    $resultGibbonGoCardlessCustomers->execute($dataGibbonGoCardlessCustomers);
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo __('The selected record does not exist, or you do not have access to it. Please contact admin.');
    echo '</div>';
}

if($resultGibbonGoCardlessCustomers->rowCount() > 0){

        // Init goCardLess
    $client = new \GoCardlessPro\Client([
        'access_token' => $GoCardlessAPIKey,
        // For testing with Sandbox accounts only, please see https://github.com/gocardless/gocardless-pro-php
        'environment' => \GoCardlessPro\Environment::LIVE
        ]);

    while ($rowGibbonGoCardlessCustomers = $resultGibbonGoCardlessCustomers->fetch()) {
        $gibbonCustomerPaymentStatusData = json_decode($rowGibbonGoCardlessCustomers['gibbonCustomerPaymentStatusData']);
        $paymentID = $gibbonCustomerPaymentStatusData->api_response->body->payments->id;
        $gibbonGoCardlessCustomersID = $rowGibbonGoCardlessCustomers['gibbonGoCardlessCustomersID'];
        $key = $rowGibbonGoCardlessCustomers['key'];
        $getPaymentDetails = false;
        try {
            $getPaymentDetails = $client->payments()->get($paymentID);
        } catch (\GoCardlessPro\Core\Exception\ApiException $e) {

        } catch (\GoCardlessPro\Core\Exception\MalformedResponseException $e) {

        } catch (\GoCardlessPro\Core\Exception\ApiConnectionException $e) {
            $getPaymentDetails = $client->payments()->get($paymentID);
        }

        if($getPaymentDetails){
            $getPaymentStatus = $getPaymentDetails->api_response->body->payments->status;
            try {
                $dataKeyRead = array('key' => $key);
                $sqlKeyRead = "SELECT * FROM gibbonFinanceInvoice WHERE `key`=:key";
                $resultKeyRead = $connection2->prepare($sqlKeyRead);
                $resultKeyRead->execute($dataKeyRead);
            } catch (PDOException $e) {

            }
            if($getPaymentStatus == "confirmed" || $getPaymentStatus == "paid_out"){

                if ($resultKeyRead->rowCount() > 0) {
                    $rowKeyRead = $resultKeyRead->fetch();
                    $gibbonFinanceInvoiceID = $rowKeyRead['gibbonFinanceInvoiceID'];

                    $feeOK = true;
                    try {
                        $dataFees['gibbonFinanceInvoiceID'] = $gibbonFinanceInvoiceID;
                        $sqlFees = 'SELECT gibbonFinanceInvoiceFee.gibbonFinanceInvoiceFeeID, gibbonFinanceInvoiceFee.feeType, gibbonFinanceFeeCategory.name AS category, gibbonFinanceInvoiceFee.name AS name, gibbonFinanceInvoiceFee.fee, gibbonFinanceInvoiceFee.description AS description, NULL AS gibbonFinanceFeeID, gibbonFinanceInvoiceFee.gibbonFinanceFeeCategoryID AS gibbonFinanceFeeCategoryID, sequenceNumber FROM gibbonFinanceInvoiceFee JOIN gibbonFinanceFeeCategory ON (gibbonFinanceInvoiceFee.gibbonFinanceFeeCategoryID=gibbonFinanceFeeCategory.gibbonFinanceFeeCategoryID) WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID ORDER BY sequenceNumber';
                        $resultFees = $connection2->prepare($sqlFees);
                        $resultFees->execute($dataFees);
                    } catch (PDOException $e) {
                        $feeOK = false;                        
                    }

                    if ($feeOK == true) {
                        $feeTotal = 0;
                        while ($rowFees = $resultFees->fetch()) {
                            $feeTotal += $rowFees['fee'];
                        }

                        try {
                            $datagibbonPayment = array('foreignTableID' => $gibbonFinanceInvoiceID);
                            $sqlgibbonPayment = "SELECT * FROM gibbonPayment WHERE foreignTableID=:foreignTableID";
                            $resultgibbonPayment = $connection2->prepare($sqlgibbonPayment);
                            $resultgibbonPayment->execute($datagibbonPayment);
                        } catch (PDOException $e) {

                        }

                        if($resultgibbonPayment->rowCount() > 0) {
                            $rowgibbonPayment = $resultgibbonPayment->fetch();
                            $gibbonPaymentID = $rowgibbonPayment['gibbonPaymentID'];
                            try {
                                $data = array('paidDate' => date('Y-m-d'), 'paidAmount' => $feeTotal, 'gibbonPaymentID' => $gibbonPaymentID, 'gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID);
                                $sql = "UPDATE gibbonFinanceInvoice SET status='Paid', paidDate=:paidDate, paidAmount=:paidAmount, gibbonPaymentID=:gibbonPaymentID WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID";
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {

                            }

                            try {
                                $data = array('gibbonPaymentID' => $gibbonPaymentID);
                                $sql = "UPDATE gibbonPayment SET status='Complete', onlineTransactionStatus='Success' WHERE gibbonPaymentID=:gibbonPaymentID";
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {

                            }

                            try {
                                $data = array('gibbonGoCardlessCustomersID' => $gibbonGoCardlessCustomersID, 'gibbonCustomerPaymentStatusData' => json_encode($getPaymentDetails));
                                $sql = "UPDATE gibbonGoCardlessCustomers SET gibbonCustomerPaymentStatus='Complete', gibbonCustomerPaymentStatusData=:gibbonCustomerPaymentStatusData WHERE gibbonGoCardlessCustomersID=:gibbonGoCardlessCustomersID";
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {

                            }
                        }
                    }
                }

            }

            if($getPaymentStatus == "cancelled"){
               
                if ($resultKeyRead->rowCount() > 0) {
                    $rowKeyRead = $resultKeyRead->fetch();
                    $gibbonFinanceInvoiceID = $rowKeyRead['gibbonFinanceInvoiceID'];

                    try {
                        $datagibbonPayment = array('foreignTableID' => $gibbonFinanceInvoiceID);
                        $sqlgibbonPayment = "SELECT * FROM gibbonPayment WHERE foreignTableID=:foreignTableID";
                        $resultgibbonPayment = $connection2->prepare($sqlgibbonPayment);
                        $resultgibbonPayment->execute($datagibbonPayment);
                    } catch (PDOException $e) {

                    }

                    if($resultgibbonPayment->rowCount() > 0) {
                        $rowgibbonPayment = $resultgibbonPayment->fetch();
                        $gibbonPaymentID = $rowgibbonPayment['gibbonPaymentID'];
                        try {
                            $data = array('gibbonPaymentID' => $gibbonPaymentID, 'gibbonFinanceInvoiceID' => $gibbonFinanceInvoiceID);
                            $sql = "UPDATE gibbonFinanceInvoice SET status='Cancelled', gibbonPaymentID=:gibbonPaymentID WHERE gibbonFinanceInvoiceID=:gibbonFinanceInvoiceID";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {

                        }

                        try {
                            $data = array('gibbonPaymentID' => $gibbonPaymentID);
                            $sql = "UPDATE gibbonPayment SET status='Failure', onlineTransactionStatus='Fail' WHERE gibbonPaymentID=:gibbonPaymentID";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {

                        }

                        try {
                            $data = array('gibbonGoCardlessCustomersID' => $gibbonGoCardlessCustomersID, 'gibbonCustomerPaymentStatusData' => json_encode($getPaymentDetails));
                            $sql = "UPDATE gibbonGoCardlessCustomers SET gibbonCustomerPaymentStatus='cancelled', gibbonCustomerPaymentStatusData=:gibbonCustomerPaymentStatusData WHERE gibbonGoCardlessCustomersID=:gibbonGoCardlessCustomersID";
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {

                        }
                    }
                }

            }
        }

    }

    exit;
}

//}