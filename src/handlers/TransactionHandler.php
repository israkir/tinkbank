<?php

namespace Handlers;

use \TinkBank\Errors;
use \TinkBank\TransactionErrors;
use \TinkBank\AccountErrors;
use \Utils\CurlHelper;


class TransactionHandler extends BaseHandler {
    const APPROVAL_SERVICE = "http://handy.travel/test/success.json";
    const DAILY_TRANSFER_LIMIT = 10000;
    const SERVICE_FEE = 100;

    private $pdo;
    private $accountHandler;

    public function __construct($pdo) {
        parent::__construct($pdo);
        $this->pdo = $pdo;
        $this->accountHandler = new AccountHandler($pdo);
    }

    public function depositMoney($accountId, $amount) {
        $account = $this->accountHandler->getAccountDetails($accountId);
        if ($account == AccountErrors::NOT_EXIST || $account['status'] == 'DELETED') {
            return AccountErrors::NOT_EXIST;
        }

        $transactionStmt = $this->pdo->prepare('BEGIN TRANSACTION;');
        $transactionStmt->execute();

        $updateBalanceSql = 'UPDATE accounts SET balance = balance + (:amount) WHERE id = :id;';
        $stmt = $this->pdo->prepare($updateBalanceSql);
        $stmt->bindValue(':id', $accountId);
        $stmt->bindValue(':amount', $amount);
        $stmt->execute();

        $commitStmt = $this->pdo->prepare('COMMIT;');
        $commitStmt->execute();

        return $this->accountHandler->getAccountDetails($accountId);
    }

    public function withdrawMoney($accountId, $amount) {
        $account = $this->accountHandler->getAccountDetails($accountId);
        if ($account == AccountErrors::NOT_EXIST || $account['status'] == 'DELETED') {
            return AccountErrors::NOT_EXIST;
        }

        $currentAccount = $this->accountHandler->getAccountDetails($accountId);
        if ($currentAccount['balance'] - $amount < 0) {
            return TransactionErrors::NOT_SUFFICIENT_BALANCE;
        }

        $transactionStmt = $this->pdo->prepare('BEGIN TRANSACTION;');
        $transactionStmt->execute();

        $updateBalanceSql = 'UPDATE accounts SET balance = balance - (:amount) WHERE id = :id;';
        $stmt = $this->pdo->prepare($updateBalanceSql);
        $stmt->bindValue(':id', $accountId);
        $stmt->bindValue(':amount', $amount);
        $stmt->execute();

        $commitStmt = $this->pdo->prepare('COMMIT;');
        $commitStmt->execute();

        return $this->accountHandler->getAccountDetails($accountId);
    }

    public function transferMoney($senderAccountId, $receiverAccountId, $amount) {
        $senderAccountDetails = $this->accountHandler->getAccountDetails($senderAccountId);
        $receiverAccountDetails = $this->accountHandler->getAccountDetails($receiverAccountId);

        if ($senderAccountDetails == AccountErrors::NOT_EXIST || $senderAccountDetails['status'] == 'DELETED') {
            return TransactionErrors::SENDER_ACCOUNT_NOT_EXIST;
        }

        if ($receiverAccountDetails == AccountErrors::NOT_EXIST || $receiverAccountDetails['status'] == 'DELETED') {
            return TransactionErrors::RECEIVER_ACCOUNT_NOT_EXIST;
        }

        $serviceFee = 0;
        if ($senderAccountDetails['holderId'] != $receiverAccountDetails['holderId']) {
            $serviceFee = self::SERVICE_FEE;
            $approved = $this->_getApproval();
            if (!$approved) return TransactionErrors::NOT_APPROVED;
        }
        
        if ($this->_exceedDailyTransferLimit($senderAccountId, $amount)) {
            $withdrawMoneyResult = $this->withdrawMoney($senderAccountId, ($amount + $serviceFee));
            if (is_array($withdrawMoneyResult)) {
                $depositMoneyResult = $this->depositMoney($receiverAccountId, $amount);
                if (!empty($depositMoneyResult)) {
                    $this->_logTransferOperation($senderAccountId, $receiverAccountId, $amount);
                    if ($serviceFee > 0) {
                        $withdrawMoneyResult['serviceFee'] = $serviceFee;
                    }
                    return array(
                        'senderAccount' => $withdrawMoneyResult,
                        'receiverAccount' => $depositMoneyResult
                    );
                } else {
                    return $depositMoneyResult; // return result of corresponding api
                }
            } else {
                return $withdrawMoneyResult; // return result of corresponding api
            }
        }
        
        return TransactionErrors::DAILY_LIMIT_EXCEEDED;
    }

    private function _getApproval() {
        $curlHelper = new CurlHelper(self::APPROVAL_SERVICE);
        $response = $curlHelper->getResponse();
        if ($response['status'] == 'success') return true;
        return false;
    }

    private function _logTransferOperation($senderAccountId, $receiverAccountId, $amount) {
        $newTransactionSql = 'INSERT INTO transactions(senderAccountId, receiverAccountId, amount) VALUES (:senderAccountId, :receiverAccountId, :amount);';
        $stmt = $this->pdo->prepare($newTransactionSql);
        $stmt->bindParam(':senderAccountId', $senderAccountId);
        $stmt->bindParam(':receiverAccountId', $receiverAccountId);
        $stmt->bindParam(':amount', $amount);
        return $stmt->execute();
    }

    private function _exceedDailyTransferLimit($senderAccountId, $amount) {
        $sql = 'SELECT senderAccountId, SUM(amount) as totalTransferredAmountToday FROM transactions WHERE senderAccountId = (:senderAccountId) AND transferTs BETWEEN datetime(\'now\', \'-1 day\') AND datetime(\'now\')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':senderAccountId', $senderAccountId);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            if (($result['totalTransferredAmountToday'] + $amount) < self::DAILY_TRANSFER_LIMIT) {
                return true;
            }
        }
        
        return false;
    }
}

?>