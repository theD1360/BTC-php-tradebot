BTC php tradebot
==================

MACD tradebot daemon for various BTC exchanges written in PHP

Version 3.0.2
===========

This program is written to trade using the MACD method as best as it can. Since I am not involved in any sort
of financial mathmetician, this application was written to apply the methodolgy as best as I could understand it.

Originally this bot was written for MtGox and was very rudimentary, but as MtGox began to crumble before our eyes, I took it upon myself
to increase the quality of the code and switch exchanges. While doing so I made the descision to try and support as many
exchanges as possible to save work in the event of another [goxxing](http://www.urbandictionary.com/define.php?term=goxxed).

I have separated the exchange API interfaces and placed them another repo to be included in this project via composer. This package is 
available to use for any projects that may require a standard interface for various exchanges. Both of these projects are still in 
their infancy and contributions of all kinds are required to make, not only, them a success but bitcoin as a whole.

Disclaimer
==========

This software does not come with any gaurantees at all, use at your own risk.


Getting Started
===============

__Dependencies__
* [Composer](https://getcomposer.org/) 
* php5 CLI
* php5 cURL
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
