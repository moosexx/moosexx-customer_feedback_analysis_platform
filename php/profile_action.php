<?php
session_start();
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $user_id = $_SESSION["id"];
    $business_name = trim($_POST["business_name"]);
    $industry = trim($_POST["industry"]);
    $description = trim($_POST["description"]);

    if(empty($business_name)){
        echo json_encode(["status" => "error", "message" => "Business name is required."]);
        exit;
    }

    // Check if profile exists
    $sql = "SELECT id FROM businesses WHERE user_id = ?";
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if(mysqli_stmt_execute($stmt)){
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) == 1){
                // Update
                $sql = "UPDATE businesses SET business_name = ?, industry = ?, description = ? WHERE user_id = ?";
            } else {
                // Insert
                $sql = "INSERT INTO businesses (business_name, industry, description, user_id) VALUES (?, ?, ?, ?)";
            }
        }
        mysqli_stmt_close($stmt);
    }

    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "sssi", $business_name, $industry, $description, $user_id);
        if(mysqli_stmt_execute($stmt)){
            echo json_encode(["status" => "success", "message" => "Business profile saved successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Something went wrong. Please try again later."]);
        }
        mysqli_stmt_close($stmt);
    }
    
    mysqli_close($conn);
} elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Fetch profile data
    $user_id = $_SESSION["id"];
    $sql = "SELECT business_name, industry, description FROM businesses WHERE user_id = ?";
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if($row = mysqli_fetch_assoc($result)){
                echo json_encode(["status" => "success", "data" => $row]);
            } else {
                echo json_encode(["status" => "success", "data" => null]);
            }
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
}
?>
