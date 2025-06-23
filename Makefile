PORT ?= 8000
dev:
	php -S localhost:8080 -t public
install:
	composer install
lint:
	composer exec --verbose phpcs -- --standard=PSR12 src public
start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public
db-prepare:
	psql -a -d "$DATABASE_URL" -f database.sql
build: install db-prepare