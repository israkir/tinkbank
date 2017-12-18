<?php

namespace Handlers;

use \TinkBank\Errors;
use \TinkBank\AccountErrors;


class AccountHandler extends BaseHandler {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        parent::__construct($pdo);
    }

    public function createAccount($holderName, $deposit = 0) {
        $getHolderSql = 'SELECT * FROM holders WHERE name = :name;';
        $holderStmt = $this->pdo->prepare($getHolderSql);
        $holderStmt->bindValue(':name', $holderName);
        $holderStmt->execute();
        $holderResult = $holderStmt->fetch(\PDO::FETCH_ASSOC);

        if (!empty($holderResult)) {
            $holderId = $holderResult['id'];
        } else {
            $newHolderSql = 'INSERT INTO holders(name) VALUES (:name);';
            $stmt = $this->pdo->prepare($newHolderSql);
            $stmt->bindParam(':name', $holderName);
            $stmt->execute();
            $holderId = $this->pdo->lastInsertId();
        }

        $newAccountSql = 'INSERT INTO accounts(holderId, balance) VALUES (:holderId, :balance);';
        $stmt = $this->pdo->prepare($newAccountSql);
        $stmt->bindParam(':holderId', $holderId);
        $stmt->bindParam(':balance', $deposit);
        $stmt->execute();
        $accountId = $this->pdo->lastInsertId();

        $accountDetails = $this->getAccountDetails($accountId);
        if (is_array($accountDetails)) return $accountDetails;

        return Errors::DATABASE_ERROR;
    }

    public function getAccountDetails($accountId) {
        $getAccountSql = 'SELECT * FROM accounts WHERE id = :id;';
        $stmt = $this->pdo->prepare($getAccountSql);
        $stmt->bindValue(':id', $accountId);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!empty($result)) {
            $accountDetails = array(
                'id' =>  (int)$result['id'],
                'balance' => (int)$result['balance'],
                'holderId' => (int)$result['holderId'],
                'status' => (($result['status'] == 1) ? 'ACTIVE' : 'DELETED'),
                'createTs' => $result['createTs']
            );

            $getHolderSql = 'SELECT * FROM holders WHERE id = :id;';
            $holderStmt = $this->pdo->prepare($getHolderSql);
            $holderStmt->bindValue(':id', $accountDetails['holderId']);
            $holderStmt->execute();
            $holderResult = $holderStmt->fetch(\PDO::FETCH_ASSOC);

            if (!empty($holderResult)) {
                $accountDetails['holderName'] = $holderResult['name'];
                return $accountDetails;
            } 
        }

        return AccountErrors::NOT_EXIST;
    }

    public function closeAccount($accountId) {
        $accountSql = 'UPDATE accounts SET status = 0 WHERE id = :id;';
        $stmt = $this->pdo->prepare($accountSql);
        $stmt->bindParam(':id', $accountId);
        $success = $stmt->execute();

        if ($success) return true;

        return Errors::DATABASE_ERROR;
    }
}

?>