# Complete Fleetbase Installation Guide with Multi-Gateway Payment Support

## Overview

This guide covers the complete installation of Fleetbase with support for both Stripe and Paystack payment gateways, enabling you to serve customers globally with region-appropriate payment methods.

## Part 1: DigitalOcean Droplet Setup

### Step 1.1: Create a Droplet

1. **Log into DigitalOcean Console**
   - Navigate to "Create" → "Droplets"

2. **Choose Configuration:**
   - **Image:** Ubuntu 22.04 LTS (or latest stable)
   - **Plan:** Minimum recommended specifications:
     - CPU: 2 vCPUs
     - RAM: 4GB (8GB recommended for production)
     - Storage: 80GB SSD
     - Monthly cost: ~$24-48

3. **Select Datacenter:**
   - Choose the region closest to your target users

4. **Authentication:**
   - Add your SSH key (recommended) or use password authentication

5. **Additional Options:**
   - Enable backups (recommended for production)
   - Enable monitoring

6. **Finalize:**
   - Set hostname: `fleetbase-server`
   - Click "Create Droplet"

### Step 1.2: Initial Server Setup

1. **SSH into your droplet:**
   ```bash
   ssh root@your-droplet-ip
   ```

2. **Update the system:**
   ```bash
   apt-get update && apt-get upgrade -y
   ```

3. **Create a non-root user (optional but recommended):**
   ```bash
   adduser fleetbase
   usermod -aG sudo fleetbase
   ```

4. **Set up basic firewall:**
   ```bash
   ufw allow OpenSSH
   ufw allow 80/tcp
   ufw allow 443/tcp
   ufw allow 4200/tcp  # Fleetbase Console
   ufw allow 8000/tcp  # Fleetbase API
   ufw enable
   ```

## Part 2: Install Prerequisites

### Step 2.1: Install Docker

1. **Install Docker dependencies:**
   ```bash
   apt-get install -y \
     ca-certificates \
     curl \
     gnupg \
     lsb-release
   ```

2. **Add Docker's official GPG key:**
   ```bash
   mkdir -p /etc/apt/keyrings
   curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
   ```

3. **Set up Docker repository:**
   ```bash
   echo \
     "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
     $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
   ```

4. **Install Docker Engine:**
   ```bash
   apt-get update
   apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
   ```

5. **Verify Docker installation:**
   ```bash
   docker --version
   docker compose version
   ```

### Step 2.2: Install Git and Additional Tools

```bash
apt-get install -y git nano wget curl
```

## Part 3: Install Fleetbase

### Step 3.1: Clone the Repository

1. **Navigate to installation directory:**
   ```bash
   cd /opt
   ```

2. **Clone Fleetbase:**
   ```bash
   git clone https://github.com/fleetbase/fleetbase.git
   cd fleetbase
   ```

### Step 3.2: Run Docker Installation Script

```bash
chmod +x ./scripts/docker-install.sh
./scripts/docker-install.sh
```

This script will:
- Pull necessary Docker images
- Set up the database
- Configure Redis
- Start all services

## Part 4: Enhanced Configuration with Payment Gateway Support

### Step 4.1: Create Enhanced Override Configuration

1. **Create docker-compose.override.yml:**
   ```bash
   nano docker-compose.override.yml
   ```

