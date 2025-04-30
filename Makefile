PORT ?= 8000

install:
	composer install
lint:
	composer exec --verbose phpcs -- --standard=PSR12 src tests
start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public