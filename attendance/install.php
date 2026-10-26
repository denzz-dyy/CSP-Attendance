<?php
// Open http://localhost/attendance/install.php once. Creates the database and tables automatically.
require 'config.php';
if(PHP_SAPI!='cli')echo '<!doctype html><meta charset=utf-8><body style="font-family:system-ui;max-width:600px;margin:3rem auto"><h2>Attendance System Setup</h2><pre style="white-space:pre-wrap">';
try{$db=new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
catch(PDOException $e){exit("Cannot connect to MySQL. Start MySQL in the XAMPP Control Panel, and check DB_USER/DB_PASS in config.php.\n".$e->getMessage());}
$db->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4");$db->exec("USE `".DB_NAME."`");
$db->exec("CREATE TABLE IF NOT EXISTS courses(id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(10) UNIQUE NOT NULL,name VARCHAR(100) NOT NULL);
CREATE TABLE IF NOT EXISTS students(id INT AUTO_INCREMENT PRIMARY KEY,student_no VARCHAR(30) UNIQUE NOT NULL,code_id VARCHAR(60) UNIQUE NOT NULL,alt_id VARCHAR(40) NULL,full_name VARCHAR(120) NOT NULL,course_id INT NOT NULL,year_level TINYINT NOT NULL,section VARCHAR(10),photo_url VARCHAR(255),status ENUM('active','inactive') DEFAULT 'active',is_sample TINYINT(1) DEFAULT 0,FOREIGN KEY(course_id) REFERENCES courses(id));
CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(40) UNIQUE NOT NULL,password_hash VARCHAR(255) NOT NULL,full_name VARCHAR(100) NOT NULL,role ENUM('admin','teacher','student') NOT NULL,student_id INT NULL,FOREIGN KEY(student_id) REFERENCES students(id));
CREATE TABLE IF NOT EXISTS sessions(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,type VARCHAR(30) NOT NULL,session_date DATE NOT NULL,start_time TIME NOT NULL,end_time TIME NOT NULL,grace_minutes INT DEFAULT 15,status ENUM('open','finalized') DEFAULT 'open',created_by INT NOT NULL,FOREIGN KEY(created_by) REFERENCES users(id));
CREATE TABLE IF NOT EXISTS attendance(id INT AUTO_INCREMENT PRIMARY KEY,session_id INT NOT NULL,student_id INT NOT NULL,scanned_at DATETIME NOT NULL,status ENUM('present','late','absent','excused') NOT NULL,recorded_by INT NOT NULL,UNIQUE KEY uq_session_student(session_id,student_id),FOREIGN KEY(session_id) REFERENCES sessions(id),FOREIGN KEY(student_id) REFERENCES students(id),FOREIGN KEY(recorded_by) REFERENCES users(id));");
if($db->query("SELECT COUNT(*) FROM users")->fetchColumn()==0){
 foreach([['BSIT','BS Information Technology'],['BSCS','BS Computer Science'],['BSBA','BS Business Administration']] as $c)$db->prepare("INSERT INTO courses(code,name) VALUES(?,?)")->execute($c);
 $n=['Ana Reyes','Ben Santos','Carla Dizon','Dan Flores','Ella Cruz','File Garcia','Gio Lopez','Hana Ramos','Ian Torres','Jia Mendoza','Kai Bautista','Lia Navarro'];
 foreach($n as $i=>$name)$db->prepare("INSERT INTO students(student_no,code_id,full_name,course_id,year_level,section,is_sample) VALUES(?,?,?,?,?,?,1)")->execute([sprintf('SAMPLE-%03d',$i+1),sprintf('SMP%03d',$i+1),$name,$i%3+1,$i%4+1,'A']);
 $us=[['admin','admin123','Administrator','admin',null],['teacher','teacher123','Sample Teacher','teacher',null],['student','student123','Ana Reyes','student',1]];
 foreach($us as $u)$db->prepare("INSERT INTO users(username,password_hash,full_name,role,student_id) VALUES(?,?,?,?,?)")->execute([$u[0],password_hash($u[1],PASSWORD_DEFAULT),$u[2],$u[3],$u[4]]);
 echo "Installed. Logins: admin/admin123, teacher/teacher123, student/student123 (CHANGE THESE). Sample students have IDs SAMPLE-001..012, barcode values SMP001..SMP012.\n";
}else echo "Already installed.\n";
if(is_file(__DIR__.'/acss_data.json')&&!$db->query("SELECT COUNT(*) FROM students WHERE is_sample=0")->fetchColumn()){require __DIR__.'/import_lib.php';echo import_acss($db,1);}
if(PHP_SAPI!='cli')echo '</pre><p><a href="index.php">Go to login &rarr;</a></p><p style="color:#b91c1c">For safety, delete install.php after setup.</p>';
