<?php
/*
 * Allow storage of keys in .env file
 */
declare(strict_types=1);

require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
/* 

Download list of all stored resources on Cloudinary as CSV file.

https://atakanau.blogspot.com/2018/12/cloudinary-yuklu-tum-dosyalar-listeleme.html

*/
function src_read($resource_type, $next_cursor = false) {
    $result = new stdClass();
    $result->output = [];
    $result->column_names = [];
    $output_new = [];
    $column_names_new = [];
    $handle = curl_init();

    /* 	Replace with your own parameters :
            API_KEY
            API_SECRET
            CLOUD_NAME
             */
    $url = 'https://'
        . $_ENV['CLOUDINARY_API_KEY'] . ':'
        . $_ENV['CLOUDINARY_API_SECRET']
        . '@api.cloudinary.com/v1_1/'
        . $_ENV['CLOUDINARY_CLOUD_NAME']
        . '/resources/' . $resource_type
        . '?max_results=500&metadata=true&tags=true&moderation=true'
        . ($next_cursor ? '&next_cursor=' . $next_cursor : '');
    curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    $json_result = curl_exec($handle);
    curl_close($handle);
    $data = json_decode($json_result, true);

    $result->next_cursor = isset($data['next_cursor']) ? $data['next_cursor'] : false;

    if (isset($data['resources']) && count($data['resources'])) {
        foreach ($data['resources'] as $rsc) {
            $row_new = [];
            foreach ($rsc as $key => $value) {
                $column_names_new[$key] = $key;
                if (is_array($value)) {
                    $row_new[$key] = implode('|', $value);
                } else {
                    $row_new[$key] = $value;
                }
            }
            $output_new[] = $row_new;
        }
        $result->output = $output_new;
        $result->column_names = $column_names_new;
    }

    return $result;
}

$output_array = [];
$output = '';
$column_names = [];
$next_cursor = false;
$allowed_resource_types = ["raw", "image", "video"];
$resource_type = isset($_GET['resource_type']) ? (string)$_GET['resource_type'] : NULL;
$resource_types = ($resource_type) ? array_intersect($allowed_resource_types, [$resource_type]) : $allowed_resource_types;
foreach ($resource_types as $resource_type) {
    do {
        $r = src_read($resource_type, $next_cursor);
        $output_array = array_merge($output_array, $r->output);
        $column_names = array_merge($column_names, $r->column_names);
    } while ($next_cursor = $r->next_cursor);
}
$output .= implode("\t", $column_names) . "\r\n";
foreach ($output_array as $row) {
    $filled_row = array_fill_keys($column_names, '');
    $row = array_merge($filled_row, $row);
    $output .= implode("\t", $row) . "\r\n";
}
header("Content-type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"cloudinary-" . implode('-', $resource_types) . "-resources-list.csv\"");
echo $output;
