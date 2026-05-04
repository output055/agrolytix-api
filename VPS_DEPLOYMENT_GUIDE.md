# Agrolytix API VPS Deployment Guide

This guide provides step-by-step instructions to deploy the Agrolytix Laravel API to your Ubuntu 24.04 VPS running Nginx, PHP 8.3, and MySQL.

## 1. Initial Server Setup (One-time)

If you haven't already, install the required software on your Ubuntu server:
```bash
sudo apt update
sudo apt install nginx mysql-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip supervisor git unzip
```

Install Composer globally:
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## 2. Clone the Repository

Clone your project into `/var/www/agrolytix`:
```bash
sudo mkdir -p /var/www/agrolytix
sudo chown -R $USER:$USER /var/www/agrolytix
git clone <your-git-repo-url> /var/www/agrolytix
cd /var/www/agrolytix
```

## 3. Environment Configuration

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```
2. Edit the `.env` file with your production details:
   ```bash
   nano .env
   ```
   **Key fields to update:**
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://your_domain.com`
   - Update `DB_*` with your MySQL database credentials.
   - Update `PAYSTACK_*` with your live Paystack API keys.
3. Generate the application key:
   ```bash
   php artisan key:generate
   ```

## 4. Set Directory Permissions

Laravel requires write permissions for the `storage` and `bootstrap/cache` directories.
```bash
sudo chown -R www-data:www-data /var/www/agrolytix/storage
sudo chown -R www-data:www-data /var/www/agrolytix/bootstrap/cache
sudo chmod -R 775 /var/www/agrolytix/storage
sudo chmod -R 775 /var/www/agrolytix/bootstrap/cache
```

## 5. Nginx Configuration

1. Copy the provided Nginx configuration file:
   ```bash
   sudo cp server-setup/nginx.conf.example /etc/nginx/sites-available/agrolytix
   ```
2. Edit `/etc/nginx/sites-available/agrolytix` and replace `your_domain.com` with your actual domain.
3. Enable the site and restart Nginx:
   ```bash
   sudo ln -s /etc/nginx/sites-available/agrolytix /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl restart nginx
   ```

## 6. Supervisor Configuration (Queue Worker)

To keep your queued jobs (like Paystack webhooks or emails) processing continuously:
1. Copy the Supervisor configuration:
   ```bash
   sudo cp server-setup/supervisor.conf.example /etc/supervisor/conf.d/agrolytix-worker.conf
   ```
2. Update Supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start agrolytix-worker:*
   ```

## 7. Ongoing Deployments

Once the initial setup is complete, you can deploy updates simply by making the deploy script executable and running it:

```bash
# Make the script executable (one time)
chmod +x server-setup/deploy.sh

# Run the deployment script
./server-setup/deploy.sh
```

> [!TIP]
> The `deploy.sh` script automatically pulls the latest code from `main`, installs dependencies without dev packages, runs migrations, caches your configuration, routes, and views, and restarts the queue worker seamlessly.
