<?php
include_once('config.php');
include_once('CurlClient.php');
include_once('queryGenerator.php');
include_once('ftpClass.php');
include_once('influxSeriesHelper.php');
include_once('DateTimeHelper.php');

$config_datapoints = get_config('data_config/VIJAYANTH2.json');
// Usage
$url = "https://influxdb.renewgrid.in/query";
$username = "";
$password = "fT3cTIwFwtpoPQe6_lU6d24lNEkr0_Zl0q8aaJyr8YLwlRug7S-jrzl7hTZz1B1M_SOLGP0Au0E2-ARs7i2HQw==";



// Create an instance of the DateTimeHelper class with a specific timezone
$dateHelper = new DateTimeHelper('Asia/Kolkata');

// Get the start timestamp of the current day
$dayStart = $dateHelper->getDayStartTimestamp();

$start_time = $dayStart."s";
$end_time = ($start_time+86400)."s";
$currentDate = date($config_datapoints['filename_format']);


$outputFolder = '/var/www/reconnect/data_config/qca_csv_files';

if (!is_dir($outputFolder)) {
    mkdir($outputFolder, 0777, true); // true allows recursive directory creation
}

$fileName = $outputFolder . "/VIJAYANTH2_" . $currentDate . ".csv";


$queryGenerator = new QueryGenerator($config_datapoints,$start_time,$end_time);
$queries = $queryGenerator->generateQueries();
$query_set = implode('', $queries);



$curlClient = new CurlClient($url, $username, $password);

$data = array(
    'db' => 'Sunderapandian_Park',
    'q' => $query_set
);

$response = $curlClient->post($data);
//$response start//
//
//{"results":[{"statement_id":0},{"statement_id":1,"series":[{"name":"wattmon_std_mv","tags":{"did":"Inverter02","dlid":"9C956E78E37A","f":"AC_Active_Power"},"columns":["time","mean"],"values":[["2024-07-08T00:00:00+05:30",null],["2024-07-08T00:01:00+05:30",0],["2024-07-08T00:02:00+05:30",0]]}]}]}
//
//$response end//

// Decode JSON data
$influx_results = json_decode($response, true);

// Initialize an array to store time-based rows
$rows = [];

// Iterate through each series
/* foreach ($influx_results['results'] as $statement_id => $statements) {
    if(count($statements['series']) != 0){
        foreach ($statements['series'] as $series) {
            $series_name = $series['tags']['dlid']. '_' . $series['tags']['did']. '_' .$series['tags']['f'];
            $influxSeriesHelper = new influxSeriesHelper($config_datapoints);
            $influxSeriesHelper->findSeriesIndexFromJson($series['tags']);
            // Iterate through the values
            foreach ($series['values'] as $value) {
                $time = $value[0];
                $mean = $value[1];
        
                // Create a new row if the time doesn't exist
                if (!isset($rows[$time])) {
                    $rows[$time] = ['time' => $time];
                }
        
                // Add the series data to the corresponding time row
                $rows[$time][$series_name] = $mean;
            }
        }
    }

} */

foreach($config_datapoints['dataPoints'] as $datapoints){
    $influxSeriesHelper = new influxSeriesHelper($config_datapoints);
    $series = $influxSeriesHelper->findSeriesIndexFromJson($datapoints['dp'], $influx_results);
    $series_name = $datapoints['name'];
    if(!empty($series)){
        // Iterate through the values
        foreach ($series['values'] as $value) {
            $time = $value[0];
            $time = $dateHelper->getCustomFormattedDate($time, $config_datapoints['timestamp_format']);
            $mean = $value[1];
            
            // Create a new row if the time doesn't exist
            if (!isset($rows[$time])) {
                $rows[$time] = ['Timestamp' => $time];
            }
            
            // Add the series data to the corresponding time row
            $rows[$time][$series_name] = round($mean,2);
        }
    }
    
}




// Open a file in write mode
$csv_file = fopen($fileName, 'w');

// Extract and write CSV header
$header = array_keys(reset($rows));
fputcsv($csv_file, $header);

// Write the data to CSV
foreach ($rows as $row) {
    fputcsv($csv_file, $row);
}

// Close the CSV file
fclose($csv_file);




// Usage example:
//This ftpClass.php file needs external phpseclib library to function. so we need to install that via composer
//composer require phpseclib/phpseclib:^2.0

try {
    //This ftpClass.php file needs external phpseclib library to function. so we need to install that via composer
    //composer require phpseclib/phpseclib:^2.0   
     
    //$ftp = new FileTransfer('52.41.144.51', 'Vijayanth', 'hka564k');
    //$ftp->connect();
    // $ftp->uploadFile($fileName, '/VIJAYANTH2/'.$currentDate.'.csv');
    //$ftp->close();

    $sftp = new FileTransfer('dev.renewgrid.in', 'username', 'pwd', true);
    $sftp->connect();
    $sftp->uploadFile('output.csv', '/var/www/renewgrid/output.csv');
    $sftp->close();

    //This ftpClass.php file needs external phpseclib library to function. so we need to install that via composer
    //composer require phpseclib/phpseclib:^2.0
    //$sftp = new FileTransfer('ftp.renewgrid.in', 'ftp_test', 'ftp_test_123', true);
    //$sftp->connect();
    //$sftp->uploadFile($currentDate.'.csv', '/'.$currentDate.'.csv');
    //$sftp->close();
    echo "CSV report has been generated successfully.";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
