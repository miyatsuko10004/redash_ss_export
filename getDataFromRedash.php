<?php
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;
use Dotenv\Dotenv;

// .envファイルの読み込み
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Redash APIの設定
$redash_base_url = $_ENV['REDASH_BASE_URL'];
$api_key = $_ENV['REDASH_API_KEY'];
$query_id = $_ENV['REDASH_QUERY_ID'];

// Redashから結果を取得
function get_redash_results($redash_base_url, $api_key, $query_id) {
    $url = "$redash_base_url/api/queries/$query_id/results.json";
    $headers = [
        "Authorization: Key $api_key"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Google Sheets APIの設定
$spreadsheet_id = $_ENV['GOOGLE_SPREADSHEET_ID'];
$range_name = "Sheet1!A1";  // 書き込み範囲を指定

// Google Sheetsに書き込み
function update_google_sheet($data, $spreadsheet_id, $range_name) {
    $client = new Client();
    $client->setApplicationName('Redash to Google Sheets');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAuthConfig(__DIR__ . '/service-account.json'); // 正しいパスを指定
    $service = new Sheets($client);

    // Redashから取得したデータを整形
    $values = [];

    // **カラム名を取得して先頭に追加**
    if (!empty($data["query_result"]["data"]["columns"])) {
        $columns = array_map(function($col) {
            return $col["name"];
        }, $data["query_result"]["data"]["columns"]);
        $values[] = $columns; // カラム名を先頭に追加
    }

    // データの行を整形
    foreach ($data["query_result"]["data"]["rows"] as $row) {
        $row_data = array_values((array)$row);
        // NULL値を空文字列に変換
        foreach ($row_data as &$value) {
            if (is_null($value)) {
                $value = '';
            }
        }
        $values[] = $row_data;
    }

    // 整形後のデータを確認
    echo json_encode($values, JSON_PRETTY_PRINT);

    $body = new Sheets\ValueRange([
        'values' => $values
    ]);

    $params = [
        'valueInputOption' => 'RAW'
    ];

    // Google Sheetsにデータを更新
    $service->spreadsheets_values->update($spreadsheet_id, $range_name, $body, $params);
}

function main() {
    global $redash_base_url, $api_key, $query_id, $spreadsheet_id, $range_name;

    // Redashからデータを取得
    $redash_data = get_redash_results($redash_base_url, $api_key, $query_id);

    // Google Sheetsにデータを書き込み
    update_google_sheet($redash_data, $spreadsheet_id, $range_name);
}

main();
