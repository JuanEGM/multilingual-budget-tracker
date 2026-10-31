# Personal Budget Manager & Expense Tracker (Phase 1)

A clean, modular, and lightweight personal finance management web application built with **Native PHP 8.x**, **MySQL/MariaDB (PDO)**, **Bootstrap 5**, and full **Internationalization (i18n)** support.

---

## 🚀 Features in Phase 1

- **Financial Dashboard**:
  - Real-time summary cards: **Total Income**, **Total Expenses**, and **Net Balance** (with surplus/deficit status badges).
  - Responsive transaction ledger with color-coded badges (green for income, red for expenses).
- **Transaction Management**:
  - Register new income or expense with strict server-side validation.
  - Quick modal-confirmed deletion of records.
- **Bilingual Support (i18n)**:
  - English (`en`) as the default language.
  - Spanish (`es`) fully supported.
  - Instant language switcher in the navigation bar persisting across sessions.
- **Enterprise-Grade Security**:
  - 100% prepared statements via PHP Data Objects (PDO) to prevent SQL injection.
  - Contextual output escaping with `htmlspecialchars` against XSS.

---

## 📋 Requirements

- **PHP**: 8.0 or higher (with `pdo_mysql` and `mbstring` extensions enabled).
- **Database**: MySQL 5.7+ or MariaDB 10.3+.
- **Web Server**: Apache, Nginx, or the built-in PHP development server.

---

## 🛠️ Installation & Setup

### 1. Clone the Repository
```bash
git clone <your-repository-url> "Gestor Financiero"
cd "Gestor Financiero"
```

### 2. Configure the Database
1. Open your MySQL client (phpMyAdmin, MySQL Workbench, or terminal).
2. Import the provided [`schema.sql`](schema.sql) file:
   ```bash
   mysql -u root -p < schema.sql
   ```
   *This creates the `gestor_financiero` database, the `transacciones` table, and inserts starter sample data.*

### 3. Database Credentials
Open [`config/db.php`](config/db.php) and verify your connection parameters. By default, it connects to:
- **Host**: `127.0.0.1` (Port: `3306`)
- **Database**: `gestor_financiero`
- **User**: `root`
- **Password**: *(empty by default for XAMPP/WAMP/Laragon)*

You can also customize these credentials using environment variables:
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`.

---

## 💻 Running the Application

### Option A: Using the PHP Built-in Server (Fastest)
From the project root directory, run:
```bash
php -S localhost:8000
```
Then navigate to: **`http://localhost:8000`**

### Option B: Using XAMPP / WAMP / Laragon
Place the project folder inside your web server document root:
- XAMPP: `C:/xampp/htdocs/gestor-financiero`
- Laragon: `C:/laragon/www/gestor-financiero`

Then open: **`http://localhost/gestor-financiero/`**

---

## 🌐 Switching Languages

1. **Via UI**: Click the language dropdown button in the top navbar and choose either **🇺🇸 English** or **🇪🇸 Español**.
2. **Via URL Query Parameter**: Append `?lang=en` or `?lang=es` to any page:
   - English: `http://localhost:8000/index.php?lang=en`
   - Spanish: `http://localhost:8000/index.php?lang=es`

The active choice is automatically saved in `$_SESSION['lang']`.

---

## 📂 Project Structure

```text
├── config/
│   └── db.php            # Secure PDO database connection
├── helpers/
│   └── lang.php          # i18n logic, session handling & helper utilities
├── includes/
│   ├── header.php        # Reusable navbar & HTML head template
│   └── footer.php        # Reusable footer & delete confirmation modal
├── lang/
│   ├── en.php            # English translation dictionary (default)
│   └── es.php            # Spanish translation dictionary
├── .gitignore            # Git exclusion rules
├── agregar.php           # Form to register transactions with server-side validation
├── eliminar.php          # Controller to delete transactions safely
├── index.php             # Main dashboard (summary cards + transaction table)
├── README.md             # Documentation & getting started guide
└── schema.sql            # MySQL database schema and sample records
```

---

## 📄 License
Open source and available under the MIT License.
