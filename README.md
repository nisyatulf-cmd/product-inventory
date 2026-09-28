# Product Inventory System

Aplikasi manajemen inventaris produk berbasis web menggunakan **PHP Native** dan **MySQL (PDO)**. Dilengkapi dengan fitur CRUD lengkap, validasi data di server-side, serta keamanan web (Anti SQL Injection, XSS, dan CSRF).

## 🌟 Fitur Utama
* **CRUD Functionality**: Tambah, tampilkan, perbarui, dan hapus data produk.
* **Security Layer**:
  * PDO Prepared Statements (Mencegah SQL Injection).
  * Sanitasi Output `htmlspecialchars` (Mencegah XSS).
  * Random Session Token Verification (Mencegah CSRF).
  * PRG Pattern (Mencegah resubmission data saat refresh).
* **Responsive Layout**: Antarmuka modern bertema Dark Teal.

## 🗄️ Struktur Database
* **Database**: `db_product_inventory`
* **Tabel**: `items`

```sql
CREATE DATABASE IF NOT EXISTS db_product_inventory;
USE db_product_inventory;

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);