2. **Add production configuration with payment gateway support:**
   ```yaml
   version: '3.8'
   services:
     application:
       environment:
         # Core Configuration
         - APP_URL=https://api.yourdomain.com
         - APP_ENV=production
         - APP_DEBUG=false
         - APP_KEY=base64:YOUR_GENERATED_KEY_HERE
         
         # Console Configuration
         - CONSOLE_HOST=https://console.yourdomain.com
         
         # Database Configuration
         - DATABASE_URL=mysql://fleetbase:your_password@db:3306/fleetbase
         
         # Redis Configuration
         - REDIS_URL=redis://redis:6379
         - QUEUE_CONNECTION=redis
         - CACHE_DRIVER=redis
         
         # Payment Gateway Configuration
         - DEFAULT_PAYMENT_GATEWAY=stripe
         - ENABLED_PAYMENT_GATEWAYS=stripe,paystack
         
         # Stripe Configuration
         - STRIPE_KEY=pk_live_xxxxx
         - STRIPE_SECRET=sk_live_xxxxx
         - STRIPE_WEBHOOK_SECRET=whsec_xxxxx
         
         # Paystack Configuration
         - PAYSTACK_PUBLIC_KEY=pk_live_xxxxx
         - PAYSTACK_SECRET_KEY=sk_live_xxxxx
         - PAYSTACK_WEBHOOK_SECRET=xxxxx
         - PAYSTACK_BASE_URL=https://api.paystack.co
         
         # Mail Configuration (using Mailgun as example)
         - MAIL_MAILER=mailgun
         - MAIL_FROM_ADDRESS=noreply@yourdomain.com
         - MAIL_FROM_NAME="Fleetbase"
         - MAILGUN_DOMAIN=mg.yourdomain.com
         - MAILGUN_SECRET=your-mailgun-api-key
         
         # SMS Configuration (optional - using Twilio)
         - TWILIO_SID=your_twilio_sid
         - TWILIO_TOKEN=your_twilio_token
         - TWILIO_FROM=+1234567890
         
         # Security
         - SESSION_SECURE_COOKIE=true
         - JWT_SECRET=your_jwt_secret_here

     console:
       environment:
         - API_HOST=https://api.yourdomain.com
         - API_NAMESPACE=int/v1
         - API_SECURE=true
         - SOCKETCLUSTER_HOST=wss://api.yourdomain.com
         - SOCKETCLUSTER_PORT=38000
         - SOCKETCLUSTER_SECURE=true
         - ENVIRONMENT=production
         
         # Payment Gateway Configuration for Frontend
         - PAYSTACK_PUBLIC_KEY=pk_live_xxxxx
         - DEFAULT_PAYMENT_GATEWAY=stripe
         - ENABLED_PAYMENT_GATEWAYS=stripe,paystack
       ports:
         - "4200:4200"

     db:
       environment:
         - MYSQL_ROOT_PASSWORD=your_root_password
         - MYSQL_DATABASE=fleetbase
         - MYSQL_USER=fleetbase
         - MYSQL_PASSWORD=your_password
       volumes:
         - ./data/mysql:/var/lib/mysql

     redis:
       command: redis-server --requirepass your_redis_password
       volumes:
         - ./data/redis:/data
   ```

3. **Generate secure keys:**
   ```bash
   # Generate APP_KEY
   openssl rand -base64 32
   
   # Generate JWT_SECRET
   openssl rand -base64 64
   ```

### Step 4.2: Configure Console Settings

1. **Navigate to console configuration:**
   ```bash
   cd /opt/fleetbase/console
   ```

2. **Create production configuration:**
   ```bash
   nano fleetbase.config.json
   ```

3. **Add enhanced configuration:**
   ```json
   {
     "API_HOST": "https://api.yourdomain.com",
     "API_NAMESPACE": "int/v1",
     "API_SECURE": true,
     "SOCKETCLUSTER_HOST": "wss://api.yourdomain.com",
     "SOCKETCLUSTER_PORT": 38000,
     "SOCKETCLUSTER_SECURE": true,
     "PAYSTACK_PUBLIC_KEY": "pk_live_xxxxx",
     "DEFAULT_PAYMENT_GATEWAY": "stripe",
     "ENABLED_PAYMENT_GATEWAYS": ["stripe", "paystack"]
   }
   ```

### Step 4.3: Payment Gateway API Keys Setup

