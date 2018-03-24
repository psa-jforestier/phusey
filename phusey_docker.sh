sudo docker run -itu root --rm --name phusey_running -v "$PWD/scenario":/usr/src/scenario -w /var/www/phusey phusey ./phusey.sh /usr/src/scenario/SampleTest.php
