# Personal Budget Manager & Expense Tracker (Phase 1+)

A modern, modular, and lightweight personal finance management web application built with **Native PHP 8.x**, **MySQL / MariaDB (PDO)**, **Bootstrap 5**, and full **Bilingual (i18n)** support.

---

## 🚀 Key Features

- **Financial Dashboard**:
  - Real-time summary cards: **Total Income**, **Total Expenses**, and **Net Balance** (with contextual *Surplus / Deficit* badges).
  - Responsive transaction ledger with color-coded badges (green for income, red for expenses) and recurring payment indicators.
- **Fixed Monthly Payments & Subscriptions**:
  - Dedicated manager for monthly recurring expenses and income (e.g. Netflix, Spotify, Gym, Rent, Utilities, Fixed Salary).
  - Monthly budget projection (Fixed Income vs Fixed Expenses).
  - 1-click execution: apply individual or all pending monthly subscriptions to the current month's transactions ledger.
  - Quick toggle to pause or activate subscriptions anytime.
- **Any Transaction Type**:
  - Support for any one-time or recurring transaction with strict server-side validation.
- **In-App Language Switching (Clean URLs)**:
  - English (`en`) as the default language.
  - Spanish (`es`) fully supported.
  - Change language directly in the app using the navbar switcher **without any `?lang=` parameters in your URL**. Active choice is persisted in PHP session.
- **Security First**:
  - 100% prepared statements via PHP Data Objects (PDO) to prevent SQL injection.
  - Contextual output escaping with `htmlspecialchars` against XSS.

---

## 💻 How to Run Locally with XAMPP and phpMyAdmin

### Step 1: Install and Start XAMPP
1. Download and install **XAMPP** (with PHP 8.x) from [apachefriends.org](https://www.apachefriends.org/).
2. Open the **XAMPP Control Panel**.
3. Click **Start** on both **Apache** and **MySQL**. Both should turn green.

---

### Step 2: Place the Project in `htdocs`
Copy or move the project folder into your XAMPP web root directory:
```text
C:\xampp\htdocs\gestor-financiero
```
*(Make sure the project files like `index.php`, `schema.sql`, etc. are directly inside `C:\xampp\htdocs\gestor-financiero`)*

---

### Step 3: Create and Import the Database via phpMyAdmin
1. Open your browser and navigate to:
   ```text
   http://localhost/phpmyadmin
   ```
2. In the top navigation bar, click on **Import** (or click **New** on the left sidebar, name the database `gestor_financiero`, and then go to Import).
3. Click **Choose File** (Seleccionar archivo) and select [`schema.sql`](schema.sql) from:
   ```text
   C:\xampp\htdocs\gestor-financiero\schema.sql
   ```
4. Scroll to the bottom and click **Import** (or **Go / Continuar**).
5. phpMyAdmin will execute the script and create:
   - Database: `gestor_financiero`
   - Tables: `transacciones` and `transacciones_fijas`
   - Initial sample transactions and subscriptions.

---

### Step 4: Verify Database Credentials
Open [`config/db.php`](config/db.php). The default settings are preconfigured for standard XAMPP out-of-the-box:
- **Host**: `127.0.0.1` (Port: `3306`)
- **Database Name**: `gestor_financiero`
- **User**: `root`
- **Password**: `""` *(empty string by default in XAMPP)*

---

### Step 5: Open the Application in Your Browser
Simply open your browser and navigate to:
```text
http://localhost/gestor-financiero/
```
You will immediately see the Financial Dashboard with live balance cards, subscriptions, and transaction ledger!

---

## ⚡ Alternative: Running with PHP Built-in Server
If you already have PHP and MySQL running, you can run it directly from the project folder:
```bash
php -S localhost:8000
```
Then visit: **`http://localhost:8000`**

---

## 🌐 In-App Language Switching
Click on the **Language** dropdown in the top navbar and choose either **🇺🇸 English** or **🇪🇸 Español**.
The application updates the session and reloads cleanly **without modifying the URL route**.

---

## 📂 Project Structure

```text
├── config/
│   └── db.php            # Secure PDO database connection
├── helpers/
│   └── lang.php          # i18n logic, session handling & helper utilities
├── includes/
│   ├── header.php        # Reusable navbar, styling & in-app language switcher
│   └── footer.php        # Reusable footer, modal & AJAX handlers
├── lang/
│   ├── en.php            # English dictionary (default)
│   └── es.php            # Spanish dictionary
├── .gitignore            # Git exclusion rules
├── agregar.php           # Form to register transactions (one-time or recurring)
├── cambiar_idioma.php    # Clean in-app language switcher controller (POST/AJAX)
├── eliminar.php          # Controller to delete transactions safely
├── fijas.php             # Subscriptions & monthly fixed payments manager
├── index.php             # Main dashboard (summary cards & ledger)
├── README.md             # Documentation & getting started guide
└── schema.sql            # MySQL schema with tables & sample data
```

---

## 📄 License
Open source and available under the MIT License.
