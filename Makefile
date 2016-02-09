
test:
	phpunit --coverage-text --whitelist=msgpack.php msgpackTest.php

clean:
	rm -f *~
