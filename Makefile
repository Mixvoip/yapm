.PHONY: help db-migrate-demo db-fixtures-demo db-migrate db-fixtures db-u db-u-d cc cct db-diff test update

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

db-u: db-migrate db-fixtures ## Update test db
db-u-d: db-migrate-demo db-fixtures-demo ## Update demo db

db-migrate: ## Run test database migrations
	bin/console doctrine:migrations:migrate --no-interaction --env=test

db-fixtures: ## Load test fixtures
	bin/console doctrine:fixtures:load --no-interaction --env=test

db-migrate-demo: ## Run demo database migrations
	bin/console doctrine:migrations:migrate --no-interaction

db-fixtures-demo: ## Load demo fixtures
	bin/console doctrine:fixtures:load --no-interaction

db-diff: ## Generate a new migration by comparing your current database to your mapping information
	bin/console doctrine:migrations:diff --env=test

cct: ## Clear Symfony test cache
	bin/console cache:clear --env=test

cc: ## Clear Symfony cache
	bin/console cache:clear

ccp: ## Clear prod cache
	sudo -u www-data php bin/console cache:clear \
	&& sudo -u www-data php bin/console doctrine:cache:clear-metadata

test: ## Run PHPUnit tests
	bin/phpunit

gitPull:
	git pull

composerDumpEnv:
	APP_ENV=prod APP_DEBUG=0 composer dump-env prod

composerInstall:
	APP_ENV=prod APP_DEBUG=0 composer install --no-dev --optimize-autoloader

setOwnerGroup:
	sudo chown -R www-data:www-data ./

setGidBit:
	find . -type d | sudo xargs chmod g+s

messengerRestart:
	bin/console messenger:stop-workers

permissions: setOwnerGroup setGidBit

install: gitPull composerInstall composerDumpEnv permissions ccp
update: gitPull composerDumpEnv composerInstall permissions ccp messengerRestart
