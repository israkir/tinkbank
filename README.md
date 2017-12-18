## Description 

A simple account and transaction system simulating the functionality of a basic bank account implemented as a restful web service.

Implemented using [Slim Micro Framework[(https://www.slimframework.com/). Data is stored in-memory database provided by sqlite. Remember that each server restart will cause data lost. 

## Requirements

[PHP](http://php.net/)

Version: ^5.6.30

[Composer](https://getcomposer.org/download/)

Better install `composer.phar` in `tinkbank/src` directory locally. Follow up the link above for directions.

## Installation 

`cd tinkbank/src`

`php composer.phar install`

`php composer.phar dump-autoload -o`

## Development Server

`cd tinkbank/src/public`

`php -S localhost:8080`

## Accounts API Endpoints

### POST /api/v1/accounts

Request parameters: `holderName`, `deposit`

Response paramaters: `id`, `balance`, `holderId`, `holderName`, `status`, `createTs`

Sample request:

`curl -d '{"holderName":"account4", "deposit": 338}' -H "Content-Type: application/json" -X POST http://localhost:8080/api/v1/accounts`

Sample response: 

`{"id":4,"balance":338,"holderId":3,"status":"ACTIVE","createTs":"2017-12-18 08:04:51","holderName":"account4"}`

------------

### GET /api/v1/accounts/{accountId} 

Request parameters: `accountId`

Response paramaters: `id`, `balance`, `holderId`, `holderName`, `status`, `createTs`

Sample request: 

`curl -H "Content-Type: application/json" -X GET http://localhost:8080/api/v1/accounts/3`

Sample response: 

`{"id":3,"balance":518,"holderId":1,"status":"ACTIVE","createTs":"2017-12-18 07:22:10","holderName":"account1"}`

------------

### DELETE /api/v1/accounts/{accountId} 

Request parameters: `accountId`

Response paramaters: `NO_CONTENT`

Sample request:

`curl -X "DELETE" http://localhost:8080/api/v1/accounts/2`

Sample response: 

`NO_CONTENT`

## Transactions API Endpoints

### PUT /api/v1/transactions/{accountId} 

Request parameters: `operation`, `amount`

Response paramaters: `id`, `balance`, `holderId`, `holderName`, `status`, `createTs`

Sample requests: 

`curl -d operation=deposit -d amount=2147 -H "Content-Type: application/x-www-form-urlencoded" -X PUT http://localhost:8080/api/v1/transactions/1`

Sample response: 

`{"id":1,"balance":5988,"holderId":1,"status":"ACTIVE","createTs":"2017-12-18 07:18:05","holderName":"account1"}`

Sample requests: 

`curl -d operation=withdraw -d amount=47 -H "Content-Type: application/x-www-form-urlencoded" -X PUT http://localhost:8080/api/v1/transactions/1`

Sample response: 

`{"id":1,"balance":5941,"holderId":1,"status":"ACTIVE","createTs":"2017-12-18 07:18:05","holderName":"account1"}`

Sample error response:

`{"error":{"message":"Bad request","details":"Not sufficient balance","code":40001}}`

------------

### PUT /api/v1/transactions 

Request parameters: `senderAccountId`, `receiverAccountId`, `amount`

Response paramaters: `senderAccount`, `receiverAccount`

Sample request:

`curl -d senderAccountId=1 -d receiverAccountId=3 -d amount=60 -H "Content-Type: application/x-www-form-urlencoded" -X PUT http://localhost:8080/api/v1/transactions`

Sample response: 

`{"senderAccount":{"id":1,"balance":5881,"holderId":1,"status":"ACTIVE","createTs":"2017-12-18 07:18:05","holderName":"account1"},"receiverAccount":{"id":3,"balance":578,"holderId":1,"status":"ACTIVE","createTs":"2017-12-18 07:22:10","holderName":"account1"}}`

Sample error response:

`{"error":{"message":"Internal Server Error","details":"Sender account not exist","code":50003}}`


