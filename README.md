sosocio-php-sdk
===============

SoSocio PHP SDK

Examples:

```
$config = array(
  'apiUrl' => 'https://sosocio.com/api',
  'apiKey' => 'myApiKey',
);
$sosocio = new sosocio($config);

$conditions = array(
	'where' => array(
		'id' => array(101,102), // id can be 101 OR 102
		'userid' => 10 // userid has to be 10
	),
	'limit' => 	10, // return 10 records max
	'offset' => 5 // skip first 5 records
);

# GET multiple records
$sosocio->api('/product', 'GET', $conditions);

# GET one specific record
$sosocio->api('/product/25', 'GET', $conditions);

# POST request
$data = array('title' => 'My product');
$sosocio->api('/product', 'POST', $data);

# PUT request
$data = array('title' => 'New title');
$sosocio->api('/product/25', 'PUT', $data);
```
