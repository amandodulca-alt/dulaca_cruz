<?php

$pdo = new PDO(
    "mysql:host=127.0.0.1;charset=utf8",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("DROP DATABASE IF EXISTS company_dw;");
$pdo->exec("CREATE DATABASE company_dw;");
$pdo->exec("USE company_dw;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$pdo->exec("CREATE TABLE dim_date (
    date_key DATE NOT NULL PRIMARY KEY,
    year SMALLINT NOT NULL,
    quarter TINYINT NOT NULL,  /*1-4*/
    half_year TINYINT NOT NULL,  /*1-2*/
    month TINYINT NOT NULL,
    month_name VARCHAR(12) NOT NULL,
    quarter_label VARCHAR(10) NOT NULL,
    half_label VARCHAR(12) NOT NULL
);");

$pdo->exec("CREATE TABLE dim_product (
    product_key VARCHAR(15) NOT NULL PRIMARY KEY,
    product_name VARCHAR(70) NOT NULL,
    product_line VARCHAR(50) NOT NULL,
    quantity_in_stock INT NOT NULL,
    buy_price DECIMAL(10,2) NOT NULL,
    msrp DECIMAL(10,2) NOT NULL
);");

$pdo->exec("CREATE TABLE dim_customer (
    customer_key INT NOT NULL PRIMARY KEY,
    customer_name VARCHAR(50) NOT NULL,
    city VARCHAR(50) NOT NULL,
    country VARCHAR(50) NOT NULL,
    office_key VARCHAR(10) NOT NULL
);");

$pdo->exec("CREATE TABLE dim_office (
    office_key VARCHAR(10) NOT NULL PRIMARY KEY,
    city VARCHAR(50) NOT NULL,
    country VARCHAR(50) NOT NULL
);");

$pdo->exec("CREATE TABLE dim_order_details (
    order_number INT NOT NULL,
    customer_key INT NOT NULL,
    date_key DATE NOT NULL,
    product_key VARCHAR(15) NOT NULL,
    quantity_ordered INT NOT NULL,
    price_each DECIMAL(10,2) NOT NULL
);");

$pdo->exec("DROP TABLE IF EXISTS fact_stock_health;");
$pdo->exec("CREATE TABLE fact_stock_health (
    fact_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    date_key DATE NOT NULL,
    country VARCHAR(50) NOT NULL,
    product_key VARCHAR(15) NOT NULL,
    units_ordered INT NOT NULL DEFAULT 0
);");

$pdo->exec("CREATE TABLE fact_city_sales (
    fact_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    date_key DATE NOT NULL,
    customer_key INT NOT NULL,
    city VARCHAR(50) NOT NULL,   
    country VARCHAR(50) NOT NULL,   
    total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    order_count INT NOT NULL DEFAULT 0
);");

$pdo->exec("CREATE TABLE fact_productline_sales (
    fact_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    date_key DATE NOT NULL,
    country VARCHAR(50) NOT NULL,
    product_line VARCHAR(50) NOT NULL,
    total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    units_sold INT NOT NULL DEFAULT 0
);");

$pdo->exec("CREATE TABLE fact_product_sales (
    fact_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    date_key DATE NOT NULL,
    country VARCHAR(50) NOT NULL,
    product_key VARCHAR(15) NOT NULL,
    product_name VARCHAR(70) NOT NULL,   
    product_line VARCHAR(50) NOT NULL,   
    total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    units_sold INT NOT NULL DEFAULT 0
);");

$pdo->exec("CREATE TABLE fact_office_sales (
    fact_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    date_key DATE NOT NULL,
    office_key VARCHAR(10) NOT NULL,
    city VARCHAR(50) NOT NULL,   
    country VARCHAR(50) NOT NULL,   
    total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    order_count INT NOT NULL DEFAULT 0,
    customer_count INT NOT NULL DEFAULT 0
);");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "dw schema created successfully.\n";
?>