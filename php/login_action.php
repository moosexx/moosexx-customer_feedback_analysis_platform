<?php
header('Content-Type: application/json');
session_start();
require_once "config.php";

$email = $password = "";
$email_err = $password_err = $login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    if(empty($email_err) && empty($password_err)){
        $sql = "SELECT id, email, password FROM users WHERE email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = $email;
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){                    
                    mysqli_stmt_bind_result($stmt, $id, $email, $hashed_password);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Session already started at the top
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["email"] = $email;                            
                            
                            echo json_encode(["status" => "success", "message" => "Login successful."]);
                        } else {
                            echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
                        }
                    }
                } else {
                    echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Database error. Please try again later."]);
            }
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(["status" => "error", "message" => "Server error. Please check database tables."]);
        }
    } else {
         echo json_encode(["status" => "error", "errors" => ["email" => $email_err, "password" => $password_err]]);
    }
    
    mysqli_close($conn);
}
?>
