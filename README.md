# Personal Budget Manager & Expense Tracker — Phase 2

A modern, lightweight, and modular personal finance web application built with **Native PHP 8.x**, **MySQL / MariaDB (PDO)**, **Bootstrap 5**, a seamless **multi-environment architecture** (XAMPP, MAMP, Laragon, Docker), and **native 4-language support** (English, Spanish, French, and Portuguese).

---

## 🚀 Key Features & Phase 2 Enhancements

### 1. 🔄 Inter-Account & Card Transfers
- **Direct Transfers**: Move money between bank accounts, debit cards, credit cards, or cash wallets.
- **Atomic Transactions (ACID)**: Funds are debited from the source account and credited to the destination account in a single secure database transaction (`beginTransaction` / `commit`) with automatic rollback on errors.
- **Net-Neutral Equity**: Internal transfers update individual account balances without skewing your overall personal income and expense ledger.
- **Automatic Reversal**: Deleting a transfer automatically refunds the source account and deducts the amount from the destination account.
- **1-Click Swap**: Instant source/destination swap button for seamless adjustments.

### 2. 📅 Flexible Subscriptions: Monthly, Biweekly & Annual
- **Recurrence Frequencies**:
  - **Monthly**: Scheduled billing on a specific day of each month (e.g., the 5th).
  - **Biweekly (2x/month)**: Split payments with two custom days per month (e.g., 15th and 30th or payroll cycles).
  - **Annual (1x/year)**: Yearly commitments with month and day selectors (e.g., Amazon Prime, domain renewals, car insurance).
- **Automated Monthly Equivalent Projection**: Automatically weights biweekly ($\text{amount} \times 2$) and annual ($\text{amount} \div 12$) commitments to calculate projected card balances and monthly budget forecasts accurately.
- **Cycle-Aware Logging**: Apply recurring commitments to the current transaction cycle with automatic duplicate prevention.

### 3. 🔌 Universal Multi-Environment Support (XAMPP, MAMP, Laragon, Docker)
- **Smart Auto-Detection**: Connects out-of-the-box in sequential priority:
  1. Environment variables (`.env` or system environment).
  2. **XAMPP / Laragon**: `127.0.0.1:3306` (`root` / empty password).
  3. **MAMP**: `127.0.0.1:8889` and `127.0.0.1:3306` (`root` / `root`).
  4. **Docker**: Internal service network `db:3306`.
- **Automatic Database Provisioning**: If MySQL is running but the database `gestor_financiero` does not exist yet, the connection engine creates it automatically (`CREATE DATABASE IF NOT EXISTS`) and runs structural migrations dynamically.
- **Official Docker Setup**: Includes preconfigured `Dockerfile` and `docker-compose.yml` for instant startup.
- **Environment File Support**: Lightweight `.env.example` template for custom configurations without third-party dependencies.

### 4. 🌍 Full Multilingual Support (i18n)
- **4 Native Languages**:
  - 🇺🇸 **English** (`en`)
  - 🇪🇸 **Spanish** (`es`)
  - 🇫🇷 **French** (`fr`)
  - 🇵🇹 **Portuguese** (`pt`)
- **Clean URLs**: Change languages seamlessly from the sidebar dropdown without polluting the browser address bar with `?lang=` parameters. Active choice is persisted in PHP session.
- **Localized Date Formatting**: Dates format automatically according to regional conventions (`M d, Y` or `d/m/Y`).

### 5. 🎨 Modern Fintech UI & High-Contrast Controls
- **Segmented Radio Controls**: Solid pure color fills when selected (**Red** for expenses and withdrawals, **Green** for income and deposits, and **Blue** for transfers) in both Light and Dark themes.

---

## 💻 Quick Start Guide by Operating System

Select the setup instructions for your operating system:

```
├── 🪟 1. Windows
├── 🍎 2. macOS
├── 🐧 3. Linux
└── 🐳 4. Docker (Cross-Platform)
```

---

### 🪟 1. Windows Guide

#### Method A: 1-Click Fast Launcher (Recommended)
1. Ensure your MySQL service is running (e.g. green status in XAMPP or Laragon).
2. Double-click **`iniciar.bat`** in the project root.
3. The script detects your PHP runtime, starts the local server, and automatically opens your browser at:
   ```text
   http://localhost:8000
   ```

*(Optional: To import or reset the clean schema manually, double-click `importar_bd.bat`)*.

---

#### Method B: Running with XAMPP
1. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. Move the project folder to:
   ```text
   C:\xampp\htdocs\gestor-financiero
   ```
3. Open your browser and navigate to:
   ```text
   http://localhost/gestor-financiero/
   ```

---

#### Method C: Running with Laragon
1. Open **Laragon** and click **Start All**.
2. Place the project folder in:
   ```text
   C:\laragon\www\gestor-financiero
   ```
3. Access your app at:
   ```text
   http://gestor-financiero.test
   ```

---

### 🍎 2. macOS Guide

#### Method A: 1-Click Finder Launcher (Native Mac Style)
1. Ensure your MySQL service is active (via **MAMP**, **Homebrew**, or **XAMPP**).
2. In **Finder**, navigate to the project directory and double-click **`iniciar.command`**.
3. macOS Terminal will open, boot the development server, and launch your default browser at:
   ```text
   http://localhost:8000
   ```

*(Optional: To import or reset the database via Finder, double-click `importar_bd.command`)*.

---

#### Method B: Via macOS Terminal
1. Open **Terminal** and navigate to the project folder:
   ```bash
   cd "/path/to/multilingual-budget-tracker"
   ```
2. Start the application using the executable shell script:
   ```bash
   ./iniciar.sh
   ```
