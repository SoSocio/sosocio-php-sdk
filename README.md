sosocio-php-sdk
===============

SoSocio PHP SDK

Example:

```
$config = array(
  'apiUrl' => 'https://sosocio.com/api',
  'apiKey' => 'myApiKey',
  'apiSecret' => 'myApiSecret'
);
$sosocio = new sosocio($config);

$conditions = array(
	'where' => array('id'=>array(101,102),'userid'=>10))),
	'limit' => 	10,
	'offset' => 5
);

$sosocio->api('/products','GET',$conditions);
```
