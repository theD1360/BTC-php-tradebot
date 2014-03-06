mtgox-php-tradebot
==================

Tradebot daemon for MtGox Written in PHP

Version 3.0
===========
Another complete refactor of the code. This version now uses a different method for making trades. 
Now instead of trying to manage each order, which was a hassle btw, I have opted to use a EMA/SMA convergence
divergence methodology. This is not gauranteed to make profit on every trade but it attempts to predict the price
change based on the EMA of the percent change for the last 25 trades. This appears to be working with limited success
and would appreciate if some more experienced programmers would contribute to this project. :)

What to expect from the new version.

* Cleaner code.
* A more standard trading algorithm
* Not much else :\

Sorry for changing the code so drastically every version. I don't have very much experience with writing trading 
alogrithms.


Disclaimer
==========

This software does not come with any gaurantees at all, use at your own risk.

I have not made any money using this script due to the constant fine tuning done but it should work in theory.
If you have any complaints or criticism about the bot please contribute them in a positive manner by actually 
contributing to this project. If you would like to show your appreciation just send me a donation 
(remember I've lost money during the fine tuning of this bot).


Getting Started
===============

__Dependencies__
* [Composer](https://getcomposer.org/) 
* PHP5 CLI
* ~~MtGox~~ [Bitstamp](https://www.bitstamp.net) Account [(working on kraken, and btc-e support)](https://github.com/theD1360/btc-ex)

__Installing__

After getting all your dependencies downloaded, you will need to either clone this repo or download it to your server.
Once you have done so go ahead and drop into your directory and run:

`$ composer install --require-dev`


__Setup Config__

To get started we need to create a `config.json` you can use rename the `config.sample.json` which looks like this

```
{
	"auth":{
		"key" : "your key here",
		"secret" : "your secret here",
		"client_id" : 000000	
	},
	"driver": "bitstamp",
	"wait" : 5,
	"dry" : false
}

```

As you can see we need to specify the driver to an exchange supported by [btc-ex](https://github.com/theD1360/btc-ex). The `auth` 
variable will be the connection values that are needed by our driver to connect to that exchanges API. 
Our `wait` is the wait time in minutes between cycles for ticker data and executing trades (I recomend you keep it at 5). 
Las but not least is the `dry` setting is for people who want to run the bot without actually making trades. This is good for developers testing changes.

`$ php daemon.php`

This will spawn the daemon install a service and start writing to a log file. After running this for the first time 
you will now be able to stop and start the service as any other system daemon by using the following commands.

`$ service tradebot start`
to start
and
`$ service tradebot stop`
to stop

WARNING: you may get a ton of PEAR warnings just disregard these. 

Donations
=========

Gimme your monies!

bitcoin address: 1J4muBrfJrkr7tjeDuPTwBGgaG5wASWtYn

Thank you.
