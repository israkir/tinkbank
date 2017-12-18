<?php

namespace TinkBank;

use \PDO;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Handlers\AccountHandler as AccountHandler;
use \Handlers\TransactionHandler as TransactionHandler;
use \Utils\CurlHelper;


class Endpoints {
    const V1_ACCOUNTS = '/api/v1/accounts';
    const V1_TRANSACTIONS = '/api/v1/transactions';
}


class Errors {
    const DATABASE_ERROR = 10;
}

class TransactionErrors {
    const NOT_SUFFICIENT_BALANCE = 100;
    const DAILY_LIMIT_EXCEEDED = 101;
    const NOT_APPROVED= 102;
    const SENDER_ACCOUNT_NOT_EXIST = 103;
    const RECEIVER_ACCOUNT_NOT_EXIST = 104;
}

class AccountErrors {
    const NOT_EXIST = 200;
}


class App {
    private $app;

    public function __construct() {
        $app = new \Slim\App();

        $container = $app->getContainer();
        $container['db'] = function () {
            try {
                $pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_PERSISTENT => true));
            } catch (PDOException $e) {
                echo 'Connection failed: ' . $e->getMessage();
            }
            return $pdo;
        };
        
        $app->group(Endpoints::V1_ACCOUNTS, function () {
            /**
             * Open account
             */
            $this->post('', function (Request $request, Response $response) {
                $requestBody = $request->getParsedBody();
                $holderName = filter_var($requestBody['holderName'], FILTER_SANITIZE_STRING);
                $deposit = filter_var($requestBody['deposit'], FILTER_SANITIZE_NUMBER_INT);
                
                if ($holderName && $deposit) {
                    $accountHandler = new AccountHandler($this->db);
                    $createAccountResult = $accountHandler->createAccount($holderName, $deposit);
                    
                    if (is_array($createAccountResult)) {
                        return $response->withJson($createAccountResult, 201);
                    } else {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Internal Server Error',
                                'details' => 'Database error',
                                'code' => 50001
                            )
                        ], 500); 
                    }
                }

                $details = "Not specified:";
                if (empty($holderName)) {
                    $details .= " holderName";
                }
                if (empty($deposit)) {
                    $details .= " deposit";
                }
                
                return $response->withJson([
                    'error' => array(
                        'message' => 'Bad Request',
                        'details' => $details,
                        'code' => 40003 // Input data is invalid
                    )
                ], 400); 
            });

            /**
             * Get account details, e.g. balance etc.
             */
            $this->get('/{accountId}', function (Request $request, Response $response, Array $args) {
                $accountId = (int)$args['accountId'];

                $accountHandler = new AccountHandler($this->db);
                $getAccountDetailsResult = $accountHandler->getAccountDetails($accountId);
                
                if (is_array($getAccountDetailsResult)) {
                    if ($getAccountDetailsResult['status'] == 'DELETED') {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Not Found',
                                'details' => 'Account not exist',
                                'code' => 40401 // deleted 
                            )
                        ], 404);
                    }
                    return $response->withJson($getAccountDetailsResult, 200);
                } else if ($getAccountDetailsResult == AccountErrors::NOT_EXIST) {
                    return $response->withJson([
                        'error' => array(
                            'message' => 'Not Found',
                            'details' => 'Account not exist',
                            'code' => 40401 // deleted 
                        )
                    ], 404);
                } else {
                    return $response->withJson([
                        'error' => array(
                            'message' => 'Internal Server Error',
                            'details' => 'Database error',
                            'code' => 50001
                        )
                    ], 500); 
                }

                return $response->withJson([
                    'error' => array(
                        'message' => 'Not Found',
                        'code' => 40400 // not created at all
                    )
                ], 404);
            });

            /**
             * Close account
             */
            $this->delete('/{accountId}', function (Request $request, Response $response, Array $args) {
                $accountId = (int)$args['accountId'];
                
                $accountHandler = new AccountHandler($this->db);
                $closeAccountResult = $accountHandler->closeAccount($accountId);

                if ($closeAccountResult) {
                    return $response->withStatus(204);
                }

                return $response->withJson([
                    'error' => array(
                        'message' => 'Internal Server Error',
                        'details' => 'Database error',
                        'code' => 50001
                    )
                ], 500);
            });
        });

        $app->group(Endpoints::V1_TRANSACTIONS, function () {
            /**
             * Withdraw or deposit money
             */
            $this->put('/{accountId}', function (Request $request, Response $response, Array $args) {
                $accountId = (int)$args['accountId'];
                $requestBody = $request->getParsedBody();
                $amount = filter_var($requestBody['amount'], FILTER_SANITIZE_NUMBER_INT);
                $operation = filter_var($requestBody['operation'], FILTER_SANITIZE_STRING);

                if (!empty($accountId) && !empty($amount) && !empty($operation)) {
                    $transactionHandler = new TransactionHandler($this->db);

                    if ($operation == "deposit") {    
                        $result = $transactionHandler->depositMoney($accountId, $amount);
                    } else if ($operation == "withdraw") {
                        $result = $transactionHandler->withdrawMoney($accountId, $amount);
                    } else {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Bad Request',
                                'details' => 'Unsupported transaction operation',
                                'code' => 40000
                            )
                        ], 400); 
                    }

                    if (is_array($result)) {
                        return $response->withJson($result, 200); // OK
                    } else if ($result == AccountErrors::NOT_EXIST) {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Not Found',
                                'details' => 'Account not exist',
                                'code' => 40401 // deleted 
                            )
                        ], 404);
                    } else if ($result == TransactionErrors::NOT_SUFFICIENT_BALANCE) {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Bad request',
                                'details' => 'Not sufficient balance',
                                'code' => 40001
                            )
                        ], 400);
                    }
                }

                return $response->withJson([
                    'error' => array(
                        'message' => 'Bad Request',
                        'code' => 40000 // Input data is invalid
                    )
                ], 400); 
            });

            /**
             * Transfer money
             */
            $this->put('', function (Request $request, Response $response) {
                $requestBody = $request->getParsedBody();
                $amount = filter_var($requestBody['amount'], FILTER_SANITIZE_NUMBER_INT);
                $senderAccountId = filter_var($requestBody['senderAccountId'], FILTER_SANITIZE_NUMBER_INT);
                $receiverAccountId = filter_var($requestBody['receiverAccountId'], FILTER_SANITIZE_NUMBER_INT);

                if (!empty($senderAccountId) && !empty($receiverAccountId) && !empty($amount)) {
                    $transactionHandler = new TransactionHandler($this->db);
                    $transferMoneyResult = $transactionHandler->transferMoney(
                        $senderAccountId, 
                        $receiverAccountId, 
                        $amount
                    );

                    if (is_array($transferMoneyResult)) {
                        return $response->withJson($transferMoneyResult, 200); // OK
                    } else if ($transferMoneyResult == TransactionErrors::NOT_APPROVED) {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Internal Server Error',
                                'details' => 'Transfer not approved',
                                'code' => 50002
                            )
                        ], 500);
                    } else if ($transferMoneyResult == TransactionErrors::NOT_SUFFICIENT_BALANCE) {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Bad request',
                                'details' => 'Not sufficient balance',
                                'code' => 40001
                            )
                        ], 400);
                    } else if ($transferMoneyResult == TransactionErrors::DAILY_LIMIT_EXCEEDED) {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Bad request',
                                'details' => 'Daily transfer limit exceeded',
                                'code' => 40002
                            )
                        ], 400);
                    } else if ($transferMoneyResult == TransactionErrors::SENDER_ACCOUNT_NOT_EXIST) {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Internal Server Error',
                                'details' => 'Sender account not exist',
                                'code' => 50003
                            )
                        ], 500);
                    } else if ($transferMoneyResult == TransactionErrors::RECEIVER_ACCOUNT_NOT_EXIST) {
                        return $response->withJson([
                            'error' => array(
                                'message' => 'Internal Server Error',
                                'details' => 'Receiver account not exist',
                                'code' => 50003
                            )
                        ], 500);
                    }
                }

                return $response->withJson([
                    'error' => array(
                        'message' => 'Bad Request',
                        'code' => 40000 // Input data is invalid
                    )
                ], 400); 
            });
        });

        $this->app = $app;
    }

    public function get() {
        return $this->app;
    }
}