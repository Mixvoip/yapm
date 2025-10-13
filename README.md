# Yet Another Password Manager (YAPM)

A secure, self-hosted password manager built with Symfony 7.3 and PHP 8.4, featuring end-to-end encryption, group-based access control, and a RESTful API.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
	- [Development environment](#development-environment)
	- [Production environment](#production-environment)
- [Security](#security)

## Requirements

### System requirements

- **PHP**: 8.4 or higher

### PHP extensions

```bash
# Required extensions
php8.4-cli
php8.4-mysql  
php8.4-mbstring
php8.4-xml
php8.4-curl
php8.4-zip
php8.4-intl
php8.4-sodium
php8.4-pdo
```

For production, you also need to install php-fpm and configure it to run as a service.

### Mail server

You'll need an SMTP server for sending verification emails.

## Installation

### Development Environment

#### 1. Clone the repository

```bash
git clone https://github.com/mixvoip/yapm.git
cd yapm
```

#### 2. Install PHP dependencies

```bash
composer install
```

#### 3. Configure environment

Copy the example environment file and update it with your settings:

```bash
cp .env .env.local
```

Edit `.env.local`:

```env
###> symfony/framework-bundle ###
APP_ENV=dev
APP_SECRET=your-secret-key-here
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/yapm_dev?serverVersion=8.0.32&charset=utf8mb4"
###< doctrine/doctrine-bundle ###

###> symfony/mailer ###
MAILER_DSN=smtp://localhost:1025
MAILER_FROM_ADDRESS=yapm@yourdomain.com
###< symfony/mailer ###

SERVER_PRIVATE_KEY=your-private-key-here
SERVER_PUBLIC_KEY=your-public-key-here

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-jwt-passphrase
###< lexik/jwt-authentication-bundle ###

# Application specific
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

#### 4. Generate JWT keys

```bash
# First set your passphrase in the .env.local file
# Then generate the keys
php bin/console lexik:jwt:generate-keypair
```

#### 5. Generate server keys

```bash
php bin/console app:encryption:generate-server-keypair
```

#### 6. Setup database

```bash
# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# (Optional) Load fixtures for testing
php bin/console doctrine:fixtures:load
```

#### 7. Start development server

```bash
# Start Symfony development server
symfony serve -d

# Or use PHP built-in server
php -S localhost:8000 -t public public/index.php

# Start message queue worker
bin/console messenger:consume async_doctrine -vv
```

Your API should now be accessible at `http://localhost:8000`

### Production environment

The production setup mirrors the development environment, with automation handled through the provided `Makefile`.  
System prerequisites (PHP 8.4 + extensions, web server, Composer) must already be installed.

#### 1. Clone the repository

```bash
git clone https://github.com/mixvoip/yapm.git
cd yapm
git checkout main
```

#### 2. Setup .env.local file

Follow the steps in the [Development Environment](#development-environment) section.

```
APP_ENV=prod
```

#### 3. Generate keys

Follow the steps in the [Development Environment](#development-environment) section.

#### 4. Install dependencies

```bash
# Use make install to setup the project
make install
```

#### 5. Setup workers

For the background tasks you need to setup a systemd service.

```
[Unit]
Description=Symfony Messenger worker for queue %i
After=network.target

[Service]
WorkingDirectory=/your-working-directory/
ExecStart=/usr/bin/php /your-working-directory/bin/console messenger:consume %i --limit=5 --env=prod
Restart=always
RestartSec=2
TimeoutSec=300
User=www-data

[Install]
WantedBy=multi-user.target
```

#### 6. Create the initial user

```bash
php bin/console app:create-admin your_username your_email
```

## Security

### Key security features

1. **End-to-End Encryption**: All sensitive data is encrypted before transmission
2. **JWT Authentication**: Secure token-based authentication
3. **User Password Hashing**: Uses Symfony's native password hashing (bcrypt/argon2)
4. **Public Key Cryptography**: Asymmetric encryption for secure key sharing
5. **CORS Protection**: Configurable cross-origin resource sharing
6. **SQL Injection Protection**: Doctrine ORM parameter binding

### Security best bractices

1. Always use HTTPS in production
2. Keep JWT passphrase and other private keys secure and never commit to version control
3. Use strong database passwords
4. Enable PHP opcache in production
5. Regularly backup your database
6. Monitor logs for suspicious activity

### Important Files to Secure

```bash
# Never add these files to VCS
config/jwt/private.pem
config/jwt/public.pem
.env.local
```

**Note**: Check out [our frontend repository](https://github.com/mixvoip/yapm-interface) to use with this API.
