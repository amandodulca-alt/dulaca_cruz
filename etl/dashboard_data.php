<?php

if (isset($_GET['sync'])) {
    require 'etl.php'; 
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=company_dw;charset=utf8",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

$dateFromRaw = $_GET['date_from'] ?? null;
$dateToRaw = $_GET['date_to'] ?? null;
$country = $_GET['country'] ?? 'all';

$dateWhere = "";
$dateParams = [];
if ($dateFromRaw && $dateToRaw) {
    $dateWhere = " AND date_key BETWEEN :date_from AND :date_to ";
    $dateParams = [':date_from' => $dateFromRaw, ':date_to' => $dateToRaw];
}

$countryWhere = "";
$countryParams = [];
if ($country && $country !== 'all') {
    $countryWhere = " AND country = :country ";
    $countryParams = [':country' => $country];
}

$combinedParams = array_merge($dateParams, $countryParams);

$result = [
    'stock_health' => [],
    'city_sales' => [],
    'productline_sales' => [],
    'product_sales' => [],
    'office_sales' => [],
    'meta' => ['countries' => [], 'date_min' => '', 'date_max' => '']
];

function query($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 1. Stock Health
$sql = "SELECT 
            p.product_key, 
            p.product_name, 
            p.product_line,
            p.quantity_in_stock, 
            p.buy_price, 
            p.msrp,
            COALESCE(f.total_ordered, 0) AS total_ordered,
            (p.quantity_in_stock - COALESCE(f.total_ordered, 0)) AS remaining_stock,
            (p.quantity_in_stock - COALESCE(f.total_ordered, 0)) * p.buy_price AS remaining_value,
            CASE
                WHEN (p.quantity_in_stock - COALESCE(f.total_ordered, 0)) <= 0 THEN 'Out of Stock'
                WHEN (p.quantity_in_stock - COALESCE(f.total_ordered, 0)) / p.quantity_in_stock < 0.20 THEN 'Low Stock'
                WHEN (p.quantity_in_stock - COALESCE(f.total_ordered, 0)) / p.quantity_in_stock < 0.50 THEN 'Watch out'
                ELSE 'Healthy'
            END AS `status`
        FROM dim_product p
        LEFT JOIN (
            SELECT product_key, SUM(units_ordered) AS total_ordered
            FROM fact_stock_health
            WHERE 1=1 $dateWhere $countryWhere
            GROUP BY product_key
        ) f ON p.product_key = f.product_key
        ORDER BY remaining_stock DESC";
$result['stock_health'] = query($pdo, $sql, $combinedParams);

// 2. City Sales
$sql = "SELECT city, country, 
            SUM(total_revenue) AS revenue,
            SUM(order_count) AS orders
        FROM fact_city_sales
        WHERE 1=1 $dateWhere $countryWhere
        GROUP BY city, country
        ORDER BY revenue DESC 
        LIMIT 15";
$result['city_sales'] = query($pdo, $sql, $combinedParams);

// 3. Productline Sales
$sql = "SELECT product_line, 
            SUM(total_revenue) AS revenue,
            SUM(units_sold) AS units
        FROM fact_productline_sales
        WHERE 1=1 $dateWhere $countryWhere
        GROUP BY product_line
        ORDER BY revenue DESC";
$result['productline_sales'] = query($pdo, $sql, $combinedParams);

// 4. Product Sales
$sql = "SELECT product_name, product_line, 
            SUM(total_revenue) AS revenue,
            SUM(units_sold) AS units
        FROM fact_product_sales
        WHERE 1=1 $dateWhere $countryWhere
        GROUP BY product_name, product_line
        ORDER BY revenue DESC
        LIMIT 8";
$result['product_sales'] = query($pdo, $sql, $combinedParams);

// 5. Office Sales
$sql = "SELECT office_key, city, country,
            SUM(total_revenue) AS revenue,
            SUM(order_count) AS orders,
            SUM(customer_count) AS customers
        FROM fact_office_sales
        WHERE 1=1 $dateWhere $countryWhere
        GROUP BY office_key, city, country
        ORDER BY revenue DESC";
$result['office_sales'] = query($pdo, $sql, $combinedParams);

// Meta System Context
$result['meta']['countries'] = array_column(
    query($pdo, "SELECT DISTINCT country FROM dim_customer ORDER BY country"),
    'country'
);

$minMax = query($pdo, "SELECT MIN(date_key) AS min_d, MAX(date_key) AS max_d FROM dim_order_details");
$result['meta']['date_min'] = $minMax[0]['min_d'] ?? "";
$result['meta']['date_max'] = $minMax[0]['max_d'] ?? "";

echo json_encode($result);
?>