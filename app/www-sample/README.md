# Sample website
This directory contains a sample website, used to test PHUSEY loading capacity.

You can use the PHP internal webserver to serve theses files :
```
$> php -S 0.0.0.0:80
```
/!\ The webserver must not include PTHREAD library, or you will have en error "PHP Fatal error:  The cli-server SAPI is not supported by pthreads" when started.

And use your browser to connect to ```http://localhost/```