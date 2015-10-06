# apns-php

Library used to send APNS push notifications from PHP.

Example usage:

```php

$tokens = ["APNS token from device",...];

$message = new Message();

$message->initialize($tokens);

$message->setAlert("Thunderstorms predicted in your area");

$push = new Push();

$push->connect(false,'cert.pem');

$push->send($message);

// Get a list of failed tokens to remove for next time
$push->getFailedTokens();

```