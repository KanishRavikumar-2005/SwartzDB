

# SwartzDB
## Lightweight JSON-Based Database for Web Apps

This project provides a simple, file-based JSON database for small-scale web applications. It includes built-in encryption and various functions for database control, data access, and manipulation.

## ⚠ Important Notice

Before using this database, **change the default encryption key (`key`), Extra encryption key (`Ekey`) and initialization vector (`IV`)** in `config.php` to ensure security.

## 📌 Features

-   **Database Control Functions**: Create, Delete, Backup, Restore, Integrity check
-   **Data Access Functions**: Get, Put, Get_row, Add_row, Update_row, Remove_row
-   **Data Manipulation Functions**: Filter, Aggregate
-   **Utility Functions**: Getkeys, IDGEN, Encryption helpers

## 🚀 Basic Usage

### 1️⃣ Initializing the Database

Include `connect.php` in your project:

```php
require_once 'connect.php';
$sdb = new SwartzDB('<database_path>');
$sdb->create('users');

```
Default database_path, if left empty is a folder called `storage` in the SwartzDB folder.

### 2️⃣ Adding a Row

```php
$data = ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'];
$sdb->add_row('users', $data);

```

### 3️⃣ Fetching Data

```php
$user = $sdb->get_row('users', ['id' => 1, 'name' => 'John Doe']);
print_r($user);

```

### 4️⃣ Updating a Row

```php
$update = ['email' => 'newemail@example.com'];
$sdb->update_row('users', ['id' => 1, 'name' => 'John Doe'], $update);

```

### 5️⃣ Deleting a Row

```php
$sdb->remove_row('users', ['id' => 1, 'name' => 'John Doe']);

```

## 🔜 Future Documentation

A **detailed documentation** with examples and more details into Aggregate and Filter function, will be released soon.

## ⚠ Limitations

This is a **fun and learning project**, not meant for production or large-scale systems. **SwartzBD** is designed for **small-scale PHP websites** that need simple data storage and retrieval. If you require **high-performance, concurrent, or large-scale** database operations, consider using **SQL-based databases** or more robust **NoSQL solutions** like MongoDB.

## 🎗 Tribute

This project is dedicated to **Aaron Swartz**, a visionary in open access and digital freedom.
