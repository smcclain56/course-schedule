<?php
  session_start();
  require_once 'conn.php';

  if(ISSET($_POST['register'])){
    $username = $_POST['username'];
    $password = $_POST['password'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $department = $_POST['department'];

    $query = "INSERT INTO User (username, password, firstname, lastname, department) VALUES (:username, :password, :firstname, :lastname, :department)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':firstname', $firstname);
    $stmt->bindParam(':lastname', $lastname);
    $stmt->bindParam(':department', $department);

    if($stmt->execute()){
      $_SESSION['success'] = "Sucessfully created an account";
      header('location: index.php'); //TODO CHANGE FROM INDEX to homepage?
    }

  }
?>