3. To import the database schema via terminal:
   ```bash
   ./importar_bd.sh
   ```

---

#### Method C: Running with MAMP for Mac
1. Open **MAMP** and click **Start Servers**.
2. MAMP defaults to port `8889` with credentials `root` / `root`. **The system detects this automatically without any manual configuration**.
3. Run `iniciar.command` or `./iniciar.sh`.

---

### 🐧 3. Linux Guide

1. Open your terminal in the project directory:
   ```bash
   cd /path/to/multilingual-budget-tracker
   ```
2. Ensure PHP (with `pdo_mysql`) and MySQL/MariaDB are installed:
   ```bash
   sudo apt update && sudo apt install php php-mysql mysql-server -y   # (Debian/Ubuntu)
   ```
3. Start the application:
   ```bash
   ./iniciar.sh
   ```
4. Open your browser at `http://localhost:8000`.

---

### 🐳 4. Docker Guide (Windows, macOS, or Linux)

If you prefer not to install PHP or MySQL directly on your machine:

1. Open your terminal in the project root directory.
2. Spin up the containers with a single command:
   ```bash
   docker compose up -d
   ```
3. The stack boots up automatically:
   - **Web Service**: Apache + PHP 8.2 on port `8000`.
   - **Database Service**: MariaDB 10.11 pre-provisioned with `gestor_financiero`.
4. Open your browser at:
   ```text
   http://localhost:8000
   ```
5. When finished, stop the containers with:
   ```bash
   docker compose down
   ```

---

## 📖 User Guide & Walkthrough

Follow this step-by-step walkthrough to get the most out of your budget tracker:

### Step 1: Set Up Your Accounts & Cards
1. Navigate to **Accounts & Cards** from the sidebar.
2. Click **+ New Account / Card** to create:
   - Bank Checking or Savings Accounts.
   - Credit Cards (Visa, Mastercard, Amex).
   - Debit Cards.
   - Cash Wallets.
3. Enter your current available balance. If greater than zero, an initial opening balance entry is automatically logged.
4. **Deposit or Withdraw Funds**: Use the quick **Deposit / Withdraw** buttons on any card to add or take out cash with custom notes.
5. **Transfer Between Accounts**: Click **Transfer Between Accounts** to move money seamlessly (e.g., paying a credit card bill from checking, or withdrawing ATM cash to your wallet).

### Step 2: Configure Subscriptions & Recurring Commitments
1. Go to **Subscriptions & Fixed Payments** from the navigation menu.
2. Click **+ New Fixed Payment / Subscription**.
3. Choose the recurrence frequency:
   - **Monthly**: Enter your billing day (e.g., day 5 of each month).
   - **Biweekly**: Enter primary and secondary days (e.g., days 15 and 30 for payroll or split rent).
   - **Annual**: Select the billing month and day (e.g., November 15 for Amazon Prime or domain renewals).
4. **Link to a Card or Account**: Select which payment method covers this subscription.
   - The app automatically calculates your card's **Projected Remaining Balance** (Current Balance minus active recurring commitments).
5. **1-Click Execution**: At the start of a cycle, click **Apply All Pending** to instantly record all due commitments into your transactions ledger and update account balances.

### Step 3: Record Daily Transactions
1. Click **+ New Transaction** in the topbar or sidebar.
2. Select your transaction type with high-contrast visual indicators:
   - **Expense** (illuminates in pure Red): Groceries, dining out, bills.
   - **Income** (illuminates in pure Green): Salary, freelance, dividends.
   - **Transfer** (illuminates in pure Blue): Move money directly between two of your accounts.
3. Enter the amount, date, category, and linked account.
4. *(Optional)* Toggle **"Also save as recurring subscription"** if you want future cycles to track this expense automatically.
5. Click **Save Transaction**. The ledger and linked account balances update immediately.

### Step 4: Monitor Dashboard & Cash Flow
1. Visit the **Dashboard** for an instant high-level overview:
   - **Total Income**: All verified inflows.
   - **Total Expenses**: All verified outflows.
   - **Net Balance**: Live cash flow with contextual **Surplus** (green) or **Deficit** (red) badges.
   - **Fixed Commitment Banner**: Real-time monthly equivalent cost of your active subscriptions.
2. **Reviewing & Managing the Ledger**:
   - Inspect transaction dates, categories, linked card tags, and transfer routes (`[Origin] ➔ [Destination]`).
   - Need to remove an entry? Click the delete button. The system **automatically reverses the financial impact** on any linked accounts.

### Step 5: Personalize Your Workspace
- **Multilingual Switcher**: Open the language dropdown in the sidebar to switch between **🇺🇸 English**, **🇪🇸 Español**, **🇫🇷 Français**, and **🇵🇹 Português** with clean URLs.
- **Theme Engine**: Toggle between **Dark Mode** and **Light Mode** instantly using the Sun/Moon button in the topbar.
- **Collapsible Sidebar**: Collapse the navigation bar for a distraction-free widescreen budgeting view.

---

## ⚙️ Custom Configuration (.env)

If your database server uses non-standard ports or custom credentials:

1. Copy the `.env.example` file to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Configure your credentials:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=gestor_financiero
   DB_USER=root
   DB_PASS=your_password
   ```

---

## 🔒 Security & Best Practices
- **100% Prepared Statements**: Structured queries via PDO with strictly typed error modes prevent SQL injection.
- **XSS Mitigation**: Contextual output escaping with `htmlspecialchars`.
- **Transaction Safety**: Atomic database transactions with rollback protection on transfers and record deletions.
- **Responsive Architecture**: Ultra-fluid interface with local storage persistence for Theme (Light/Dark) and sidebar collapsed states.

---
Crafted with ❤️ by JuanEGM. All rights reserved.
