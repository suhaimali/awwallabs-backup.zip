# SUHAIM SOFT - Advanced Laboratory Management System

SUHAIM SOFT is a comprehensive, highly secure, and modern Laboratory Information Management System (LIMS) built on the powerful **Laravel 12** framework. It is meticulously designed to streamline the management of clinical patients, laboratory tests, dynamic reference intervals, billing, and highly specialized diagnostic reporting.

## 🚀 Key Features

* **Robust Patient Management:** A complete, secure patient registry with live search and rapid AJAX-based CRUD operations.
* **Laboratory Tests & Billing:** Dynamic pricing, detailed test configuration, discount application, and comprehensive payment tracking.
* **Advanced Clinical Setup:** Highly specialized biological reference intervals based on age, gender, and clinical logic (e.g., standard min-max boundaries vs. immunoassay textual results).
* **Automated PDF Invoicing:** Client-side, instant PDF invoice generation powered by `jsPDF` and `AutoTable`, featuring beautifully aligned, professional layouts and dynamic calculations.
* **Server-Side PDF Partials:** Clean, scoped Blade templates (`report.blade.php`, `invoice.blade.php`) designed without HTML boilerplate for flexible integration into modals, layouts, and direct PDF generation.
* **Diagnostic Test Reports:** Generate, manage, and securely print clinical reports with custom dynamic templates, flag templates, and authorized doctor signatures.
* **Optimized Workflow UI:** Fast, inline "Edit" buttons directly linked to drop-down fields across reports.
* **Master Data Configuration:** Manage clinical units of measurement, reference text templates, and flag templates from a centralized UI.
* **Progressive Web App (PWA):** Fully PWA-ready with service workers, manifest configuration, and installability on mobile/desktop devices.

### Visit Workflow & QR Verification
* **One-flow visits (`/visits/new`):** Find a returning patient or register a new one → pick tests or a package (with discount, amount received and payment mode) → optional vital signs → confirm. One bill is created for the visit, and the next screen opens **Generate Report** pre-filled with the patient, referring doctor and every pending test, so staff only type results. Saving the report opens it straight away with its QR code.
* **Work queue:** The dashboard lists patients still waiting for results (oldest first), and the Patients list shows an *Enter results* shortcut for anyone with pending tests.
* **QR codes:** Every printed lab report carries a QR code that opens `/r/{token}`, a mobile-friendly copy of that report. Every invoice carries one that opens `/p/{token}`, which lists the patient's reports, booked tests and bills. Tokens are random 40-character strings, so the pages need no login but cannot be guessed. They are rate-limited and marked `noindex`.
* **Consistent billing:** Each booked test is linked to the bill it was charged on. Editing a patient or a booking only recalculates that bill, and the patient's totals are always the sum of their tests.

### Recent Updates
* **Database Optimization:** Removed all traces of SQLite (`database.sqlite`, fallback connections) to enforce a strict, 100% MySQL architecture.
* **UI Enhancements:** Improved styling and color schemes for edit icons in the Patient Custom Test selection workflow.
* **Queue Cleanup:** Removed unused default queue tables (`jobs`, `job_batches`, `failed_jobs`) and set `QUEUE_CONNECTION=sync` for immediate synchronous processing.

## 🛡️ Enterprise-Grade Security & Architecture

* **100% SQL Driven:** All application state is securely stored in MySQL using Eloquent ORM.
* **Modular Blade Layouts:** Organized Laravel Blade architecture with reusable partials.
* **Zero-Tracking Policies:** Removal of unnecessary tracking scripts and local-only data handling.
* **Security Protocols:** Authenticated routes, CSRF protection, secure sessions, and browser storage cleanup.

## 💻 Tech Stack

* **Backend:** Laravel 12 (PHP 8.2+)
* **Database:** MySQL
* **Frontend Engine:** Laravel Blade
* **Frontend:** HTML5, CSS3, JavaScript (jQuery + AJAX), Bootstrap 5
* **Plugins:** DataTables, Select2, SweetAlert2, jsPDF, Moment.js
* **Design:** Responsive UI with FontAwesome 6 icons.

## 📂 Folder Structure

Below is an overview of the key directories and files in this project:

