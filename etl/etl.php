<?php
$src = new PDO(
    "mysql:host=127.0.0.1;dbname=company_db;charset=utf8",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$dw = new PDO(
    "mysql:host=127.0.0.1;dbname=company_dw;charset=utf8",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function monthName(int $m): string
{
    return date('F', mktime(0, 0, 0, $m, 1));
}

// 1. Populate Date Dimension
$dates = $src->query("SELECT DISTINCT orderDate AS d FROM orders UNION SELECT DISTINCT paymentDate FROM payments")->fetchAll(PDO::FETCH_COLUMN);
$ins = $dw->prepare("INSERT IGNORE INTO dim_date (date_key, year, quarter, half_year, month, month_name, quarter_label, half_label) VALUES (?,?,?,?,?,?,?,?)");
foreach ($dates as $d) {
    $y = (int) date('Y', strtotime($d));
    $m = (int) date('n', strtotime($d));
    $q = (int) ceil($m / 3);
    $h = $m <= 6 ? 1 : 2;
    $ins->execute([$d, $y, $q, $h, $m, monthName($m), "Q$q $y", "H$h $y"]);
}

// 2. Populate Product Dimension
$dw->exec("TRUNCATE TABLE dim_product");
$rows = $src->query("SELECT productCode, productName, productLine, quantityInStock, buyPrice, MSRP FROM products")->fetchAll(PDO::FETCH_ASSOC);
$ins = $dw->prepare("INSERT INTO dim_product (product_key, product_name, product_line, quantity_in_stock, buy_price, msrp) VALUES (?,?,?,?,?,?)");
foreach ($rows as $r) {
    $ins->execute([$r['productCode'], $r['productName'], $r['productLine'], $r['quantityInStock'], $r['buyPrice'], $r['MSRP']]);
}

// 3. Populate Customer Dimension
$dw->exec("TRUNCATE TABLE dim_customer");
$rows = $src->query("SELECT c.customerNumber, c.customerName, c.city, c.country, e.officeCode FROM customers c LEFT JOIN employees e ON e.employeeNumber = c.salesRepEmployeeNumber")->fetchAll(PDO::FETCH_ASSOC);
$ins = $dw->prepare("INSERT INTO dim_customer (customer_key, customer_name, city, country, office_key) VALUES (?,?,?,?,?)");
foreach ($rows as $r) {
    $ins->execute([$r['customerNumber'], $r['customerName'], $r['city'], $r['country'], $r['officeCode']]);
}

// 4. Populate Office Dimension
$dw->exec("TRUNCATE TABLE dim_office");
$rows = $src->query("SELECT officeCode, city, country FROM offices")->fetchAll(PDO::FETCH_ASSOC);
$ins = $dw->prepare("INSERT INTO dim_office (office_key, city, country) VALUES (?,?,?)");
foreach ($rows as $r) {
    $ins->execute([$r['officeCode'], $r['city'], $r['country']]);
}

// 5. Populate Order Details Dimension
$dw->exec("TRUNCATE TABLE dim_order_details");
$rows = $src->query("SELECT o.orderNumber, o.customerNumber, o.orderDate, od.productCode, od.quantityOrdered, od.priceEach FROM orders o JOIN orderdetails od ON o.orderNumber = od.orderNumber")->fetchAll(PDO::FETCH_ASSOC);
$ins = $dw->prepare("INSERT INTO dim_order_details (order_number, customer_key, date_key, product_key, quantity_ordered, price_each) VALUES (?,?,?,?,?,?)");
foreach ($rows as $sr) {
    $ins->execute([$sr['orderNumber'], $sr['customerNumber'], $sr['orderDate'], $sr['productCode'], $sr['quantityOrdered'], $sr['priceEach']]);
}
unset($src);


// 1.Stock Health
$dw->exec("TRUNCATE TABLE fact_stock_health");
$dw->exec("INSERT INTO fact_stock_health (date_key, country, product_key, units_ordered)
    SELECT s.date_key, c.country, s.product_key, SUM(s.quantity_ordered)
    FROM dim_order_details s
    JOIN dim_customer c ON c.customer_key = s.customer_key
    GROUP BY s.date_key, c.country, s.product_key");

// 2. City Sales
$dw->exec("TRUNCATE TABLE fact_city_sales");
$dw->exec("INSERT INTO fact_city_sales (date_key, customer_key, city, country, total_revenue, order_count)
    SELECT s.date_key, s.customer_key, c.city, c.country, SUM(s.quantity_ordered * s.price_each), COUNT(DISTINCT s.order_number)
    FROM dim_order_details s
    JOIN dim_customer c ON c.customer_key = s.customer_key
    GROUP BY s.date_key, s.customer_key, c.city, c.country");

// 3. Productline Sales
$dw->exec("TRUNCATE TABLE fact_productline_sales");
$dw->exec("INSERT INTO fact_productline_sales (date_key, country, product_line, total_revenue, units_sold)
    SELECT s.date_key, c.country, p.product_line, SUM(s.quantity_ordered * s.price_each), SUM(s.quantity_ordered)
    FROM dim_order_details s
    JOIN dim_product p ON p.product_key = s.product_key
    JOIN dim_customer c ON c.customer_key = s.customer_key
    GROUP BY s.date_key, c.country, p.product_line");

// 4. Product Sales (including Country)
$dw->exec("TRUNCATE TABLE fact_product_sales");
$dw->exec("INSERT INTO fact_product_sales (date_key, country, product_key, product_name, product_line, total_revenue, units_sold)
    SELECT s.date_key, c.country, p.product_key, p.product_name, p.product_line, SUM(s.quantity_ordered * s.price_each), SUM(s.quantity_ordered)
    FROM dim_order_details s
    JOIN dim_product p ON p.product_key = s.product_key
    JOIN dim_customer c ON c.customer_key = s.customer_key
    GROUP BY s.date_key, c.country, p.product_key, p.product_name, p.product_line");

// 5. Office Sales
$dw->exec("TRUNCATE TABLE fact_office_sales");
$dw->exec("INSERT INTO fact_office_sales (date_key, office_key, city, country, total_revenue, order_count, customer_count)
    SELECT s.date_key, o.office_key, o.city, o.country, SUM(s.quantity_ordered * s.price_each), COUNT(DISTINCT s.order_number), COUNT(DISTINCT s.customer_key)
    FROM dim_order_details s
    JOIN dim_customer c ON c.customer_key = s.customer_key
    JOIN dim_office o ON o.office_key = c.office_key
    GROUP BY s.date_key, o.office_key, o.city, o.country");

echo "\nETL Complete.\n";
?>