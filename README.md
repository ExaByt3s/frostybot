# frostybot

Authors:    CryptoMF from the Krown's Crypto Cave discord group (the creator of this project)
            Barnz from the Krown's Crypto Cave discord group (release 0.6 to 0.8)
            
Dedication: Dedicated to @christiaan's mom, what a classy lady!

Disclaimer: Use this bot at your own risk, the authors accept no responsibility if you get rekt.
            This is a 0.x release which means ists beta software. So it may and probably will have some bugs.
            
Scope:      As of now deribit and mexico are supported (plus testnets) with BTC and ETH pairs (perpetuals)
            This is mostly useful for strategies that don't trade too often. For every trade multiple REST api calls are done
            to the exchange, which is not super fast, but it keeps the bot simple.  So if you want to do higher frequency 
            trades its probably better to use websocket API's and don't use FrostyBot.
            
Summary:    FrostyBot is a minimal endpoint that is designed to be used with webhooks in tradingview alerts.
            It receives simple commands and translates them to specific exchange orders and sends them to the 
            exchange. There is little logic as the idea is to keep the trading logic in tradingview. The bot has 
            some minimal logic:
            - going short after a long first closes the long position completely and then executes the short (and 
              vice versa) So if yo have a long position of 100 and you enter a short of 10 after that the result is
              a netto position of 10 contracts short. (and not 100-10=90 contracts long). This is done to simplify the
              bot logic to be written in pinescript.
            - max positions can be specified per bot. Which means if your logic fails on tradingview and you keep 
              placing orders the bot will stop accepting orders (but keeps the max specified position)
            - anything that can trigger an alert in tradingview can use this bot for trade execution. so also the 
              crossing of a trendline, etc.
            - closing all positions means you are spot long your account value! in the future we might add logic going 
              automatically to 'cash' on perpetual contracts. This is already easy on Deribit: just enter a 'short 100%' 
              BE AWARE on mexico though, as it defaults to 100x leverage if you use cross leverage. So about 99% 
              of your account value is still spot long if you do a 100% short there on cross leverage. You basically took 
              2 opposite sides and paying transaction and funding costs in that case. So on mexico the slider must be set to 1x
              to behave the same as Deribit. Make sure you really understand the leverage mechanism on mexico if you prefer 
              mexico over Deribit for some reason (liquidity being the main one). The leverage system on mexico is 
              really designed to fuck you.
              
Usage:      Its recommended to use subaccounts for the individual bots to limit risk. 
            First follow the instructions in the INSTALLATION file. Then configure your Trading View alerts to call 
            the webhook using the appropriate commands:

            Webhook Example URLs. Note the use of pct for percentages here and that we use deribit and trade btc:
            
                https://my.bot.com/bot/deribit/btc_usd/?command=LONG (initiate long ontry  the DEFAULT_ORDER_SIZE, see config)
                https://my.bot.com/bot/deribit/btc_usd/?command=LONG&size=1000&price=7600 (Limit buy for USD1000 at $7600)
                https://my.bot.com/bot/deribit/btc_usd/?command=LONG&size=200pct (Market buy 200% of current balance, ie. 2x)
                https://my.bot.com/bot/deribit/btc_usd/?command=CLOSE (Will exit the current position)
                https://my.bot.com/bot/deribit/btc_usd/?command=CLOSE&size=100 (Will reduce the current long position with $100, taking profit / reducing risk )
                https://my.bot.com/bot/deribit/btc_usd/?command=CLOSE&size=50pct (Will reduce the current long position with 50%, taking profit / reducing risk )
                https://my.bot.com/bot/deribit/btc_usd/?command=SHORT&size=20000 (buy for USD20000, at market price)
                
                // NOTE anything ing the message box of the alert in tradingview is interpreted as a command, 
                // so don't use the message bog for a general description! Also the message box overwrites
                // the webhook url parameters.
                                   
            CLI Examples: (run inside the subdir for the specific bot instance)
              
                // trading
                php index.php long            (Will use the DEFAULT_ORDER_SIZE specified below with a market order)
                php index.php long 10000      (Will enter long position of USD 10000 with a market order)
                php index.php long 1000 7600  (Will enter long position of USD 10000 with a limit order at $7600)
                php index.php long 200%       (Will enter long position of 200% of balance (2x) at market price)
                php index.php close           (Will exit the full current long/short position)
                php index.php close 50%       (Take profit on 50% of the current position)
                php index.php short 20000     (Will enter short position of USD 20000, for other options see long)
                
                // info (also callable from webhook urls if you enabled your local ip in the whitelist, see INSTALLATION)
                php index.php trades          (Recent trades, oldest listed first)
                php index.php balance         (Current account balances)
                php index.php orders          (Open orders)
                php index.php cancel          (Cancel all open orders)
                php index.php position        (Show current open position, if any)
                php index.php log             (Show log file contents for bot instance, last 10 lines is the default)
                php index.php log 20          (Show log file contents for bot instance, last 20 lines)

           examples in messagebox / pinescript alerts
           
                // NOTE anything ing the message box is interpreted as a command, so don't use the message bog for a general description!
             
                long            (Will use the DEFAULT_ORDER_SIZE specified below with a market order)
                long 10000      (Will enter long position of USD 10000 with a market order)
                long 1000 7600  (Will enter long position of USD 10000 with a limit order at $7600)
                long 200%       (Will enter long position of 200% of balance (2x) at market price)
                close           (Will exit the full current long/short position)
                close 50%       (Take profit on 50% of the current position)
                short 20000     (Will enter short position of USD 20000, for other options see long)
               
           example pinescript alert structure
                // note you have to set the url in the webhook field
           
                strategy("testing alerts with frostybot")
                
                var botCommandA = 'long 50%'
                var botCommandB = 'short 50%'
        
                // some strategy conditional logic here
        
                // we need  to set the alerts to run the strategy
                // unfortunately 1 for each command.
                alertcondition(<conditionA>, 'Secret sauce bot: alert(1 of 2)', botCommandA);
                alertcondition(<conditionB>, 'Secret sauce bot: alert(2 of 2)', botCommandB);
        
                // Limitation is that we can not use dynamic values (damn you pinescript) for the third param of an alertcondition
                // botCommand = 'LONG 50pct' + dynamicallyDeterminedResistanceValue; is not possible.
