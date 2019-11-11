<p align="center">
    <a href="https://gibbonedu.org/" target="_blank"><img width="200" src="https://gibbonedu.org/img/gibbon-logo.png"></a><br>
    Gibbon is a flexible, open source school management platform designed <br>
    to make life better for teachers, students, parents and schools.
</p>

------

GoCardless Integration
===========
This repository fork contains core changes for the addition of the GoCardless payment gateway solution to the Finance module of Gibbonedu.

Please ensure you have installed GoCardless Pro as per the instructions here: https://github.com/gocardless/gocardless-pro-php
Also, please remember to enable payments by navigating to Home > School Admin > Manage Finance Settings and setting the "Enable Online Payment" select box to "Yes".

## SQL Changes

After replacing files, please run the following SQL queries:

CREATE TABLE `gibbonGoCardlessCustomers` (
  `gibbonGoCardlessCustomersID` int(11) NOT NULL,
  `gibbonPersonID` int(14) NOT NULL,
  `gibbonFinanceInvoiceID` int(14) NOT NULL,
  `key` varchar(50) NOT NULL,
  `gibbonCustomerData` longtext NOT NULL,
  `gibbonCustomerMandate` longtext NOT NULL,
  `gibbonCustomerPaymentStatusData` longtext NOT NULL,
  `gibbonCustomerPaymentStatus` varchar(20) NOT NULL,
  `timestampCreator` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

ALTER TABLE `gibbonGoCardlessCustomers` ADD PRIMARY KEY (`gibbonGoCardlessCustomersID`);

ALTER TABLE `gibbonGoCardlessCustomers` MODIFY `gibbonGoCardlessCustomersID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1

ALTER TABLE gibbonPayment CHANGE status status ENUM('Complete','Partial','Final','Failure','Awaiting') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Complete' COMMENT 'Complete means paid in one go, partial is part of a set of payments, and final is last in a set of payments.';

ALTER TABLE gibbonPayment CHANGE onlineTransactionStatus onlineTransactionStatus ENUM('Success','Failure','Awaiting') CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE gibbonPayment CHANGE gateway gateway ENUM('Paypal','GoCardless') CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

INSERT INTO gibbonSetting (gibbonSettingID, scope, name, nameDisplay, description, value) VALUES (NULL, 'System', 'paymentGatewaySettings', 'Choose payment gateway', 'Select any payment gateway to make payment through', 'GoCardless')

INSERT INTO gibbonSetting (gibbonSettingID, scope, name, nameDisplay, description, value) VALUES (NULL, 'System', 'enableGoCardLess', 'Enable GoCardless Payments', 'Should payments be enabled across the system?', 'Y')

INSERT INTO gibbonSetting (gibbonSettingID, scope, name, nameDisplay, description, value) VALUES (NULL, 'System', 'GoCardlessAPIkey', 'GoCardless API key', 'Set API key for make payment through GoCardless payment gateway ', ' ')

## License

Gibbon is licensed under GNU General Public License v3.0. You can obtain a copy of the license [here](https://github.com/GibbonEdu/core/blob/master/LICENSE).