```text
awwal-lab/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── AdminController.php
│   │       ├── AuthController.php
│   │       ├── CategoryController.php
│   │       └── HomeController.php
│   ├── Models/
│   │   ├── Appointment.php
│   │   ├── Category.php
│   │   ├── Doctor.php
│   │   ├── FlagTemplate.php
│   │   ├── LabTest.php
│   │   ├── Patient.php
│   │   ├── Payment.php
│   │   ├── Product.php
│   │   ├── ReferenceInterval.php
│   │   ├── ReportTemplate.php
│   │   ├── TestParameter.php
│   │   └── ... (Other Eloquent models)
│   └── Providers/
├── bootstrap/
│   └── cache/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── CsvDataSeeder.php
│       └── DatabaseSeeder.php
├── public/
│   ├── css/
│   ├── js/
│   └── images/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
│       ├── auth/
│       ├── inc/
│       │   ├── footer.blade.php
│       │   ├── header.blade.php
│       │   ├── sidebar.blade.php
│       │   └── ...
│       ├── layouts/
│       │   ├── app.blade.php
│       │   └── auth.blade.php
│       ├── patients.blade.php
│       ├── reports.blade.php
│       ├── templates.blade.php
│       ├── tests.blade.php
│       └── ... (Other blade views)
├── routes/
│   ├── console.php
│   └── web.php
├── .env.example
├── composer.json
├── package.json
└── vite.config.js
```

## ⚙️ Installation

### 1. Clone the Repository

```bash
git clone https://github.com/suhaimali/awwal-lab.git
cd awwal-lab
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Configure Environment

Copy the environment file and generate the application key:

```bash
cp .env.example .env
php artisan key:generate
```

Edit the `.env` file and configure your MySQL database credentials.

### 4. Run Database Migrations

```bash
php artisan migrate
```

### 5. Build Frontend Assets (Optional for Development)

```bash
npm run dev
```

### 6. Start the Application

```bash
php artisan serve
```

Open your browser and visit:

```
http://127.0.0.1:8000
```

## 🚢 Production Deployment (Docker)

1. `cp .env.example .env` and set at least:
   `APP_KEY` (`php artisan key:generate --show`), `APP_URL` (the public https URL, because QR codes link to it),
   `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `INCOME_REPORT_PASSWORD`, `ADMIN_PASSWORD` and the `LAB_*` details.
2. `docker compose up -d --build`. The entrypoint waits for MySQL, runs migrations and caches config, routes and views.
3. First boot only: set `RUN_SEEDERS=true` to create the admin user and load the test catalogue, then set it back to `false`.
4. Put the app behind HTTPS. HSTS is sent automatically on secure requests.

> `APP_URL` must be the address patients can reach. The QR codes printed on reports and invoices are built from it.

## ✅ Running the Tests

The feature tests need a MySQL database named `awwal_lab_test` (see `phpunit.xml`):

```bash
mysql -e "CREATE DATABASE awwal_lab_test"
php artisan test
```

They cover every page, billing consistency, the visit → report flow, the QR pages, signature PINs, purchases and backup-path safety.

## 🔧 Troubleshooting

### Missing `bootstrap/cache` Directory

If you encounter errors related to `bootstrap/cache`, run the following commands:

```powershell
dir bootstrap
```

If the `cache` folder does not exist, create it:

```powershell
mkdir bootstrap\cache
```

Verify that the folder now exists:

```powershell
dir bootstrap
```

You should see a structure similar to:

```text
bootstrap
│
├── app.php
├── providers.php
└── cache
```

Then clear and rebuild the Laravel caches:

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan serve
```

If `mkdir bootstrap\cache` reports that the folder already exists or another error occurs, check the directory contents:

```powershell
dir bootstrap
dir bootstrap\cache
```

> **Note:** This issue can occur after a Git clone, merge, or rebase because Git does not track empty directories. Creating the `bootstrap/cache` folder resolves the problem.

## 🔒 Security Maintenance

To check and automatically fix known npm package vulnerabilities:

```bash
npm audit
npm audit fix
```

If needed, update development dependencies:

```bash
npm install concurrently@latest --save-dev
npm audit fix
```

## 📤 Updating and Pushing Changes

```bash
git add .
git commit -m "Update project files"
git push origin main
```

## 🗄️ Database Seeding

To execute the seeders and import data into your database, run one of the following commands:

* **Option 1: Run only the CSV seeder** (Import only CSV data without affecting other records):
  ```bash
  php artisan db:seed --class=CsvDataSeeder
  ```
* **Option 2: Run all seeders** (Run all registered seeders):
  ```bash
  php artisan db:seed
  ```
* **Option 3: Reset and re-seed** (Completely refresh migrations and run all seeders - *use with caution in development*):
  ```bash
  php artisan migrate:fresh --seed
  ```

## 📜 License

This project is proprietary. Unauthorized copying, distribution, or modification of this project, via any medium, is strictly prohibited without prior written permission.