#### Stripe Configuration:
1. **Get Stripe Keys:**
   - Visit [Stripe Dashboard](https://dashboard.stripe.com/)
   - Navigate to Developers → API Keys
   - Copy Publishable Key and Secret Key
   - Set up webhooks and copy webhook secret

#### Paystack Configuration:
1. **Get Paystack Keys:**
   - Visit [Paystack Dashboard](https://dashboard.paystack.com/)
   - Navigate to Settings → API Keys & Webhooks
   - Copy Public Key and Secret Key
   - Set up webhooks and copy webhook secret

2. **Regional Considerations:**
   - **Nigeria (NGN):** Use Paystack as default
   - **Ghana (GHS):** Use Paystack as default
   - **South Africa (ZAR):** Use Paystack as default
   - **Kenya (KES):** Use Paystack as default
   - **US/EU/Other:** Use Stripe as default

## Part 5: Domain and SSL Setup

### Step 5.1: Configure DNS

1. **In your domain registrar or DNS provider:**
   - Create A record: `api.yourdomain.com` → Your Droplet IP
   - Create A record: `console.yourdomain.com` → Your Droplet IP

2. **Wait for DNS propagation** (5-30 minutes)

### Step 5.2: Install Nginx as Reverse Proxy

1. **Install Nginx:**
   ```bash
   apt-get install -y nginx
   ```

2. **Create API configuration:**
   ```bash
   nano /etc/nginx/sites-available/fleetbase-api
   ```

3. **Add enhanced configuration with webhook support:**
   ```nginx
   server {
       listen 80;
       server_name api.yourdomain.com;
       
       # Main API proxy
       location / {
           proxy_pass http://localhost:8000;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
       
       # WebSocket support
       location /socketcluster/ {
           proxy_pass http://localhost:38000/socketcluster/;
           proxy_http_version 1.1;
           proxy_set_header Upgrade $http_upgrade;
           proxy_set_header Connection "upgrade";
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
       }
       
       # Payment webhook endpoints
       location /int/v1/webhooks/stripe {
           proxy_pass http://localhost:8000/int/v1/webhooks/stripe;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
       
       location /int/v1/webhooks/paystack {
           proxy_pass http://localhost:8000/int/v1/webhooks/paystack;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

4. **Create Console configuration:**
   ```bash
   nano /etc/nginx/sites-available/fleetbase-console
   ```

5. **Add configuration:**
   ```nginx
   server {
       listen 80;
       server_name console.yourdomain.com;
       
       location / {
           proxy_pass http://localhost:4200;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

6. **Enable configurations:**
   ```bash
   ln -s /etc/nginx/sites-available/fleetbase-api /etc/nginx/sites-enabled/
   ln -s /etc/nginx/sites-available/fleetbase-console /etc/nginx/sites-enabled/
   nginx -t
   systemctl restart nginx
   ```

### Step 5.3: Install SSL with Let's Encrypt

1. **Install Certbot:**
   ```bash
   apt-get install -y certbot python3-certbot-nginx
   ```

2. **Obtain SSL certificates:**
   ```bash
   certbot --nginx -d api.yourdomain.com -d console.yourdomain.com
   ```

3. **Follow prompts:**
   - Enter email address
   - Agree to terms
   - Choose to redirect HTTP to HTTPS

4. **Set up auto-renewal:**
   ```bash
   systemctl enable certbot.timer
   systemctl start certbot.timer
   ```

## Part 6: Enhanced Installation with Payment Gateway Support

### Step 6.1: Install Payment Dependencies

```bash
cd /opt/fleetbase

# Install Paystack PHP SDK in the application container
docker exec -it fleetbase-application-1 bash
composer require yabacon/paystack-php
exit
```

### Step 6.2: Restart Services with New Configuration

```bash
cd /opt/fleetbase
docker compose down
docker compose up -d
```

### Step 6.3: Initialize Database with Payment Support

```bash
# Run standard migrations
docker exec -it fleetbase-application-1 bash
php artisan migrate --force

# Run payment gateway migrations
php artisan migrate --path=database/migrations --force

# Seed database
php artisan db:seed --force
exit
```

### Step 6.4: Create Admin User

```bash
docker exec -it fleetbase-application-1 bash
php artisan fleetbase:create-admin \
  --name="Admin User" \
  --email="admin@yourdomain.com" \
  --password="your_secure_password"
exit
```

## Part 7: Payment Gateway Configuration

### Step 7.1: Configure Webhook Endpoints

#### Stripe Webhooks:
1. **In Stripe Dashboard:**
   - Go to Developers → Webhooks
   - Add endpoint: `https://api.yourdomain.com/int/v1/webhooks/stripe`
   - Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `customer.subscription.*`, `invoice.*`

#### Paystack Webhooks:
1. **In Paystack Dashboard:**
   - Go to Settings → API Keys & Webhooks
   - Add webhook URL: `https://api.yourdomain.com/int/v1/webhooks/paystack`
   - Select events: `charge.success`, `charge.failed`, `subscription.*`, `invoice.*`

### Step 7.2: Test Payment Gateway Integration

```bash
# Test Stripe connection
curl -X GET https://api.yourdomain.com/int/v1/payments/test/stripe \
  -H "Authorization: Bearer YOUR_API_KEY"

# Test Paystack connection
curl -X GET https://api.yourdomain.com/int/v1/payments/test/paystack \
  -H "Authorization: Bearer YOUR_API_KEY"

# Get available gateways
curl -X GET https://api.yourdomain.com/int/v1/payments/gateways \
  -H "Authorization: Bearer YOUR_API_KEY"

# Test gateway recommendation
curl -X GET "https://api.yourdomain.com/int/v1/payments/recommended-gateway?country=NG" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

## Part 8: Testing the Enhanced Installation

### Step 8.1: Access the Applications

1. **Fleetbase Console:**
   - URL: `https://console.yourdomain.com`
   - Login with admin credentials created above

2. **API Documentation:**
   - URL: `https://api.yourdomain.com/docs`

### Step 8.2: Verify Payment Gateway Functionality

1. **Test Console Access:**
   - Login to the console
   - Navigate through the dashboard
   - Check payment gateway settings

2. **Test Payment Gateway Selection:**
   - Create a test transaction
   - Verify gateway selection based on region
   - Test both Stripe and Paystack flows

3. **Test API Endpoints:**
   ```bash
   # Initialize payment with Stripe
   curl -X POST https://api.yourdomain.com/int/v1/payments/initialize \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -d '{
       "email": "test@example.com",
       "amount": 100,
       "currency": "USD",
       "gateway": "stripe"
     }'

   # Initialize payment with Paystack
   curl -X POST https://api.yourdomain.com/int/v1/payments/initialize \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -d '{
       "email": "test@example.com",
       "amount": 10000,
       "currency": "NGN",
       "gateway": "paystack"
     }'
   ```

### Step 8.3: Regional Testing

1. **Test Nigerian Market (Paystack):**
   - Currency: NGN
   - Expected gateway: Paystack
   - Test local payment methods

2. **Test US Market (Stripe):**
   - Currency: USD
   - Expected gateway: Stripe
   - Test card payments

3. **Test Gateway Fallback:**
   - Disable one gateway
   - Verify fallback to available gateway

## Part 9: Production Optimization

### Step 9.1: Performance Tuning

1. **Configure Redis persistence:**
   ```bash
   docker exec -it fleetbase-redis-1 redis-cli
   CONFIG SET save "900 1 300 10 60 10000"
   CONFIG REWRITE
   exit
   ```

2. **Set up log rotation:**
   ```bash
   nano /etc/logrotate.d/fleetbase
   ```
   
   Add:
   ```
   /opt/fleetbase/logs/*.log {
       daily
       rotate 14
       compress
       missingok
       notifempty
   }
   ```

### Step 9.2: Enhanced Monitoring Setup

1. **Install monitoring tools:**
   ```bash
   apt-get install -y htop netdata
   ```

2. **Configure payment gateway monitoring:**
   - Set up alerts for failed payments
   - Monitor webhook delivery success rates
   - Track gateway performance metrics

### Step 9.3: Enhanced Backup Configuration

1. **Create enhanced backup script:**
   ```bash
   nano /opt/fleetbase/backup.sh
   ```

2. **Add backup logic with payment data:**
   ```bash
   #!/bin/bash
   BACKUP_DIR="/opt/backups"
   DATE=$(date +%Y%m%d_%H%M%S)
   
   mkdir -p $BACKUP_DIR
   
   # Backup database with payment data
   docker exec fleetbase-db-1 mysqldump -u root -p$MYSQL_ROOT_PASSWORD fleetbase > $BACKUP_DIR/db_$DATE.sql
   
   # Backup configuration including payment settings
   tar -czf $BACKUP_DIR/config_$DATE.tar.gz \
     /opt/fleetbase/*.yml \
     /opt/fleetbase/console/fleetbase.config.json \
     /opt/fleetbase/api/config/payment.php
   
   # Keep only last 7 days
   find $BACKUP_DIR -type f -mtime +7 -delete
   ```

3. **Schedule backups:**
   ```bash
   chmod +x /opt/fleetbase/backup.sh
   crontab -e
   ```
   
   Add:
   ```
   0 2 * * * /opt/fleetbase/backup.sh
   ```

## Part 10: Payment Gateway Specific Troubleshooting

### Common Payment Issues and Solutions

1. **Stripe Issues:**
   ```bash
   # Check Stripe configuration
   docker exec -it fleetbase-application-1 bash
   php artisan tinker
   >>> config('services.stripe')
   
   # Test Stripe API connection
   curl -X GET https://api.yourdomain.com/int/v1/payments/test/stripe
   ```

2. **Paystack Issues:**
   ```bash
   # Check Paystack configuration
   docker exec -it fleetbase-application-1 bash
   php artisan tinker
   >>> config('services.paystack')
   
   # Test Paystack API connection
   curl -X GET https://api.yourdomain.com/int/v1/payments/test/paystack
   ```

3. **Webhook Issues:**
   ```bash
   # Check webhook logs
   docker logs fleetbase-application-1 | grep webhook
   
   # Test webhook endpoints
   curl -X POST https://api.yourdomain.com/int/v1/webhooks/stripe \
     -H "Content-Type: application/json" \
     -d '{"test": true}'
   ```

4. **Gateway Selection Issues:**
   ```bash
   # Check gateway configuration
   curl -X GET https://api.yourdomain.com/int/v1/payments/gateways
   
   # Test regional recommendations
   curl -X GET "https://api.yourdomain.com/int/v1/payments/recommended-gateway?country=NG&currency=NGN"
   ```

## Part 11: Advanced Configuration

### Step 11.1: Custom Gateway Rules

You can customize gateway selection by editing the payment configuration:

```bash
docker exec -it fleetbase-application-1 bash
nano config/payment.php
```

Add custom rules:
```php
'regional_gateways' => [
    'NG' => 'paystack',
    'GH' => 'paystack',
    'ZA' => 'paystack',
    'KE' => 'paystack',
    'US' => 'stripe',
    'CA' => 'stripe',
    'GB' => 'stripe',
    // Add more countries as needed
],

'currency_gateways' => [
    'NGN' => 'paystack',
    'GHS' => 'paystack',
    'ZAR' => 'paystack',
    'KES' => 'paystack',
    'USD' => 'stripe',
    'EUR' => 'stripe',
    'GBP' => 'stripe',
    // Add more currencies as needed
],
```

### Step 11.2: Environment-Specific Configuration

For different environments (staging, production), create separate override files:

```bash
# Production
cp docker-compose.override.yml docker-compose.production.yml

# Staging
cp docker-compose.override.yml docker-compose.staging.yml
```

Deploy with specific environment:
```bash
docker compose -f docker-compose.yml -f docker-compose.production.yml up -d
```

## Part 12: Security Hardening for Payment Processing

### Step 12.1: Additional Security Measures

1. **Secure webhook endpoints:**
   ```bash
   # Add rate limiting to Nginx
   nano /etc/nginx/sites-available/fleetbase-api
   ```
   
   Add to webhook locations:
   ```nginx
   location /int/v1/webhooks/ {
       limit_req zone=webhook burst=10 nodelay;
       # ... existing proxy configuration
   }
   ```

2. **Set up fail2ban for payment endpoints:**
   ```bash
   apt-get install -y fail2ban
   nano /etc/fail2ban/jail.local
   ```
   
   Add:
   ```ini
   [fleetbase-payment]
   enabled = true
   port = http,https
   filter = fleetbase-payment
   logpath = /var/log/nginx/access.log
   maxretry = 5
   bantime = 3600
   ```

### Step 12.2: Payment Data Encryption

Ensure sensitive payment data is properly encrypted:

```bash
# Generate additional encryption keys for payment data
docker exec -it fleetbase-application-1 bash
php artisan key:generate --show
```

## Part 13: Scaling Considerations

### Step 13.1: Load Balancing for High Traffic

For high-traffic scenarios, consider:

1. **Multiple application instances**
2. **Database read replicas**
3. **Redis clustering**
4. **CDN for static assets**

### Step 13.2: Payment Gateway Redundancy

Configure multiple accounts per gateway for redundancy:

```yaml
# In docker-compose.override.yml
- STRIPE_SECRET_PRIMARY=sk_live_primary
- STRIPE_SECRET_SECONDARY=sk_live_secondary
- PAYSTACK_SECRET_PRIMARY=sk_live_primary
- PAYSTACK_SECRET_SECONDARY=sk_live_secondary
```

## Support Resources

- **Documentation:** https://docs.fleetbase.io
- **GitHub:** https://github.com/fleetbase/fleetbase
- **Discord Community:** Join for support
- **API Reference:** https://api.yourdomain.com/docs
- **Stripe Documentation:** https://stripe.com/docs
- **Paystack Documentation:** https://paystack.com/docs

## Final Notes

- Always test payment flows in sandbox/test mode before going live
- Keep regular backups of payment configuration and transaction data
- Monitor payment success rates and gateway performance
- Stay updated with Fleetbase releases and payment gateway API changes
- Document any custom modifications for future reference
- Ensure PCI compliance for handling payment data
- Regularly review and update webhook endpoints
- Test failover scenarios between payment gateways

**Congratulations!** Your Fleetbase installation with multi-gateway payment support is now complete and ready for global operations with region-appropriate payment methods.