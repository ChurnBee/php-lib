ChurnBee php-lib
=======

ChurnBee php API integration library.

## Requirements ##

This module has no external dependencies.


## Installing with Composer ##

### Define Your Dependencies ###
We recommend installing this package with [Composer](http://getcomposer.org/).
Add the following dependencies to your projects composer.json file:

```json

    "require": {
        "churnbee/php-lib": "dev-master"
    }
```
### Install Composer ###

Run in your project root:

```
curl -s http://getcomposer.org/installer | php
```

### Install Dependencies ###

Run in your project root:

```
php composer.phar install
```

### Require Autoloader ###

You can autoload all dependencies by adding this to your code:
```
require 'vendor/autoload.php';
```

## Installing without Composer ##

Place the ChurnBee files in the `include_path` as specified in your `php.ini` file or place it in the same directory as your PHP scripts.
Alternatively include all files:
````
include_once("ChurnBee/Library/CBConf.php");
include_once("ChurnBee/Library/ChurnBeeException.php");
include_once("ChurnBee/Library/CurlUtil.php");
include_once("ChurnBee/Library/ChurnBee.php");
````

