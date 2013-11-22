ChurnBee php-lib
=======

ChurnBee php API integration library. [Visit official page for more information](https://churnbee.com/)

## Requirements ##

This module has no external dependencies.


## Installing with Composer ##

### Define Your Dependencies ###
We recommend installing this package with [Composer](http://getcomposer.org/).
Add the following dependencies to your projects composer.json file:

```javascript

    "require": {
        "churnbee/php-lib": "dev-master"
    }
```
Then, on the command line:

``` bash
curl -s http://getcomposer.org/installer | php
php composer.phar install
```

Use the generated `vendor/autoload.php` file to autoload the library classes.


## Installing without Composer ##

Place the ChurnBee files in the `include_path` as specified in your `php.ini` file or place it in the same directory as your PHP scripts.
Alternatively include all files:
````
require_once("ChurnBee/Library/CBConf.php");
require_once("ChurnBee/Library/ChurnBeeException.php");
require_once("ChurnBee/Library/CurlUtil.php");
require_once("ChurnBee/Library/ChurnBee.php");
````

Basic Usage Example
===================

```php
<?php
require_once 'vendor/autoload.php';
$options = array("accessToken"=>"change_me");

$cb = new ChurnBee\Library\ChurnBee($options);

// Send registration event
$cb->register("MyUserId","MyPlan");

```
