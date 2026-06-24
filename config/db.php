<?php 
	
	$db	= getenv('DB_NAME');
	$user	= getenv('DB_USER'); 
	$pass	= getenv('DB_PASS'); 
	$host	= getenv('DB_HOST'); 
	$char	= "utf8mb4";  
	$dsn	= "mysql:host=$host;dbname=$db;charset=$char"; 

try { $pdo = 
	new PDO(
	$dsn, 
	$user, 
	$pass, [ 
		
	PDO::ATTR_ERRMODE => 
	PDO::ERRMODE_EXCEPTION, 
	PDO::ATTR_DEFAULT_FETCH_MODE => 
	PDO::FETCH_ASSOC, ]); }
		
	catch 
		(PDOException $e) { die
		("Database connection failed: " . $e->getMessage())
	; }
		
	?>