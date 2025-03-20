# Reva Core for Magento 2

Core

## How to install & upgrade Reva_Core

### 1. Install via composer (recommend)

We recommend you to install Reva_Core module via composer. It is easy to install, update and maintaince.

Run the following command in Magento 2 root folder.

#### 1.1 Install

```
composer require magereva/module-core
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```

#### 1.2 Upgrade

```
composer update magereva/module-core
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```

Run compile if your store in Product mode:

```
php bin/magento setup:di:compile
```

### 2. Copy and paste

If you don't want to install via composer, you can use this way. 

- Download [the latest version here](https://github.com/harishkhant/module-core/archive/master.zip) 
- Extract `module-core-main.zip` file to `app/code/Reva/Core` ; You should create a folder path `app/code/Reva/Core` if not exist.
- Go to Magento root folder and run upgrade command line to install `Reva_Core`:

```
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```
