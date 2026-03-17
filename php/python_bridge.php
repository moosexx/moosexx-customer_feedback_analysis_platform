<?php
/**
 * Python Analytics Bridge
 * Executes Python advanced analytics engine and returns results
 */

function run_python_analytics($data) {
    $python_path = '../python/advanced_analytics.py';
    
    // Check if Python is available
    $python_cmd = get_python_command();
    if (!$python_cmd) {
        return null; // Python not available, fallback to PHP
    }
    
    // Prepare data for Python script
    $json_data = json_encode($data);
    
    // Execute Python script
    $cmd = escapeshellarg($json_data);
    $full_cmd = "$python_cmd \"$python_path\" $cmd";
    
    $output = shell_exec($full_cmd . ' 2>&1');
    $result = json_decode($output, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
        return $result;
    }
    
    return null; // Fallback if Python execution fails
}

function get_python_command() {
    // Try common Python commands
    $commands = ['python3', 'python', 'py'];
    
    foreach ($commands as $cmd) {
        $test = shell_exec("$cmd --version 2>&1");
        if (stripos($test, 'python') !== false) {
            return $cmd;
        }
    }
    
    return null;
}

// Example usage:
// $data = [
//     'total_feedback' => $total_feedback,
//     'avg_rating' => $avg_rating,
//     'recent_feedback' => $recent_feedback,
//     'distribution' => $distribution,
//     'categories' => $categories,
//     'trend_data' => $trend_data
// ];
// 
// $python_recommendations = run_python_analytics($data);
// if ($python_recommendations) {
//     $recommendations = $python_recommendations;
// }
?>
