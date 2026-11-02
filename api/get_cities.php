<?php
require_once __DIR__ . '/../config/init.php';

try {
    // 1. Yaklaşan aktif kalkış sefer sayıları
    $departure_stmt = $pdo->query("
        SELECT departure_city as city, COUNT(*) as count 
        FROM Trips 
        WHERE departure_time > datetime('now', 'localtime')
        GROUP BY departure_city
    ");
    $departure_data = $departure_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Yaklaşan aktif varış sefer sayıları
    $arrival_stmt = $pdo->query("
        SELECT destination_city as city, COUNT(*) as count 
        FROM Trips 
        WHERE departure_time > datetime('now', 'localtime')
        GROUP BY destination_city
    ");
    $arrival_data = $arrival_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $all_cities = array_unique(array_merge(array_keys($departure_data), array_keys($arrival_data)));

    // Fallback: Eğer yaklaşan sefer yoksa tablodaki tüm geçmiş/gelecek şehirleri al
    if (empty($all_cities)) {
        $dep_fallback = $pdo->query("SELECT DISTINCT departure_city FROM Trips")->fetchAll(PDO::FETCH_COLUMN);
        $arr_fallback = $pdo->query("SELECT DISTINCT destination_city FROM Trips")->fetchAll(PDO::FETCH_COLUMN);
        $all_cities = array_unique(array_merge($dep_fallback, $arr_fallback));
    }

    sort($all_cities, SORT_LOCALE_STRING);

    $popular_defaults = ['İstanbul', 'Ankara', 'İzmir', 'Antalya', 'Bursa', 'Eskişehir', 'Trabzon'];
    $city_objects = [];

    foreach ($all_cities as $city_name) {
        $dep_count = (int)($departure_data[$city_name] ?? 0);
        $arr_count = (int)($arrival_data[$city_name] ?? 0);
        $total = $dep_count + $arr_count;

        $city_objects[] = [
            'name' => $city_name,
            'dep_count' => $dep_count,
            'arr_count' => $arr_count,
            'total_trips' => $total,
            'is_popular' => in_array($city_name, $popular_defaults)
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'cities' => $city_objects,
        'raw_names' => array_values($all_cities)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Şehir listesi yüklenemedi.'
    ], JSON_UNESCAPED_UNICODE);
}