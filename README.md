coinbase-wpecommerce
====================

Accept Bitcoin on your WP-eCommerce powered website with Coinbase.

Download the plugin here: https://github.com/coinbase/coinbase-wpecommerce/archive/master.zip

# Requirements

PHP >= 5.3.0 with curl, openssl

# Installation

First generate an API key with the 'user' and 'merchant' permissions at https://coinbase.com/settings/api. If you don't have a Coinbase account, sign up at https://coinbase.com/merchants. Coinbase offers daily payouts for merchants in the United States. For more infomation on setting up payouts, see https://coinbase.com/docs/merchant_tools/payouts.

Download the plugin and copy the 'coinbase-php' folder and 'coinbase.merchant.php' into wp-content/plugins/wp-e-commerce/wpsc-merchants on your server. If upgrading, make sure to remove coinbase.php from wp-content/plugins/wp-e-commerce/wpsc-merchants.

After copying the files, open the Wordpress dashboard and navigate to Settings > Store and click the "Payments" tab. Next, check the box beside "Coinbase", enter your API credentials, and click update.

NOTE: Do not set the callback and redirect URLs manually on coinbase.com as this will interfere with the operation of the plugin.
