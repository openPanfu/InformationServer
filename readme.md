# OpenPanfu InformationServer

### About
AMF stands for Action Message Format, it's used for remoting between a Java and apache flex program.

As it turns out, Panfu uses Flex remoting, however implementing it in Java (which requires tomcat) is not a good option.

We went for Amfphp because it's pretty easy to setup for yourself. (Amfphp license can be found in license.txt)

### Installation

For installation you'll need a MYSQL server.

#### Gateway setup
Alter /Plugins/Database/Database.php to match your MYSQL setup.

(optionally) add new words to the wordfilter in /Plugins/Panfu/wordfilter.txt.

Run the php localserver with `php -S 0.0.0.0:9090` or use a webserver like apache or nginx.

#### Client setup

Using the official panfu client is easy as pie.

However, since the redistribution of the flash client is in a legal grey area, we will not distribute them here.

(Just google for them!)

Once you have them, you can start the client with these flashvars:

`iServer=(AMF gateway)`

`langId=EN/DE/NL`

`mode=dev` (if you run on live, sso-less login won't work!)
