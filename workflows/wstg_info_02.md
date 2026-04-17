---
description: Test WSTG-INFO-02 – Fingerprint Web Server for local Picodoc app
---

# Workflow: WSTG‑INFO‑02 – Fingerprint Web Server (Local Picodoc)

## 1️⃣ Preparation
1. **Start the local environment**
   - Ensure XAMPP Apache is running.
   - Verify the site is reachable, e.g. `http://localhost/Picodoc/`.
2. **Tools required**
   - **Burp Suite** (proxy only, no active scanner).
   - **Browser** (Chrome/Firefox) configured to use `127.0.0.1:8080`.
   - **cURL** (built‑in Windows) for quick header checks.
   - **PowerShell** (or CMD) for `netstat` / `tasklist` if needed.
   - **Optional**: **Wireshark** to capture raw packets for deeper analysis.
3. **Add target to Burp scope**
   - `Target → Scope → Add` → `http://localhost/Picodoc/`.
   - Keep **Passive scanner** enabled (it will highlight obvious tech hints).

## 2️⃣ Collect Server‑Side Information
### 2.1 HTTP Header Inspection (Burp / cURL)
- **Burp**: Open any page, go to **Proxy → HTTP history**, select a request and view **Response → Headers**.
- Look for headers such as:
  - `Server:` – reveals web‑server software and version.
  - `X-Powered-By:` – often shows PHP version or framework.
  - `X-AspNet-Version:` / `X-AspNetMvc-Version:` (unlikely for PHP).
- **cURL example** (run in PowerShell):
  ```powershell
  curl -I http://localhost/Picodoc/
  ```
  Capture the output.

### 2.2 Error Page Analysis
- Trigger a 404/500 error (e.g., request `http://localhost/Picodoc/nonexistent.php`).
- Observe the HTML of the error page – default Apache/PHP error pages contain version strings.
- Record any stack traces, file paths, or configuration details displayed.

### 2.3 Directory Listing & .htaccess
- Browse to a directory without an `index` file (e.g., `http://localhost/Picodoc/uploads/`).
- If directory listing is enabled, note the format – Apache shows `Index of /uploads/`.
- Review `.htaccess` (already open) for directives like `Options -Indexes` which affect listing.

### 2.4 PHP Configuration Exposure
- Access `phpinfo()` if available (often at `http://localhost/Picodoc/phpinfo.php`).
- If not present, create a temporary file **only for testing** (delete after):
  ```php
  <?php phpinfo(); ?>
  ```
  Save as `info.php` in the web root, request it, and capture the output.
- Note PHP version, loaded modules, `Loaded Configuration File`, and any exposed paths.

### 2.5 Server‑Side Technology Detection (Passive Scanner)
- In Burp, open **Dashboard → Issues → Info → Technology Fingerprint**.
- The passive scanner may automatically list:
  - Web server (Apache/NGINX)
  - Language (PHP) and version.
  - Frameworks (e.g., Laravel, CodeIgniter) if detectable.

## 3️⃣ Corroborate with Local Configuration Files
- Open `httpd.conf` (XAMPP) to confirm the `ServerTokens` and `ServerSignature` settings – they control how much info Apache reveals.
- Review `php.ini` for `expose_php = On` (default) – this adds `X-Powered-By: PHP/...` header.
- Check `Picodoc/.htaccess` for custom headers (`Header set X-...`).

## 4️⃣ Document Findings
Create a markdown report (`wstg_info_02_report.md`) with the following sections:
1. **Target URL**
2. **Observed Server Header** – value and inferred software/version.
3. **X‑Powered‑By Header** – PHP version.
4. **Error Page Details** – any version strings or file paths.
5. **Directory Listing** – whether enabled and sample output.
6. **phpinfo() Output** – PHP version, modules, configuration file path.
7. **Passive Scanner Findings** – technology fingerprint summary.
8. **Recommendations**
   - Hide or modify `Server` header (`ServerTokens Prod`, `ServerSignature Off`).
   - Set `expose_php = Off` in `php.ini`.
   - Disable directory listing (`Options -Indexes`).
   - Remove or protect any publicly accessible `phpinfo.php`.
   - Ensure error pages are custom and do not leak stack traces.

## 5️⃣ Clean‑up (if you created temporary files)
- Delete `info.php` (or any test scripts) from the web root.
- Restart Apache to apply any configuration changes made during testing.

---

**Important:** This workflow respects the “no full‑system scan” rule – it relies solely on **manual header inspection, controlled requests, and passive scanning**. No aggressive scanning tools (e.g., Nmap, Burp Scanner) are used.
