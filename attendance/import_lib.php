<?php
// Imports acss_data.json (from ACSS_Attendance_Record_S_Y__2026-2027.xlsx). Safe to run repeatedly.
function import_acss(PDO $db,int $adminId):string{
 $d=json_decode(file_get_contents(__DIR__.'/acss_data.json'),true);
 if(!$db->query("SHOW COLUMNS FROM students LIKE 'alt_id'")->fetch())$db->exec("ALTER TABLE students ADD alt_id VARCHAR(40) NULL AFTER code_id");
 $db->exec("ALTER TABLE attendance MODIFY status ENUM('present','late','absent','excused') NOT NULL");
 $db->beginTransaction();
 $names=['BSIT'=>'BS Information Technology','BSCS'=>'BS Computer Science','BMMA'=>'Bachelor of Multimedia Arts','BLIS'=>'Bachelor of Library and Information Science'];
 foreach($names as $c=>$n)$db->prepare("INSERT IGNORE INTO courses(code,name) VALUES(?,?)")->execute([$c,$n]);
 $cid=$db->query("SELECT code,id FROM courses")->fetchAll(PDO::FETCH_KEY_PAIR);
 $db->exec("UPDATE students SET status='inactive' WHERE is_sample=1");
 $st=$db->prepare("INSERT INTO students(student_no,code_id,alt_id,full_name,course_id,year_level,section) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE alt_id=VALUES(alt_id),full_name=VALUES(full_name),course_id=VALUES(course_id),year_level=VALUES(year_level),section=VALUES(section)");
 foreach($d['students'] as $s)$st->execute([$s['student_no'],$s['student_no'],$s['alt_id'],$s['name'],$cid[$s['course']],$s['year'],$s['section']]);
 $sm=$db->query("SELECT student_no,id FROM students")->fetchAll(PDO::FETCH_KEY_PAIR);$sx=[];
 foreach($d['sessions'] as $s){$f=$db->prepare("SELECT id FROM sessions WHERE name=? AND session_date=?");$f->execute([$s['name'],$s['date']]);$id=$f->fetchColumn();
  if(!$id){$db->prepare("INSERT INTO sessions(name,type,session_date,start_time,end_time,grace_minutes,created_by) VALUES(?,?,?,'08:00','17:00',15,?)")->execute([$s['name'],'Event',$s['date'],$adminId]);$id=$db->lastInsertId();}$sx[$s['key']]=$id;}
 $at=$db->prepare("INSERT INTO attendance(session_id,student_id,scanned_at,status,recorded_by) SELECT ?,?,?,?,? FROM DUAL ON DUPLICATE KEY UPDATE status=VALUES(status)");
 $dates=array_column($d['sessions'],'date','key');
 foreach($d['attendance'] as [$no,$k,$status])$at->execute([$sx[$k],$sm[$no],$dates[$k].' 00:00:00',$status,$adminId]);
 $db->commit();
 return 'Imported '.count($d['students']).' students, '.count($d['sessions']).' sessions, '.count($d['attendance'])." attendance records. Demo sample students were deactivated.\n";}
