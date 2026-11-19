# Blood Donation System

A PHP + MySQL clinical operations console built from the Figma prototype screens
(all original prototype images are kept in `design-prototype/`).

## Requirements
PHP 8.1+ (PDO MySQL extension), MySQL 5.7+/8 or MariaDB 10.4+, Apache/Nginx or XAMPP/Laragon/WAMP.

## Setup (XAMPP example)
1. Copy this folder to `htdocs/blood-donation-system`.
2. Start Apache + MySQL. Open phpMyAdmin → **Import** → choose `database.sql`
   (or run `mysql -u root -p < database.sql`). This creates the `blood_donation` database with sample data.
3. Edit `config.php` if your MySQL user/password differ (defaults: root / empty).
4. Visit `http://localhost/blood-donation-system/`.

**Seed accounts**
- User: `tantancasquejo@gmail.com`
- Administrator: `kleintaneoo@gmail.com`

## Screens → files
| Prototype screen | File |
|---|---|
| Log in / Sign in (register) | `login.php`, `register.php` |
| Forgot password → code → new password | `forgot-password.php`, `verify-code.php`, `reset-password.php` |
| Operations Dashboard | `dashboard.php` |
| Admin Console (Admin Ops only) | `admin.php` |
| Donor Database | `donors.php` |
| Appointments & Scheduling | `appointments.php` |
| Blood Inventory | `inventory.php` |
| Mobile Drives | `drives.php` |
| Staff & Roles | `staff.php` |
| Security & System Settings (+ Logout) | `settings.php`, `logout.php` |

Shared: `config.php` (DB, helpers), `includes/` (layout, auth), `assets/css/style.css`, `assets/js/app.js`.

## Notes
- **Reset codes / mail:** with `DEV_MODE = true` the 6-digit code is shown on screen so it works without a mail server.
  Set it to `false` in production and configure PHP `mail()` / SMTP.
- **Registration** creates a *Pending* account; an Admin Ops user approves it in Staff & Roles.
- Only **Admin Ops** users can invite/suspend staff and change settings.
- "Session Timeout" (15 min) is enforced when enabled. "Two-Factor" and "IP Whitelisting" are stored settings only —
  they are not enforced yet.
- Seed appointments are dated with the day you import the SQL, so "Today's Agenda" has data on import day.
- Mobile Drives map is a placeholder panel, as in the prototype.
