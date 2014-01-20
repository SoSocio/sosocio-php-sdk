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
$sosocio->api('/products?limit=10');
```
