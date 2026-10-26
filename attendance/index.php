<?php
require 'config.php';
session_start();
try{$db=new PDO(DSN,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->query("SELECT 1 FROM users LIMIT 1");}
catch(PDOException $e){if(file_exists(__DIR__.'/install.php')){header('Location: install.php');exit;}exit('Database not ready. Check config.php.');}
function q($s,$a=[]){global $db;$t=$db->prepare($s);$t->execute($a);return $t;}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function js($d,$c=200){http_response_code($c);header('Content-Type: application/json');echo json_encode($d);exit;}
$_SESSION['csrf']=$_SESSION['csrf']??bin2hex(random_bytes(16));$T=$_SESSION['csrf'];
$u=$_SESSION['u']??null;$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($T,$_POST['csrf']??''))js(['error'=>'Session expired. Reload the page.'],403);
 if(isset($_POST['login'])){$r=q("SELECT * FROM users WHERE username=?",[$_POST['username']])->fetch();
  if($r&&password_verify($_POST['password'],$r['password_hash'])){session_regenerate_id(true);$_SESSION['u']=$r;header('Location: index.php');exit;}$msg='Invalid username or password.';}
}
if(!$u){?><!doctype html><html><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>Login · CSP Attendance</title><link rel=icon href=favicon.png><style>
:root{--navy:#04543f;--navy2:#0b7658;--gold:#ecd98f;--amber:#e0b64a;--red:#d71920}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Inter,system-ui,sans-serif;background:radial-gradient(circle at 20% 10%,var(--navy2),var(--navy) 60%);padding:1rem}
form{background:#fff;padding:2rem 2rem 1.6rem;border-radius:18px;width:100%;max-width:370px;text-align:center;box-shadow:0 20px 50px #0006;border-top:6px solid var(--amber)}
img{width:130px;margin:-4.5rem auto .5rem;display:block;filter:drop-shadow(0 6px 10px #0004)}h2{margin:.2rem 0 0;color:var(--navy)}p.sub{margin:.2rem 0 1.2rem;color:#64748b;font-size:.9rem}
input,button{width:100%;padding:.8rem;margin:.35rem 0;border-radius:9px;font-size:1rem}input{border:1.5px solid #d6dbe6}input:focus{outline:0;border-color:var(--amber);box-shadow:0 0 0 3px #e0b64a66}
button{background:var(--navy);color:#fff;border:0;font-weight:600;cursor:pointer;margin-top:.8rem}button:hover{background:var(--navy2)}.err{color:var(--red);min-height:1.2rem;font-size:.9rem}</style></head><body>
<form method=post><img src=logo.png alt=CSP><h2>CSP Attendance</h2><p class=sub>College of Special Programs<br>Cor Jesu College, Inc.</p><input type=hidden name=csrf value="<?=$T?>"><input name=username placeholder=Username required autofocus><input type=password name=password placeholder=Password required><button name=login>Sign in</button><div class=err><?=h($msg)?></div></form></body></html><?php exit;}
$role=$u['role'];$uid=$u['id'];
function need(...$r){global $role;if(!in_array($role,$r)){http_response_code(403);exit('Forbidden');}}
function status_for($s){$st=strtotime($s['session_date'].' '.$s['start_time'])+$s['grace_minutes']*60;return time()>$st?'late':'present';}
// ---- APIs ----
if(isset($_GET['api'])){need('admin','teacher');
 if($_GET['api']=='search'){$l='%'.$_GET['q'].'%';js(q("SELECT s.id,s.student_no,s.full_name,c.code course FROM students s JOIN courses c ON c.id=s.course_id WHERE s.status='active' AND (s.full_name LIKE ? OR s.student_no LIKE ?) LIMIT 8",[$l,$l])->fetchAll());}
 if($_GET['api']=='live'){js(q("SELECT s.student_no,s.full_name,c.code course,s.year_level,TIME(a.scanned_at) t,a.status FROM attendance a JOIN students s ON s.id=a.student_id JOIN courses c ON c.id=s.course_id WHERE a.session_id=? ORDER BY a.scanned_at DESC LIMIT 50",[(int)$_GET['sid']])->fetchAll());}
 if($_GET['api']=='scan'&&$_SERVER['REQUEST_METHOD']==='POST'){
  $S=q("SELECT * FROM sessions WHERE id=?",[(int)$_POST['sid']])->fetch();
  if(!$S)js(['error'=>'Select an attendance session first.'],400);
  if($S['status']!='open')js(['error'=>'This session is finalized.'],400);
  if($S['session_date']!=date('Y-m-d'))js(['error'=>'This session is not scheduled for today.'],400);
  $code=trim($_POST['code']??'');
  $st=q("SELECT s.*,c.code course FROM students s JOIN courses c ON c.id=s.course_id WHERE s.code_id=? OR s.student_no=? OR s.alt_id=?",[$code,$code,$code])->fetch();
  if(!$st)js(['error'=>"Invalid ID: not a registered student."],404);
  if($st['status']!='active')js(['error'=>'Student record is inactive.','student'=>$st],400);
  $status=status_for($S);
  try{q("INSERT INTO attendance(session_id,student_id,scanned_at,status,recorded_by) VALUES(?,?,NOW(),?,?)",[$S['id'],$st['id'],$status,$uid]);}
  catch(PDOException $e){if($e->getCode()=='23000')js(['error'=>'Already recorded for this session.','student'=>$st],409);throw $e;}
  js(['ok'=>1,'status'=>$status,'student'=>$st,'time'=>date('h:i:s A')]);}
 exit;}
// ---- POST actions ----
if($_SERVER['REQUEST_METHOD']==='POST'){
 $a=$_POST['act']??'';
 if($a=='student'){need('admin');$f=[$_POST['student_no'],$_POST['code_id'],$_POST['full_name'],(int)$_POST['course_id'],(int)$_POST['year_level'],$_POST['section'],$_POST['photo_url']];
  try{if($_POST['id'])q("UPDATE students SET student_no=?,code_id=?,full_name=?,course_id=?,year_level=?,section=?,photo_url=? WHERE id=?",[...$f,(int)$_POST['id']]);
  else q("INSERT INTO students(student_no,code_id,full_name,course_id,year_level,section,photo_url) VALUES(?,?,?,?,?,?,?)",$f);}catch(PDOException $e){$msg='Save failed: duplicate student ID or barcode.';}}
 if($a=='toggle'){need('admin');q("UPDATE students SET status=IF(status='active','inactive','active') WHERE id=?",[(int)$_POST['id']]);}
 if($a=='session'){need('admin','teacher');q("INSERT INTO sessions(name,type,session_date,start_time,end_time,grace_minutes,created_by) VALUES(?,?,?,?,?,?,?)",[$_POST['name'],$_POST['type'],$_POST['session_date'],$_POST['start_time'],$_POST['end_time'],(int)$_POST['grace'],$uid]);}
 if($a=='finalize'){need('admin','teacher');$sid=(int)$_POST['id'];$s=q("SELECT * FROM sessions WHERE id=? AND status='open'",[$sid])->fetch();
  if($s&&($role=='admin'||$s['created_by']==$uid)){q("INSERT IGNORE INTO attendance(session_id,student_id,scanned_at,status,recorded_by) SELECT ?,id,NOW(),'absent',? FROM students WHERE status='active'",[$sid,$uid]);q("UPDATE sessions SET status='finalized' WHERE id=?",[$sid]);}}
 if($a=='user'){need('admin');try{q("INSERT INTO users(username,password_hash,full_name,role,student_id) VALUES(?,?,?,?,?)",[$_POST['username'],password_hash($_POST['password'],PASSWORD_DEFAULT),$_POST['full_name'],$_POST['role'],$_POST['student_id']?:null]);}catch(PDOException $e){$msg='Could not create user (duplicate username?).';}}
}
if(isset($_GET['logout'])){session_destroy();header('Location: index.php');exit;}
$pages=['dashboard'=>'Dashboard','scan'=>'Scan Attendance','students'=>'Students','records'=>'Attendance Records','sessions'=>'Attendance Sessions','reports'=>'Reports','users'=>'User Management'];
if($role=='student'){$pages=['mine'=>'My Attendance'];$p='mine';}elseif($role=='teacher')unset($pages['users']);
$p=$_GET['p']??array_key_first($pages);if(!isset($pages[$p]))$p=array_key_first($pages);
$sess=q("SELECT * FROM sessions ORDER BY session_date DESC,start_time DESC LIMIT 100")->fetchAll();
$sid=(int)($_GET['sid']??($sess[0]['id']??0));
// CSV export
if($p=='reports'&&isset($_GET['csv'])){need('admin','teacher');[$w,$ar]=filt();$r=q("SELECT s.student_no,s.full_name,c.code course,s.year_level,x.name activity,x.session_date,a.scanned_at,a.status FROM attendance a JOIN students s ON s.id=a.student_id JOIN courses c ON c.id=s.course_id JOIN sessions x ON x.id=a.session_id WHERE $w ORDER BY x.session_date DESC,s.full_name",$ar)->fetchAll();
 header('Content-Type: text/csv');header('Content-Disposition: attachment; filename=attendance.csv');$o=fopen('php://output','w');fputcsv($o,['Student ID','Name','Course','Year','Activity','Date','Scanned At','Status']);foreach($r as $x)fputcsv($o,$x);exit;}
function filt(){global $role,$uid;$w=['1=1'];$a=[];
 if(!empty($_GET['from'])){$w[]='x.session_date>=?';$a[]=$_GET['from'];}if(!empty($_GET['to'])){$w[]='x.session_date<=?';$a[]=$_GET['to'];}
 if(!empty($_GET['course'])){$w[]='s.course_id=?';$a[]=(int)$_GET['course'];}if(!empty($_GET['year'])){$w[]='s.year_level=?';$a[]=(int)$_GET['year'];}
 if(!empty($_GET['rs'])){$w[]='x.id=?';$a[]=(int)$_GET['rs'];}if(!empty($_GET['name'])){$w[]='(s.full_name LIKE ? OR s.student_no LIKE ?)';$a[]=$a[]='%'.$_GET['name'].'%';}
 if($role=='teacher'){$w[]='x.created_by=?';$a[]=$uid;}return[implode(' AND ',$w),$a];}
function badge($s){$c=['present'=>'#16a34a','late'=>'#ea580c','absent'=>'#dc2626','excused'=>'#2563eb'][$s]??'#6b7280';return "<b style='color:#fff;background:$c;padding:2px 8px;border-radius:10px;font-size:.8rem'>".h(ucfirst($s))."</b>";}
$courses=q("SELECT * FROM courses")->fetchAll();
?><!doctype html><html><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>CSP Attendance</title><link rel=icon href=favicon.png><style>
:root{--navy:#04543f;--navy2:#0b7658;--gold:#ecd98f;--amber:#e0b64a;--red:#d71920;--bg:#f7f4ec}
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:var(--bg);color:#1e293b;display:flex;min-height:100vh}
nav{width:240px;background:linear-gradient(180deg,var(--navy),#033a2c);padding:1.2rem .8rem;flex-shrink:0;position:sticky;top:0;height:100vh;overflow:auto}
.brand{text-align:center;padding-bottom:1rem;margin-bottom:.8rem;border-bottom:1px solid #ffffff22}.brand img{width:92px;filter:drop-shadow(0 4px 8px #0006)}.brand b{display:block;color:var(--gold);margin-top:.4rem;letter-spacing:.5px}.brand small{color:#a9cbbd;font-size:.72rem}
nav a{display:block;color:#cfe5da;padding:.65rem .9rem;border-radius:8px;text-decoration:none;margin:2px 0;border-left:3px solid transparent;font-size:.93rem}nav a:hover{background:#ffffff14;color:#fff}nav a.on{background:#e0b64a26;color:var(--gold);border-left-color:var(--amber);font-weight:600}
main{flex:1;padding:1.6rem;min-width:0;max-width:1300px}h2{margin:.2rem 0 1rem;color:var(--navy);font-size:1.5rem}h3{color:var(--navy);margin-top:0}
.card{background:#fff;border-radius:14px;padding:1.1rem;margin-bottom:1rem;box-shadow:0 1px 3px #04543f14,0 6px 18px #04543f0a;border:1px solid #d9e8d3}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem}.stat{border-top:4px solid var(--amber);margin:0}.stat b{font-size:2rem;display:block;color:var(--navy)}.stat:nth-child(2){border-top-color:#16a34a}.stat:nth-child(3){border-top-color:#ea580c}.stat:nth-child(4){border-top-color:var(--red)}.stat:nth-child(5){border-top-color:var(--navy)}
table{width:100%;border-collapse:collapse}th{background:#e1efdb;color:var(--navy);text-align:left;padding:.6rem .5rem;font-size:.82rem;text-transform:uppercase;letter-spacing:.4px}td{padding:.55rem .5rem;border-bottom:1px solid #e3eedf;font-size:.9rem}tr:hover td{background:#f5faf3}.tw{overflow-x:auto}
input,select{padding:.55rem .7rem;margin:2px;border:1.5px solid #d6dbe6;border-radius:8px;font-size:.92rem;background:#fff}input:focus,select:focus{outline:0;border-color:var(--amber);box-shadow:0 0 0 3px #e0b64a66}
button,.btn{background:var(--navy);color:#fff;border:0;cursor:pointer;padding:.55rem 1rem;border-radius:8px;text-decoration:none;font-size:.92rem;font-weight:600;display:inline-block;margin:2px}button:hover,.btn:hover{background:var(--navy2)}
#go{background:var(--amber);color:var(--navy);padding:.8rem 1.6rem;font-size:1rem}#go:hover{background:#edc860}#stop{background:var(--red)}
#reader{width:100%;max-width:560px;min-height:300px;background:#033a2c;border-radius:14px;border:3px solid var(--gold);margin-bottom:.6rem;overflow:hidden}
#res{padding:1.1rem;border-radius:12px;background:#eef3ec;border:3px dashed #c3d3bd;min-height:170px;color:#64748b}#res h2{color:var(--navy)}
a{color:var(--navy2)}small{color:#64748b}
@media(max-width:800px){body{flex-direction:column}nav{width:100%;height:auto;position:static;display:flex;flex-wrap:wrap;gap:2px;padding:.6rem}.brand{width:100%;border:0;margin:0;padding:0 0 .4rem;display:flex;align-items:center;gap:.6rem;text-align:left}.brand img{width:44px}.brand b{margin:0}main{padding:1rem}}
@media print{nav,.np{display:none}body{background:#fff}.card{box-shadow:none;border:0}}
</style></head><body>
<nav><div class=brand><img src=logo.png alt=CSP><div><b>CSP Attendance</b><small>Cor Jesu College, Inc.</small></div></div><?php foreach($pages as $k=>$v)echo "<a class='".($k==$p?'on':'')."' href='?p=$k'>".h($v)."</a>";?><a href="?logout=1">Logout (<?=h($u['username'])?>)</a></nav><main>
<?php if($msg)echo "<div class=card style='color:#b91c1c'>".h($msg)."</div>";
function sessSel($sid){global $sess;$o="<select name=sid onchange='this.form.submit()'>";foreach($sess as $s)$o.="<option value={$s['id']} ".($s['id']==$sid?'selected':'').">".h($s['name'].' — '.$s['session_date'].' ('.$s['status'].')')."</option>";return $o."</select>";}
if($p=='dashboard'){need('admin','teacher');$S=q("SELECT * FROM sessions WHERE id=?",[$sid])->fetch();
 $tot=q("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
 $c=q("SELECT status,COUNT(*) n FROM attendance WHERE session_id=? GROUP BY status",[$sid])->fetchAll(PDO::FETCH_KEY_PAIR);
 $pr=($c['present']??0)+($c['late']??0);$fin=$S&&$S['status']=='finalized';?>
<h2>Dashboard</h2><form class=np><input type=hidden name=p value=dashboard><?=sessSel($sid)?></form><br>
<div class=grid><div class="card stat"><b><?=$tot?></b>Registered</div><div class="card stat"><b><?=$pr?></b>Present (incl. late)</div><div class="card stat"><b><?=$c['late']??0?></b>Late</div>
<div class="card stat"><b><?=$fin?($c['absent']??0):'—'?></b>Absent <?=$fin?'':'(after finalize)'?></div><div class="card stat"><b><?=$tot?round($pr/$tot*100):0?>%</b>Attendance</div></div>
<div class=card><h3>Live attendance <small id=lv></small></h3><div class=tw><table><thead><tr><th>Student ID<th>Name<th>Course<th>Year<th>Time In<th>Status</thead><tbody id=live></tbody></table></div></div>
<script>async function L(){let r=await (await fetch('?api=live&sid=<?=$sid?>')).json();live.innerHTML=r.map(x=>`<tr><td>${x.student_no}<td>${x.full_name}<td>${x.course}<td>${x.year_level}<td>${x.t}<td>${x.status}`).join('')||'<tr><td colspan=6>No scans yet';}L();setInterval(L,4000)</script>
<?php }
if($p=='scan'){need('admin','teacher');?><h2>Scan Attendance</h2><form class=np><input type=hidden name=p value=scan><?=sessSel($sid)?></form>
<div class=grid style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))"><div class=card><div id=reader></div><button id=go>Scan ID</button><button id=stop style=display:none>Stop</button><p id=cam></p>
<h4>Manual search</h4><input id=q placeholder="Name or student ID"><div id=sr></div></div>
<div class=card><h3>Scanned student</h3><div id=res>Waiting for scan…</div></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script><script>
const SID=<?=$sid?>,CS='<?=$T?>';let sc,busy=0;
async function rec(code){if(busy)return;busy=1;try{let r=await fetch('?api=scan',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({sid:SID,code,csrf:CS})});let d=await r.json(),s=d.student;
 let col=d.ok?(d.status=='late'?'#ea580c':'#16a34a'):'#dc2626';res.style.background=col+'22';res.style.border='3px solid '+col;
 res.innerHTML=(s?`${s.photo_url?`<img src="${esc(s.photo_url)}" width=80 style="float:right;border-radius:8px">`:''}<h2 style=margin:0>${esc(s.full_name)}</h2><p><b>${esc(s.course)}</b> · Year ${s.year_level} · ${esc(s.student_no)}</p>`:'')+`<h3 style="color:${col}">${d.ok?'✔ '+d.status.toUpperCase()+' at '+d.time:'✖ '+esc(d.error)}</h3>`;}
 catch(e){res.innerHTML='<h3 style=color:#dc2626>Network error. Try again.</h3>'}setTimeout(()=>busy=0,2500)}
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
go.onclick=async()=>{if(!window.isSecureContext&&location.hostname!='localhost'){cam.textContent='Camera needs HTTPS (or localhost).';return}
 try{sc=new Html5Qrcode('reader');await sc.start({facingMode:'environment'},{fps:10,qrbox:{width:260,height:160}},rec,()=>{});go.style.display='none';stop.style.display='';cam.textContent='';}
 catch(e){cam.style.color='#dc2626';cam.textContent=/denied|Permission/i.test(e)?'Camera permission denied. Allow it in browser settings.':'Camera unavailable: '+e}};
stop.onclick=async()=>{await sc.stop();go.style.display='';stop.style.display='none'};
q.oninput=async()=>{if(q.value.length<2){sr.innerHTML='';return}let r=await (await fetch('?api=search&q='+encodeURIComponent(q.value))).json();sr.innerHTML=r.map(x=>`<div><a href=# onclick="rec('${esc(x.student_no)}');return false">${esc(x.full_name)} (${esc(x.student_no)}, ${x.course})</a></div>`).join('')||'No match'}
</script><?php }
if($p=='students'){need('admin','teacher');$e=['id'=>'','student_no'=>'','code_id'=>'','full_name'=>'','course_id'=>1,'year_level'=>1,'section'=>'','photo_url'=>''];
 if(isset($_GET['edit']))$e=q("SELECT * FROM students WHERE id=?",[(int)$_GET['edit']])->fetch()?:$e;$k='%'.($_GET['k']??'').'%';
 $rows=q("SELECT s.*,c.code course FROM students s JOIN courses c ON c.id=s.course_id WHERE s.full_name LIKE ? OR s.student_no LIKE ? ORDER BY s.full_name",[$k,$k])->fetchAll();?>
<h2>Students</h2><form class=np><input type=hidden name=p value=students><input name=k placeholder=Search value="<?=h($_GET['k']??'')?>"><button>Search</button></form>
<?php if($role=='admin'){?><form method=post class=card><input type=hidden name=csrf value="<?=$T?>"><input type=hidden name=act value=student><input type=hidden name=id value="<?=h($e['id'])?>">
<input name=student_no placeholder="Student ID" required value="<?=h($e['student_no'])?>"><input name=code_id placeholder="Barcode/QR value" required value="<?=h($e['code_id'])?>"><input name=full_name placeholder="Full name" required value="<?=h($e['full_name'])?>">
<select name=course_id><?php foreach($courses as $c)echo "<option value={$c['id']} ".($c['id']==$e['course_id']?'selected':'').">{$c['code']}</option>";?></select><input type=number name=year_level min=1 max=6 value="<?=h($e['year_level'])?>" style=width:70px><input name=section placeholder=Section size=6 value="<?=h($e['section'])?>"><input name=photo_url placeholder="Photo URL (optional)" value="<?=h($e['photo_url'])?>"><button><?=$e['id']?'Update':'Add'?> student</button></form><?php }?>
<div class="card tw"><table><tr><th>ID<th>Name<th>Course<th>Yr/Sec<th>Barcode<th>Status<th></tr><?php foreach($rows as $r){?><tr><td><?=h($r['student_no'])?><?=$r['is_sample']?' <i>(sample)</i>':''?><td><?=h($r['full_name'])?><td><?=$r['course']?><td><?=$r['year_level'].'-'.h($r['section'])?><td><?=h($r['code_id'])?><td><?=$r['status']?><td><?php if($role=='admin'){?><a href="?p=students&edit=<?=$r['id']?>">Edit</a> <form method=post style=display:inline><input type=hidden name=csrf value="<?=$T?>"><input type=hidden name=act value=toggle><input type=hidden name=id value=<?=$r['id']?>><button><?=$r['status']=='active'?'Deactivate':'Activate'?></button></form><?php }?></tr><?php }?></table></div><?php }
if($p=='sessions'){need('admin','teacher');?><h2>Attendance Sessions</h2><form method=post class=card><input type=hidden name=csrf value="<?=$T?>"><input type=hidden name=act value=session>
<input name=name placeholder="Activity name" required><select name=type><option>Class<option>Event<option>Seminar<option>Workshop<option>Meeting<option>Other</select><input type=date name=session_date value="<?=date('Y-m-d')?>" required><input type=time name=start_time required><input type=time name=end_time required><input type=number name=grace value=15 style=width:80px title="Grace minutes"><button>Create</button></form>
<div class="card tw"><table><tr><th>Name<th>Type<th>Date<th>Time<th>Grace<th>Status<th></tr><?php foreach($sess as $s){?><tr><td><?=h($s['name'])?><td><?=$s['type']?><td><?=$s['session_date']?><td><?=substr($s['start_time'],0,5).'–'.substr($s['end_time'],0,5)?><td><?=$s['grace_minutes']?>m<td><?=$s['status']?><td><?php if($s['status']=='open'&&($role=='admin'||$s['created_by']==$uid)){?><form method=post onsubmit="return confirm('Finalize? Absent students will be marked.')"><input type=hidden name=csrf value="<?=$T?>"><input type=hidden name=act value=finalize><input type=hidden name=id value=<?=$s['id']?>><button>Finalize</button></form><?php }?></tr><?php }?></table></div><?php }
if($p=='records'||$p=='reports'){need('admin','teacher');[$w,$ar]=filt();$g=$_GET;
 $rows=q("SELECT s.student_no,s.full_name,c.code course,s.year_level,x.name activity,x.session_date,a.scanned_at,a.status FROM attendance a JOIN students s ON s.id=a.student_id JOIN courses c ON c.id=s.course_id JOIN sessions x ON x.id=a.session_id WHERE $w ORDER BY x.session_date DESC,a.scanned_at DESC LIMIT 500",$ar)->fetchAll();?>
<h2><?=$pages[$p]?></h2><form class=np><input type=hidden name=p value=<?=$p?>>From<input type=date name=from value="<?=h($g['from']??'')?>">To<input type=date name=to value="<?=h($g['to']??'')?>"><input name=name placeholder="Student" value="<?=h($g['name']??'')?>">
<select name=course><option value="">All courses<?php foreach($courses as $c)echo "<option value={$c['id']}".(($g['course']??'')==$c['id']?' selected':'').">{$c['code']}";?></select><select name=year><option value="">All years<?php for($i=1;$i<=6;$i++)echo "<option".(($g['year']??'')==$i?' selected':'').">$i";?></select>
<select name=rs><option value="">All activities<?php foreach($sess as $s)echo "<option value={$s['id']}".(($g['rs']??'')==$s['id']?' selected':'').">".h($s['name'])."</option>";?></select><button>Filter</button>
<?php if($p=='reports'){?><a class=btn href="?<?=h(http_build_query($g+['csv'=>1]))?>">Export CSV</a><button type=button onclick=print()>Print</button><?php }?></form>
<?php if($p=='reports'){$sm=q("SELECT s.student_no,s.full_name,SUM(a.status IN('present','late')) att,SUM(a.status<>'excused') tot FROM attendance a JOIN students s ON s.id=a.student_id JOIN sessions x ON x.id=a.session_id WHERE x.status='finalized' AND $w GROUP BY s.id ORDER BY s.full_name",$ar)->fetchAll();?>
<div class="card tw"><h3>Attendance % (finalized sessions)</h3><table><tr><th>Student<th>Attended<th>Total<th>%</tr><?php foreach($sm as $r)echo "<tr><td>".h($r['full_name'].' ('.$r['student_no'].')')."<td>{$r['att']}<td>{$r['tot']}<td>".round($r['att']/max(1,$r['tot'])*100)."%</tr>";?></table></div><?php }?>
<div class="card tw"><table><tr><th>Student ID<th>Name<th>Course<th>Yr<th>Activity<th>Date<th>Time<th>Status</tr><?php foreach($rows as $r)echo "<tr><td>".h($r['student_no'])."<td>".h($r['full_name'])."<td>{$r['course']}<td>{$r['year_level']}<td>".h($r['activity'])."<td>{$r['session_date']}<td>".substr($r['scanned_at'],11,8)."<td>".badge($r['status'])."</tr>";?></table></div><?php }
if($p=='mine'){$sidn=(int)$u['student_id'];$rows=q("SELECT x.name,x.session_date,a.scanned_at,a.status FROM attendance a JOIN sessions x ON x.id=a.session_id WHERE a.student_id=? ORDER BY x.session_date DESC",[$sidn])->fetchAll();?><h2>My Attendance</h2><div class="card tw"><table><tr><th>Activity<th>Date<th>Time<th>Status</tr><?php foreach($rows as $r)echo "<tr><td>".h($r['name'])."<td>{$r['session_date']}<td>".substr($r['scanned_at'],11,8)."<td>".badge($r['status'])."</tr>";?></table></div><?php }
if($p=='users'){need('admin');$us=q("SELECT * FROM users")->fetchAll();?><h2>User Management</h2><form method=post class=card><input type=hidden name=csrf value="<?=$T?>"><input type=hidden name=act value=user><input name=username placeholder=Username required><input name=full_name placeholder="Full name" required><input type=password name=password placeholder=Password minlength=8 required><select name=role><option>teacher<option>admin<option>student</select><input name=student_id placeholder="Student row id (students only)" type=number><button>Add user</button></form>
<div class="card tw"><table><tr><th>Username<th>Name<th>Role</tr><?php foreach($us as $r)echo "<tr><td>".h($r['username'])."<td>".h($r['full_name'])."<td>{$r['role']}</tr>";?></table></div><?php }
?></main></body></html>
