<?php

namespace Handlers;


class BaseHandler {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createTables();
    }

    protected function createTables() {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS holders (
                id INTEGER PRIMARY KEY, 
                name VARCHAR(255) NOT NULL,
                createTs DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (name)
            );
            CREATE TABLE IF NOT EXISTS accounts (
                id INTEGER PRIMARY KEY, 
                holderId INTEGER, 
                balance INTEGER DEFAULT 0, 
                status INTEGER DEFAULT 1,
                createTs DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(holderId) REFERENCES holders(id)
            );
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY, 
                senderAccountId INTEGER NOT NULL, 
                receiverAccountId INTEGER NOT NULL, 
                amount INTEGER NOT NULL, 
                transferTs DATETIME DEFAULT CURRENT_TIMESTAMP
            );'
        );
    }
}

?>