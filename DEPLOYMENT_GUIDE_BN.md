# Automenta - Live Deployment Guide (Bangla)

## প্রস্তুতি

### প্রয়োজনীয় জিনিস:
- PHP 8.1+ সহ Web Hosting
- MySQL/MariaDB Database
- HTTPS/SSL Certificate
- Domain Name
- SSLCOMMERZ Merchant Account (Payment এর জন্য)

---

## ধাপ ১: Hosting Setup

1. **Hosting নিন** - যেকোনো reliable provider (Hostinger, Bluehost, DreamHost, Local Provider ইত্যাদি)
2. **Domain যুক্ত করুন** এবং **SSL Certificate activate করুন** (বেশিরভাগ hosting-এ free Let's Encrypt পাবেন)
3. **File Manager/FTP** access নিন

---

## ধাপ ২: Database তৈরি

1. Hosting panel (cPanel/Plesk) খুলুন
2. **MySQL Databases** section-এ যান
3. নতুন database তৈরি করুন:
   - Database Name: `automenta_db`
   - User: `automenta_user`
   - Password: **একটি শক্তিশালী password তৈরি করুন** (32+ characters)
4. User-কে **ALL PRIVILEGES** দিন

---

## ধাপ ৩: Files Upload করুন

1. GitHub থেকে সব files download করুন (ZIP download করুন)
2. Hosting panel খুলুন → File Manager
3. `public_html` folder খুলুন
4. ZIP extract করুন অথবা সব files upload করুন

---

## ধাপ ৪: Config করুন

1. File Manager-এ `config.php` খুলুন (Edit করুন)
2. আপনার database details fill করুন:

```php
define('DB_HOST', 'localhost');              // সাধারণত localhost
define('DB_NAME', 'automenta_db');           // আপনার database name
define('DB_USER', 'automenta_user');         // আপনার database user
define('DB_PASS', 'YOUR_STRONG_PASSWORD');   // আপনার password
define('SITE_URL', 'https://yourdomain.com'); // আপনার সাইট URL (HTTPS required)
```

3. Save করুন

---

## ধাপ ৫: Installation চালান

1. Browser খুলুন এবং যান: `https://yourdomain.com/install.php`
2. Admin email এবং password enter করুন (10+ characters)
3. "Create Admin & Install" ক্লিক করুন
4. সফল হলে: `install.php` **DELETE করুন**

---

## ধাপ ৬: Admin Panel Setup

1. `https://yourdomain.com/admin.php` খুলুন
2. Admin email/password দিয়ে login করুন
3. **Site & Payment Settings** section-এ যান
4. Fill করুন:
   - **Site URL**: `https://yourdomain.com` (HTTPS required)
   - **Site Name**: `Automenta` (বা আপনার নাম)
   - **Contact Email**: আপনার email
   - **Payment Currency**: BDT/USD/EUR etc.
   - **SSLCOMMERZ Store ID**: আপনার merchant ID
   - **SSLCOMMERZ Store Password**: আপনার merchant password
   - **Mode**: Sandbox (test) অথবা Live
5. Save করুন

---

## ধাপ ৭: SSLCOMMERZ Setup

1. SSLCOMMERZ merchant account open করুন
2. Sandbox account এ test করুন (Live তকে)
3. আপনার **Store ID** এবং **Store Password** copy করুন
4. Admin panel-এ paste করুন
5. **Webhook/IPN URL** set করুন:
   ```
   https://yourdomain.com/payment_ipn.php
   ```

---

## ধাপ ৮: Test করুন

1. Site খুলুন: `https://yourdomain.com`
2. Articles, Workflows add করুন (Admin panel থেকে)
3. Premium Workflow-এ price set করুন
4. Checkout test করুন (SSLCOMMERZ sandbox credentials)
5. Payment test করুন

---

## ধাপ ৯: Live Mode Enable করুন

১. SSLCOMMERZ merchant account approve পেলে
2. Live credentials copy করুন
3. Admin panel-এ paste করুন
4. Mode: **Live** select করুন
5. Test করুন production payment

---

## Security Checklist

- ✅ `install.php` delete করা হয়েছে
- ✅ `config.php` secure রাখা হয়েছে (FTP শুধু আপনার)
- ✅ Strong admin password (10+ characters)
- ✅ HTTPS enabled
- ✅ Database backup নেওয়া (regular)
- ✅ `private_files` folder 0700 permission (private)
- ✅ SSLCOMMERZ password কখনো public share করবেন না

---

## Troubleshooting

### "Database not configured" error
- ✓ `config.php`-এর database details check করুন
- ✓ Database user-এর ALL PRIVILEGES আছে কিনা check করুন
- ✓ Database host (localhost/IP) correct কিনা check করুন

### Payment gateway error
- ✓ SSLCOMMERZ Store ID/Password correct?
- ✓ Site URL HTTPS সহ set করা?
- ✓ IPN URL correct?
- ✓ Sandbox এ test করছেন?

### Articles/Products save না হওয়া
- ✓ Database connected?
- ✓ Admin logged in?
- ✓ CSRF token valid?

---

## Support

যেকোনো সমস্যা হলে:
1. Hosting provider-এর support contact করুন
2. Database credentials verify করুন
3. Logs check করুন (Hosting panel)
4. SSLCOMMERZ support contact করুন

---

**সাইট লাইভ! 🎉**